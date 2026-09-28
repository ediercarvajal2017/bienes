<?php

declare(strict_types=1);

/**
 * Resumen diario de errores de MIA por correo. Solo lee los registros; no toca la base.
 *
 *   php database/herramientas/resumen_errores.php                  # el día de ayer
 *   php database/herramientas/resumen_errores.php --fecha=2026-09-27
 *   php database/herramientas/resumen_errores.php --sin-correo     # solo lo muestra
 *   php database/herramientas/resumen_errores.php --siempre        # envía aunque no haya nada
 *
 * Revisa del día:
 *  - logs/app-AAAA-MM-DD.log: errores (500) y avisos de PHP, agrupados por mensaje;
 *  - logs/csp-AAAA-MM-DD.log: avisos de la política de contenido (App\Helpers\ReporteCsp);
 *  - logs/respaldo-drive.log: si el respaldo nocturno terminó bien.
 * Si hay algo que mirar, lo envía a BACKUP_EMAIL. Si todo está limpio, no envía nada.
 *
 * Se programa en hPanel > Avanzado > Cron Jobs, después del respaldo de las 2:00:
 *     30 6 * * *  cd /home/u397951547/domains/ediertech.com/public_html/bienes && php database/herramientas/resumen_errores.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Env;
use App\Services\MailService;

Env::cargar();

$config = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($config['timezone']);
$opciones = getopt('', ['fecha:', 'sin-correo', 'siempre']) ?: [];

$fecha = (string) ($opciones['fecha'] ?? date('Y-m-d', strtotime('-1 day')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    fwrite(STDERR, "Fecha inválida: {$fecha} (formato AAAA-MM-DD)\n");
    exit(1);
}
$logs = $config['storage_path'] . '/logs';

/**
 * Entradas del registro de la aplicación: cada una empieza con "[fecha hora] TIPO" y puede
 * seguir en varias líneas (la traza).
 *
 * @return list<array{hora: string, tipo: string, texto: string}>
 */
$leerEntradas = static function (string $archivo): array {
    if (!is_file($archivo)) {
        return [];
    }
    $entradas = [];
    $manejador = fopen($archivo, 'rb');
    while ($manejador !== false && ($linea = fgets($manejador)) !== false) {
        if (preg_match('/^\[\d{4}-\d{2}-\d{2} (\d{2}:\d{2}):\d{2}\] ([A-Z]+) (.*)$/', rtrim($linea), $m)) {
            $entradas[] = ['hora' => $m[1], 'tipo' => $m[2], 'texto' => $m[3]];
        } elseif ($entradas !== [] && !str_starts_with($linea, '#')) {
            // La línea con la excepción (lo útil); las de la traza "#0 ..." se omiten.
            $ultima = count($entradas) - 1;
            $entradas[$ultima]['texto'] .= ' ' . trim($linea);
        }
    }
    if ($manejador !== false) {
        fclose($manejador);
    }

    return $entradas;
};

/** Agrupa mensajes parecidos (sin el código de incidente, números ni rutas con id). */
$agrupar = static function (array $textos): array {
    $grupos = [];
    foreach ($textos as [$texto, $hora]) {
        $clave = (string) preg_replace(['/#[0-9A-F]{8}\s*/', '/\b\d+\b/', '/\s+/'], ['', 'N', ' '], $texto);
        $clave = mb_substr($clave, 0, 220);
        $grupos[$clave] ??= ['veces' => 0, 'ejemplo' => $texto, 'ultima' => $hora];
        $grupos[$clave]['veces']++;
        $grupos[$clave]['ultima'] = $hora;
    }
    uasort($grupos, static fn (array $a, array $b): int => $b['veces'] <=> $a['veces']);

    return $grupos;
};

$entradas = $leerEntradas("{$logs}/app-{$fecha}.log");
$errores = array_values(array_filter($entradas, static fn (array $e): bool => $e['tipo'] === 'ERROR'));
$avisos = array_values(array_filter($entradas, static fn (array $e): bool => $e['tipo'] !== 'ERROR'));
$gruposErrores = $agrupar(array_map(static fn (array $e): array => [$e['texto'], $e['hora']], $errores));
$gruposAvisos = $agrupar(array_map(static fn (array $e): array => [$e['texto'], $e['hora']], $avisos));

