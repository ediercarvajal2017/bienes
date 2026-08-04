<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

/**
 * Bitácora de acciones destructivas/reversibles (hoy: enviar a la papelera y
 * restaurar). La tabla `auditoria` ya existía en el esquema desde el inicio del
 * proyecto pero nunca se conectó a ningún código — este modelo es lo que la pone
 * en uso. No registra cada creación/edición del sistema, solo lo relacionado con
 * la papelera, que es lo que puede perder datos si nadie se entera a tiempo.
 */
final class Auditoria
{
    public static function registrar(
        ?int $usuarioId,
        ?int $institucionId,
        string $accion,
        string $entidad,
        int $entidadId,
        ?array $datosAntes = null,
        ?array $datosDespues = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO auditoria (usuario_id, institucion_id, accion, entidad, entidad_id, datos_antes, datos_despues, ip)
             VALUES (:usuario_id, :institucion_id, :accion, :entidad, :entidad_id, :datos_antes, :datos_despues, :ip)'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'institucion_id' => $institucionId,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'datos_antes' => $datosAntes !== null ? json_encode($datosAntes, JSON_UNESCAPED_UNICODE) : null,
            'datos_despues' => $datosDespues !== null ? json_encode($datosDespues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function listar(int $pagina = 1, int $porPagina = 50): array
    {
        $sql = 'SELECT a.*, u.nombres AS usuario_nombres, u.apellidos AS usuario_apellidos, i.nombre AS institucion_nombre
                FROM auditoria a
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                LEFT JOIN instituciones i ON i.id = a.institucion_id
                ORDER BY a.created_at DESC' . Paginador::limitSql($pagina, $porPagina);

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function contar(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
    }
}
