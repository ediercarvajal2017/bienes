<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
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
            $this->volverAlLogin(Aviso::error('No se completó el ingreso', 'Pasó demasiado tiempo sin escribir el código de verificación.',
                'Ingresa de nuevo con tu correo y contraseña y escribe el código enseguida.'));
        }

        $usuario = Usuario::findParaAcceso((int) $pendiente['usuario_id']);

        View::render('auth/2fa_verificar', [
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
            $this->volverAlLogin(Aviso::error('No se completó el ingreso', 'La página estuvo abierta mucho tiempo y venció por seguridad.',
                'Ingresa de nuevo con tu correo y contraseña.'));
        }

        $pendiente = Auth::ingresoPendiente();
        $usuario = $pendiente !== null ? Usuario::findParaAcceso((int) $pendiente['usuario_id']) : null;
        if ($pendiente === null || $usuario === null || !Auth::puedeIngresar($usuario)) {
            Auth::descartarIngresoPendiente();
            $this->volverAlLogin(Aviso::error('No se completó el ingreso', 'Pasó demasiado tiempo sin escribir el código de verificación.',
                'Ingresa de nuevo con tu correo y contraseña y escribe el código enseguida.'));
        }

        $email = (string) $usuario['email'];
        $ventana = DosFactoresService::VENTANA_MINUTOS;
        if (LimiteIntentos::sobreClave('2fa', $email, $ventana) >= DosFactoresService::MAX_FALLOS_CUENTA) {
            Auth::descartarIngresoPendiente();
            $this->volverAlLogin(Aviso::error('No se completó el ingreso', 'Se escribieron demasiados códigos incorrectos.',
                "Espera {$ventana} minutos y vuelve a ingresar. Revisa que la hora de tu teléfono esté en automático."));
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
                $this->volverAlLogin(Aviso::error('No se completó el ingreso', 'Se escribieron demasiados códigos incorrectos.',
                    'Ingresa de nuevo con tu contraseña y escribe el código que muestra ahora la aplicación.'));
            }

            Auth::actualizarIngresoPendiente($pendiente);
            Aviso::enviar(Aviso::error('No se pudo ingresar', 'Código incorrecto o vencido: cambia cada 30 segundos.',
                'Escribe el código que muestra ahora la aplicación y verifica que la hora de tu teléfono esté en automático.'));
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
                "Se ingresó a tu cuenta con un código de recuperación. Te quedan {$quedan}.");
            Aviso::enviar($quedan <= 3
                ? Aviso::advertencia('Ingresaste con un código de recuperación.', "Te quedan {$quedan} códigos de recuperación.",
                    'Genera códigos nuevos en «Mi cuenta» antes de que se acaben.', Aviso::accion('Ir a Mi cuenta', '/mi-cuenta'))
                : Aviso::exito('Ingresaste con un código de recuperación.', "Te quedan {$quedan}. Cada código sirve una sola vez."));
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
            Aviso::enviar(Aviso::error('No se activó la verificación en dos pasos', 'El código no coincide con el de la aplicación.',
                'Escribe el que muestra ahora la aplicación (cambia cada 30 segundos) y verifica que la hora del teléfono esté en automático.'));
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
            'Se activó la verificación en dos pasos en tu cuenta de MIA.');

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
            $this->volverAMiCuenta(Aviso::error('No se desactivó la verificación en dos pasos', 'El código de verificación no es válido.',
                'Escribe el código que muestra ahora tu aplicación, o uno de tus códigos de recuperación.'));
        }

        Usuario::desactivarTotp($id);
        CodigoRecuperacion::borrarDe($id);
        DispositivoConfiable::revocarTodosDe($id);
        Auth::actualizarVersionSesion(Usuario::invalidarSesiones($id));

        Auditoria::registrar($id, (int) $usuario['institucion_id'], '2fa_desactivar', 'usuario', $id);
        DosFactoresService::avisarPorCorreo($usuario, 'verificación en dos pasos desactivada',
            'Se desactivó la verificación en dos pasos en tu cuenta de MIA.');

        $this->volverAMiCuenta(Aviso::exito('Verificación en dos pasos desactivada.', 'Desde ahora entras solo con tu contraseña. Puedes activarla de nuevo cuando quieras.'));
    }

    public function revocarDispositivo(string $id): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/mi-cuenta');
        $usuario = $this->usuarioActual();

        if (DispositivoConfiable::revocar((int) $id, (int) $usuario['id'])) {
            Auditoria::registrar((int) $usuario['id'], (int) $usuario['institucion_id'], 'dispositivo_revocado',
                'usuario', (int) $usuario['id'], null, ['dispositivo_id' => (int) $id]);
            $this->volverAMiCuenta(Aviso::exito('Dispositivo quitado.', 'La próxima vez que entres desde él, MIA te pedirá el código.'));
        }

        $this->volverAMiCuenta(Aviso::error('No se quitó el dispositivo', 'Ese dispositivo ya no estaba en la lista.', 'Recarga la página para ver la lista actual.'));
    }

    // ───────────────────────── Apoyo ─────────────────────────

    private function usuarioActual(): array
    {
        $usuario = Usuario::findParaAcceso((int) Auth::id());
        if ($usuario === null) {
            $this->volverAlLogin(Aviso::error('Tu sesión se cerró', 'No se encontró tu cuenta activa.', 'Ingresa de nuevo con tu correo y contraseña.'));
        }

        return $usuario;
    }

    private function exigirContrasena(array $usuario, string $password): void
    {
        if (!password_verify($password, (string) $usuario['password_hash'])) {
            $this->volverAMiCuenta(Aviso::error('No se hizo el cambio', 'La contraseña actual no es correcta.',
                'Escribe la contraseña con la que entraste hoy (respeta mayúsculas) y vuelve a intentarlo.'));
        }
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volverAMiCuenta(array $aviso): never
    {
        Aviso::enviar($aviso);
        header('Location: ' . Url::to('/mi-cuenta'));
        exit;
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volverAlLogin(array $aviso): never
    {
        Aviso::enviar($aviso);
        header('Location: ' . Url::to('/login'));
        exit;
    }
}
