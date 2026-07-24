<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Cargo;

final class CargoController
{
    public function index(): void
    {
        View::layout('partials/layout', 'cargos/index', [
            'title' => 'Cargos',
            'cargos' => Cargo::all(),
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
            Session::flash('error', 'El nombre del cargo es obligatorio.');
        } elseif (Cargo::existeNombre($nombre)) {
            Session::flash('error', 'Ese cargo ya existe.');
        } else {
            Cargo::create($nombre);
            Session::flash('ok', 'Cargo agregado.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    public function actualizar(string $id): void
    {
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
            Session::flash('ok', 'Cargo actualizado.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
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
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $cargo = Cargo::find($id);

        if (!$cargo) {
            Session::flash('error', 'Ese cargo ya no existe.');
        } elseif (Cargo::estaEnUso($id)) {
            Session::flash('error', 'No se puede eliminar: hay usuarios con este cargo asignado. Desactívalo en su lugar.');
        } else {
            Cargo::eliminar($id);
            Session::flash('ok', 'Cargo eliminado.');
        }

        header('Location: ' . Url::to('/cargos'));
        exit;
    }

    private function verificarCsrf(Request $request): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/cargos'));
            exit;
        }
    }
}
