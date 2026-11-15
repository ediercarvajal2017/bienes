<?php

/**
 * Definición ÚNICA del menú de navegación: de aquí salen el menú lateral
 * (partials/menu_lateral.php), la barra inferior del celular (partials/menu_inferior.php)
 * y el nombre de sección de la ruta de navegación (layout.php). Antes los grupos y sus
 * rutas se escribían dos veces y había que mantenerlos a mano.
 *
 * Cada opción:
 *  - texto e icono: clave del diccionario partials/iconos.php (de ahí sale también la versión
 *    rellena de la opción activa);
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
            ['texto' => 'Panel principal', 'icono' => 'panel', 'ruta' => '/dashboard', 'activo' => ['/dashboard'], 'visible' => true],
            ['texto' => 'Buscar', 'icono' => 'buscar', 'ruta' => '/buscar', 'activo' => ['/buscar'], 'visible' => true],
        ],
    ],
    [
        'clave' => 'inventario',
        'titulo' => 'Inventario',
        'opciones' => [
            // Asignar y reintegrar en lote, la carga masiva y los QR se abren desde Bienes >
            // Acciones masivas: en esas pantallas el menú sigue marcando "Bienes".
            ['texto' => 'Bienes', 'icono' => 'bien', 'ruta' => '/bienes',
                'activo' => ['/bienes', '=/asignaciones', '=/reintegros', '/reintegros/unidades', '/cargas-masivas'], 'visible' => $puede('bienes.ver')],
            ['texto' => 'Espacios', 'icono' => 'espacio', 'ruta' => '/espacios', 'activo' => ['/espacios'], 'visible' => $puede('espacios.ver')],
            ['texto' => 'Escanear QR', 'icono' => 'escanear', 'ruta' => '/escanear', 'activo' => ['/escanear'], 'visible' => true],
        ],
    ],
    [
        'clave' => 'reintegros',
        'titulo' => 'Reintegros',
        'opciones' => [
            ['texto' => 'Solicitudes', 'icono' => 'solicitud', 'ruta' => '/reintegros/solicitudes',
                'activo' => ['/reintegros/solicitudes'], 'visible' => $puede('asignaciones.crear') || Auth::tienePermiso('reintegros.solicitar'), 'contador' => 'solicitudes'],
            ['texto' => 'Lotes de reintegro', 'icono' => 'reintegro', 'ruta' => '/reintegros/lotes',
                'activo' => ['/reintegros/lotes'], 'visible' => $puede('asignaciones.crear'), 'contador' => 'lotes'],
        ],
    ],
    [
        'clave' => 'control',
        'titulo' => 'Control',
        'opciones' => [
            ['texto' => 'Bajas', 'icono' => 'baja', 'ruta' => '/bajas',
                'activo' => ['/bajas'], 'visible' => $puede('bajas.crear') || $puede('bajas.aprobar'), 'contador' => 'bajas'],
            ['texto' => 'Verificación física', 'icono' => 'verificacion', 'ruta' => '/verificaciones',
                'activo' => ['/verificaciones'], 'visible' => $puede('verificaciones.gestionar'), 'contador' => 'hallazgos'],
        ],
    ],
    [
        'clave' => 'documentos',
        'titulo' => 'Documentos',
        'opciones' => [
            ['texto' => 'Reportes', 'icono' => 'reporte', 'ruta' => '/reportes', 'activo' => ['/reportes'], 'visible' => $puede('reportes.generar')],
            ['texto' => 'Cartera (histórico)', 'icono' => 'cartera', 'ruta' => '/cartera/enviar', 'activo' => ['/cartera'], 'visible' => $puede('cartera.gestionar')],
            ['texto' => 'Formatos de reintegro', 'icono' => 'formato_reintegro', 'ruta' => '/formatos-reintegro', 'activo' => ['/formatos-reintegro'], 'visible' => $puede('formatos_reintegro.gestionar')],
            ['texto' => 'Formatos de plaqueteo', 'icono' => 'formato_plaqueteo', 'ruta' => '/formatos-plaqueteo', 'activo' => ['/formatos-plaqueteo'], 'visible' => $puede('formatos_plaqueteo.gestionar')],
            ['texto' => 'Facturas', 'icono' => 'factura', 'ruta' => '/facturas', 'activo' => ['/facturas'], 'visible' => $puede('facturas_admin.gestionar')],
        ],
    ],
    [
        'clave' => 'administracion',
        'titulo' => 'Administración',
        'opciones' => [
            ['texto' => 'Usuarios', 'icono' => 'usuarios', 'ruta' => '/usuarios', 'activo' => ['/usuarios'], 'visible' => $puede('usuarios.ver')],
            ['texto' => 'Instituciones', 'icono' => 'institucion', 'ruta' => '/instituciones', 'activo' => ['/instituciones'], 'visible' => $puede('instituciones.ver')],
            ['texto' => 'Cargos', 'icono' => 'cargo', 'ruta' => '/cargos', 'activo' => ['/cargos'], 'visible' => $su],
            ['texto' => 'Categorías', 'icono' => 'categoria', 'ruta' => '/categorias', 'activo' => ['/categorias'], 'visible' => $puede('categorias.gestionar')],
            ['texto' => 'Papelera de reciclaje', 'icono' => 'papelera', 'ruta' => '/papelera', 'activo' => ['/papelera'], 'visible' => $su],
            ['texto' => 'Auditoría', 'icono' => 'auditoria', 'ruta' => '/auditoria', 'activo' => ['/auditoria'], 'visible' => $su],
        ],
    ],
];
