<?php

declare(strict_types=1);

namespace App\Core;

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
        Session::put('sede_activa_id', (int) $usuario['institucion_id']);
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
     * La sede sobre la que se debe filtrar/crear en este momento. Para la mayoría de
     * usuarios es igual a institucionId(); solo cambia para un rector que usó el
     * selector "cambiar de sede" para operar temporalmente en otra sede de su familia
     * (ver SedeActivaController).
     */
    public static function sedeActivaId(): ?int
    {
        return Session::get('sede_activa_id') ?? self::institucionId();
    }

    public static function cambiarSedeActiva(int $institucionId): void
    {
        Session::put('sede_activa_id', $institucionId);
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