$csp = [];
if (is_file("{$logs}/csp-{$fecha}.log")) {
    foreach (file("{$logs}/csp-{$fecha}.log", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
        if (preg_match('/^\[\d{4}-\d{2}-\d{2} (\d{2}:\d{2}):\d{2}\] CSP (.*)$/', $linea, $m)) {
            // Se agrupa por directiva y recurso bloqueado; la página es solo un ejemplo.
            $csp[] = [(string) preg_replace('/ pagina=.*$/', '', $m[2]), $m[1]];
        }
    }
}
$gruposCsp = $agrupar($csp);

// Respaldo nocturno: la última corrida del registro debe ser de esta fecha (o del día
// siguiente, pues corre a las 2:00) y terminar en "Fin del respaldo ...: OK".
$respaldo = 'No se encontró el registro del respaldo (logs/respaldo-drive.log).';
$respaldoOk = false;
if (is_file("{$logs}/respaldo-drive.log")) {
    $lineas = array_slice(file("{$logs}/respaldo-drive.log", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -200);
    $ultimoFin = null;
    $ultimoInicio = null;
    foreach ($lineas as $linea) {
        if (str_contains($linea, '=== Inicio del respaldo')) {
            $ultimoInicio = $linea;
            $ultimoFin = null;
        } elseif (str_contains($linea, '=== Fin del respaldo') || str_contains($linea, 'FALLO')) {
            $ultimoFin = $linea;
        }
    }
    $diaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
    $reciente = $ultimoInicio !== null && (str_contains($ultimoInicio, "[{$fecha}") || str_contains($ultimoInicio, "[{$diaSiguiente}"));
    $respaldoOk = $reciente && $ultimoFin !== null && str_contains($ultimoFin, ': OK');
    $respaldo = match (true) {
        $respaldoOk => 'Respaldo nocturno: OK (' . substr((string) $ultimoFin, 1, 16) . ').',
        !$reciente => 'Respaldo nocturno: NO corrió (última corrida: ' . ($ultimoInicio !== null ? substr($ultimoInicio, 1, 16) : 'ninguna') . ').',
        default => 'Respaldo nocturno: FALLÓ o no terminó. ' . ($ultimoFin ?? $ultimoInicio),
    };
}

// ---------- Texto del resumen ----------
$texto = ["MIA · resumen del {$fecha}", ''];
$texto[] = sprintf('Errores: %d · Avisos de PHP: %d · Avisos de CSP: %d', count($errores), count($avisos), count($csp));
$texto[] = $respaldo;
foreach (['Errores' => $gruposErrores, 'Avisos de PHP' => $gruposAvisos, 'Avisos de CSP' => $gruposCsp] as $titulo => $grupos) {
    if ($grupos === []) {
        continue;
    }
    $texto[] = '';
    $texto[] = mb_strtoupper($titulo) . ':';
    foreach (array_slice($grupos, 0, 10) as $g) {
        $texto[] = sprintf('  %dx (última %s) %s', $g['veces'], $g['ultima'], mb_substr($g['ejemplo'], 0, 300));
    }
    if (count($grupos) > 10) {
        $texto[] = sprintf('  ... y %d tipos más.', count($grupos) - 10);
    }
}
echo implode("\n", $texto), "\n";

$hayAlgo = $errores !== [] || $avisos !== [] || $csp !== [] || !$respaldoOk;
if (isset($opciones['sin-correo']) || (!$hayAlgo && !isset($opciones['siempre']))) {
    exit(0);
}

$para = (string) $config['backup_email'];
if ($para === '') {
    fwrite(STDERR, "BACKUP_EMAIL no está configurado: el resumen no se envió.\n");
    exit(1);
}

$e = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
$html = '<h2 style="font-family:sans-serif">MIA · resumen del ' . $e($fecha) . '</h2>'
    . '<p style="font-family:sans-serif">' . $e($texto[2]) . '<br>' . $e($respaldo) . '</p>';
foreach (['Errores' => $gruposErrores, 'Avisos de PHP' => $gruposAvisos, 'Avisos de CSP' => $gruposCsp] as $titulo => $grupos) {
    if ($grupos === []) {
        continue;
    }
    $html .= '<h3 style="font-family:sans-serif">' . $e($titulo) . '</h3><table cellpadding="4" style="border-collapse:collapse;font-family:monospace;font-size:12px">';
    foreach (array_slice($grupos, 0, 10) as $g) {
        $html .= '<tr style="border-top:1px solid #ddd"><td valign="top"><b>' . (int) $g['veces'] . '×</b></td><td valign="top">' . $e($g['ultima'])
            . '</td><td>' . $e(mb_substr($g['ejemplo'], 0, 400)) . '</td></tr>';
    }
    $html .= '</table>';
}
$html .= '<p style="font-family:sans-serif;color:#666;font-size:12px">Detalle completo en storage_sigebi/logs/ del servidor (busca el código de incidente #XXXXXXXX).</p>';

$asunto = $errores !== [] || !$respaldoOk
    ? "MIA: {$fecha} con " . count($errores) . ' error(es)' . (!$respaldoOk ? ' y problema en el respaldo' : '')
    : "MIA: resumen del {$fecha}";

try {
    MailService::enviar($para, 'Responsable de MIA', $asunto, $html);
    echo "Resumen enviado a {$para}.\n";
} catch (Throwable $ex) {
    fwrite(STDERR, 'No se pudo enviar el resumen: ' . $ex->getMessage() . "\n");
    exit(1);
}
