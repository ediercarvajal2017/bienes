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
            // Sin COLLATE sobre los parámetros: con consultas preparadas reales, MariaDB
            // 10.4 recibe el parámetro como "binary" y "? COLLATE utf8mb4_unicode_ci" daba
            // error 1253 (la pantalla respondía 500). Las columnas se comparan con su propia
            // colación, y "aplicada"/"pendiente" se resuelven aquí, en PHP, en vez de
            // comparar literales contra el parámetro en SQL (lo que causaba el error 1267
            // "Illegal mix of collations" que el COLLATE intentaba evitar).
            $termino = '%' . $busqueda . '%';
            $estado = [];
            $minuscula = mb_strtolower($busqueda);
            if (str_contains('aplicada', $minuscula)) {
                $estado[] = 'cm.aplicada = 1';
            }
            if (str_contains('pendiente', $minuscula)) {
                $estado[] = 'cm.aplicada = 0';
            }
            $condiciones[] = "(u.nombres LIKE ? OR u.apellidos LIKE ?
                OR DATE_FORMAT(cm.created_at, '%Y-%m-%d %H:%i') LIKE ?"
                . ($estado !== [] ? ' OR ' . implode(' OR ', $estado) : '') . ')';
            array_push($params, $termino, $termino, $termino);
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
