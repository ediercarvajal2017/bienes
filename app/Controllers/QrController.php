<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;
use App\Models\Asignacion;
use App\Models\Bien;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;

final class QrController
{
    public function mostrar(string $token): void
    {
        $bien = Bien::findPorToken($token);

        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $puedeGestionar = Auth::check()
            && (Auth::esSuperusuario() || (int) $bien['institucion_id'] === Auth::institucionId());

        View::render('qr/mostrar', [
            'bien' => $bien,
            'token' => $token,
            'asignacion' => Asignacion::activaDe((int) $bien['id']),
            'puedeGestionar' => $puedeGestionar,
            'puedeReportarBaja' => Auth::check() && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear')),
        ]);
    }

    public function imagen(string $token): void
    {
        $bien = Bien::findPorToken($token);

        if (!$bien) {
            http_response_code(404);
            exit;
        }

        $builder = new Builder(
            writer: new PngWriter(),
            data: Url::absoluta("/qr/{$token}"),
            size: 400,
            margin: 12,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        );
        $resultado = $builder->build();

        header('Content-Type: ' . $resultado->getMimeType());
        header('Cache-Control: public, max-age=86400');
        echo $resultado->getString();
    }
}
