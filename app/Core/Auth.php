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
        Session::put('usuario_id', (int) $usuario['id']);
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

    public static function check(): bool
    {
        return Session::has('usuario_id');
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
