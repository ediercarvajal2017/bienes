<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\PoliticaDatos;
use App\Models\Auditoria;
use App\Models\Usuario;

/**
 * Política de tratamiento de datos personales y términos de uso: la página pública
 * (/politica-de-datos, enlazada desde el inicio de sesión y el pie de página) y la
 * aceptación obligatoria en el primer ingreso (/politica/aceptar).
 */
final class PoliticaController
{
    public function mostrar(): void
    {
        View::render('legal/politica', [
            'conSesion' => Session::has('usuario_id'),
        ]);
    }

    public function formularioAceptar(): void
    {
        if (!Session::get('politica_pendiente')) {
            header('Location: ' . Url::to('/dashboard'));
            exit;
        }

        View::render('legal/aceptar', [
            'nombre' => (string) Auth::nombreCompleto(),
            'institucion' => Auth::esSuperusuario() ? null : Auth::institucionNombre(),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function aceptar(): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/politica/aceptar');

        if ($request->input('acepto') !== '1') {
            Session::flash('error', 'Para continuar debes marcar la casilla de aceptación.');
            header('Location: ' . Url::to('/politica/aceptar'));
            exit;
        }

        $usuarioId = (int) Auth::id();
        Usuario::aceptarPolitica($usuarioId, PoliticaDatos::VERSION);
        Auditoria::registrar($usuarioId, Auth::institucionId(), 'aceptar_politica', 'usuario', $usuarioId,
            null, ['version' => PoliticaDatos::VERSION]);
        Session::put('politica_pendiente', false);

        // Vuelve a la página que se había pedido (solo rutas internas), o al panel.
        $destino = (string) Session::get('politica_destino', '');
        unset($_SESSION['politica_destino']);
        if (!preg_match('#^/[A-Za-z0-9/_\-]*(\?[^\s]*)?$#', $destino) || str_starts_with($destino, '//')) {
            $destino = '/dashboard';
        }

        header('Location: ' . Url::to($destino));
        exit;
    }
}
