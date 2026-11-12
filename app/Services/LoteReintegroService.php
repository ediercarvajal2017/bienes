<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Helpers\FechaMovimiento;
use App\Models\Auditoria;
use App\Models\LoteReintegro;
use App\Models\Movimiento;

/**
 * Reglas de los lotes de reintegro: generarlos (un lote por categoría), marcarlos como
 * reintegrados (entregados a la Alcaldía) y reabrirlos. Cada operación es todo o nada:
 * si algo no cumple, no cambia nada.
 *
 * Las reglas lanzan \DomainException con un mensaje para el usuario; cualquier otra
 * excepción es un error real y se reporta aparte.
 */
final class LoteReintegroService
{
    private const LARGO_MAXIMO_NOTA = 500;
    private const LARGO_MAXIMO_DESCRIPCION = 200;

    /**
     * Agrupa en lotes los reintegros sueltos seleccionados: UN LOTE POR CATEGORÍA (Muebles,
     * Tecnología…) y por institución, cada uno con su comprobante. Solo cuenta los
     * reintegros que siguen sin lote y a los que el usuario tiene acceso.
     *
     * @param ?int $institucionId null = todas (superusuario)
     * @param array<mixed> $movimientoIds
     * @return list<array{id: int, categoria: string, bienes: int}> lotes creados
     */
    public static function generarPorCategoria(?int $institucionId, array $movimientoIds, ?string $descripcion, ?string $observaciones): array
    {
        $movimientoIds = self::idsUnicos($movimientoIds);
        if ($movimientoIds === []) {
            throw new \DomainException('Selecciona al menos un reintegro para agrupar en el lote.');
        }
        $descripcion = self::textoOpcional($descripcion, 'La descripción', self::LARGO_MAXIMO_DESCRIPCION);
        $observaciones = self::textoOpcional($observaciones, 'Las observaciones');

        $seleccionados = array_flip($movimientoIds);
        $porInstitucion = [];
        foreach (Movimiento::pendientesDeLoteTodos($institucionId) as $movimiento) {
            if (isset($seleccionados[(int) $movimiento['id']])) {
                $porInstitucion[(int) $movimiento['institucion_id']][] = $movimiento;
            }
        }
        if ($porInstitucion === []) {
            throw new \DomainException('Ningún reintegro seleccionado pudo agruparse (verifica que sigan sin lote y que tengas acceso).');
        }

        return Database::transaccion(static function () use ($porInstitucion, $descripcion, $observaciones): array {
            $creados = [];
            foreach ($porInstitucion as $institucion => $movimientos) {
                $grupos = self::agruparPorCategoria($movimientos);
                $creados = [...$creados, ...self::crearPorCategoria($institucion, $grupos, FechaMovimiento::hoy(), $descripcion, $observaciones)];
            }

            return $creados;
        });
    }

    /**
     * Reparte movimientos de reintegro por la categoría de su bien. Los bienes sin
     * categoría forman su propio grupo, al final. Si los movimientos vienen de lotes
     * (al unificar), cada grupo recuerda de qué lotes salió.
     *
     * @param array<int, array<string, mixed>> $movimientos con id, categoria_id, categoria_nombre
     *        y, si ya estaban en un lote, lote_reintegro_id
     * @return list<array{categoria_id: ?int, categoria: string, movimientos: list<int>, origenes: list<int>}>
     */
    public static function agruparPorCategoria(array $movimientos): array
    {
        $grupos = [];
        foreach ($movimientos as $movimiento) {
            $categoriaId = $movimiento['categoria_id'] !== null ? (int) $movimiento['categoria_id'] : null;
            $clave = $categoriaId ?? 0;
            $grupos[$clave] ??= [
                'categoria_id' => $categoriaId,
                'categoria' => $categoriaId !== null ? (string) ($movimiento['categoria_nombre'] ?? '') : LoteReintegro::NOMBRE_SIN_CATEGORIA,
                'movimientos' => [],
                'origenes' => [],
            ];
            $grupos[$clave]['movimientos'][] = (int) $movimiento['id'];
            $origen = (int) ($movimiento['lote_reintegro_id'] ?? 0);
            if ($origen > 0 && !in_array($origen, $grupos[$clave]['origenes'], true)) {
                $grupos[$clave]['origenes'][] = $origen;
            }
        }

        foreach ($grupos as &$grupo) {
            sort($grupo['movimientos']);
            sort($grupo['origenes']);
        }
        unset($grupo);

        // Por nombre de categoría; "Sin categoría" siempre al final.
        usort($grupos, static fn (array $a, array $b): int => [$a['categoria_id'] === null, mb_strtolower($a['categoria'])]
            <=> [$b['categoria_id'] === null, mb_strtolower($b['categoria'])]);

        return $grupos;
    }

