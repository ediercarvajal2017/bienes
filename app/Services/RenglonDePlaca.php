<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;

/**
 * El "renglón de la cartera" de una placa con unidades: el bien suelto con el código de la
 * placa (Bien::resumenDeCodigo, p. ej. 13019802 "8 SILLA DE METAL…") cuyas unidades del alta
 * masiva son 13019802-001…-008. Dice cuántas sillas de la placa siguen en la cartera de la
 * Alcaldía (activas o en reparación y fuera de "Sin cartera") y cuánto valen.
 *
 * Se sincroniza desde el estado REAL de las unidades (no restando) cada vez que una unidad
 * cambia: reintegro (por cantidad, ficha, selección, QR, solicitud), reactivación, o cambio de
 * categoría (ficha o carga masiva). Solo se toca si cambia la cantidad o el estado.
 *
 * El VALOR es proporcional al del propio renglón (el de la cartera de la Alcaldía): $302.847
 * por 8 sillas → con 7, $302.847 ÷ 8 × 7. No sale de la suma de las unidades: en los datos
 * reales muchas tienen mal cargado el valor unitario (decisión del usuario, 2026-11-16).
 *
 * Nunca le crea movimientos: así no entra a lotes ni comprobantes (antes 8 sillas salían 9).
 */
final class RenglonDePlaca
{
    /**
     * Pone el renglón de la placa al día: cantidad, valor y estado. null si la placa no tiene
     * renglón o si no cambió nada (entonces tampoco se audita).
     *
     * @return array{antes: string, despues: string, reintegrado: bool, reactivado: bool}|null
     */
    public static function sincronizar(int $institucionId, string $lote, string $motivo): ?array
    {
        return Database::transaccion(static function () use ($institucionId, $lote, $motivo): ?array {
            $calculo = self::calcular($institucionId, $lote, true);
            if ($calculo === null || !$calculo['cambia']) {
                return null;
            }

            $bien = $calculo['bien'];
            ['descripcion' => $descripcion, 'valor' => $valor, 'estado' => $estadoNuevo, 'quedan' => $quedan] = $calculo;
            $estadoAntes = (string) $bien['estado'];
            $reintegrado = $estadoNuevo === 'reintegrado' && $estadoAntes !== 'reintegrado';
            $reactivado = $estadoAntes === 'reintegrado' && $estadoNuevo === 'activo';
            Bien::actualizarResumenDeCodigo((int) $bien['id'], $descripcion, $valor, $estadoNuevo);
            if ($reintegrado) {
                Asignacion::cerrarActivasDe((int) $bien['id']);
            }
            Auditoria::registrar(Auth::id(), $institucionId, 'editar', 'bien', (int) $bien['id'],
                ['descripcion' => $bien['descripcion'], 'valor' => $bien['valor'], 'estado' => $estadoAntes],
                ['descripcion' => $descripcion, 'valor' => $valor, 'estado' => $estadoNuevo,
                    'motivo' => "{$motivo}: " . ($reintegrado ? 'se reintegraron todas las unidades de la placa ' . $lote : "quedan {$quedan} en cartera")]);

            return ['antes' => (string) $bien['descripcion'], 'despues' => $descripcion, 'reintegrado' => $reintegrado, 'reactivado' => $reactivado];
        });
    }

    /**
     * Lo que el renglón de la placa DEBERÍA decir, sin escribir nada (el script de corrección
     * lo usa para simular). null si la placa no tiene renglón. Con $bloquear, lee el renglón
     * con FOR UPDATE (dentro de una transacción).
     *
     * @return array{bien: array<string, mixed>, descripcion: string, valor: float, estado: string, quedan: int,
     *     reintegradas: int, reintegroPropio: bool, cambia: bool}|null
     */
    public static function calcular(int $institucionId, string $lote, bool $bloquear = false): ?array
    {
        $bien = Bien::resumenDeCodigo($institucionId, $lote, $bloquear);
        $estado = Bien::resumenDeLote($institucionId, $lote);
        if ($bien === null || $estado === null) {
            return null;
        }

        $quedan = (int) $estado['en_cartera'];
        $cantidadDice = self::cantidadQueDice((string) $bien['descripcion']);
        $reintegroPropio = self::salioPorReintegroPropio((int) $bien['id']);
        $estadoNuevo = self::estadoQueCorresponde((string) $bien['estado'], $quedan, (int) $estado['reintegradas'], $reintegroPropio);
        // Solo cambia si cambia la cantidad o el estado: un renglón al día no se reescribe
        // (ni su valor, aunque no coincida con la suma de sus unidades).
        $cambia = $cantidadDice !== $quedan || $estadoNuevo !== (string) $bien['estado'];

        return [
            'bien' => $bien,
            'descripcion' => $cambia ? self::descripcionConCantidad((string) $bien['descripcion'], $quedan) : (string) $bien['descripcion'],
            'valor' => $cambia
                ? round(self::valorPorUnidad($bien, $cantidadDice, (int) $estado['total'], $estado) * $quedan, 2)
                : (float) $bien['valor'],
            'estado' => $estadoNuevo,
            'quedan' => $quedan,
            'reintegradas' => (int) $estado['reintegradas'],
            'reintegroPropio' => $reintegroPropio,
            'cambia' => $cambia,
        ];
    }

