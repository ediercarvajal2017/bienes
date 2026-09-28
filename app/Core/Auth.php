<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\LimiteIntentos;
use App\Helpers\PoliticaDatos;
use App\Models\Auditoria;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Services\DosFactoresService;

final class Auth
{
    private static ?array $permisosCache = null;

    /** Máximo de intentos fallidos desde una misma IP (cualquier cuenta) en la ventana. */
    public const MAX_FALLOS_POR_IP = 20;
    /** Máximo de intentos fallidos sobre una cuenta desde una misma IP en la ventana. */
    public const MAX_FALLOS_CUENTA_IP = 5;
    /** Máximo de intentos fallidos sobre una cuenta desde cualquier IP (ataque distribuido). */
    public const MAX_FALLOS_CUENTA_TOTAL = 50;
    public const VENTANA_MINUTOS = 15;

    /**
     * Hash bcrypt de una cadena aleatoria: si el correo no existe, se verifica la contraseña
     * contra este hash igual, para que el tiempo de respuesta no revele qué correos tienen
     * cuenta en el sistema.
     */
    private const HASH_FICTICIO = '$2y$10$quaqN3SjV2U6mqra4tY.FORwKsEVi5E2JIZFS13b41EHT47FYX9E2';

    /** Resultados de attempt(). */
    public const INGRESO_OK = 'ok';
    /** Contraseña correcta, falta el código de la verificación en dos pasos (/2fa/verificar). */
    public const INGRESO_REQUIERE_2FA = 'requiere_2fa';
    public const INGRESO_FALLIDO = 'fallido';

    /** Por qué falló el último attempt(): 'credenciales' o 'bloqueado'. */
    private static string $motivoFallo = 'credenciales';

    public static function motivoFallo(): string
    {
        return self::$motivoFallo;
    }

    /**
     * Límites (ver constantes), guardados en el servidor (App\Helpers\LimiteIntentos):
     *  - por IP: frena a quien prueba muchas cuentas o contraseñas desde una conexión (el
     *    límite es holgado porque en un colegio todos salen por la misma IP);
     *  - por cuenta + IP: bloquea esa cuenta SOLO desde la conexión que falla. Antes el
     *    bloqueo era de la cuenta entera: cualquiera podía dejar sin acceso a otra persona
     *    (p. ej. al superusuario) fallando 5 veces con su correo;
     *  - por cuenta desde cualquier IP: tope alto contra ataques distribuidos.
     *
     * Si la cuenta tiene la verificación en dos pasos activa (y este navegador no es un
     * dispositivo de confianza), la contraseña correcta NO abre la sesión: solo deja un
     * estado '2fa_pendiente' que ningún middleware acepta (check() mira solo usuario_id).
     */
    public static function attempt(string $email, string $password, bool $recordar = false): string
    {
        self::$motivoFallo = 'credenciales';
        $v = self::VENTANA_MINUTOS;

        if (LimiteIntentos::desdeEstaIp('login', $v) >= self::MAX_FALLOS_POR_IP
            || LimiteIntentos::sobreClave('login', $email, $v, true) >= self::MAX_FALLOS_CUENTA_IP
            || LimiteIntentos::sobreClave('login', $email, $v) >= self::MAX_FALLOS_CUENTA_TOTAL
        ) {
            self::$motivoFallo = 'bloqueado';

            return self::INGRESO_FALLIDO;
        }

        $usuario = Usuario::findByEmail($email);
        $claveCorrecta = password_verify($password, $usuario['password_hash'] ?? self::HASH_FICTICIO);

        if (!$usuario || !$claveCorrecta) {
            LimiteIntentos::registrar('login', $email);

            return self::INGRESO_FALLIDO;
        }

        // Cuenta o institución desactivada: se responde igual que con una contraseña
        // incorrecta (no cuenta como intento fallido: la contraseña era la correcta).
        if (!self::puedeIngresar($usuario)) {
            return self::INGRESO_FALLIDO;
        }

        LimiteIntentos::limpiar('login', $email);

        if (DosFactoresService::tieneActiva($usuario)) {
            if (DosFactoresService::esDispositivoConfiable((int) $usuario['id'])) {
                self::iniciarSesionCompleta($usuario, 'dispositivo_confiable', $recordar);

                return self::INGRESO_OK;
            }

            Session::regenerate();
            Session::put('2fa_pendiente', [
                'usuario_id' => (int) $usuario['id'],
                'expira' => time() + DosFactoresService::SEGUNDOS_PARA_VERIFICAR,
                'intentos' => 0,
                'recordar' => $recordar,
            ]);

            return self::INGRESO_REQUIERE_2FA;
        }

        self::iniciarSesionCompleta($usuario, 'contrasena', $recordar);

        return self::INGRESO_OK;
    }

