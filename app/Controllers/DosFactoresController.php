<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\LimiteIntentos;
use App\Helpers\LlaveAplicacion;
use App\Helpers\Totp;
use App\Models\Auditoria;
use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use App\Models\Usuario;
use App\Services\DosFactoresService;

/**
 * Verificación en dos pasos (OPCIONAL: cada usuario decide si la activa): el segundo paso
 * del inicio de sesión, la configuración con la aplicación autenticadora, los códigos de
 * recuperación y los dispositivos de confianza.
 */
final class DosFactoresController
{
    // ───────────────────────── Segundo paso del inicio de sesión ─────────────────────────

    public function verificar(): void
    {
        $pendiente = Auth::ingresoPendiente();
        if ($pendiente === null) {
            $this->volverAlLogin('El tiempo para escribir el código venció. Ingresa de nuevo.');
        }

        $usuario = Usuario::findParaAcceso((int) $pendiente['usuario_id']);

        View::render('auth/2fa_verificar', [
            'error' => Session::pullFlash('error'),
            // Si APP_KEY cambió, la clave del autenticador ya no se puede leer: solo sirven
            // los códigos de recuperación (o que un administrador la restablezca).
            'appDisponible' => $usuario !== null && DosFactoresService::secretoDe($usuario) !== null,
            'intentosRestantes' => DosFactoresService::MAX_INTENTOS_POR_INGRESO - (int) $pendiente['intentos'],
        ]);
    }

    public function validar(): void
    {
        $request = new Request();
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            $this->volverAlLogin('Tu sesión expiró, ingresa de nuevo.');
        }

        $pendiente = Auth::ingresoPendiente();
        $usuario = $pendiente !== null ? Usuario::findParaAcceso((int) $pendiente['usuario_id']) : null;
        if ($pendiente === null || $usuario === null || !Auth::puedeIngresar($usuario)) {
            Auth::descartarIngresoPendiente();
            $this->volverAlLogin('El tiempo para escribir el código venció. Ingresa de nuevo.');
        }

        $email = (string) $usuario['email'];
        $ventana = DosFactoresService::VENTANA_MINUTOS;
        if (LimiteIntentos::sobreClave('2fa', $email, $ventana) >= DosFactoresService::MAX_FALLOS_CUENTA) {
            Auth::descartarIngresoPendiente();
            $this->volverAlLogin("Demasiados códigos incorrectos. Espera {$ventana} minutos antes de volver a intentarlo.");
        }

        $codigo = trim((string) $request->input('codigo'));
        $metodo = null;
        if (CodigoRecuperacion::pareceCodigo($codigo)) {
            if (CodigoRecuperacion::usar((int) $usuario['id'], $codigo)) {
                $metodo = 'codigo_recuperacion';
            }
        } elseif (DosFactoresService::validarCodigoApp($usuario, $codigo)) {
            $metodo = '2fa_totp';
        }

        if ($metodo === null) {
            LimiteIntentos::registrar('2fa', $email);
            Auditoria::registrar((int) $usuario['id'], (int) $usuario['institucion_id'], 'login_2fa_fallido',
                'usuario', (int) $usuario['id']);

            $pendiente['intentos'] = (int) $pendiente['intentos'] + 1;
            if ($pendiente['intentos'] >= DosFactoresService::MAX_INTENTOS_POR_INGRESO) {
                Auth::descartarIngresoPendiente();
                $this->volverAlLogin('Demasiados códigos incorrectos. Ingresa de nuevo con tu contraseña.');
            }

            Auth::actualizarIngresoPendiente($pendiente);
            Session::flash('error', 'Código incorrecto o vencido. Verifica que la hora de tu teléfono esté en automático.');
            header('Location: ' . Url::to('/2fa/verificar'));
            exit;
        }

        LimiteIntentos::limpiar('2fa', $email);
        if ($request->input('confiar')) {
            DosFactoresService::recordarDispositivo((int) $usuario['id']);
        }

