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

final class CargoController
{
    public function index(): void
    {
        $this->verificarSuperusuario();

        View::layout('partials/layout', 'cargos/index', [
            'title' => 'Cargos',
            'cargos' => Cargo::all(),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(): void
    {
        $this->verificarSuperusuario();

        $request = new Request();
        $this->verificarCsrf($request);

        $nombre = trim((string) $request->input('nombre'));

        if ($nombre === '') {
            Session::flash('error', 'El nombre del cargo es obligatorio.');
        } elseif (Cargo::existeNombre($nombre)) {
            Session::flash('error', 'Ese cargo ya existe.');
        } else {
            $id = Cargo::create($nombre);
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'crear', 'cargo', $id, null, ['nombre' => $nombre]);
            Session::flash('ok', 'Cargo creado correctamente.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    public function actualizar(string $id): void
    {
        $this->verificarSuperusuario();

        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $cargo = Cargo::find($id);
        $nombre = trim((string) $request->input('nombre'));

        if (!$cargo) {
            Session::flash('error', 'Ese cargo ya no existe.');
        } elseif ($nombre === '') {
            Session::flash('error', 'El nombre del cargo es obligatorio.');
        } elseif (Cargo::existeNombre($nombre, $id)) {
            Session::flash('error', 'Ya existe otro cargo con ese nombre.');
        } else {
            Cargo::renombrar($id, $nombre);
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'editar', 'cargo', $id, $cargo, ['nombre' => $nombre]);
            Session::flash('ok', 'Cargo actualizado.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $this->verificarSuperusuario();

        $request = new Request();
        $this->verificarCsrf($request);

        $cargo = Cargo::find((int) $id);

        if ($cargo) {
            Cargo::setActivo((int) $id, !((bool) $cargo['activo']));
            Session::flash('ok', 'Estado del cargo actualizado.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $this->verificarSuperusuario();

        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $cargo = Cargo::find($id);

        if (!$cargo) {
            Session::flash('error', 'Ese cargo ya no existe.');
        } elseif (Cargo::estaEnUso($id)) {
            Session::flash('error', 'No se puede eliminar: hay usuarios con este cargo asignado. Desactívalo en su lugar.');
        } else {
            Cargo::eliminar($id, Auth::id());
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'eliminar', 'cargo', $id, $cargo);
            Session::flash('ok', 'Cargo enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    private function verificarCsrf(Request $request): void
    {
        Csrf::verificarORedirigir($request, '/cargos');
    }

    /**
     * Segunda capa, independiente de la ruta: /cargos ya está protegida en
     * public/index.php con SuperusuarioMiddleware, pero el catálogo de cargos es
     * compartido por todas las instituciones (a propósito, ver decisión del usuario) —
     * así que este controlador no depende únicamente de que las rutas queden bien
     * configuradas para siempre. Si algún día alguien agrega una ruta nueva aquí y
     * olvida el middleware, esto sigue bloqueando el acceso.
     */
    private function verificarSuperusuario(): void
    {
        if (!Auth::esSuperusuario()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }
}