    /**
     * Sincroniza, una vez por placa, los renglones de las unidades tocadas (los bienes sin
     * lote no tienen renglón y se ignoran).
     *
     * @param iterable<array<string, mixed>> $bienes filas de bienes (institucion_id, lote)
     */
    public static function sincronizarDe(iterable $bienes, string $motivo): void
    {
        $placas = [];
        foreach ($bienes as $bien) {
            if (!empty($bien['lote'])) {
                $placas[(int) $bien['institucion_id'] . '|' . $bien['lote']] = [(int) $bien['institucion_id'], (string) $bien['lote']];
            }
        }
        foreach ($placas as [$institucionId, $lote]) {
            self::sincronizar($institucionId, $lote, $motivo);
        }
    }

    /**
     * El estado que le corresponde al renglón:
     *  - quedan unidades: sigue en circulación; si MIA lo había sacado (Reintegrado, sin
     *    reintegro propio), vuelve a Activo (se reactivó una unidad o volvió a su categoría);
     *  - no queda ninguna y alguna se reintegró: Reintegrado;
     *  - no queda ninguna y ninguna se reintegró (todas pasaron a "Sin cartera"): no cambia
     *    (se revisa a mano: database/correcciones/renglon_placa_en_cero.php lo lista).
     * Un renglón que el usuario reintegró aparte (con su propio movimiento, antes de v1.2.31)
     * no se reactiva solo: tiene un reintegro registrado.
     */
    public static function estadoQueCorresponde(string $estadoActual, int $quedan, int $reintegradas, bool $reintegroPropio): string
    {
        if ($quedan > 0) {
            return $estadoActual === 'reintegrado' && !$reintegroPropio ? 'activo' : $estadoActual;
        }
        if (in_array($estadoActual, Bien::ESTADOS_REINTEGRABLES, true) && $reintegradas > 0) {
            return 'reintegrado';
        }

        return $estadoActual;
    }

    /**
     * La descripción del renglón con la cantidad nueva al comienzo: "178 SILLAS
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

    /** La cantidad con la que empieza la descripción ("178 SILLAS…" → 178); null si no empieza por una. */
    public static function cantidadQueDice(string $descripcion): ?int
    {
        return preg_match('/^(\d{1,5})\s/u', trim($descripcion), $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Valor de una silla según el propio renglón: su valor entre la cantidad que dice (o, si no
     * dice ninguna, entre las unidades de la placa). En "0 SILLAS…" con $0 (se reactiva una
     * unidad después de reintegrarlas todas), sale de la última vez que tuvo sillas, en la
     * auditoría; si no la hay, del valor de las unidades que quedan.
     *
     * @param array<string, mixed> $bien
     * @param array<string, mixed> $estadoLote fila de Bien::resumenDeLote
     */
    private static function valorPorUnidad(array $bien, ?int $cantidadDice, int $totalUnidades, array $estadoLote): float
    {
        $base = $cantidadDice ?? $totalUnidades;
        if ($base > 0) {
            return (float) $bien['valor'] / $base;
        }

        $stmt = Database::connection()->prepare(
            "SELECT datos_antes FROM auditoria WHERE entidad = 'bien' AND entidad_id = ? AND datos_antes IS NOT NULL ORDER BY id DESC LIMIT 50"
        );
        $stmt->execute([(int) $bien['id']]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $json) {
            $antes = json_decode((string) $json, true);
            if (!is_array($antes) || !isset($antes['descripcion'], $antes['valor'])) {
                continue;
            }
            $cantidad = self::cantidadQueDice((string) $antes['descripcion']);
            if ($cantidad !== null && $cantidad > 0 && (float) $antes['valor'] > 0) {
                return (float) $antes['valor'] / $cantidad;
            }
        }

        $quedan = (int) $estadoLote['en_cartera'];

        return $quedan > 0 ? (float) $estadoLote['valor_en_cartera'] / $quedan : 0.0;
    }

    /** ¿Su último reintegro o reactivación propio es un reintegro? (lo reintegró el usuario aparte) */
    private static function salioPorReintegroPropio(int $bienId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT tipo FROM movimientos WHERE bien_id = ? AND tipo IN ('reintegro', 'reactivacion') ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$bienId]);

        return $stmt->fetchColumn() === 'reintegro';
    }
}
