<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Helpers\FechaMovimiento;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;

/**
 * Reintegro POR CANTIDAD de un código con varias unidades (alta masiva: bienes.lote, p. ej.
 * la placa 200033042 con 178 sillas). El usuario dice cuántas unidades reintegra desde cada
 * ubicación; MIA toma esas unidades (los últimos consecutivos) y las reintegra con la regla
 * de siempre (ReintegroService). Si el código tiene además su bien "resumen" (el renglón de la
 * cartera de la Alcaldía, p. ej. "178 SILLAS UNIVERSITARIAS"), le resta lo reintegrado. Todo o nada.
 */
final class ReintegroUnidadesService
{
    public const CLAVE_SIN_ASIGNAR = 'sin';
    private const LARGO_MAXIMO_DESTINO = 200;
    private const LARGO_MAXIMO_OBSERVACIONES = 500;

    /**
     * Datos del código y unidades disponibles por ubicación, para el formulario. null si el
     * código no existe en la institución.
     *
     * @return array{lote: array<string, mixed>, ubicaciones: list<array<string, mixed>>, motivo: ?string, bienResumen: ?array}|null
     */
    public static function resumen(int $institucionId, string $lote): ?array
    {
        $resumen = Bien::resumenDeLote($institucionId, $lote);
        if ($resumen === null) {
            return null;
        }

        $ubicaciones = [];
        foreach (Bien::disponiblesDeLotePorUbicacion($institucionId, $lote) as $fila) {
            $sinAsignar = (int) $fila['sin_asignar'] === 1;
            $ubicaciones[] = $fila + [
                'clave' => $sinAsignar ? self::CLAVE_SIN_ASIGNAR : self::claveGrupo(
                    $fila['espacio_id'] !== null ? (int) $fila['espacio_id'] : null,
                    $fila['usuario_responsable_id'] !== null ? (int) $fila['usuario_responsable_id'] : null
                ),
                'tipo' => $sinAsignar ? null : ($fila['usuario_responsable_id'] !== null ? 'individual' : 'grupal'),
            ];
        }

        // Misma regla que cualquier reintegro (categoría asignada y distinta de "Sin cartera").
        $motivo = Bien::motivoNoReintegrable([
            'estado' => 'activo', 'lote' => $lote,
            'categoria_id' => $resumen['categoria_id'], 'categoria_nombre' => $resumen['categoria_nombre'],
        ], false);

        return ['lote' => $resumen, 'ubicaciones' => $ubicaciones, 'motivo' => $motivo, 'bienResumen' => Bien::resumenDeCodigo($institucionId, $lote)];
    }

    /**
     * Reintegra las cantidades pedidas por ubicación y, si se pide, las agrupa enseguida
     * en lotes de reintegro (uno por categoría) con su comprobante.
     *
     * @param array<mixed> $cantidadesPorUbicacion clave de ubicación => cantidad
     * @return array{reintegradas: int, lotes: list<array{id: int, categoria: string, bienes: int}>, resumen: ?array{antes: string, despues: string}}
     */
    public static function reintegrar(
        int $institucionId,
        string $lote,
        array $cantidadesPorUbicacion,
        string $fecha,
        string $destino,
        ?string $observaciones,
        bool $generarLote
    ): array {
        if ($error = FechaMovimiento::error($fecha)) {
            throw new \DomainException('Fecha del reintegro: ' . $error);
        }
        $destino = trim($destino);
        if ($destino === '') {
            throw new \DomainException('Indica el destino del reintegro.');
        }
        if (mb_strlen($destino) > self::LARGO_MAXIMO_DESTINO) {
            throw new \DomainException('El destino no puede tener más de ' . self::LARGO_MAXIMO_DESTINO . ' caracteres.');
        }
        $observaciones = trim((string) $observaciones);
        if (mb_strlen($observaciones) > self::LARGO_MAXIMO_OBSERVACIONES) {
            throw new \DomainException('Las observaciones no pueden tener más de ' . self::LARGO_MAXIMO_OBSERVACIONES . ' caracteres.');
        }

        $pedidas = self::cantidadesValidas($cantidadesPorUbicacion);
        $total = array_sum($pedidas);
        $observacionMovimiento = "Reintegro por cantidad de la placa {$lote}" . ($observaciones !== '' ? ". {$observaciones}" : '');

        return Database::transaccion(static function () use ($institucionId, $lote, $pedidas, $total, $fecha, $destino, $observaciones, $observacionMovimiento, $generarLote): array {
            $nombres = self::nombresDeUbicaciones($institucionId, $lote);
            $movimientos = [];

            foreach ($pedidas as $clave => $cantidad) {
                $grupo = self::parsearClaveGrupo($clave);
                if ($grupo === null) {
                    throw new \DomainException('Una de las ubicaciones no es válida. Vuelve a abrir el formulario.');
                }

                $unidades = Bien::unidadesDeLoteParaReintegrar($institucionId, $lote, $grupo['espacio'], $grupo['usuario'], $grupo['sin_asignar'], $cantidad);
                if (count($unidades) < $cantidad) {
                    $donde = $nombres[$clave] ?? 'esa ubicación';
                    throw new \DomainException("En {$donde} solo quedan " . count($unidades) . " unidad(es) de la placa {$lote}; pediste {$cantidad}. No se aplicó ningún cambio.");
                }

                foreach ($unidades as $bienId) {
                    $bien = Bien::find($bienId);
                    if ($bien === null) {
                        throw new \DomainException('Una de las unidades ya no existe. No se aplicó ningún cambio.');
                    }
                    $movimientos[] = ReintegroService::reintegrar($bien, Asignacion::activaDe($bienId), $fecha, $destino, $observacionMovimiento,
                        ['placa' => $lote, 'por_cantidad' => true]);
                }
            }

            $resumen = self::actualizarBienResumen($institucionId, $lote);

            $lotes = $generarLote
                ? LoteReintegroService::generarPorCategoria($institucionId, $movimientos,
                    "Placa {$lote}: {$total} " . ($total === 1 ? 'unidad' : 'unidades'), $observaciones !== '' ? $observaciones : null)
                : [];

            return ['reintegradas' => count($movimientos), 'lotes' => $lotes, 'resumen' => $resumen];
        });
    }

