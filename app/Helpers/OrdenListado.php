<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Orden por columna de un listado paginado (clic en el título de la columna: ?orden=&dir=).
 * El orden lo hace la base de datos, así que abarca todo el listado y no solo la página.
 *
 * Cada modelo declara qué columnas se pueden ordenar, como clave => expresiones SQL:
 *   public const ORDENES = ['nombre' => ['u.nombres', 'u.apellidos'], 'estado' => ['u.activo']];
 * Una expresión que empieza con «=» va tal cual, sin sentido (p. ej. '=c.nombre IS NULL', para
 * dejar los vacíos siempre al final). Solo esas claves llegan al SQL: cualquier otro valor deja
 * el orden de siempre.
 */
final class OrdenListado
{
    /**
     * La columna y el sentido pedidos en la dirección, si la columna está permitida.
     *
     * @param array<string, list<string>> $columnas
     * @param array<mixed>|null $consulta por defecto $_GET
     * @return array{0: ?string, 1: string} [clave o null, 'asc'|'desc']
     */
    public static function desdeConsulta(array $columnas, ?array $consulta = null): array
    {
        $consulta ??= $_GET;
        $orden = $consulta['orden'] ?? null;
        $orden = is_string($orden) && isset($columnas[$orden]) ? $orden : null;
        $dir = $orden !== null && ($consulta['dir'] ?? '') === 'desc' ? 'desc' : 'asc';

        return [$orden, $dir];
    }

    /**
     * « ORDER BY …» con la columna elegida y el desempate (para que una página no repita ni
     * salte filas), o el orden de siempre si no hay columna elegida.
     *
     * @param array<string, list<string>> $columnas
     */
    public static function sql(array $columnas, ?string $orden, string $dir, string $porDefecto, string $desempate): string
    {
        if ($orden === null || !isset($columnas[$orden])) {
            return ' ORDER BY ' . $porDefecto;
        }
        $sentido = $dir === 'desc' ? ' DESC' : ' ASC';
        $partes = array_map(
            static fn (string $expresion): string => str_starts_with($expresion, '=') ? substr($expresion, 1) : $expresion . $sentido,
            [...$columnas[$orden], $desempate]
        );

        return ' ORDER BY ' . implode(', ', $partes);
    }
}
