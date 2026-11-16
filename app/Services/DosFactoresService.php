<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Helpers\LlaveAplicacion;
use App\Helpers\Totp;
use App\Models\DispositivoConfiable;
use App\Models\Usuario;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Verificación en dos pasos (2FA): reglas de negocio compartidas por el inicio de sesión
 * (Auth), la pantalla de verificación y "Mi cuenta".
 *
 * Es OPCIONAL: cada usuario decide si la activa desde "Mi cuenta"; nunca se exige.
 *  - sin APP_KEY en el .env, simplemente no está disponible;
 *  - si se pierde el teléfono: códigos de recuperación, o un administrador la restablece.
 */
final class DosFactoresService
{
    public const EMISOR = 'MIA';
    public const COOKIE_DISPOSITIVO = 'sigebi_dispositivo';
    /** Intentos de código por cada inicio de sesión (luego se vuelve al login). */
    public const MAX_INTENTOS_POR_INGRESO = 5;
    /** Intentos fallidos de código sobre una cuenta en la ventana, sumando todos los ingresos. */
    public const MAX_FALLOS_CUENTA = 10;
    public const VENTANA_MINUTOS = 15;
    /** Segundos que tiene el usuario para escribir el código después de la contraseña. */
    public const SEGUNDOS_PARA_VERIFICAR = 300;

    public static function disponible(): bool
    {
        return LlaveAplicacion::disponible();
    }

    public static function tieneActiva(array $usuario): bool
    {
        return !empty($usuario['totp_activado_en']) && !empty($usuario['totp_secreto']);
    }

    /**
     * Clave del autenticador en claro, o null si no se puede descifrar (APP_KEY ausente o
     * distinta de la que se usó al configurarla: en ese caso solo sirven los códigos de
     * recuperación).
     */
    public static function secretoDe(array $usuario): ?string
    {
        return !empty($usuario['totp_secreto']) ? LlaveAplicacion::descifrar((string) $usuario['totp_secreto']) : null;
    }

    /** Valida un código de la aplicación: vigente (±30 s) y no usado antes. */
    public static function validarCodigoApp(array $usuario, string $codigo): bool
    {
        $secreto = self::secretoDe($usuario);
        if ($secreto === null) {
            return false;
        }

        $paso = Totp::pasoValido($secreto, $codigo);

        return $paso !== null && Usuario::registrarPasoTotp((int) $usuario['id'], $paso);
    }

    /** ¿Este navegador tiene una cookie de dispositivo confiable vigente para el usuario? */
    public static function esDispositivoConfiable(int $usuarioId): bool
    {
        $token = (string) ($_COOKIE[self::COOKIE_DISPOSITIVO] ?? '');

        return $token !== '' && DispositivoConfiable::esValido($usuarioId, $token);
    }

    public static function recordarDispositivo(int $usuarioId): void
    {
        $token = DispositivoConfiable::crear(
            $usuarioId,
            isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null
        );

        setcookie(self::COOKIE_DISPOSITIVO, $token, [
            'expires' => time() + DispositivoConfiable::DIAS * 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => Session::esHttps(),
        ]);
    }

    /** Imagen PNG (data URI) del QR que se escanea con la aplicación autenticadora. */
    public static function qrDataUri(string $secreto, string $cuenta): string
    {
        $resultado = (new Builder(
            writer: new PngWriter(),
            data: Totp::uriOtpauth($secreto, $cuenta, self::EMISOR),
            size: 240,
            margin: 8,
        ))->build();

        return $resultado->getDataUri();
    }

    /**
     * Aviso por correo de un cambio de seguridad en la cuenta. Si el correo no está
     * configurado (p. ej. en local) o falla, no interrumpe la operación.
     */
    public static function avisarPorCorreo(array $usuario, string $asunto, string $mensaje): void
    {
        $nombre = trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
        $correo = PlantillaCorreo::armar(
            'Aviso de seguridad: ' . $asunto,
            $nombre,
            [$mensaje, 'Fecha: ' . date('Y-m-d H:i') . ' · Dirección IP: ' . (string) ($_SERVER['REMOTE_ADDR'] ?? '—')],
            null,
            'Si no fuiste tú, cambia tu contraseña de inmediato y avisa al administrador del sistema.'
        );

        try {
            MailService::enviar((string) $usuario['email'], $nombre, 'MIA: ' . $asunto, $correo['html'], $correo['texto']);
        } catch (\RuntimeException) {
            // Sin correo configurado: el cambio igual queda en la auditoría.
        }
    }
}
