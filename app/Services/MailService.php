<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    /**
     * Envía un correo simple en HTML (con su versión en texto plano de respaldo).
     * Lanza RuntimeException si el SMTP no está configurado o si el envío falla.
     */
    public static function enviar(string $paraCorreo, string $paraNombre, string $asunto, string $cuerpoHtml): void
    {
        $config = require dirname(__DIR__, 2) . '/config/mail.php';

        if ($config['host'] === '' || $config['from_address'] === '') {
            throw new \RuntimeException('El envío de correo no está configurado en este servidor.');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->Port = $config['port'];
            if ($config['encryption'] !== '') {
                $mail->SMTPSecure = $config['encryption'];
            }
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['from_address'], $config['from_name']);
            $mail->addAddress($paraCorreo, $paraNombre);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpoHtml;
            $mail->AltBody = trim(strip_tags($cuerpoHtml));

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new \RuntimeException('No se pudo enviar el correo: ' . $mail->ErrorInfo);
        }
    }
}
