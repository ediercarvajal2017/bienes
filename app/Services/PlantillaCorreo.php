<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Url;

/**
 * Presentación común de los correos que MIA envía a los usuarios: encabezado verde con
 * el logo, título, saludo, párrafos, un botón para la acción y una nota destacada.
 *
 * Está hecha para los programas de correo (Gmail, Outlook, el celular): tablas y estilos
 * en línea, sin hojas de estilo ni scripts. El logo va incrustado en el mensaje (cid:)
 * para que se vea aunque el programa bloquee las imágenes de internet; MailService lo
 * adjunta cuando el HTML lo usa. Devuelve también la versión en texto plano.
 */
final class PlantillaCorreo
{
    public const CID_LOGO = 'logo-mia';
    /** Ruta del logo desde la raíz del proyecto (192×192, ~12 KB). */
    public const RUTA_LOGO = 'public/assets/img/icon-192.png';

    private const VERDE = '#1F6F54';
    private const VERDE_OSCURO = '#145C43';
    private const TEXTO = '#1F2937';
    private const GRIS = '#6B7280';
    private const FONDO = '#F5F7FA';

    /**
     * @param string $titulo    lo que pasa, en pocas palabras ("Restablece tu contraseña")
     * @param string $nombre    a quién va dirigido (para el saludo)
     * @param list<string> $parrafos  texto plano; se escapa
     * @param array{texto: string, url: string}|null $boton  la acción principal
     * @param string|null $nota  aviso destacado al final (p. ej. "¿No fuiste tú?…")
     * @return array{html: string, texto: string}
     */
    public static function armar(string $titulo, string $nombre, array $parrafos, ?array $boton = null, ?string $nota = null): array
    {
        $e = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $sitio = Url::absoluta('/');
        $sitioVisible = preg_replace('#^https?://#', '', rtrim($sitio, '/'));
        $fuente = "font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;";

        $cuerpo = '';
        foreach ($parrafos as $p) {
            $cuerpo .= '<p style="margin: 0 0 16px; ' . $fuente . ' font-size: 16px; line-height: 1.55; color: ' . self::TEXTO . ';">' . $e($p) . '</p>';
        }

        $bloqueBoton = '';
        if ($boton !== null) {
            $url = $e($boton['url']);
            // Botón "a prueba de balas": la celda lleva el color (Outlook ignora el fondo de un <a>).
            $bloqueBoton = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px auto 24px;"><tr>'
                . '<td align="center" bgcolor="' . self::VERDE . '" style="border-radius: 8px; background: ' . self::VERDE . ';">'
                . '<a href="' . $url . '" target="_blank" style="display: inline-block; padding: 14px 32px; ' . $fuente
                . ' font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; border: 1px solid ' . self::VERDE_OSCURO . ';">'
                . $e($boton['texto']) . '</a></td></tr></table>'
                . '<p style="margin: 0 0 6px; ' . $fuente . ' font-size: 13px; line-height: 1.5; color: ' . self::GRIS . ';">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>'
                . '<p style="margin: 0 0 20px; ' . $fuente . ' font-size: 13px; line-height: 1.5; word-break: break-all;">'
                . '<a href="' . $url . '" target="_blank" style="color: ' . self::VERDE . ';">' . $url . '</a></p>';
        }

        $bloqueNota = $nota === null ? '' :
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 0;"><tr>'
            . '<td style="padding: 14px 16px; background: #FFF8E6; border-left: 4px solid #F2B134; border-radius: 6px; '
            . $fuente . ' font-size: 14px; line-height: 1.5; color: ' . self::TEXTO . ';">' . $e($nota) . '</td></tr></table>';

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<meta name="color-scheme" content="light"><title>' . $e($titulo) . '</title></head>'
            . '<body style="margin: 0; padding: 0; background: ' . self::FONDO . ';">'
            // Texto que los programas muestran como vista previa junto al asunto.
            . '<div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">' . $e($parrafos[0] ?? $titulo) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . self::FONDO . '" style="background: ' . self::FONDO . ';"><tr><td align="center" style="padding: 24px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; background: #FFFFFF; border-radius: 12px; overflow: hidden; border: 1px solid #E5E7EB;">'
            // Encabezado: logo y nombre.
            . '<tr><td align="center" bgcolor="' . self::VERDE . '" style="background: ' . self::VERDE . '; padding: 28px 24px 22px;">'
            . '<img src="cid:' . self::CID_LOGO . '" width="72" height="72" alt="MIA" style="display: block; width: 72px; height: 72px; border: 0; border-radius: 50%; background: #FFFFFF;">'
            . '<p style="margin: 10px 0 0; ' . $fuente . ' font-size: 22px; font-weight: 700; letter-spacing: 1px; color: #FFFFFF;">MIA</p>'
            . '<p style="margin: 2px 0 0; ' . $fuente . ' font-size: 13px; color: #D1FAE5;">Manejo de Inventario de Activos</p>'
            . '</td></tr>'
            // Contenido.
            . '<tr><td style="padding: 32px 32px 28px;">'
            . '<h1 style="margin: 0 0 20px; ' . $fuente . ' font-size: 22px; line-height: 1.3; font-weight: 700; color: ' . self::TEXTO . ';">' . $e($titulo) . '</h1>'
            . '<p style="margin: 0 0 16px; ' . $fuente . ' font-size: 16px; line-height: 1.55; color: ' . self::TEXTO . ';">Hola <strong>' . $e($nombre) . '</strong>,</p>'
            . $cuerpo . $bloqueBoton . $bloqueNota
            . '</td></tr>'
            // Pie.
            . '<tr><td align="center" style="padding: 18px 24px 22px; border-top: 1px solid #E5E7EB; ' . $fuente . ' font-size: 12px; line-height: 1.5; color: ' . self::GRIS . ';">'
            . 'Este es un mensaje automático de MIA; por favor no lo respondas.<br>'
            . '<a href="' . $e($sitio) . '" target="_blank" style="color: ' . self::VERDE . '; text-decoration: none;">' . $e((string) $sitioVisible) . '</a>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        $texto = $titulo . "\n\n" . 'Hola ' . $nombre . ",\n\n" . implode("\n\n", $parrafos) . "\n\n"
            . ($boton !== null ? $boton['texto'] . ': ' . $boton['url'] . "\n\n" : '')
            . ($nota !== null ? $nota . "\n\n" : '')
            . "--\nMIA · Manejo de Inventario de Activos\n" . $sitio;

        return ['html' => $html, 'texto' => $texto];
    }
}