    /** Cuenta activa y, salvo el superusuario, con su institución activa. */
    public static function puedeIngresar(array $usuario): bool
    {
        return (int) $usuario['activo'] === 1
            && ($usuario['rol_nombre'] === 'superusuario' || (int) $usuario['institucion_activa'] === 1);
    }

    /** Abre la sesión (tras la contraseña, o tras la contraseña + el segundo paso). */
    public static function iniciarSesionCompleta(array $usuario, string $metodo, bool $recordar = false): void
    {
        Usuario::registrarLoginExitoso((int) $usuario['id']);

        Session::regenerate();
        unset($_SESSION['2fa_pendiente']);
        // Token CSRF nuevo: el anterior se generó antes de iniciar sesión (formulario de
        // login) y no debe seguir sirviendo para la sesión autenticada.
        Session::put('_csrf_token', bin2hex(random_bytes(32)));
        Session::put('usuario_id', (int) $usuario['id']);
        Session::put('sesion_version', (int) ($usuario['sesion_version'] ?? 0));
        Session::put('ultimo_acceso', time());
        Session::put('validado_en', time());
        Session::put('rol', $usuario['rol_nombre']);
        Session::put('institucion_id', (int) $usuario['institucion_id']);
        Session::put('institucion_nombre', $usuario['institucion_nombre']);
        Session::put('nombre_completo', trim($usuario['nombres'] . ' ' . $usuario['apellidos']));
        // Sin la política de datos aceptada, AuthMiddleware solo deja ver la pantalla de aceptación.
        Session::put('politica_pendiente', PoliticaDatos::pendiente($usuario));

        if ($recordar) {
            Session::extender(Session::DIAS_RECORDARME);
            // Con "Recordarme" no aplica el cierre por inactividad (ver check()); la
            // revalidación contra la base de datos sí sigue aplicando.
            Session::put('recordarme', true);
        }

        Auditoria::registrar((int) $usuario['id'], (int) $usuario['institucion_id'], 'login_ok', 'usuario',
            (int) $usuario['id'], null, ['metodo' => $metodo]);
    }

    /** Estado intermedio del inicio de sesión (contraseña correcta, falta el código), si sigue vigente. */
    public static function ingresoPendiente(): ?array
    {
        $pendiente = Session::get('2fa_pendiente');
        if (!is_array($pendiente)) {
            return null;
        }

        if ((int) $pendiente['expira'] < time()) {
            unset($_SESSION['2fa_pendiente']);

            return null;
        }

        return $pendiente;
    }

    /** Guarda el contador de códigos incorrectos del ingreso pendiente. */
    public static function actualizarIngresoPendiente(array $pendiente): void
    {
        Session::put('2fa_pendiente', $pendiente);
    }

