<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class CargaMasiva
{
    public static function listar(?int $institucionId = null, string $tipo = 'bienes', int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condiciones($institucionId, $tipo);

        $sql = 'SELECT cm.*, u.nombres, u.apellidos
                FROM cargas_masivas cm
                JOIN usuarios u ON u.id = cm.usuario_id'
               . $whereSql
               . ' ORDER BY cm.created_at DESC, cm.id DESC'
               . Paginador::limitSql($pagina, $porPagina);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function contarListado(?int $institucionId = null, string $tipo = 'bienes'): int
    {
        [$whereSql, $params] = self::condiciones($institucionId, $tipo);

        $sql = 'SELECT COUNT(*) FROM cargas_masivas cm' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function condiciones(?int $institucionId, string $tipo): array
    {
        $condiciones = ['cm.tipo = ?'];
        $params = [$tipo];

        if ($institucionId !== null) {
            $condiciones[] = 'cm.institucion_id = ?';
            $params[] = $institucionId;
        }

        return [' WHERE ' . implode(' AND ', $condiciones), $params];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cargas_masivas WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cargas_masivas (institucion_id, tipo, usuario_id, archivo_path, total_filas, nuevos, modificados, sin_cambios, resultado_diff_json, aplicada)
             VALUES (:institucion_id, :tipo, :usuario_id, :archivo_path, :total_filas, :nuevos, :modificados, :sin_cambios, :resultado_diff_json, 0)'
        );
        $stmt->execute($datos + ['tipo' => 'bienes']);

        return (int) Database::connection()->lastInsertId();
    }

    public static function marcarAplicada(int $id): void
    {
        Database::connection()->prepare('UPDATE cargas_masivas SET aplicada = 1 WHERE id = ?')->execute([$id]);
    }
}
