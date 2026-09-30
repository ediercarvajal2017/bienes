<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Movimiento;

/**
 * Reintegro individual de un bien: lo usan el reintegro desde la ficha del bien
 * (MovimientoController) y la aprobación de una solicitud de reintegro de un docente
 * (SolicitudReintegroController), para que ambos apliquen exactamente la misma regla
 * (Bien::motivoNoReintegrable) y los mismos pasos.
 */
final class ReintegroService
{
    /**
     * Registra el movimiento de reintegro, cierra la asignación, pasa el bien a
     * "reintegrado" y lo audita, todo en una transacción (se une a la que ya esté abierta).
     *
     * @param array<string, mixed> $extraAuditoria datos adicionales para el registro de auditoría
     * @return int id del movimiento de reintegro creado
     * @throws \DomainException si el bien no se puede reintegrar
     */
    public static function reintegrar(
        array $bien,
        ?array $asignacionActiva,
        string $fecha,
        string $destino,
        ?string $observaciones,
        array $extraAuditoria = []
    ): int {
        if ($motivo = Bien::motivoNoReintegrable($bien, $asignacionActiva !== null)) {
            throw new \DomainException('No se puede reintegrar: ' . $motivo . '.');
        }

        $bienId = (int) $bien['id'];

        return Database::transaccion(static function () use ($bien, $bienId, $asignacionActiva, $fecha, $destino, $observaciones, $extraAuditoria): int {
            $movimientoId = Movimiento::crear([
                'bien_id' => $bienId,
                'tipo' => 'reintegro',
                'fecha' => $fecha,
                'responsable_id' => Auth::id(),
                'espacio_origen_id' => $asignacionActiva['espacio_id'] ?? null,
                'persona_origen_id' => $asignacionActiva['usuario_responsable_id'] ?? null,
                'espacio_destino_id' => null,
                'destino_texto' => $destino,
                'observaciones' => $observaciones,
            ]);

            Asignacion::cerrarActivasDe($bienId);
            Bien::cambiarEstado($bienId, 'reintegrado');
            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'reintegrar', 'bien', $bienId,
                ['estado' => $bien['estado'], 'espacio_id' => $asignacionActiva['espacio_id'] ?? null,
                    'usuario_responsable_id' => $asignacionActiva['usuario_responsable_id'] ?? null],
                ['estado' => 'reintegrado', 'destino' => $destino, 'fecha' => $fecha] + $extraAuditoria);

            return $movimientoId;
        });
    }
}
