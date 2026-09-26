<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginador;

final class CargaMasiva
{
    public static function listar(?int $institucionId = null, string $tipo = 'bienes', ?string $busqueda = null, int $pagina = 1, int $porPagina = 50): array
    {
        [$whereSql, $params] = self::condiciones($institucionId, $tipo, $busqueda);

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

    public static function contarListado(?int $institucionId = null, string $tipo = 'bienes', ?string $busqueda = null): int
    {
        [$whereSql, $params] = self::condiciones($institucionId, $tipo, $busqueda);

        $sql = 'SELECT COUNT(*)
                FROM cargas_masivas cm
                JOIN usuarios u ON u.id = cm.usuario_id'
               . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca por quién la subió, la fecha o el estado (aplicada/pendiente).
     */
    private static function condiciones(?int $institucionId, string $tipo, ?string $busqueda): array
    {
        $condiciones = ['cm.tipo = ?'];
        $params = [$tipo];

        if ($institucionId !== null) {
            $condiciones[] = 'cm.institucion_id = ?';
            $params[] = $institucionId;
        }

        if ($busqueda !== null && $busqueda !== '') {
            // COLLATE explícito en cada comparación: sin esto, comparar los literales
            // 'aplicada'/'pendiente' contra el parámetro puede chocar con la colación de
            // usuarios/cargas_masivas si difieren entre sí (error de MySQL 1267 "Illegal
            // mix of collations"), algo que puede pasar aunque en el código nunca se haya
            // fijado una colación distinta a propósito.
            $termino = '%' . $busqueda . '%';
            $condiciones[] = "(u.nombres LIKE ? COLLATE utf8mb4_unicode_ci OR u.apellidos LIKE ? COLLATE utf8mb4_unicode_ci
                OR DATE_FORMAT(cm.created_at, '%Y-%m-%d %H:%i') LIKE ? COLLATE utf8mb4_unicode_ci
                OR (cm.aplicada = 1 AND 'aplicada' LIKE ? COLLATE utf8mb4_unicode_ci)
                OR (cm.aplicada = 0 AND 'pendiente' LIKE ? COLLATE utf8mb4_unicode_ci))";
            array_push($params, $termino, $termino, $termino, $termino, $termino);
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

    /**
     * Aplica la carga UNA sola vez y todo o nada: dentro de una transacción marca la carga
     * como aplicada solo si todavía no lo estaba (la condición va en el UPDATE) y ejecuta
     * $aplicar. Antes, un doble clic podía aplicarla dos veces, y un error a mitad de
     * camino la dejaba aplicada a medias con aplicada = 0.
     *
     * @template T
     * @param callable(): T $aplicar
     * @return T
     * @throws \DomainException si la carga ya había sido aplicada
     */
    public static function aplicarUnaVez(int $id, callable $aplicar): mixed
    {
        return Database::transaccion(static function (\PDO $pdo) use ($id, $aplicar) {
            $stmt = $pdo->prepare('UPDATE cargas_masivas SET aplicada = 1 WHERE id = ? AND aplicada = 0');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new \DomainException('Esta carga ya fue aplicada anteriormente.');
            }

            return $aplicar();
        });
    }
}
