<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;

Env::cargar();

$appConfig = require dirname(__DIR__) . '/config/app.php';

date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

use App\Controllers\ArchivoController;
use App\Controllers\AsignacionController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\BajaController;
use App\Controllers\BienController;
use App\Controllers\BienFotoCargaMasivaController;
use App\Controllers\CargaMasivaController;
use App\Controllers\CargoController;
use App\Controllers\CarteraController;
use App\Controllers\CategoriaController;
use App\Controllers\DashboardController;
use App\Controllers\EscaneoController;
use App\Controllers\EspacioCargaMasivaController;
use App\Controllers\EspacioController;
use App\Controllers\FacturaAdministrativaController;
use App\Controllers\FormatoPlaqueteoController;
use App\Controllers\FormatoReintegroController;
use App\Controllers\HallazgoController;
use App\Controllers\InstitucionController;
use App\Controllers\ManualController;
use App\Controllers\MovimientoController;
use App\Controllers\PapeleraController;
use App\Controllers\PasswordController;
use App\Controllers\QrController;
use App\Controllers\QrMasivoController;
use App\Controllers\ReintegroController;
use App\Controllers\ReporteController;
use App\Controllers\UsuarioCargaMasivaController;
use App\Controllers\UsuarioController;
use App\Controllers\VerificacionController;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\InstitucionScopeMiddleware;
use App\Middlewares\PermissionMiddleware;
use App\Middlewares\SuperusuarioMiddleware;

Session::start();

$router = new Router();

