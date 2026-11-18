<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
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

        $titulo = 'No se cambió la contraseña';
        $politica = PoliticaContrasena::validar($nueva, [
            $usuario['nombres'] ?? '', $usuario['apellidos'] ?? '', $usuario['documento'] ?? '', $usuario['email'] ?? '',
        ]);
        $error = match (true) {
            !password_verify($actual, (string) $usuario['password_hash']) => Aviso::error($titulo, 'La contraseña actual no es correcta.',
                'Escribe la contraseña con la que entraste hoy (respeta mayúsculas) y vuelve a intentarlo.', null, ['password_actual' => 'No es la contraseña actual.']),
            $nueva !== $confirmacion => Aviso::error($titulo, 'La confirmación no coincide con la contraseña nueva.',
                'Escribe la misma contraseña nueva en los dos campos.', null, ['password_confirmacion' => 'No coincide.']),
            password_verify($nueva, (string) $usuario['password_hash']) => Aviso::error($titulo, 'La contraseña nueva es igual a la actual.',
                'Escribe una contraseña distinta de la que usas ahora.', null, ['password_nueva' => 'Debe ser distinta de la actual.']),
            $politica !== null => Aviso::error($titulo, $politica, 'Escribe una contraseña nueva que cumpla esa condición.', null, ['password_nueva' => 'Revisa la contraseña nueva.']),
            default => null,
        };

        if ($error !== null) {
            Aviso::enviar($error);
            header('Location: ' . Url::to('/mi-cuenta') . '#contrasena');
            exit;
        }

        $id = (int) $usuario['id'];
        Usuario::updatePassword($id, password_hash($nueva, PASSWORD_BCRYPT));
        // Las demás sesiones abiertas con la contraseña anterior se cierran; esta sigue.
        Auth::actualizarVersionSesion(Usuario::invalidarSesiones($id));
        Auditoria::registrar($id, (int) $usuario['institucion_id'], 'cambiar_contrasena', 'usuario', $id);
        DosFactoresService::avisarPorCorreo($usuario, 'contraseña cambiada', 'Se cambió la contraseña de tu cuenta de MIA.');

        Aviso::enviar(Aviso::exito('Contraseña cambiada.', 'Las demás sesiones abiertas con tu cuenta se cerraron; esta sigue abierta.'));
        header('Location: ' . Url::to('/mi-cuenta'));
        exit;
    }
}
