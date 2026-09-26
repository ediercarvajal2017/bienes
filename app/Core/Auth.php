<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Institucion;
use App\Models\Usuario;

final class Auth
{
    private static ?array $permisosCache = null;

    public static function attempt(string $email, string $password): bool
    {
        $usuario = Usuario::findByEmail($email);

        if (!$usuario || !(int) $usuario['activo']) {
            return false;
        }

        if ($usuario['rol_nombre'] !== 'superusuario' && !(int) $usuario['institucion_activa']) {
            return false;
        }

        if (!empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()) {
            return false;
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            Usuario::registrarIntentoFallido((int) $usuario['id']);

            return false;
        }

        Usuario::registrarLoginExitoso((int) $usuario['id']);

        Session::regenerate();
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

        return true;
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