    /**
     * Si el código tiene su bien "resumen" (Bien::resumenDeCodigo), le pone la cantidad y el
     * valor que quedan en cartera: "178 SILLAS UNIVERSITARIAS" pasa a "175 SILLAS UNIVERSITARIAS"
     * y su valor, al de las 175 unidades. Queda en la auditoría.
     *
     * @return array{antes: string, despues: string}|null
     */
    private static function actualizarBienResumen(int $institucionId, string $lote): ?array
    {
        $bien = Bien::resumenDeCodigo($institucionId, $lote, true);
        $estado = Bien::resumenDeLote($institucionId, $lote);
        if ($bien === null || $estado === null) {
            return null;
        }

        $quedan = (int) $estado['en_cartera'];
        $valor = round((float) $estado['valor_en_cartera'], 2);
        $descripcion = self::descripcionConCantidad((string) $bien['descripcion'], $quedan);
        Bien::actualizarResumenDeCodigo((int) $bien['id'], $descripcion, $valor);
        Auditoria::registrar(Auth::id(), $institucionId, 'editar', 'bien', (int) $bien['id'],
            ['descripcion' => $bien['descripcion'], 'valor' => $bien['valor']],
            ['descripcion' => $descripcion, 'valor' => $valor, 'motivo' => "Reintegro por cantidad de la placa {$lote}: quedan {$quedan} en cartera"]);

        return ['antes' => (string) $bien['descripcion'], 'despues' => $descripcion];
    }

    /**
     * La descripción del bien resumen con la cantidad nueva al comienzo: "178 SILLAS
     * UNIVERSITARIAS" con 175 → "175 SILLAS UNIVERSITARIAS". Si no empezaba por un número,
     * se le antepone.
     */
    public static function descripcionConCantidad(string $descripcion, int $cantidad): string
    {
        $descripcion = trim($descripcion);
        // Hasta 5 cifras: es una cantidad ("178 SILLAS"), no un código ("200033042 SILLAS").
        if (preg_match('/^\d{1,5}\s+/u', $descripcion) === 1) {
            return (string) preg_replace('/^\d{1,5}/u', (string) $cantidad, $descripcion, 1);
        }

        return $cantidad . ' ' . $descripcion;
    }

    /**
     * Clave de una ubicación para el formulario: "e5-u0" (espacio 5, sin persona),
     * "e0-u7" (a cargo de la persona 7, sin espacio), "e5-u7"…
     */
    public static function claveGrupo(?int $espacioId, ?int $usuarioId): string
    {
        return 'e' . ($espacioId ?? 0) . '-u' . ($usuarioId ?? 0);
    }

    /**
     * Interpreta la clave de una ubicación (ver claveGrupo y CLAVE_SIN_ASIGNAR). null si no
     * es válida.
     *
     * @return array{espacio: ?int, usuario: ?int, sin_asignar: bool}|null
     */
    public static function parsearClaveGrupo(string $clave): ?array
    {
        if ($clave === self::CLAVE_SIN_ASIGNAR) {
            return ['espacio' => null, 'usuario' => null, 'sin_asignar' => true];
        }
        if (preg_match('/^e(\d{1,10})-u(\d{1,10})$/', $clave, $m) !== 1 || ((int) $m[1] === 0 && (int) $m[2] === 0)) {
            return null;
        }

        return ['espacio' => (int) $m[1] ?: null, 'usuario' => (int) $m[2] ?: null, 'sin_asignar' => false];
    }

    /**
     * Las cantidades mayores que cero; exige al menos una unidad en total.
     *
     * @param array<mixed> $cantidades
     * @return array<string, int>
     */
    public static function cantidadesValidas(array $cantidades): array
    {
        $validas = [];
        foreach ($cantidades as $clave => $valor) {
            $texto = trim((string) $valor);
            if ($texto === '' || $texto === '0') {
                continue;
            }
            if (preg_match('/^\d{1,4}$/', $texto) !== 1) {
                throw new \DomainException('Las cantidades deben ser números enteros (0 o más).');
            }
            $validas[(string) $clave] = (int) $texto;
        }
        if ($validas === []) {
            throw new \DomainException('Indica cuántas unidades vas a reintegrar.');
        }

        return $validas;
    }

    /** @return array<string, string> clave de ubicación => nombre legible, para los mensajes */
    private static function nombresDeUbicaciones(int $institucionId, string $lote): array
    {
        $nombres = [];
        foreach (self::resumen($institucionId, $lote)['ubicaciones'] ?? [] as $ubicacion) {
            $nombres[$ubicacion['clave']] = $ubicacion['clave'] === self::CLAVE_SIN_ASIGNAR
                ? '"Sin asignar"'
                : (string) ($ubicacion['espacio_nombre'] ?? $ubicacion['responsables_nombres'] ?? 'esa ubicación');
        }

        return $nombres;
    }
}
