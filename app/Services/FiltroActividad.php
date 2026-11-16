<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Condiciones comunes de los reportes de actividad: instituciones, funcionario y período
 * (en instantes exactos, ver ReportesControl).
 */
final class FiltroActividad
{
    /** @param list<int>|null $institucionIds */
    public function __construct(
        private readonly ?array $institucionIds,
        private readonly ?int $usuarioId,
        private readonly int $desde,
        private readonly int $hasta,
    ) {
    }

    /** @return array{0: string, 1: list<int|string>} */
    public function auditoria(string $alias): array
    {
        return $this->tabla("{$alias}.created_at", "{$alias}.institucion_id", "{$alias}.usuario_id");
    }

    /** @return array{0: string, 1: list<int|string>} */
    public function tabla(string $columnaFecha, string $columnaInstitucion, string $columnaUsuario): array
    {
        $condiciones = ["{$columnaFecha} >= FROM_UNIXTIME(?)", "{$columnaFecha} < FROM_UNIXTIME(?)"];
        $params = [$this->desde, $this->hasta];
        if ($this->institucionIds !== null) {
            $condiciones[] = $this->institucionIds === []
                ? '1 = 0'
                : "{$columnaInstitucion} IN (" . implode(',', array_map('intval', $this->institucionIds)) . ')';
        }
        if ($this->usuarioId !== null) {
            $condiciones[] = "{$columnaUsuario} = ?";
            $params[] = $this->usuarioId;
        }

        return [implode(' AND ', $condiciones), $params];
    }
}
