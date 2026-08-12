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

/**
 * Filtro de institución para el superusuario, exclusivo de pantallas de listado (ver
 * Auth::filtroInstitucionId()) — nunca afecta a dónde se crea o edita un registro.
 */
final class FiltroInstitucionController
{
    public function establecer(): void
    {
        $request = new Request();
        $volverA = $this->volverSeguro((string) $request->input('volver', '/dashboard'));

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to($volverA));
            exit;
        }

        if (!Auth::esSuperusuario()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $institucionId = (int) $request->input('institucion_id');

        if ($institucionId <= 0 || !Institucion::find($institucionId)) {
            Auth::establecerFiltroInstitucion(null);
        } else {
            Auth::establecerFiltroInstitucion($institucionId);
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    private function volverSeguro(string $ruta): string
    {
        return (str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) ? $ruta : '/dashboard';
    }
}
