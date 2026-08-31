<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Búsqueda de bienes por foto: guarda y compara la "huella visual" que el navegador
 * calcula de cada foto (un vector de ~1000 números, ver bienes.foto_vector). El cálculo
 * en sí (cargar el modelo, procesar la imagen) ocurre siempre en el navegador -- esta
 * clase solo persiste esos números y hace la comparación (similitud coseno) en PHP.
 *
 * Vive aparte de Bien.php (que ya es grande) porque es un subsistema autocontenido:
 * ninguno de sus métodos participa del CRUD normal de bienes.
 */
final class BienFotoVector
{
    /**
     * Bienes con foto pero sin huella calculada todavía -- candidatos para que el
     * navegador los procese en segundo plano al abrir la pantalla de búsqueda por foto.
     */
    public static function pendientesDeIndexar(int $institucionId, int $limite = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, foto_path FROM bienes
             WHERE institucion_id = ? AND foto_path IS NOT NULL AND foto_vector IS NULL
             ORDER BY id
             LIMIT ' . max(1, min($limite, 50))
        );
        $stmt->execute([$institucionId]);

        return $stmt->fetchAll();
    }

    public static function contarPendientes(int $institucionId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM bienes
             WHERE institucion_id = ? AND foto_path IS NOT NULL AND foto_vector IS NULL'
        );
        $stmt->execute([$institucionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Guarda la huella visual de un bien, redondeada a 5 decimales (de sobra para
     * comparar similitud, y evita filas innecesariamente pesadas). Solo escribe si el
     * bien pertenece a la institución indicada -- misma barrera de aislamiento que el
     * resto del sistema.
     *
     * @param float[] $vector
     */
    public static function guardarVector(int $id, int $institucionId, array $vector): bool
    {
        if ($vector === []) {
            return false;
        }

        $redondeado = array_map(static fn (float $n): float => round($n, 5), $vector);

        $stmt = Database::connection()->prepare(
            'UPDATE bienes SET foto_vector = ? WHERE id = ? AND institucion_id = ?'
        );
        $stmt->execute([json_encode($redondeado), $id, $institucionId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Compara la huella de la foto de consulta contra la de todos los bienes ya
     * indexados de la institución, y devuelve los más parecidos ordenados de mayor a
     * menor similitud (1.0 = idéntica, 0.0 = sin relación).
     *
     * Fuerza bruta en PHP -- perfectamente viable al tamaño de inventario de una
     * institución educativa (cientos a pocos miles de bienes); no hace falta una base
     * de datos vectorial para esto.
     *
     * @param float[] $vectorConsulta
     * @return array<int, array<string, mixed>>
     */
    public static function buscarSimilares(int $institucionId, array $vectorConsulta, int $limite = 12): array
    {
        if ($vectorConsulta === []) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, codigo_identificacion, descripcion, foto_path, estado, foto_vector
             FROM bienes
             WHERE institucion_id = ? AND foto_vector IS NOT NULL'
        );
        $stmt->execute([$institucionId]);

        $candidatos = [];
        while ($fila = $stmt->fetch()) {
            $vectorBien = json_decode((string) $fila['foto_vector'], true);
            if (!is_array($vectorBien) || $vectorBien === []) {
                continue;
            }

            $fila['similitud'] = self::similitudCoseno($vectorConsulta, $vectorBien);
            unset($fila['foto_vector']);
            $candidatos[] = $fila;
        }

        usort($candidatos, static fn (array $a, array $b) => $b['similitud'] <=> $a['similitud']);

        return array_slice($candidatos, 0, $limite);
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    private static function similitudCoseno(array $a, array $b): float
    {
        $longitud = min(count($a), count($b));
        if ($longitud === 0) {
            return 0.0;
        }

        $puntoProducto = 0.0;
        $normaA = 0.0;
        $normaB = 0.0;

        for ($i = 0; $i < $longitud; $i++) {
            $puntoProducto += $a[$i] * $b[$i];
            $normaA += $a[$i] ** 2;
            $normaB += $b[$i] ** 2;
        }

        if ($normaA === 0.0 || $normaB === 0.0) {
            return 0.0;
        }

        return $puntoProducto / (sqrt($normaA) * sqrt($normaB));
    }
}
