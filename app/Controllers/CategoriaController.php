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
use App\Models\Categoria;

final class CategoriaController
{
    public function index(): void
    {
        View::layout('partials/layout', 'categorias/index', [
            'title' => 'Categorías de bienes',
            'categorias' => Categoria::all(),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $nombre = trim((string) $request->input('nombre'));

        if ($nombre === '') {
            Session::flash('error', 'El nombre de la categoría es obligatorio.');
        } elseif (Categoria::existeNombre($nombre)) {
            Session::flash('error', 'Esa categoría ya existe.');
        } else {
            Categoria::create($nombre);
            Session::flash('ok', 'Categoría creada correctamente.');
        }

        header('Location: ' . Url::to('/categorias'));
        exit;
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $categoria = Categoria::find($id);
        $nombre = trim((string) $request->input('nombre'));

        if (!$categoria) {
            Session::flash('error', 'Esa categoría ya no existe.');
        } elseif ($nombre === '') {
            Session::flash('error', 'El nombre de la categoría es obligatorio.');
        } elseif (Categoria::existeNombre($nombre, $id)) {
            Session::flash('error', 'Ya existe otra categoría con ese nombre.');
        } else {
            Categoria::renombrar($id, $nombre);
            Session::flash('ok', 'Categoría actualizada.');
        }

        header('Location: ' . Url::to('/categorias'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $categoria = Categoria::find((int) $id);

        if ($categoria) {
            Categoria::setActivo((int) $id, !((bool) $categoria['activo']));
            Session::flash('ok', 'Estado de la categoría actualizado.');
        }

        header('Location: ' . Url::to('/categorias'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $categoria = Categoria::find($id);

        if (!$categoria) {
            Session::flash('error', 'Esa categoría ya no existe.');
        } elseif (Categoria::estaEnUso($id)) {
            Session::flash('error', 'No se puede eliminar: hay bienes registrados en esta categoría. Desactívala en su lugar.');
        } else {
            Categoria::eliminar($id, Auth::id());
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'eliminar', 'categoria', $id, $categoria);
            Session::flash('ok', 'Categoría enviada a la papelera. Un superusuario puede restaurarla si fue un error.');
        }

        header('Location: ' . Url::to('/categorias'));
        exit;
    }

    private function verificarCsrf(Request $request): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/categorias'));
            exit;
        }
    }
}
