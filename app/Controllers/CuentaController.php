<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\PoliticaContrasena;
use App\Models\Auditoria;
use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use App\Models\Usuario;
use App\Services\DosFactoresService;

/**
 * "Mi cuenta": seguridad de la propia cuenta (verificación en dos pasos, dispositivos de
 * confianza y cambio de contraseña). Disponible para todos los roles.
 */
final class CuentaController
{
    public function index(): void
    {
        $usuario = Usuario::findParaAcceso((int) Auth::id());
        if ($usuario === null) {
            header('Location: ' . Url::to('/login'));
            exit;
        }

        $id = (int) $usuario['id'];
        $activa = DosFactoresService::tieneActiva($usuario);

        View::layout('partials/layout', 'cuenta/index', [
            'title' => 'Mi cuenta',
            'usuario' => $usuario,
            'dosFactoresDisponible' => DosFactoresService::disponible(),
            'dosFactoresActiva' => $activa,
            'codigosDisponibles' => $activa ? CodigoRecuperacion::disponibles($id) : 0,
            'dispositivos' => $activa ? DispositivoConfiable::listarDe($id) : [],
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function cambiarContrasena(): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/mi-cuenta');

        $usuario = Usuario::findParaAcceso((int) Auth::id());
        if ($usuario === null) {
            header('Location: ' . Url::to('/login'));
            exit;
        }

        $actual = (string) $request->input('password_actual');
        $nueva = (string) $request->input('password_nueva');
        $confirmacion = (string) $request->input('password_confirmacion');

        $error = match (true) {
            !password_verify($actual, (string) $usuario['password_hash']) => 'La contraseña actual no es correcta.',
            $nueva !== $confirmacion => 'La confirmación no coincide con la contraseña nueva.',
            password_verify($nueva, (string) $usuario['password_hash']) => 'La contraseña nueva debe ser distinta de la actual.',
            default => PoliticaContrasena::validar($nueva, [
                $usuario['nombres'] ?? '', $usuario['apellidos'] ?? '', $usuario['documento'] ?? '', $usuario['email'] ?? '',
            ]),
        };

        if ($error !== null) {
            Session::flash('error', $error);
            header('Location: ' . Url::to('/mi-cuenta') . '#contrasena');
            exit;
        }

        $id = (int) $usuario['id'];
        Usuario::updatePassword($id, password_hash($nueva, PASSWORD_BCRYPT));
        // Las demás sesiones abiertas con la contraseña anterior se cierran; esta sigue.
        Auth::actualizarVersionSesion(Usuario::invalidarSesiones($id));
        Auditoria::registrar($id, (int) $usuario['institucion_id'], 'cambiar_contrasena', 'usuario', $id);
        DosFactoresService::avisarPorCorreo($usuario, 'contraseña cambiada', 'Se cambió la contraseña de tu cuenta de MIA.');

        Session::flash('ok', 'Contraseña cambiada. Las demás sesiones abiertas con tu cuenta se cerraron.');
        header('Location: ' . Url::to('/mi-cuenta'));
        exit;
    }
}
