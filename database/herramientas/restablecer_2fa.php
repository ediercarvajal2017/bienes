<?php

declare(strict_types=1);

/**
 * Emergencia: quita la verificación en dos pasos de una cuenta desde la consola del
 * servidor, para cuando nadie puede hacerlo desde la web (p. ej. el único superusuario
 * perdió el teléfono y sus códigos de recuperación, o APP_KEY se perdió).
 *
 * Borra la clave del autenticador, los códigos de recuperación y los dispositivos de
 * confianza de esa cuenta y cierra sus sesiones. No toca ningún otro dato. Queda en la
 * auditoría como '2fa_restablecer' (origen: consola).
 *
 * Simula por defecto; solo cambia algo con --aplicar.
 *
 * Uso: php database/herramientas/restablecer_2fa.php --email=persona@dominio.com [--aplicar]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Auditoria;
use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use App\Models\Usuario;
use App\Services\DosFactoresService;

Env::cargar();

$email = '';
$aplicar = false;
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--email=')) {
        $email = trim(substr($argumento, strlen('--email=')));
    } elseif ($argumento === '--aplicar') {
        $aplicar = true;
    }
}

if ($email === '') {
    fwrite(STDERR, "Uso: php database/herramientas/restablecer_2fa.php --email=persona@dominio.com [--aplicar]\n");
    exit(1);
}

$usuario = Usuario::findByEmail($email);
if ($usuario === null) {
    fwrite(STDERR, "No existe una cuenta (fuera de la papelera) con el correo {$email}.\n");
    exit(1);
}

$id = (int) $usuario['id'];
$nombre = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
echo "Cuenta: {$nombre} <{$email}> · rol {$usuario['rol_nombre']} · id {$id}\n";

if (!DosFactoresService::tieneActiva($usuario)) {
    echo "No tiene la verificación en dos pasos activa. Nada que hacer.\n";
    exit(0);
}

$dispositivos = count(DispositivoConfiable::listarDe($id));
$codigos = CodigoRecuperacion::disponibles($id);
echo "Activa desde {$usuario['totp_activado_en']} · {$codigos} códigos de recuperación sin usar · {$dispositivos} dispositivos de confianza.\n";

if (!$aplicar) {
    echo "\nSIMULACIÓN: no se cambió nada. Para restablecerla, repita con --aplicar.\n";
    exit(0);
}

Database::transaccion(static function () use ($id, $usuario): void {
    Usuario::desactivarTotp($id);
    CodigoRecuperacion::borrarDe($id);
    DispositivoConfiable::revocarTodosDe($id);
    Usuario::invalidarSesiones($id);
    Auditoria::registrar(null, (int) $usuario['institucion_id'], '2fa_restablecer', 'usuario', $id,
        null, ['origen' => 'consola']);
});

echo "\nListo: la verificación en dos pasos quedó restablecida. En su próximo ingreso entra con la\n"
    . "contraseña y, si su rol la exige, la configura de nuevo.\n";
