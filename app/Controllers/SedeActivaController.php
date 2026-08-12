<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Institucion;

final class SedeActivaController
{
    public function cambiar(): void
    {
        $request = new Request();
        $volverA = $this->volverSeguro((string) $request->input('volver', '/dashboard'));

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to($volverA));
            exit;
        }

        if (Auth::rol() !== 'rector') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $destino = (int) $request->input('institucion_id');
        $familia = Institucion::familiaDe((int) Auth::institucionId());

        $perteneceAFamilia = false;
        foreach ($familia as $sede) {
            if ((int) $sede['id'] === $destino) {
                $perteneceAFamilia = true;
                break;
            }
        }

        if (!$perteneceAFamilia) {
            Session::flash('error', 'Esa sede no pertenece a tu institución.');
            header('Location: ' . Url::to($volverA));
            exit;
        }

        Auth::cambiarSedeActiva($destino);
        header('Location: ' . Url::to($volverA));
        exit;
    }

    private function volverSeguro(string $ruta): string
    {
        return (str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) ? $ruta : '/dashboard';
    }
}
