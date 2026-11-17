<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ErrorGuiado;
use App\Core\Url;
use App\Helpers\FechaMovimiento;
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Categoria;

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
    /** Máximo de unidades de una alta masiva (BienController::validarLote). */
    public const MAXIMO_ALTA_MASIVA = 500;

    /**
     * Por qué Reintegrar por cantidad no muestra ningún código, y qué hacer, con la acción a un
     * clic. Aquí solo aparecen los códigos con varias unidades (Alta masiva idéntica); un bien
     * registrado como uno solo no aparece, y antes la pantalla no decía por qué.
     *
     * @param string $sufijoInstitucion "?institucion=N" del superusuario, o ''
     * @return array{titulo: string, detalle: string, acciones: list<array{texto: string, url: string}>}
     */
    public static function explicarSinResultados(int $institucionId, string $busqueda, string $sufijoInstitucion = ''): array
    {
        // El superusuario trabaja sobre una institución elegida: los enlaces la llevan.
        $conInstitucion = static function (string $ruta, array $consulta = []) use ($sufijoInstitucion, $institucionId): string {
            if ($sufijoInstitucion !== '') {
                $consulta['institucion'] = $institucionId;
            }

            return Url::to($ruta) . ($consulta !== [] ? '?' . http_build_query($consulta) : '');
        };
        $reintegrarBienes = static fn (string $texto, string $q): array => ['texto' => $texto, 'url' => $conInstitucion('/reintegros', ['q' => $q])];

        if ($busqueda === '') {
            return [
                'titulo' => 'Esta institución todavía no tiene códigos con varias unidades',
                'detalle' => 'Aquí aparecen los códigos registrados con Alta masiva idéntica (por ejemplo, una placa con 178 sillas). '
                    . 'Un bien registrado como uno solo se reintegra en Reintegrar bienes.',
                'acciones' => [['texto' => 'Ir a Reintegrar bienes', 'url' => $conInstitucion('/reintegros')]],
            ];
        }

        $exacto = Bien::buscarPorCodigoInstitucion($institucionId, $busqueda);
        $bien = $exacto !== null ? Bien::find((int) $exacto['id']) : null;
        if ($bien === null) {
            // Sin un código exacto: ¿hay bienes individuales que coincidan por código o descripción?
            $sueltos = Bien::contarListado($institucionId, $busqueda, true);

            return $sueltos > 0
                ? [
                    'titulo' => "Ningún código con varias unidades coincide con \"{$busqueda}\"",
                    'detalle' => ($sueltos === 1 ? 'Hay 1 bien registrado como uno solo que coincide' : "Hay {$sueltos} bienes registrados como uno solo que coinciden")
                        . ': se reintegran en Reintegrar bienes. Aquí solo aparecen los códigos registrados con Alta masiva idéntica.',
                    'acciones' => [$reintegrarBienes('Buscar en Reintegrar bienes', $busqueda)],
                ]
                : [
                    'titulo' => "No hay ningún bien con \"{$busqueda}\" en esta institución",
                    'detalle' => 'Revisa la placa (completa y sin espacios) o busca por la descripción.',
                    'acciones' => [['texto' => 'Buscar en Bienes', 'url' => Url::to('/bienes') . '?' . http_build_query(['q' => $busqueda])]],
                ];
        }

        $codigo = (string) $bien['codigo_identificacion'];
        $verBien = ['texto' => 'Ver el bien', 'url' => Url::to('/bienes/' . (int) $bien['id'] . '/editar')];

        // Una unidad de una placa: aquí se busca la placa completa.
        if (!empty($bien['lote'])) {
            return [
                'titulo' => "{$codigo} es una unidad de la placa {$bien['lote']}",
                'detalle' => 'En Reintegrar por cantidad se busca la placa completa, no cada unidad: elige la placa y di cuántas reintegras.',
                'acciones' => [['texto' => "Ver la placa {$bien['lote']}", 'url' => $conInstitucion('/reintegros/unidades', ['q' => (string) $bien['lote']])]],
            ];
        }

        if ($bien['estado'] === 'reintegrado') {
            return ['titulo' => "El bien {$codigo} ya está reintegrado",
                'detalle' => 'No queda nada por reintegrar. Si fue un error, el rector puede reactivarlo desde su ficha.', 'acciones' => [$verBien]];
        }
        if ($bien['estado'] === 'dado_de_baja') {
            return ['titulo' => "El bien {$codigo} fue dado de baja", 'detalle' => 'Un bien dado de baja ya no se reintegra.', 'acciones' => [$verBien]];
        }
        if (($bien['categoria_nombre'] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA) {
            return ['titulo' => "El bien {$codigo} es de la categoría \"" . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '"',
                'detalle' => 'Esos bienes no se reintegran a la Alcaldía: solo se dan de baja, desde su ficha.', 'acciones' => [$verBien]];
        }

        // Un renglón de la cartera que agrupa varias unidades ("8 SILLA…") sin unidades registradas.
        $cantidad = RenglonDePlaca::cantidadQueDice((string) $bien['descripcion']);
        if ($cantidad !== null && $cantidad > 1) {
            $puedeRegistrar = Auth::esSuperusuario() || Auth::tienePermiso('bienes.crear');
            $detalle = "Para reintegrar por cantidad, sus {$cantidad} unidades deben estar registradas por separado. "
                . "Regístralas con Alta masiva idéntica, con el mismo código y la cantidad {$cantidad}: después la placa aparece aquí y "
                . "reintegras solo las que necesites. \"{$bien['descripcion']}\" queda como el renglón de la cartera y MIA lo mantiene al día.";
            if ($cantidad > self::MAXIMO_ALTA_MASIVA) {
                $detalle = "Son más de " . self::MAXIMO_ALTA_MASIVA . " unidades, el máximo de una alta masiva: pide ayuda al administrador de MIA.";
            } elseif (!$puedeRegistrar) {
                $detalle .= ' Pídele a quien registra los bienes en tu institución que lo haga.';
            }

            return [
                'titulo' => "El bien {$codigo} está registrado como un solo bien de {$cantidad} unidades",
                'detalle' => $detalle,
                'acciones' => $puedeRegistrar && $cantidad <= self::MAXIMO_ALTA_MASIVA
                    ? [['texto' => "Registrar sus {$cantidad} unidades", 'url' => Url::to('/bienes/alta-masiva') . '?desde_bien=' . (int) $bien['id']]]
                    : [$verBien],
            ];
        }

        // Un bien individual: se reintegra en Reintegrar bienes (si cumple la regla).
        $motivo = Bien::motivoNoReintegrable($bien, Asignacion::activaDe((int) $bien['id']) !== null);

        return $motivo === null
            ? [
                'titulo' => "El bien {$codigo} es un bien individual",
                'detalle' => 'Reintegrar por cantidad es solo para códigos con varias unidades (Alta masiva idéntica). Este bien se reintegra en Reintegrar bienes.',
                'acciones' => [$reintegrarBienes('Reintegrarlo en Reintegrar bienes', $codigo)],
            ]
            : [
                'titulo' => "El bien {$codigo} es un bien individual",
                'detalle' => "Se reintegra en Reintegrar bienes, pero todavía no se puede: {$motivo}. Corrígelo desde su ficha.",
                'acciones' => [$verBien],
            ];
    }

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
     * @return array{reintegradas: int, lotes: list<array{id: int, categoria: string, bienes: int}>, resumen: ?array{antes: string, despues: string, reintegrado: bool, reactivado: bool}}
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
            throw new ErrorGuiado('Fecha del reintegro: ' . $error, 'Elige una fecha entre el 01/01/2000 y hoy y vuelve a pulsar «Reintegrar».',
                null, ['fecha' => 'Elige una fecha válida.']);
        }
        $destino = trim($destino);
        if ($destino === '') {
            throw new ErrorGuiado('Falta el destino del reintegro.',
                'Escribe a dónde van las unidades (por ejemplo «Almacén de la Secretaría de Educación») y vuelve a pulsar «Reintegrar».',
                null, ['destino_texto' => 'Escribe el destino.']);
        }
        if (mb_strlen($destino) > self::LARGO_MAXIMO_DESTINO) {
            throw new ErrorGuiado('El destino tiene más de ' . self::LARGO_MAXIMO_DESTINO . ' caracteres.', 'Acórtalo y vuelve a pulsar «Reintegrar».',
                null, ['destino_texto' => 'Máximo ' . self::LARGO_MAXIMO_DESTINO . ' caracteres.']);
        }
        $observaciones = trim((string) $observaciones);
        if (mb_strlen($observaciones) > self::LARGO_MAXIMO_OBSERVACIONES) {
            throw new ErrorGuiado('Las observaciones tienen más de ' . self::LARGO_MAXIMO_OBSERVACIONES . ' caracteres.', 'Acórtalas y vuelve a pulsar «Reintegrar».',
                null, ['observaciones' => 'Máximo ' . self::LARGO_MAXIMO_OBSERVACIONES . ' caracteres.']);
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
                    throw new ErrorGuiado('Una de las ubicaciones del formulario ya no existe (cambió mientras tenías la página abierta).',
                        'Vuelve a abrir la placa desde «Reintegrar por cantidad» y escribe de nuevo las cantidades.');
                }

                $unidades = Bien::unidadesDeLoteParaReintegrar($institucionId, $lote, $grupo['espacio'], $grupo['usuario'], $grupo['sin_asignar'], $cantidad);
                if (count($unidades) < $cantidad) {
                    $donde = $nombres[$clave] ?? 'esa ubicación';
                    throw new ErrorGuiado("En {$donde} solo quedan " . count($unidades) . " unidad(es) de la placa {$lote} y pediste {$cantidad}. No se aplicó ningún cambio.",
                        'Escribe una cantidad igual o menor a la que queda en cada ubicación y vuelve a pulsar «Reintegrar».');
                }

                foreach ($unidades as $bienId) {
                    $bien = Bien::find($bienId);
                    if ($bien === null) {
                        throw new ErrorGuiado('Una de las unidades ya no existe (cambió mientras tenías la página abierta). No se aplicó ningún cambio.',
                            'Vuelve a abrir la placa desde «Reintegrar por cantidad»: verás las cantidades actualizadas.');
                    }
                    $movimientos[] = ReintegroService::reintegrar($bien, Asignacion::activaDe($bienId), $fecha, $destino, $observacionMovimiento,
                        ['placa' => $lote, 'por_cantidad' => true], false);
                }
            }

            // El renglón de la cartera de la placa, una sola vez al final (no por cada unidad).
            $resumen = RenglonDePlaca::sincronizar($institucionId, $lote, "Reintegro por cantidad de la placa {$lote}");

            $lotes = $generarLote
                ? LoteReintegroService::generarPorCategoria($institucionId, $movimientos,
                    "Placa {$lote}: {$total} " . ($total === 1 ? 'unidad' : 'unidades'), $observaciones !== '' ? $observaciones : null)
                : [];

            return ['reintegradas' => count($movimientos), 'lotes' => $lotes, 'resumen' => $resumen];
        });
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
                throw new ErrorGuiado('Una de las cantidades no es un número entero.', 'Escribe 0 o un número entero, sin puntos ni decimales, en cada ubicación.');
            }
            $validas[(string) $clave] = (int) $texto;
        }
        if ($validas === []) {
            throw new ErrorGuiado('No escribiste cuántas unidades reintegrar.', 'Escribe la cantidad en la ubicación de donde salen las unidades (al menos 1) y vuelve a pulsar «Reintegrar».');
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
