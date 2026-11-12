<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Helpers\FechaMovimiento;
use App\Models\Auditoria;
use App\Models\LoteReintegro;

/**
 * Reglas de los lotes de reintegro: marcar como reintegrado (entregado a la Alcaldía) y
 * reabrir. Cada operación es todo o nada: si un lote no cumple, no cambia ninguno.
 *
 * Las reglas lanzan \DomainException con un mensaje para el usuario; cualquier otra
 * excepción es un error real y se reporta aparte.
 */
final class LoteReintegroService
{
    private const LARGO_MAXIMO_NOTA = 500;

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

    private static function textoOpcional(?string $texto, string $nombre): ?string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return null;
        }
        if (mb_strlen($texto) > self::LARGO_MAXIMO_NOTA) {
            throw new \DomainException("{$nombre} no puede tener más de " . self::LARGO_MAXIMO_NOTA . ' caracteres.');
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