$router->get('/', [AuthController::class, 'redirectRoot']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

$router->get('/olvide-contrasena', [PasswordController::class, 'formularioOlvideContrasena']);
$router->post('/olvide-contrasena', [PasswordController::class, 'enviarEnlaceReset']);
$router->get('/restablecer-contrasena/{token}', [PasswordController::class, 'formularioReset']);
$router->post('/restablecer-contrasena/{token}', [PasswordController::class, 'restablecerPassword']);
$router->get('/olvide-correo', [PasswordController::class, 'formularioOlvideCorreo']);
$router->post('/olvide-correo', [PasswordController::class, 'buscarCorreo']);

$router->get('/dashboard', [DashboardController::class, 'index'], [
    AuthMiddleware::class,
    InstitucionScopeMiddleware::class,
]);

$router->get('/archivos/{tipo}/{archivo}', [ArchivoController::class, 'mostrar'], [AuthMiddleware::class]);

$router->get('/instituciones', [InstitucionController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':instituciones.ver',
]);
$router->get('/instituciones/crear', [InstitucionController::class, 'crear'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':instituciones.crear',
]);
$router->post('/instituciones', [InstitucionController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':instituciones.crear',
]);
$router->get('/instituciones/{id}/editar', [InstitucionController::class, 'editar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':instituciones.ver',
]);
$router->post('/instituciones/{id}', [InstitucionController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':instituciones.editar',
]);
$router->post('/instituciones/{id}/estado', [InstitucionController::class, 'cambiarEstado'], [
    AuthMiddleware::class, SuperusuarioMiddleware::class,
]);

$router->get('/usuarios', [UsuarioController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.ver',
]);
$router->get('/usuarios/crear', [UsuarioController::class, 'crear'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);
$router->post('/usuarios', [UsuarioController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);

$router->get('/usuarios/carga-masiva', [UsuarioCargaMasivaController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);
$router->get('/usuarios/carga-masiva/plantilla.xlsx', [UsuarioCargaMasivaController::class, 'plantilla'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);
$router->post('/usuarios/carga-masiva/subir', [UsuarioCargaMasivaController::class, 'subir'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);
$router->get('/usuarios/carga-masiva/{id}', [UsuarioCargaMasivaController::class, 'mostrar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);
$router->post('/usuarios/carga-masiva/{id}/confirmar', [UsuarioCargaMasivaController::class, 'confirmar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.crear',
]);

$router->get('/usuarios/{id}/editar', [UsuarioController::class, 'editar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.editar',
]);
$router->post('/usuarios/{id}', [UsuarioController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.editar',
]);
$router->post('/usuarios/{id}/estado', [UsuarioController::class, 'cambiarEstado'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.eliminar',
]);
$router->post('/usuarios/{id}/eliminar', [UsuarioController::class, 'eliminar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':usuarios.eliminar',
]);

$router->get('/cargos', [CargoController::class, 'index'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);
$router->post('/cargos', [CargoController::class, 'guardar'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);
$router->post('/cargos/{id}', [CargoController::class, 'actualizar'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);
$router->post('/cargos/{id}/estado', [CargoController::class, 'cambiarEstado'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);
$router->post('/cargos/{id}/eliminar', [CargoController::class, 'eliminar'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);

$router->get('/papelera', [PapeleraController::class, 'index'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);
$router->post('/papelera/{tipo}/{id}/restaurar', [PapeleraController::class, 'restaurar'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);

$router->get('/auditoria', [AuditoriaController::class, 'index'], [AuthMiddleware::class, SuperusuarioMiddleware::class]);

$router->get('/espacios', [EspacioController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.ver',
]);
$router->get('/espacios/crear', [EspacioController::class, 'crear'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);
$router->post('/espacios', [EspacioController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);

$router->get('/espacios/carga-masiva', [EspacioCargaMasivaController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);
$router->get('/espacios/carga-masiva/plantilla.xlsx', [EspacioCargaMasivaController::class, 'plantilla'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);
$router->post('/espacios/carga-masiva/subir', [EspacioCargaMasivaController::class, 'subir'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);
$router->get('/espacios/carga-masiva/{id}', [EspacioCargaMasivaController::class, 'mostrar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);
$router->post('/espacios/carga-masiva/{id}/confirmar', [EspacioCargaMasivaController::class, 'confirmar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.crear',
]);

$router->get('/espacios/{id}/editar', [EspacioController::class, 'editar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.ver',
]);
$router->post('/espacios/{id}', [EspacioController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.editar',
]);
$router->post('/espacios/{id}/estado', [EspacioController::class, 'cambiarEstado'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.editar',
]);
$router->post('/espacios/{id}/eliminar', [EspacioController::class, 'eliminar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':espacios.editar',
]);

$router->get('/bienes', [BienController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);
$router->get('/bienes/crear', [BienController::class, 'crear'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.crear',
]);
$router->get('/bienes/siguiente-codigo-sin-cartera', [BienController::class, 'siguienteCodigoSinCartera'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.crear',
]);
$router->post('/bienes', [BienController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.crear',
]);

$router->get('/bienes/alta-masiva', [BienController::class, 'crearLote'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.crear',
]);
$router->post('/bienes/alta-masiva', [BienController::class, 'guardarLote'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.crear',
]);

$router->get('/bienes/qr-masivo', [QrMasivoController::class, 'formulario'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);
$router->post('/bienes/qr-masivo', [QrMasivoController::class, 'generar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);

$router->get('/bienes/carga-masiva-fotos', [BienFotoCargaMasivaController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);
$router->post('/bienes/carga-masiva-fotos', [BienFotoCargaMasivaController::class, 'subir'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);

$router->get('/bienes/{id}/editar', [BienController::class, 'editar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);
$router->post('/bienes/{id}', [BienController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.editar',
]);

$router->post('/bienes/{id}/asignar', [MovimientoController::class, 'asignar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->post('/bienes/{id}/trasladar', [MovimientoController::class, 'trasladar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->post('/bienes/{id}/reintegrar', [MovimientoController::class, 'reintegrar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);

$router->get('/asignaciones', [AsignacionController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->post('/asignaciones', [AsignacionController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);

$router->get('/reintegros', [ReintegroController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->post('/reintegros', [ReintegroController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->get('/reintegros/buscar-qr', [ReintegroController::class, 'buscarPorQr'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->get('/reintegros/lotes/generar', [ReintegroController::class, 'pendientesDeLote'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->post('/reintegros/lotes/generar', [ReintegroController::class, 'generarLote'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->get('/reintegros/lotes', [ReintegroController::class, 'lotes'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->get('/reintegros/lotes/{id}', [ReintegroController::class, 'loteDetalle'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);
$router->get('/reintegros/lotes/{id}/formato.xlsx', [ReintegroController::class, 'loteFormato'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':asignaciones.crear',
]);

$router->get('/categorias', [CategoriaController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':categorias.gestionar']);
$router->get('/categorias/por-institucion', [CategoriaController::class, 'porInstitucion'], [AuthMiddleware::class, PermissionMiddleware::class . ':bienes.crear']);
$router->post('/categorias', [CategoriaController::class, 'guardar'], [AuthMiddleware::class, PermissionMiddleware::class . ':categorias.gestionar']);
$router->post('/categorias/{id}', [CategoriaController::class, 'actualizar'], [AuthMiddleware::class, PermissionMiddleware::class . ':categorias.gestionar']);
$router->post('/categorias/{id}/estado', [CategoriaController::class, 'cambiarEstado'], [AuthMiddleware::class, PermissionMiddleware::class . ':categorias.gestionar']);
$router->post('/categorias/{id}/eliminar', [CategoriaController::class, 'eliminar'], [AuthMiddleware::class, PermissionMiddleware::class . ':categorias.gestionar']);

// Códigos QR: vista e imagen públicas (sin sesión), pensadas para escanear una etiqueta física
$router->get('/qr/{token}', [QrController::class, 'mostrar']);
$router->get('/qr/{token}/imagen', [QrController::class, 'imagen']);

$router->get('/qr/{token}/baja', [BajaController::class, 'formulario'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bajas.crear',
]);
$router->post('/qr/{token}/baja', [BajaController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bajas.crear',
]);

$router->post('/qr/{token}/verificar', [VerificacionController::class, 'verificar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);

$router->post('/qr/{token}/confirmar-etiqueta', [QrController::class, 'confirmarEtiqueta'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);

$router->get('/verificaciones', [VerificacionController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);
$router->get('/verificaciones/crear', [VerificacionController::class, 'crear'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);
$router->post('/verificaciones', [VerificacionController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);
$router->get('/verificaciones/{id}', [VerificacionController::class, 'mostrar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);
$router->post('/verificaciones/{id}/cerrar', [VerificacionController::class, 'cerrar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);
$router->post('/verificaciones/{jornadaId}/discrepancias/{verificacionId}/revisada', [VerificacionController::class, 'marcarRevisada'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);
$router->get('/verificaciones/{id}/exportar.xlsx', [VerificacionController::class, 'exportarXlsx'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':verificaciones.gestionar',
]);

$router->get('/bajas', [BajaController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bajas.crear',
]);
$router->post('/bajas/{id}/aprobar', [BajaController::class, 'aprobar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bajas.aprobar',
]);
$router->post('/bajas/{id}/rechazar', [BajaController::class, 'rechazar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bajas.aprobar',
]);

$router->get('/escanear', [EscaneoController::class, 'index'], [AuthMiddleware::class]);
$router->get('/escanear/buscar', [EscaneoController::class, 'buscar'], [AuthMiddleware::class]);

$router->get('/hallazgos/crear', [HallazgoController::class, 'crear'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);
$router->post('/hallazgos', [HallazgoController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.ver',
]);
$router->post('/hallazgos/{id}/descartar', [HallazgoController::class, 'descartar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':bienes.crear',
]);

$router->get('/manual', [ManualController::class, 'index'], [AuthMiddleware::class]);

$router->get('/reportes', [ReporteController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':reportes.generar',
]);
$router->get('/reportes/cartera.xlsx', [ReporteController::class, 'carteraXlsx'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':reportes.generar',
]);
$router->get('/reportes/cartera.csv', [ReporteController::class, 'carteraCsv'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':reportes.generar',
]);
$router->get('/reportes/reintegros.xlsx', [ReporteController::class, 'reintegrosXlsx'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':reportes.generar',
]);
$router->get('/reportes/reintegros-historial.xlsx', [ReporteController::class, 'reintegrosHistorialXlsx'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':reportes.generar',
]);

$router->get('/cartera/enviar', [CarteraController::class, 'formulario'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cartera.gestionar',
]);
$router->post('/cartera/enviar', [CarteraController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cartera.gestionar',
]);
$router->get('/cartera/enviados', [CarteraController::class, 'historial'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cartera.gestionar',
]);
$router->get('/cartera/{id}/editar', [CarteraController::class, 'formularioEditar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cartera.gestionar',
]);
$router->post('/cartera/{id}/actualizar', [CarteraController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cartera.gestionar',
]);
$router->post('/cartera/{id}/eliminar', [CarteraController::class, 'eliminar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cartera.gestionar',
]);

$router->get('/formatos-reintegro', [FormatoReintegroController::class, 'formulario'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_reintegro.gestionar',
]);
$router->post('/formatos-reintegro', [FormatoReintegroController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_reintegro.gestionar',
]);
$router->get('/formatos-reintegro/historial', [FormatoReintegroController::class, 'historial'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_reintegro.gestionar',
]);
$router->get('/formatos-reintegro/{id}/editar', [FormatoReintegroController::class, 'formularioEditar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_reintegro.gestionar',
]);
$router->post('/formatos-reintegro/{id}/actualizar', [FormatoReintegroController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_reintegro.gestionar',
]);
$router->post('/formatos-reintegro/{id}/eliminar', [FormatoReintegroController::class, 'eliminar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_reintegro.gestionar',
]);

$router->get('/formatos-plaqueteo', [FormatoPlaqueteoController::class, 'formulario'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_plaqueteo.gestionar',
]);
$router->post('/formatos-plaqueteo', [FormatoPlaqueteoController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_plaqueteo.gestionar',
]);
$router->get('/formatos-plaqueteo/historial', [FormatoPlaqueteoController::class, 'historial'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_plaqueteo.gestionar',
]);
$router->get('/formatos-plaqueteo/{id}/editar', [FormatoPlaqueteoController::class, 'formularioEditar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_plaqueteo.gestionar',
]);
$router->post('/formatos-plaqueteo/{id}/actualizar', [FormatoPlaqueteoController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_plaqueteo.gestionar',
]);
$router->post('/formatos-plaqueteo/{id}/eliminar', [FormatoPlaqueteoController::class, 'eliminar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':formatos_plaqueteo.gestionar',
]);

$router->get('/facturas', [FacturaAdministrativaController::class, 'formulario'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':facturas_admin.gestionar',
]);
$router->post('/facturas', [FacturaAdministrativaController::class, 'guardar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':facturas_admin.gestionar',
]);
$router->get('/facturas/historial', [FacturaAdministrativaController::class, 'historial'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':facturas_admin.gestionar',
]);
$router->get('/facturas/{id}/editar', [FacturaAdministrativaController::class, 'formularioEditar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':facturas_admin.gestionar',
]);
$router->post('/facturas/{id}/actualizar', [FacturaAdministrativaController::class, 'actualizar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':facturas_admin.gestionar',
]);
$router->post('/facturas/{id}/eliminar', [FacturaAdministrativaController::class, 'eliminar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':facturas_admin.gestionar',
]);

$router->get('/cargas-masivas', [CargaMasivaController::class, 'index'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);
$router->post('/cargas-masivas', [CargaMasivaController::class, 'subir'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);
$router->get('/cargas-masivas/plantilla.xlsx', [CargaMasivaController::class, 'plantilla'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);
$router->get('/cargas-masivas/{id}', [CargaMasivaController::class, 'mostrar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);
$router->post('/cargas-masivas/{id}/confirmar', [CargaMasivaController::class, 'confirmar'], [
    AuthMiddleware::class, InstitucionScopeMiddleware::class, PermissionMiddleware::class . ':cargas.masivas',
]);

$router->dispatch(new Request());