    public static function descartarIngresoPendiente(): void
    {
        unset($_SESSION['2fa_pendiente']);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * ¿Hay una sesión válida? Además de mirar la sesión:
     *  - Inactividad: sin actividad por más de session_lifetime_minutes (config/app.php),
     *    la sesión se cierra — salvo que se haya marcado "Recordarme".
     *  - Revalidación (cada session_revalidacion_segundos, no en cada clic): la cuenta sigue activa, no está
     *    en la papelera, su institución sigue activa y su versión de sesión no cambió
     *    (Usuario::invalidarSesiones). Antes, desactivar o eliminar a alguien no lo sacaba
     *    del sistema si ya había iniciado sesión.
     */
    public static function check(): bool
    {
        if (!Session::has('usuario_id')) {
            return false;
        }

        $ahora = time();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $limiteInactividad = (int) $config['session_lifetime_minutes'] * 60;
        $ultimoAcceso = (int) Session::get('ultimo_acceso', $ahora);

        if (!Session::get('recordarme') && $ahora - $ultimoAcceso > $limiteInactividad) {
            self::cerrarPorSeguridad('Tu sesión se cerró por inactividad. Ingresa de nuevo.');

            return false;
        }

        if ($ahora - (int) Session::get('validado_en', 0) >= (int) $config['session_revalidacion_segundos']) {
            $estado = Usuario::estadoSesion((int) Session::get('usuario_id'));

            $valida = $estado !== null
                && (int) $estado['activo'] === 1
                && $estado['eliminado_en'] === null
                && ($estado['rol_nombre'] === 'superusuario' || (int) $estado['institucion_activa'] === 1)
                && (int) $estado['sesion_version'] === (int) Session::get('sesion_version', 0);

            if (!$valida) {
                self::cerrarPorSeguridad('Tu sesión se cerró porque tu cuenta cambió o fue desactivada. Ingresa de nuevo.');

                return false;
            }

            Session::put('validado_en', $ahora);
            // Las sesiones abiertas antes de publicar una versión nueva de la política también la piden.
            Session::put('politica_pendiente', PoliticaDatos::pendiente($estado));
        }

        Session::put('ultimo_acceso', $ahora);

        return true;
    }

    /**
     * Para el propio usuario que cambió algo de su cuenta (p. ej. su contraseña): sus
     * OTRAS sesiones se cierran, pero la actual sigue con la versión nueva.
     */
    public static function actualizarVersionSesion(int $version): void
    {
        Session::put('sesion_version', $version);
    }

    /**
     * Vacía la sesión y le da un id nuevo (en vez de destruirla y crear otra en la misma
     * petición, lo que dejaba el id en un estado ambiguo y perdía el mensaje).
     */
    private static function cerrarPorSeguridad(string $mensaje): void
    {
        self::$permisosCache = null;
        $_SESSION = [];
        Session::regenerate();
        Session::flash('error', $mensaje);
    }

    public static function id(): ?int
    {
        return Session::get('usuario_id');
    }

    public static function rol(): ?string
    {
        return Session::get('rol');
    }

    public static function institucionId(): ?int
    {
        return Session::get('institucion_id');
    }

    public static function institucionNombre(): ?string
    {
        return Session::get('institucion_nombre');
    }

    /**
     * Alias de institucionId() para los sitios donde importa dejar explícito que se
     * está leyendo la sede que el usuario tiene activa en este momento (el selector del
     * encabezado), no "su institución" en abstracto — son el mismo valor.
     */
    public static function sedeActivaId(): ?int
    {
        return self::institucionId();
    }

    /**
     * Usado por SedeActivaController cuando un rector cambia de sede: institucionId()
     * pasa a ser la sede elegida para el resto de la sesión (así TODAS las pantallas que
     * ya filtran por institucionId() — Bienes, Usuarios, Categorías, etc. — quedan
     * automáticamente ancladas a la nueva sede, sin tener que tocar cada controlador).
     * Se refresca institucion_nombre junto con el id para que el encabezado no muestre
     * el nombre de la sede vieja.
     */
    public static function cambiarSedeActiva(int $institucionId): void
    {
        $institucion = Institucion::find($institucionId);

        Session::put('institucion_id', $institucionId);
        Session::put('institucion_nombre', $institucion['nombre'] ?? Session::get('institucion_nombre'));
    }

    /**
     * Filtro opcional de institución para el superusuario, usado SOLO por pantallas de
     * listado/conteo (ver FiltroInstitucionController) — a diferencia de sedeActivaId(),
     * nunca decide en cuál institución se crea o edita algo; esos formularios le siguen
     * pidiendo elegirla de forma explícita, sin cambios. null = sin filtro, ver todo.
     */
    public static function filtroInstitucionId(): ?int
    {
        return Session::get('filtro_institucion_id');
    }

    public static function establecerFiltroInstitucion(?int $institucionId): void
    {
        Session::put('filtro_institucion_id', $institucionId);
    }

    public static function nombreCompleto(): ?string
    {
        return Session::get('nombre_completo');
    }

    public static function esSuperusuario(): bool
    {
        return self::rol() === 'superusuario';
    }

    public static function tienePermiso(string $codigo): bool
    {
        if (self::$permisosCache === null) {
            self::$permisosCache = self::id() !== null ? Usuario::permisosDe(self::id()) : [];
        }

        return in_array($codigo, self::$permisosCache, true);
    }
}
