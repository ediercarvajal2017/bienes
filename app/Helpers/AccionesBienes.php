<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Core\Url;

/**
 * Opciones del menú "Acciones" de Bienes, definidas una sola vez: el mismo menú está en el
 * listado y en cada pantalla a la que lleva, para pasar de una acción a otra sin volver al
 * listado. Cada opción aparece con el mismo permiso que exige su ruta (public/index.php).
 */
final class AccionesBienes
{
    /**
     * @return list<array{clave: string, texto: string, url: string}>
     */
    public static function opciones(): array
    {
        $puede = static fn (string $permiso): bool => Auth::esSuperusuario() || Auth::tienePermiso($permiso);

        $opciones = [
            ['alta_masiva', 'Alta masiva idéntica', '/bienes/alta-masiva', $puede('bienes.crear')],
            ['carga_masiva', 'Carga masiva de bienes', '/cargas-masivas', $puede('cargas.masivas')],
            ['asignar', 'Asignar bienes', '/asignaciones', $puede('asignaciones.crear')],
            ['reintegrar', 'Reintegrar bienes', '/reintegros', $puede('asignaciones.crear')],
            ['reintegrar_cantidad', 'Reintegrar por cantidad', '/reintegros/unidades', $puede('asignaciones.crear')],
            ['qr_masivo', 'Generar QR masivo', '/bienes/qr-masivo', Auth::rol() !== 'docente' && $puede('bienes.ver')],
            ['buscar_foto', 'Buscar por foto', '/bienes/buscar-por-foto', $puede('bienes.ver')],
        ];

        $visibles = [];
        foreach ($opciones as [$clave, $texto, $ruta, $visible]) {
            if ($visible) {
                $visibles[] = ['clave' => $clave, 'texto' => $texto, 'url' => Url::to($ruta)];
            }
        }

        return $visibles;
    }
}
