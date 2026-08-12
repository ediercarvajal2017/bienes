<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Reúne lo que está en la papelera (eliminado_en IS NOT NULL) de las 8 tablas que
 * soportan borrado suave, sin filtrar por institución — esta pantalla es exclusiva
 * de superusuario, que ya opera sin ese filtro en el resto del sistema. Cada
 * consulta es simple y aparte (en vez de un UNION entre tablas con columnas muy
 * distintas) y se combinan y ordenan en PHP; el volumen esperado es bajo.
 */
final class Papelera
{
    private const TIPOS = [
        'usuario' => 'usuarios',
        'espacio' => 'espacios',
        'categoria' => 'categorias_bienes',
        'cargo' => 'cargos',
        'factura_administrativa' => 'facturas_administrativas',
        'formato_reintegro' => 'formatos_reintegro',
        'formato_plaqueteo' => 'formatos_plaqueteo',
        'cartera_envio' => 'cartera_envios',
    ];

    public static function tiposValidos(): array
    {
        return array_keys(self::TIPOS);
    }

    public static function listar(): array
    {
        $pdo = Database::connection();
        $filas = [];

        $consultas = [
            'usuario' => 'SELECT u.id, CONCAT(u.nombres, " ", u.apellidos, " (", u.documento, ")") AS titulo,
                                 i.nombre AS institucion_nombre, u.eliminado_en,
                                 CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                          FROM usuarios u
                          JOIN instituciones i ON i.id = u.institucion_id
                          LEFT JOIN usuarios e ON e.id = u.eliminado_por
                          WHERE u.eliminado_en IS NOT NULL',
            'espacio' => 'SELECT es.id, CONCAT(es.codigo, " - ", es.nombre) AS titulo,
                                 i.nombre AS institucion_nombre, es.eliminado_en,
                                 CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                          FROM espacios es
                          JOIN instituciones i ON i.id = es.institucion_id
                          LEFT JOIN usuarios e ON e.id = es.eliminado_por
                          WHERE es.eliminado_en IS NOT NULL',
            'categoria' => 'SELECT c.id, c.nombre AS titulo, i.nombre AS institucion_nombre, c.eliminado_en,
                                   CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                            FROM categorias_bienes c
                            JOIN instituciones i ON i.id = c.institucion_id
                            LEFT JOIN usuarios e ON e.id = c.eliminado_por
                            WHERE c.eliminado_en IS NOT NULL',
            'cargo' => 'SELECT c.id, c.nombre AS titulo, NULL AS institucion_nombre, c.eliminado_en,
                               CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                        FROM cargos c
                        LEFT JOIN usuarios e ON e.id = c.eliminado_por
                        WHERE c.eliminado_en IS NOT NULL',
            'factura_administrativa' => 'SELECT f.id, CONCAT(f.fecha_factura, " — ", f.descripcion) AS titulo,
                                                i.nombre AS institucion_nombre, f.eliminado_en,
                                                CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                                         FROM facturas_administrativas f
                                         JOIN instituciones i ON i.id = f.institucion_id
                                         LEFT JOIN usuarios e ON e.id = f.eliminado_por
                                         WHERE f.eliminado_en IS NOT NULL',
            'formato_reintegro' => 'SELECT f.id, CONCAT(f.fecha_reintegro, " — ", f.descripcion) AS titulo,
                                           i.nombre AS institucion_nombre, f.eliminado_en,
                                           CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                                    FROM formatos_reintegro f
                                    JOIN instituciones i ON i.id = f.institucion_id
                                    LEFT JOIN usuarios e ON e.id = f.eliminado_por
                                    WHERE f.eliminado_en IS NOT NULL',
            'formato_plaqueteo' => 'SELECT f.id, CONCAT(f.fecha_plaqueteo, " — ", f.descripcion) AS titulo,
                                           i.nombre AS institucion_nombre, f.eliminado_en,
                                           CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                                    FROM formatos_plaqueteo f
                                    JOIN instituciones i ON i.id = f.institucion_id
                                    LEFT JOIN usuarios e ON e.id = f.eliminado_por
                                    WHERE f.eliminado_en IS NOT NULL',
            'cartera_envio' => 'SELECT ce.id, CONCAT(ce.fecha_envio, " — ", ce.nombre_funcionario) AS titulo,
                                       i.nombre AS institucion_nombre, ce.eliminado_en,
                                       CONCAT(e.nombres, " ", e.apellidos) AS eliminado_por_nombre
                                FROM cartera_envios ce
                                JOIN instituciones i ON i.id = ce.institucion_id
                                LEFT JOIN usuarios e ON e.id = ce.eliminado_por
                                WHERE ce.eliminado_en IS NOT NULL',
        ];

        foreach ($consultas as $tipo => $sql) {
            foreach ($pdo->query($sql)->fetchAll() as $fila) {
                $fila['tipo'] = $tipo;
                $filas[] = $fila;
            }
        }

        usort($filas, static fn (array $a, array $b): int => strcmp($b['eliminado_en'], $a['eliminado_en']));

        return $filas;
    }

    public static function contar(): int
    {
        return count(self::listar());
    }

    /**
     * Nombre de tabla para un tipo válido, o null si $tipo no es uno de los 8
     * soportados (para que el controlador pueda rechazar un tipo inventado en la URL).
     */
    public static function tabla(string $tipo): ?string
    {
        return self::TIPOS[$tipo] ?? null;
    }
}
