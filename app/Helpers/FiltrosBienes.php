<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Usuario;

/**
 * Filtros de la lista de bienes (/bienes), validados en un solo lugar: los usa la lista y
 * la descarga de la cartera en Excel con esos mismos filtros (ReporteController). Todos
 * acotan dentro de la institución que se consulta, así que un id ajeno no trae nada.
 *
 * @phpstan-type Filtros array{q: ?string, categoria: ?int, estado: ?string, espacio: ?int, responsable: ?int, tipo: ?string}
 */
final class FiltrosBienes
{
    public const ESTADOS = ['activo' => 'Activo', 'reintegrado' => 'Reintegrado', 'en_reparacion' => 'En reparación', 'dado_de_baja' => 'Dado de baja'];

    /** Tipo de responsabilidad (ver App\Models\Asignacion). */
    public const TIPOS = ['grupal' => 'Grupal', 'individual' => 'Individual', 'sin_asignar' => 'Sin asignar'];

    /**
     * @param array<string, mixed> $consulta normalmente $_GET
     * @return Filtros
     */
    public static function desdeConsulta(array $consulta): array
    {
        $texto = static fn (string $clave): string => is_string($consulta[$clave] ?? null) ? trim($consulta[$clave]) : '';
        $entero = static fn (string $clave): ?int => ((int) $texto($clave)) > 0 ? (int) $texto($clave) : null;

        return [
            'q' => $texto('q') !== '' ? $texto('q') : null,
            'categoria' => $entero('categoria'),
            'estado' => array_key_exists($texto('estado'), self::ESTADOS) ? $texto('estado') : null,
            'espacio' => $entero('espacio'),
            'responsable' => $entero('responsable'),
            'tipo' => array_key_exists($texto('tipo'), self::TIPOS) ? $texto('tipo') : null,
        ];
    }

    /**
     * Los filtros con valor, para armar la dirección de la lista o de la descarga.
     *
     * @param Filtros $filtros
     * @return array<string, string|int>
     */
    public static function aConsulta(array $filtros): array
    {
        return array_filter($filtros, static fn ($valor): bool => $valor !== null);
    }

    /**
     * Los filtros aplicados, en palabras (para la hoja "Filtros" de la cartera).
     *
     * @param Filtros $filtros
     * @return list<array{0: string, 1: string}>
     */
    public static function descripcion(array $filtros): array
    {
        $filas = [];
        if ($filtros['q'] !== null) {
            $filas[] = ['Búsqueda', $filtros['q']];
        }
        if ($filtros['categoria'] !== null) {
            $filas[] = ['Categoría', (string) (Categoria::find($filtros['categoria'])['nombre'] ?? '#' . $filtros['categoria'])];
        }
        if ($filtros['estado'] !== null) {
            $filas[] = ['Estado', self::ESTADOS[$filtros['estado']]];
        }
        if ($filtros['espacio'] !== null) {
            $espacio = Espacio::find($filtros['espacio']);
            $filas[] = ['Espacio', $espacio ? $espacio['codigo'] . ' - ' . $espacio['nombre'] : '#' . $filtros['espacio']];
        }
        if ($filtros['responsable'] !== null) {
            $persona = Usuario::find($filtros['responsable']);
            $filas[] = ['Responsable', $persona ? trim($persona['nombres'] . ' ' . $persona['apellidos']) : '#' . $filtros['responsable']];
        }
        if ($filtros['tipo'] !== null) {
            $filas[] = ['Tipo de responsabilidad', self::TIPOS[$filtros['tipo']]];
        }

        return $filas;
    }
}
