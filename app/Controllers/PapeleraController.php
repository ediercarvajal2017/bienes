<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
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
        ]);
    }

    public function restaurar(string $tipo, string $id): void
    {
        $id = (int) $id;
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to('/papelera'));
            exit;
        }

        if (Papelera::tabla($tipo) === null) {
            Aviso::enviar(Aviso::error('No se restauró el elemento', 'El enlace no corresponde a ningún tipo de elemento de la papelera.',
                'Vuelve a la lista de la Papelera y pulsa «Restaurar» en la fila del elemento.'));
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

        $donde = ['usuario' => 'Usuarios', 'espacio' => 'Espacios', 'categoria' => 'Categorías', 'cargo' => 'Cargos',
            'factura_administrativa' => 'Facturas', 'formato_reintegro' => 'Formatos de reintegro',
            'formato_plaqueteo' => 'Formatos de plaqueteo', 'cartera_envio' => 'Cartera'][$tipo];
        Aviso::enviar(Aviso::exito('Elemento restaurado.', "Vuelve a aparecer en «{$donde}», como estaba antes de eliminarlo."));
        header('Location: ' . Url::to('/papelera'));
        exit;
    }
}