    /**
     * "#14 Muebles (3 bienes) y #15 Tecnología (1 bien)", para los mensajes.
     *
     * @param list<array{id: int, categoria: string, bienes: int}> $lotes
     */
    public static function resumenLotes(array $lotes): string
    {
        $partes = array_map(
            static fn (array $l): string => "#{$l['id']} {$l['categoria']} (" . $l['bienes'] . ($l['bienes'] === 1 ? ' bien)' : ' bienes)'),
            $lotes
        );
        $ultimo = array_pop($partes);

        return $partes === [] ? (string) $ultimo : implode(', ', $partes) . ' y ' . $ultimo;
    }

    /**
     * Crea un lote por cada grupo (ver agruparPorCategoria) y le pasa sus movimientos:
     * los sueltos se asignan; los que vienen de otros lotes se mueven y se anota el
     * origen. Si otra petición tocó alguno entre la lectura y ahora, se deshace todo.
     *
     * @param list<array{categoria_id: ?int, categoria: string, movimientos: list<int>, origenes: list<int>}> $grupos
     * @return list<array{id: int, categoria: string, bienes: int}>
     */
    private static function crearPorCategoria(int $institucionId, array $grupos, string $fecha, ?string $descripcion, ?string $observaciones): array
    {
        return Database::transaccion(static function () use ($institucionId, $grupos, $fecha, $descripcion, $observaciones): array {
            $creados = [];
            foreach ($grupos as $grupo) {
                $loteId = LoteReintegro::create([
                    'institucion_id' => $institucionId,
                    'fecha' => $fecha,
                    'destino_texto' => $descripcion,
                    'observaciones' => $observaciones,
                    'registrado_por' => Auth::id(),
                ]);

                $asignados = $grupo['origenes'] === []
                    ? LoteReintegro::asignarMovimientos($loteId, $grupo['movimientos'])
                    : LoteReintegro::moverMovimientos($loteId, $grupo['movimientos'], $grupo['origenes']);
                if ($asignados !== count($grupo['movimientos'])) {
                    throw new \DomainException('Otro usuario agrupó alguno de estos reintegros al mismo tiempo. No se aplicó ningún cambio; intenta de nuevo.');
                }
                if ($grupo['origenes'] !== []) {
                    LoteReintegro::registrarOrigenes($loteId, $grupo['origenes']);
                }

                Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'lote_reintegro', $loteId, null, [
                    'categoria' => $grupo['categoria'],
                    'movimientos' => $grupo['movimientos'],
                    'descripcion' => $descripcion,
                    'observaciones' => $observaciones,
                ] + ($grupo['origenes'] !== [] ? ['lotes_origen' => $grupo['origenes']] : []));

                $creados[] = ['id' => $loteId, 'categoria' => $grupo['categoria'], 'bienes' => count($grupo['movimientos'])];
            }

            return $creados;
        });
    }

    /**
     * Marca como 'reintegrado' uno o varios lotes pendientes (la entrega a la Alcaldía
     * ya se hizo). Después quedan bloqueados: no se unifican ni reciben reintegros nuevos.
     *
     * @param list<int> $loteIds
     * @return int cuántos lotes se marcaron
     */
    public static function marcarReintegrados(array $loteIds, string $fecha, ?string $nota): int
    {
        $loteIds = self::idsUnicos($loteIds);
        if ($loteIds === []) {
            throw new \DomainException('Selecciona al menos un lote.');
        }
        if ($error = FechaMovimiento::error($fecha)) {
            throw new \DomainException('Fecha de entrega: ' . $error);
        }
        $nota = self::textoOpcional($nota, 'La nota');

        return Database::transaccion(static function () use ($loteIds, $fecha, $nota): int {
            $lotes = self::bloquearAccesibles($loteIds);
            foreach ($lotes as $lote) {
                if ($lote['estado'] !== 'pendiente') {
                    throw new \DomainException(self::noPendiente($lote));
                }
            }

            if (LoteReintegro::marcarReintegrados($loteIds, $fecha, $nota, (int) Auth::id()) !== count($loteIds)) {
                throw new \DomainException('Otro usuario cambió alguno de los lotes al mismo tiempo. No se aplicó ningún cambio; intenta de nuevo.');
            }

            foreach ($lotes as $lote) {
                Auditoria::registrar(Auth::id(), (int) $lote['institucion_id'], 'marcar_reintegrado', 'lote_reintegro', (int) $lote['id'],
                    ['estado' => 'pendiente'],
                    ['estado' => 'reintegrado', 'reintegrado_fecha' => $fecha, 'reintegrado_nota' => $nota]);
            }

            return count($loteIds);
        });
    }

    /**
     * Devuelve un lote 'reintegrado' a 'pendiente' (se marcó por error). El rol (rector o
     * superusuario) lo valida el controlador; aquí, el motivo y el estado.
     */
    public static function reabrir(int $loteId, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \DomainException('Escribe el motivo para reabrir el lote.');
        }
        $motivo = self::textoOpcional($motivo, 'El motivo');

        Database::transaccion(static function () use ($loteId, $motivo): void {
            [$lote] = self::bloquearAccesibles([$loteId]);
            if ($lote['estado'] !== 'reintegrado') {
                throw new \DomainException("El lote #{$loteId} no está reintegrado; no hay nada que reabrir.");
            }

            if (LoteReintegro::reabrir($loteId) !== 1) {
                throw new \DomainException('Otro usuario cambió el lote al mismo tiempo. Intenta de nuevo.');
            }

            Auditoria::registrar(Auth::id(), (int) $lote['institucion_id'], 'reabrir', 'lote_reintegro', $loteId,
                ['estado' => 'reintegrado', 'reintegrado_fecha' => $lote['reintegrado_fecha'], 'reintegrado_nota' => $lote['reintegrado_nota']],
                ['estado' => 'pendiente', 'motivo' => $motivo]);
        });
    }

    /** "Pendiente", "Reintegrado"… para los mensajes. */
    public static function etiquetaEstado(string $estado): string
    {
        return LoteReintegro::ESTADOS[$estado] ?? $estado;
    }

    /**
     * Bloquea los lotes y comprueba que existan todos y que sean de la sede del usuario
     * (el superusuario puede operar sobre cualquiera).
     *
     * @param list<int> $loteIds
     */
    private static function bloquearAccesibles(array $loteIds): array
    {
        $lotes = LoteReintegro::bloquear($loteIds);
        if ($lotes === [] || count($lotes) !== count($loteIds)) {
            throw new \DomainException('Alguno de los lotes seleccionados ya no existe. No se aplicó ningún cambio.');
        }

        foreach ($lotes as $lote) {
            if (!Auth::esSuperusuario() && (int) $lote['institucion_id'] !== Auth::institucionId()) {
                throw new \DomainException("No tienes acceso al lote #{$lote['id']}. No se aplicó ningún cambio.");
            }
        }

        return $lotes;
    }

    private static function noPendiente(array $lote): string
    {
        return "El lote #{$lote['id']} ya no está pendiente (está \"" . self::etiquetaEstado((string) $lote['estado'])
            . '"). No se aplicó ningún cambio.';
    }

    private static function textoOpcional(?string $texto, string $nombre, int $maximo = self::LARGO_MAXIMO_NOTA): ?string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return null;
        }
        if (mb_strlen($texto) > $maximo) {
            throw new \DomainException("{$nombre} no puede tener más de {$maximo} caracteres.");
        }

        return $texto;
    }

    /**
     * @param array<mixed> $ids
     * @return list<int>
     */
    public static function idsUnicos(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
