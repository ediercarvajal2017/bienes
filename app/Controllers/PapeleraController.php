<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Auditoria;
use App\Models\Cargo;
use App\Models\CarteraEnvio;
use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\FacturaAdministrativa;
use App\Models\FormatoPlaqueteo;
use App\Models\FormatoReintegro;
use App\Models\Papelera;
use App\Models\Usuario;

/**
 * Solo para superusuario (gateado en la ruta por SuperusuarioMiddleware, igual que
 * /cargos y la activación de instituciones): ver y restaurar lo que cualquier
 * institución envió a la papelera.
 */
final class PapeleraController
{
    public function index(): void
    {
        View::layout('partials/layout', 'papelera/index', [
            'title' => 'Papelera de reciclaje',
            'elementos' => Papelera::listar(),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function restaurar(string $tipo, string $id): void
    {
        $id = (int) $id;
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/papelera'));
            exit;
        }

        if (Papelera::tabla($tipo) === null) {
            Session::flash('error', 'Tipo de elemento no reconocido.');
            header('Location: ' . Url::to('/papelera'));
            exit;
        }

        match ($tipo) {
            'usuario' => Usuario::restaurar($id),
            'espacio' => Espacio::restaurar($id),
            'categoria' => Categoria::restaurar($id),
            'cargo' => Cargo::restaurar($id),
            'factura_administrativa' => FacturaAdministrativa::restaurar($id),
            'formato_reintegro' => FormatoReintegro::restaurar($id),
            'formato_plaqueteo' => FormatoPlaqueteo::restaurar($id),
            'cartera_envio' => CarteraEnvio::restaurar($id),
            default => throw new \RuntimeException("Tipo de papelera no reconocido: {$tipo}"),
        };

        Auditoria::registrar(Auth::id(), Auth::institucionId(), 'restaurar', $tipo, $id);

        Session::flash('ok', 'Elemento restaurado.');
        header('Location: ' . Url::to('/papelera'));
        exit;
    }
}
