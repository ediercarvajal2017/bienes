<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Bien;

final class EscaneoController
{
    public function index(): void
    {
        View::layout('partials/layout', 'escanear/index', [
            'title' => 'Escanear código QR',
            'error' => Session::pullFlash('error'),
        ]);
    }

    /**
     * Búsqueda manual por el código del bien (lo que el usuario realmente conoce e
     * imprime en la planilla) — a diferencia del token del QR, que es un UUID interno
     * generado al crear el bien y que nadie teclea a mano. Si lo encuentra, redirige a
     * la misma vista pública /qr/{token} a la que llega el escaneo por cámara.
     */
    public function buscar(): void
    {
        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        $institucionId = Auth::institucionId();

        if ($codigo === '') {
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        if ($institucionId === null) {
            Session::flash('error', 'Tu usuario no tiene una institución asignada para buscar por código.');
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        $bien = Bien::buscarPorCodigoInstitucion($institucionId, $codigo);

        if (!$bien) {
            Session::flash('error', 'No se encontró ningún bien con el código "' . $codigo . '".');
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        header('Location: ' . Url::to('/qr/' . $bien['qr_token']));
        exit;
    }
}
