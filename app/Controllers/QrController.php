<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\JornadaVerificacion;
use App\Models\Verificacion;
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

        $jornadaActiva = $puedeGestionar ? JornadaVerificacion::activaPara((int) $bien['institucion_id']) : null;
        $puedeVerificar = $jornadaActiva !== null
            && (Auth::rol() !== 'docente' || Bien::esResponsableDe((int) $bien['id'], (int) Auth::id()));
        $verificacionActual = $jornadaActiva !== null
            ? Verificacion::deBienEnJornada((int) $jornadaActiva['id'], (int) $bien['id'])
            : null;

        View::render('qr/mostrar', [
            'bien' => $bien,
            'token' => $token,
            'asignacion' => Asignacion::activaDe((int) $bien['id']),
            'puedeGestionar' => $puedeGestionar,
            'puedeReportarBaja' => Auth::check() && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear')),
            'jornadaActiva' => $jornadaActiva,
            'puedeVerificar' => $puedeVerificar,
            'verificacionActual' => $verificacionActual,
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    /**
     * Botón "Confirmar etiquetado" en /qr/{token}: quien acaba de pegar la etiqueta
     * física la escanea y, si lo que ve en pantalla coincide con lo que tiene enfrente,
     * confirma — deja constancia de que ESA etiqueta específica quedó pegada en el bien
     * correcto (no solo que el QR fue generado). Ver Bien::confirmarEtiqueta().
     */
    public function confirmarEtiqueta(string $token): void
    {
        $bien = Bien::findPorToken($token);

        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $institucionId = (int) $bien['institucion_id'];

        if (!Auth::esSuperusuario() && $institucionId !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        if ($bien['qr_confirmado_en'] === null) {
            Bien::confirmarEtiqueta((int) $bien['id'], (int) Auth::id());
            Auditoria::registrar(Auth::id(), $institucionId, 'confirmar_qr', 'bien', (int) $bien['id'], null, [
                'codigo_identificacion' => $bien['codigo_identificacion'],
            ]);
        }

        Session::flash('ok', 'Etiqueta confirmada: coincide con este bien.');
        header('Location: ' . Url::to("/qr/{$token}"));
        exit;
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
