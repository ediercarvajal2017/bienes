<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Recibe los avisos que el navegador envía cuando algo viola la política de contenido (CSP,
 * ver PoliticaContenido: "report-uri /csp-reporte") y los deja en logs/csp-AAAA-MM-DD.log.
 * database/herramientas/resumen_errores.php los incluye en el resumen diario: cuando pasen
 * unos días sin avisos reales, la CSP se puede volver obligatoria sin romper nada.
 *
 * Se atiende antes de abrir la sesión (public/index.php): no la necesita y así no se crea
 * un archivo de sesión por cada aviso. No usa la base de datos.
 */
final class ReporteCsp
{
    /** Tope diario del archivo: si alguien lo inunda, se deja de escribir. */
    private const TAMANO_MAXIMO = 1048576;

    public static function recibir(string $dirLogs): void
    {
        http_response_code(204);

        $cuerpo = (string) file_get_contents('php://input', false, null, 0, 16384);
        $datos = json_decode($cuerpo, true);
        $aviso = is_array($datos) && is_array($datos['csp-report'] ?? null) ? $datos['csp-report'] : null;
        if ($aviso === null) {
            return;
        }

        $bloqueado = self::texto($aviso['blocked-uri'] ?? '');
        // Las extensiones del navegador del usuario también generan avisos: no son de MIA.
        if (preg_match('#^(chrome|moz|safari|ms-browser)-extension:#i', $bloqueado)) {
            return;
        }

        $linea = sprintf(
            "[%s] CSP directiva=%s bloqueado=%s pagina=%s origen=%s\n",
            date('Y-m-d H:i:s'),
            self::texto($aviso['effective-directive'] ?? $aviso['violated-directive'] ?? ''),
            self::sinConsulta($bloqueado),
            self::sinConsulta(self::texto($aviso['document-uri'] ?? '')),
            self::sinConsulta(self::texto($aviso['source-file'] ?? '')) . (isset($aviso['line-number']) ? ':' . (int) $aviso['line-number'] : '')
        );

        $archivo = $dirLogs . '/csp-' . date('Y-m-d') . '.log';
        if ((is_dir($dirLogs) || @mkdir($dirLogs, 0775, true))
            && (!is_file($archivo) || (int) filesize($archivo) < self::TAMANO_MAXIMO)
        ) {
            @file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
        }
    }

    /** Una sola línea, sin caracteres de control y de largo acotado. */
    private static function texto(mixed $valor): string
    {
        $texto = is_scalar($valor) ? (string) $valor : '';

        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F\s]+/', ' ', $texto), 0, 300);
    }

    /** Sin la parte "?..." de la URL (podría traer un token o datos del usuario). */
    private static function sinConsulta(string $url): string
    {
        return $url === '' ? '-' : explode('?', $url, 2)[0];
    }
}
