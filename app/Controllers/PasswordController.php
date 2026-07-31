<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Institucion;
use App\Models\PasswordReset;
use App\Models\Usuario;
use App\Services\MailService;

final class PasswordController
{
    private const MENSAJE_GENERICO_ENVIO = 'Si el correo está registrado en el sistema, te enviamos un enlace para restablecer la contraseña.';

    public function formularioOlvideContrasena(): void
    {
        View::render('auth/olvide_contrasena', [
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function enviarEnlaceReset(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/olvide-contrasena'));
            exit;
        }

        $email = trim((string) $request->input('email'));

        // Siempre se responde igual, exista o no el correo, para no revelar qué
        // correos tienen cuenta en el sistema.
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $usuario = Usuario::findByEmail($email);

            if ($usuario && (int) $usuario['activo'] === 1) {
                try {
                    $token = PasswordReset::crear((int) $usuario['id']);
                    $enlace = Url::absoluta('/restablecer-contrasena/' . $token);

                    MailService::enviar(
                        $usuario['email'],
                        trim($usuario['nombres'] . ' ' . $usuario['apellidos']),
                        'Restablecer tu contraseña · SIGEBI',
                        $this->plantillaCorreoReset(trim($usuario['nombres'] . ' ' . $usuario['apellidos']), $enlace)
                    );
                } catch (\RuntimeException $e) {
                    error_log('MailService (reset de contraseña): ' . $e->getMessage());
                }
            }
        }

        Session::flash('ok', self::MENSAJE_GENERICO_ENVIO);
        header('Location: ' . Url::to('/olvide-contrasena'));
        exit;
    }

    public function formularioReset(string $token): void
    {
        $reset = PasswordReset::validar($token);

        View::render('auth/restablecer_contrasena', [
            'token' => $token,
            'valido' => $reset !== null,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function restablecerPassword(string $token): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/restablecer-contrasena/{$token}"));
            exit;
        }

        $reset = PasswordReset::validar($token);
        if (!$reset) {
            Session::flash('error', 'Este enlace ya no es válido. Solicita uno nuevo.');
            header('Location: ' . Url::to('/olvide-contrasena'));
            exit;
        }

        $password = (string) $request->input('password');
        $confirmacion = (string) $request->input('password_confirmacion');

        if (strlen($password) < 8) {
            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
            header('Location: ' . Url::to("/restablecer-contrasena/{$token}"));
            exit;
        }

        if ($password !== $confirmacion) {
            Session::flash('error', 'Las contraseñas no coinciden.');
            header('Location: ' . Url::to("/restablecer-contrasena/{$token}"));
            exit;
        }

        Usuario::updatePassword((int) $reset['usuario_id'], password_hash($password, PASSWORD_BCRYPT));
        PasswordReset::marcarUsado((int) $reset['id']);

        Session::flash('ok', 'Contraseña actualizada. Ya puedes iniciar sesión.');
        header('Location: ' . Url::to('/login'));
        exit;
    }

    public function formularioOlvideCorreo(): void
    {
        View::render('auth/olvide_correo', [
            'instituciones' => Institucion::listadoParaSelect(true),
            'resultado' => Session::pullFlash('resultado_correo'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function buscarCorreo(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/olvide-correo'));
            exit;
        }

        $config = require dirname(__DIR__, 2) . '/config/app.php';

        if (Session::bloqueadoPorIntentos('olvide_correo')) {
            Session::flash('error', 'Demasiados intentos. Espera unos minutos antes de volver a intentarlo.');
            header('Location: ' . Url::to('/olvide-correo'));
            exit;
        }

        $documento = trim((string) $request->input('documento'));
        $institucionId = (int) $request->input('institucion_id');

        if ($documento === '' || $institucionId === 0) {
            Session::flash('error', 'Indica tu número de documento y selecciona tu institución.');
            header('Location: ' . Url::to('/olvide-correo'));
            exit;
        }

        $usuario = Usuario::findByDocumento($documento, $institucionId);

        if (!$usuario || (int) $usuario['activo'] !== 1) {
            Session::registrarIntentoFallido('olvide_correo', $config['login_max_attempts'], $config['login_lockout_minutes']);
            Session::flash('error', 'No encontramos una cuenta activa con ese documento en esa institución.');
            header('Location: ' . Url::to('/olvide-correo'));
            exit;
        }

        Session::resetearIntentos('olvide_correo');
        Session::flash('resultado_correo', $this->enmascararCorreo($usuario['email']));
        header('Location: ' . Url::to('/olvide-correo'));
        exit;
    }

    private function enmascararCorreo(string $correo): string
    {
        [$usuarioCorreo, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        $ocultar = static function (string $texto): string {
            $longitud = mb_strlen($texto);
            if ($longitud <= 1) {
                return $texto;
            }

            return mb_substr($texto, 0, 1) . str_repeat('*', $longitud - 1);
        };

        $partesDominio = explode('.', $dominio);
        $primeraParteDominio = array_shift($partesDominio);

        $dominioMascarado = $ocultar($primeraParteDominio) . (count($partesDominio) ? '.' . implode('.', $partesDominio) : '');

        return $ocultar($usuarioCorreo) . '@' . $dominioMascarado;
    }

    private function plantillaCorreoReset(string $nombre, string $enlace): string
    {
        $nombreEscapado = htmlspecialchars($nombre, ENT_QUOTES);
        $enlaceEscapado = htmlspecialchars($enlace, ENT_QUOTES);

        return <<<HTML
            <p>Hola {$nombreEscapado},</p>
            <p>Recibimos una solicitud para restablecer tu contraseña en SIGEBI. Si fuiste tú, haz clic en el siguiente enlace (válido por 60 minutos):</p>
            <p><a href="{$enlaceEscapado}">{$enlaceEscapado}</a></p>
            <p>Si no solicitaste esto, puedes ignorar este correo — tu contraseña actual sigue funcionando normalmente.</p>
            HTML;
    }
}
