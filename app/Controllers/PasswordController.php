<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\LimiteIntentos;
use App\Helpers\PoliticaContrasena;
use App\Models\Auditoria;
use App\Models\Institucion;
use App\Models\PasswordReset;
use App\Models\Usuario;
use App\Services\MailService;
use App\Services\PlantillaCorreo;

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

        // Límite (en el servidor): 5 solicitudes por conexión y 3 por correo cada 15
        // minutos. Sin él, se podía llenar de correos la bandeja de cualquier usuario.
        if (LimiteIntentos::desdeEstaIp('olvide_contrasena', 15) >= 5
            || ($email !== '' && LimiteIntentos::sobreClave('olvide_contrasena', $email, 15) >= 3)
        ) {
            Session::flash('error', 'Demasiadas solicitudes. Espera 15 minutos antes de volver a intentarlo.');
            header('Location: ' . Url::to('/olvide-contrasena'));
            exit;
        }
        LimiteIntentos::registrar('olvide_contrasena', $email);

        // Siempre se responde igual, exista o no el correo, para no revelar qué
        // correos tienen cuenta en el sistema.
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $usuario = Usuario::findByEmail($email);

            if ($usuario && (int) $usuario['activo'] === 1) {
                try {
                    $token = PasswordReset::crear((int) $usuario['id']);
                    $enlace = Url::absoluta('/restablecer-contrasena/' . $token);

                    $nombre = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
                    $correo = $this->correoReset($nombre, $enlace);
                    MailService::enviar($usuario['email'], $nombre, 'Restablecer tu contraseña · MIA', $correo['html'], $correo['texto']);
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

        $errorContrasena = PoliticaContrasena::validar(
            $password,
            [$reset['email'], $reset['nombres'], $reset['apellidos']]
        );
        if ($errorContrasena !== null) {
            Session::flash('error', $errorContrasena);
            header('Location: ' . Url::to("/restablecer-contrasena/{$token}"));
            exit;
        }

        if ($password !== $confirmacion) {
            Session::flash('error', 'Las contraseñas no coinciden.');
            header('Location: ' . Url::to("/restablecer-contrasena/{$token}"));
            exit;
        }

        $usuarioId = (int) $reset['usuario_id'];
        Usuario::updatePassword($usuarioId, password_hash($password, PASSWORD_BCRYPT));
        PasswordReset::marcarUsado((int) $reset['id']);
        // Quien restablece por correo demostró ser el dueño de la cuenta: se quita el
        // bloqueo por intentos fallidos y se cierran las demás sesiones abiertas (por si
        // la contraseña se cambió justamente porque alguien más la conocía).
        Usuario::desbloquear($usuarioId);
        Usuario::invalidarSesiones($usuarioId);
        Auditoria::registrar($usuarioId, null, 'restablecer_contrasena', 'usuario', $usuarioId);

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

        // Límite en el servidor por conexión (antes vivía en la sesión y bastaba con borrar
        // la cookie para saltarlo): 10 búsquedas fallidas cada 15 minutos.
        if (LimiteIntentos::desdeEstaIp('olvide_correo', 15) >= 10) {
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
            LimiteIntentos::registrar('olvide_correo', $institucionId . ':' . $documento);
            Session::flash('error', 'No encontramos una cuenta activa con ese documento en esa institución.');
            header('Location: ' . Url::to('/olvide-correo'));
            exit;
        }

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

        // La parte antes del "@" deja ver 2 letras al inicio y 1 al final (en vez de
        // solo 1 al inicio) — con textos tan cortos como "sofia" ocultar casi todo
        // dejaba muy pocas pistas para que la persona reconociera SU correo entre
        // varios parecidos. Con 3 letras o menos no hay margen para tapar nada sin
        // mostrarlo completo, así que se deja tal cual.
        $ocultarUsuario = static function (string $texto): string {
            $longitud = mb_strlen($texto);
            if ($longitud <= 3) {
                return $texto;
            }

            return mb_substr($texto, 0, 2) . str_repeat('*', $longitud - 3) . mb_substr($texto, -1);
        };

        $partesDominio = explode('.', $dominio);
        $primeraParteDominio = array_shift($partesDominio);

        $dominioMascarado = $ocultar($primeraParteDominio) . (count($partesDominio) ? '.' . implode('.', $partesDominio) : '');

        return $ocultarUsuario($usuarioCorreo) . '@' . $dominioMascarado;
    }

    /** @return array{html: string, texto: string} */
    private function correoReset(string $nombre, string $enlace): array
    {
        return PlantillaCorreo::armar(
            'Restablece tu contraseña',
            $nombre,
            [
                'Recibimos una solicitud para restablecer la contraseña de tu cuenta en MIA.',
                'Para crear una contraseña nueva, haz clic en el botón. El enlace vence en 60 minutos y solo se puede usar una vez.',
            ],
            ['texto' => 'Restablecer mi contraseña', 'url' => $enlace],
            '¿No fuiste tú? Ignora este correo: tu contraseña actual sigue funcionando y nadie puede cambiarla sin este enlace.'
        );
    }
}
