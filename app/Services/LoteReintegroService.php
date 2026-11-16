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

    /**
     * Vista previa de una unificación, SIN cambiar nada: qué lotes nuevos saldrían (uno
     * por categoría) y de qué lotes viene cada uno. Aplica las mismas reglas que unificar().
     *
     * @param array<mixed> $loteIds
     * @return array{lotes: array, grupos: list<array{categoria_id: ?int, categoria: string, movimientos: list<int>, origenes: list<int>}>, bienesPorLote: array<int, int>}
     */
    public static function previsualizarUnificacion(array $loteIds): array
    {
        $loteIds = self::idsUnicos($loteIds);
        $lotes = self::accesibles(LoteReintegro::buscarVarios($loteIds), $loteIds);
        self::validarParaUnificar($lotes);
        [$grupos, $bienesPorLote] = self::gruposParaUnificar($loteIds);

        return ['lotes' => $lotes, 'grupos' => $grupos, 'bienesPorLote' => $bienesPorLote];
    }

    /**
     * Une los lotes pendientes seleccionados y reparte sus bienes en lotes nuevos, UNO POR
     * CATEGORÍA (con un solo lote mixto, lo separa por categoría). Los lotes de origen
     * pasan a 'unificado' y queda anotado a qué lotes nuevos fue cada uno. Todo o nada.
     *
     * @param array<mixed> $loteIds
     * @return list<array{id: int, categoria: string, bienes: int}> lotes nuevos
     */
    public static function unificar(array $loteIds, ?string $descripcion, ?string $observaciones): array
    {
        $loteIds = self::idsUnicos($loteIds);
        $descripcion = self::textoOpcional($descripcion, 'La descripción', self::LARGO_MAXIMO_DESCRIPCION)
            ?? self::descripcionUnificacion($loteIds);
        $observaciones = self::textoOpcional($observaciones, 'Las observaciones');

        return Database::transaccion(static function () use ($loteIds, $descripcion, $observaciones): array {
            $lotes = self::bloquearAccesibles($loteIds);
            $institucionId = self::validarParaUnificar($lotes);
            [$grupos] = self::gruposParaUnificar($loteIds);

            $creados = self::crearPorCategoria($institucionId, $grupos, FechaMovimiento::hoy(), $descripcion, $observaciones);

            if (LoteReintegro::marcarUnificados($loteIds, (int) Auth::id()) !== count($loteIds)) {
                throw new \DomainException('Otro usuario cambió alguno de los lotes al mismo tiempo. No se aplicó ningún cambio; intenta de nuevo.');
            }

            // A qué lotes nuevos fue cada lote de origen (crearPorCategoria respeta el orden de los grupos).
            $destinos = array_fill_keys($loteIds, []);
            foreach ($grupos as $i => $grupo) {
                foreach ($grupo['origenes'] as $origen) {
                    $destinos[$origen][] = $creados[$i]['id'];
                }
            }
            foreach ($lotes as $lote) {
                Auditoria::registrar(Auth::id(), $institucionId, 'unificar', 'lote_reintegro', (int) $lote['id'],
                    ['estado' => 'pendiente'],
                    ['estado' => 'unificado', 'lotes_destino' => $destinos[(int) $lote['id']]]);
            }

            return $creados;
        });
    }

    /**
     * ¿La unificación dejaría todo igual? Pasa cuando cada lote nuevo vendría de un solo
     * lote y cada lote iría a un solo lote nuevo (p. ej. un lote que ya es de una sola
     * categoría, o un lote de Muebles con otro de Tecnología).
     *
     * @param list<array{origenes: list<int>}> $grupos
     */
    public static function esUnificacionSinCambios(array $grupos): bool
    {
        $vecesPorOrigen = [];
        foreach ($grupos as $grupo) {
            if (count($grupo['origenes']) !== 1) {
                return false;
            }
            $vecesPorOrigen[$grupo['origenes'][0]] = ($vecesPorOrigen[$grupo['origenes'][0]] ?? 0) + 1;
        }

        return max($vecesPorOrigen ?: [0]) <= 1;
    }

    /**
     * Descripción por defecto de los lotes nuevos: "Unificación de los lotes #3, #5 y #8"
     * o "Separación por categoría del lote #3".
     *
     * @param list<int> $loteIds
     */
    public static function descripcionUnificacion(array $loteIds): string
    {
        if (count($loteIds) === 1) {
            return "Separación por categoría del lote #{$loteIds[0]}";
        }
        if (count($loteIds) > 8) {
            return 'Unificación de ' . count($loteIds) . ' lotes';
        }

        $numeros = array_map(static fn (int $id): string => "#{$id}", $loteIds);
        $ultimo = array_pop($numeros);

        return 'Unificación de los lotes ' . implode(', ', $numeros) . ' y ' . $ultimo;
    }

    /** "Pendiente", "Reintegrado"… para los mensajes. */
    public static function etiquetaEstado(string $estado): string
    {
        return LoteReintegro::ESTADOS[$estado] ?? $estado;
    }

    /**
     * Reglas para unificar: todos pendientes y de una misma sede. Devuelve la sede.
     *
     * @param non-empty-array $lotes
     */
    private static function validarParaUnificar(array $lotes): int
    {
        foreach ($lotes as $lote) {
            if ($lote['estado'] !== 'pendiente') {
                throw new \DomainException(self::noPendiente($lote));
            }
        }

        $sedes = array_unique(array_map(static fn (array $l): int => (int) $l['institucion_id'], $lotes));
        if (count($sedes) > 1) {
            throw new \DomainException('Solo se pueden unificar lotes de una misma sede. No se aplicó ningún cambio.');
        }

        return (int) reset($sedes);
    }

    /**
     * Grupos por categoría de los bienes de los lotes, y cuántos bienes tiene cada lote.
     *
     * @param list<int> $loteIds
     * @return array{0: list<array{categoria_id: ?int, categoria: string, movimientos: list<int>, origenes: list<int>}>, 1: array<int, int>}
     */
    private static function gruposParaUnificar(array $loteIds): array
    {
        $movimientos = LoteReintegro::movimientosDeLotes($loteIds);
        if ($movimientos === []) {
            throw new \DomainException('Los lotes seleccionados no tienen bienes para unificar.');
        }

        $grupos = self::agruparPorCategoria($movimientos);
        if (self::esUnificacionSinCambios($grupos)) {
            throw new \DomainException(count($loteIds) === 1
                ? "El lote #{$loteIds[0]} ya es de una sola categoría: no hay nada que separar."
                : 'Los lotes seleccionados ya están separados por categoría y no comparten ninguna: no hay nada que unificar.');
        }

        $bienesPorLote = array_fill_keys($loteIds, 0);
        foreach ($movimientos as $movimiento) {
            $bienesPorLote[(int) $movimiento['lote_reintegro_id']]++;
        }

        return [$grupos, $bienesPorLote];
    }

    /**
     * Bloquea los lotes y comprueba que existan todos y que sean de la sede del usuario
     * (el superusuario puede operar sobre cualquiera).
     *
     * @param list<int> $loteIds
     * @return non-empty-array
     */
    private static function bloquearAccesibles(array $loteIds): array
    {
        return self::accesibles(LoteReintegro::bloquear($loteIds), $loteIds);
    }

    /**
     * @param list<int> $loteIds
     * @return non-empty-array
     */
    private static function accesibles(array $lotes, array $loteIds): array
    {
        if ($loteIds === []) {
            throw new \DomainException('Selecciona al menos un lote.');
        }
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