        Auth::iniciarSesionCompleta($usuario, $metodo, (bool) $pendiente['recordar']);

        if ($metodo === 'codigo_recuperacion') {
            $quedan = CodigoRecuperacion::disponibles((int) $usuario['id']);
            Auditoria::registrar((int) $usuario['id'], (int) $usuario['institucion_id'], 'codigo_recuperacion_usado',
                'usuario', (int) $usuario['id'], null, ['codigos_restantes' => $quedan]);
            DosFactoresService::avisarPorCorreo($usuario, 'se usó un código de recuperación',
                "Se ingresó a su cuenta con un código de recuperación. Le quedan {$quedan}.");
            Session::flash('ok', $quedan <= 3
                ? "Ingresaste con un código de recuperación. Te quedan {$quedan}: genera códigos nuevos en «Mi cuenta»."
                : "Ingresaste con un código de recuperación. Te quedan {$quedan}.");
        }

        header('Location: ' . Url::to('/dashboard'));
        exit;
    }

    // ───────────────────────── Configuración (usuario con sesión) ─────────────────────────

    public function configurar(): void
    {
        $usuario = $this->usuarioActual();

        if (DosFactoresService::tieneActiva($usuario)) {
            header('Location: ' . Url::to('/mi-cuenta'));
            exit;
        }

        if (!LlaveAplicacion::disponible()) {
            View::layout('partials/layout', 'dosfa/no_disponible', ['title' => 'Verificación en dos pasos']);

            return;
        }

        // La clave nueva vive en la sesión hasta que el usuario confirma un código: así,
        // recargar la página no cambia el QR que ya escaneó.
        $secreto = Session::get('2fa_secreto_nuevo');
        if (!is_string($secreto) || $secreto === '') {
            $secreto = Totp::generarSecreto();
            Session::put('2fa_secreto_nuevo', $secreto);
        }

        View::layout('partials/layout', 'dosfa/configurar', [
            'title' => 'Verificación en dos pasos',
            'qr' => DosFactoresService::qrDataUri($secreto, (string) $usuario['email']),
            'claveManual' => Totp::formatearParaMostrar($secreto),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function activar(): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/2fa/configurar');

        $usuario = $this->usuarioActual();
        $secreto = Session::get('2fa_secreto_nuevo');

        if (DosFactoresService::tieneActiva($usuario) || !is_string($secreto) || !LlaveAplicacion::disponible()) {
            header('Location: ' . Url::to('/2fa/configurar'));
            exit;
        }

        $paso = Totp::pasoValido($secreto, (string) $request->input('codigo'));
        if ($paso === null) {
            Session::flash('error', 'El código no coincide. Escribe el que muestra ahora la aplicación (cambia cada 30 segundos) y verifica que la hora del teléfono esté en automático.');
            header('Location: ' . Url::to('/2fa/configurar'));
            exit;
        }

        $id = (int) $usuario['id'];
        Usuario::activarTotp($id, LlaveAplicacion::cifrar($secreto), $paso);
        $codigos = CodigoRecuperacion::regenerar($id);
        // Las demás sesiones abiertas de la cuenta se cierran; esta sigue.
        Auth::actualizarVersionSesion(Usuario::invalidarSesiones($id));
        unset($_SESSION['2fa_secreto_nuevo']);

        Auditoria::registrar($id, (int) $usuario['institucion_id'], '2fa_activar', 'usuario', $id);
        DosFactoresService::avisarPorCorreo($usuario, 'verificación en dos pasos activada',
            'Se activó la verificación en dos pasos en su cuenta de MIA.');

        Session::put('2fa_codigos_nuevos', $codigos);
        header('Location: ' . Url::to('/2fa/codigos'));
        exit;
    }

    /** Muestra los códigos de recuperación recién generados. Solo una vez. */
    public function codigos(): void
    {
        $codigos = Session::get('2fa_codigos_nuevos');
        unset($_SESSION['2fa_codigos_nuevos']);

        if (!is_array($codigos) || $codigos === []) {
            header('Location: ' . Url::to('/mi-cuenta'));
            exit;
        }

        header('Cache-Control: no-store');
        View::layout('partials/layout', 'dosfa/codigos', [
            'title' => 'Códigos de recuperación',
            'codigos' => $codigos,
            'correo' => (string) $this->usuarioActual()['email'],
        ]);
    }

    public function regenerarCodigos(): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/mi-cuenta');
        $usuario = $this->usuarioActual();

        if (!DosFactoresService::tieneActiva($usuario)) {
            header('Location: ' . Url::to('/mi-cuenta'));
            exit;
        }
        $this->exigirContrasena($usuario, (string) $request->input('password_actual'));

        $id = (int) $usuario['id'];
        Session::put('2fa_codigos_nuevos', CodigoRecuperacion::regenerar($id));
        Auditoria::registrar($id, (int) $usuario['institucion_id'], '2fa_regenerar_codigos', 'usuario', $id);

        header('Location: ' . Url::to('/2fa/codigos'));
        exit;
    }

    public function desactivar(): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/mi-cuenta');
        $usuario = $this->usuarioActual();
        $id = (int) $usuario['id'];

        if (!DosFactoresService::tieneActiva($usuario)) {
            header('Location: ' . Url::to('/mi-cuenta'));
            exit;
        }

        $this->exigirContrasena($usuario, (string) $request->input('password_actual'));

        $codigo = (string) $request->input('codigo');
        $codigoValido = CodigoRecuperacion::pareceCodigo($codigo)
            ? CodigoRecuperacion::usar($id, $codigo)
            : DosFactoresService::validarCodigoApp($usuario, $codigo);
        if (!$codigoValido) {
            $this->volverAMiCuenta('error', 'El código de verificación no es válido.');
        }

        Usuario::desactivarTotp($id);
        CodigoRecuperacion::borrarDe($id);
        DispositivoConfiable::revocarTodosDe($id);
        Auth::actualizarVersionSesion(Usuario::invalidarSesiones($id));

        Auditoria::registrar($id, (int) $usuario['institucion_id'], '2fa_desactivar', 'usuario', $id);
        DosFactoresService::avisarPorCorreo($usuario, 'verificación en dos pasos desactivada',
            'Se desactivó la verificación en dos pasos en su cuenta de MIA.');

        $this->volverAMiCuenta('ok', 'Verificación en dos pasos desactivada.');
    }

    public function revocarDispositivo(string $id): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/mi-cuenta');
        $usuario = $this->usuarioActual();

        if (DispositivoConfiable::revocar((int) $id, (int) $usuario['id'])) {
            Auditoria::registrar((int) $usuario['id'], (int) $usuario['institucion_id'], 'dispositivo_revocado',
                'usuario', (int) $usuario['id'], null, ['dispositivo_id' => (int) $id]);
            $this->volverAMiCuenta('ok', 'Dispositivo quitado: la próxima vez te pedirá el código.');
        }

        $this->volverAMiCuenta('error', 'Ese dispositivo ya no estaba en la lista.');
    }

    // ───────────────────────── Apoyo ─────────────────────────

    private function usuarioActual(): array
    {
        $usuario = Usuario::findParaAcceso((int) Auth::id());
        if ($usuario === null) {
            $this->volverAlLogin('Ingresa de nuevo.');
        }

        return $usuario;
    }

    private function exigirContrasena(array $usuario, string $password): void
    {
        if (!password_verify($password, (string) $usuario['password_hash'])) {
            $this->volverAMiCuenta('error', 'La contraseña actual no es correcta.');
        }
    }

    private function volverAMiCuenta(string $tipo, string $mensaje): never
    {
        Session::flash($tipo, $mensaje);
        header('Location: ' . Url::to('/mi-cuenta'));
        exit;
    }

    private function volverAlLogin(string $mensaje): never
    {
        Session::flash('error', $mensaje);
        header('Location: ' . Url::to('/login'));
        exit;
    }
}
