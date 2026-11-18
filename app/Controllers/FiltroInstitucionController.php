<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
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
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to($volverA));
            exit;
        }

        if (!Auth::esSuperusuario()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $institucionId = (int) $request->input('institucion_id');

        $institucion = $institucionId > 0 ? Institucion::find($institucionId) : null;
        if ($institucion === null) {
            Auth::establecerFiltroInstitucion(null);
            Aviso::enviar(Aviso::exito('Ves de nuevo todas las instituciones.'));
        } else {
            Auth::establecerFiltroInstitucion($institucionId);
            Aviso::enviar(Aviso::exito('Los listados muestran solo ' . $institucion['nombre'] . '.',
                'Para ver todas, elige «Ver todas las instituciones» en la lista de arriba.'));
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    private function volverSeguro(string $ruta): string
    {
        return (str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) ? $ruta : '/dashboard';
    }
}
