<?php

/**
 * Definición ÚNICA del menú de navegación: de aquí salen el menú lateral
 * (partials/menu_lateral.php), la barra inferior del celular (partials/menu_inferior.php)
 * y el nombre de sección de la ruta de navegación (layout.php). Antes los grupos y sus
 * rutas se escribían dos veces y había que mantenerlos a mano.
 *
 * Cada opción:
 *  - texto, icono (Bootstrap Icons) e iconoActivo (versión rellena, si existe);
 *  - ruta: a dónde lleva;
 *  - activo: prefijos de URL que la marcan como página actual (con "=" delante, ruta exacta);
 *  - visible: si el usuario tiene permiso (mismas reglas que las rutas en public/index.php);
 *  - contador: clave de App\Services\ContadoresMenu (pendientes que se muestran al lado).
 */

use App\Core\Auth;

$su = Auth::esSuperusuario();
$puede = static fn (string $permiso): bool => $su || Auth::tienePermiso($permiso);

return [
    [
        'clave' => 'inicio',
        'titulo' => null,
        'opciones' => [
            ['texto' => 'Panel principal', 'icono' => 'grid-1x2', 'iconoActivo' => 'grid-1x2-fill', 'ruta' => '/dashboard', 'activo' => ['/dashboard'], 'visible' => true],
            ['texto' => 'Buscar', 'icono' => 'search', 'ruta' => '/buscar', 'activo' => ['/buscar'], 'visible' => true],
        ],
    ],
    [
        'clave' => 'inventario',
        'titulo' => 'Inventario',
        'opciones' => [
            // Asignar y reintegrar en lote, la carga masiva y los QR se abren desde Bienes >
            // Acciones masivas: en esas pantallas el menú sigue marcando "Bienes".
            ['texto' => 'Bienes', 'icono' => 'box-seam', 'iconoActivo' => 'box-seam-fill', 'ruta' => '/bienes',
                'activo' => ['/bienes', '=/asignaciones', '=/reintegros', '/cargas-masivas'], 'visible' => $puede('bienes.ver')],
            ['texto' => 'Espacios', 'icono' => 'door-open', 'iconoActivo' => 'door-open-fill', 'ruta' => '/espacios', 'activo' => ['/espacios'], 'visible' => $puede('espacios.ver')],
            ['texto' => 'Escanear QR', 'icono' => 'qr-code-scan', 'ruta' => '/escanear', 'activo' => ['/escanear'], 'visible' => true],
        ],
    ],
    [
        'clave' => 'reintegros',
        'titulo' => 'Reintegros',
        'opciones' => [
            ['texto' => 'Solicitudes', 'icono' => 'inbox', 'iconoActivo' => 'inbox-fill', 'ruta' => '/reintegros/solicitudes',
                'activo' => ['/reintegros/solicitudes'], 'visible' => $puede('asignaciones.crear') || Auth::tienePermiso('reintegros.solicitar'), 'contador' => 'solicitudes'],
            ['texto' => 'Lotes de reintegro', 'icono' => 'file-earmark-spreadsheet', 'iconoActivo' => 'file-earmark-spreadsheet-fill', 'ruta' => '/reintegros/lotes',
                'activo' => ['/reintegros/lotes'], 'visible' => $puede('asignaciones.crear')],
        ],
    ],
    [
        'clave' => 'control',
        'titulo' => 'Control',
        'opciones' => [
            ['texto' => 'Bajas', 'icono' => 'exclamation-triangle', 'iconoActivo' => 'exclamation-triangle-fill', 'ruta' => '/bajas',
                'activo' => ['/bajas'], 'visible' => $puede('bajas.crear') || $puede('bajas.aprobar'), 'contador' => 'bajas'],
            ['texto' => 'Verificación física', 'icono' => 'clipboard2-check', 'iconoActivo' => 'clipboard2-check-fill', 'ruta' => '/verificaciones',
                'activo' => ['/verificaciones'], 'visible' => $puede('verificaciones.gestionar'), 'contador' => 'hallazgos'],
        ],
    ],
    [
        'clave' => 'documentos',
        'titulo' => 'Documentos',
        'opciones' => [
            ['texto' => 'Reportes', 'icono' => 'bar-chart', 'iconoActivo' => 'bar-chart-fill', 'ruta' => '/reportes', 'activo' => ['/reportes'], 'visible' => $puede('reportes.generar')],
            ['texto' => 'Cartera (histórico)', 'icono' => 'archive', 'iconoActivo' => 'archive-fill', 'ruta' => '/cartera/enviar', 'activo' => ['/cartera'], 'visible' => $puede('cartera.gestionar')],
            ['texto' => 'Formatos de reintegro', 'icono' => 'file-earmark-check', 'iconoActivo' => 'file-earmark-check-fill', 'ruta' => '/formatos-reintegro', 'activo' => ['/formatos-reintegro'], 'visible' => $puede('formatos_reintegro.gestionar')],
            ['texto' => 'Formatos de plaqueteo', 'icono' => 'tag', 'iconoActivo' => 'tag-fill', 'ruta' => '/formatos-plaqueteo', 'activo' => ['/formatos-plaqueteo'], 'visible' => $puede('formatos_plaqueteo.gestionar')],
            ['texto' => 'Facturas', 'icono' => 'receipt', 'ruta' => '/facturas', 'activo' => ['/facturas'], 'visible' => $puede('facturas_admin.gestionar')],
        ],
    ],
    [
        'clave' => 'administracion',
        'titulo' => 'Administración',
        'opciones' => [
            ['texto' => 'Usuarios', 'icono' => 'people', 'iconoActivo' => 'people-fill', 'ruta' => '/usuarios', 'activo' => ['/usuarios'], 'visible' => $puede('usuarios.ver')],
            ['texto' => 'Instituciones', 'icono' => 'building', 'iconoActivo' => 'building-fill', 'ruta' => '/instituciones', 'activo' => ['/instituciones'], 'visible' => $puede('instituciones.ver')],
            ['texto' => 'Cargos', 'icono' => 'person-badge', 'iconoActivo' => 'person-badge-fill', 'ruta' => '/cargos', 'activo' => ['/cargos'], 'visible' => $su],
            ['texto' => 'Categorías', 'icono' => 'tags', 'iconoActivo' => 'tags-fill', 'ruta' => '/categorias', 'activo' => ['/categorias'], 'visible' => $puede('categorias.gestionar')],
            ['texto' => 'Papelera de reciclaje', 'icono' => 'trash3', 'iconoActivo' => 'trash3-fill', 'ruta' => '/papelera', 'activo' => ['/papelera'], 'visible' => $su],
            ['texto' => 'Auditoría', 'icono' => 'journal-text', 'ruta' => '/auditoria', 'activo' => ['/auditoria'], 'visible' => $su],
        ],
    ],
];
