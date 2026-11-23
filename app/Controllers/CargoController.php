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

final class CargoController
{
    public function index(): void
    {
        $this->verificarSuperusuario();

        View::layout('partials/layout', 'cargos/index', [
            'title' => 'Cargos',
            'cargos' => Cargo::all(),
        ]);
    }

    public function guardar(): void
    {
        $this->verificarSuperusuario();

        $request = new Request();
        $this->verificarCsrf($request);

        $nombre = trim((string) $request->input('nombre'));

        if ($nombre === '') {
            Aviso::enviar(Aviso::error('No se creó el cargo', 'Falta el nombre del cargo.', 'Escribe el nombre (por ejemplo «Docente») y vuelve a intentarlo.'));
        } elseif (mb_strlen($nombre) > 80) {
            Aviso::enviar(Aviso::error('No se creó el cargo', 'El nombre es más largo de lo que se puede guardar.', 'Acórtalo a 80 caracteres o menos y vuelve a intentarlo.'));
        } elseif (Cargo::existeNombre($nombre)) {
            Aviso::enviar(Aviso::error('No se creó el cargo', "El cargo «{$nombre}» ya existe.",
                'Búscalo en la lista; si está inactivo, actívalo en lugar de crearlo otra vez.'));
        } else {
            $id = Cargo::create($nombre);
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'crear', 'cargo', $id, null, ['nombre' => $nombre]);
            Aviso::enviar(Aviso::exito("Cargo «{$nombre}» creado.", 'Ya se puede elegir al registrar o editar un usuario.'));
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
            Aviso::enviar(\App\Helpers\Mensajes::yaNoExiste('No se guardó el cargo', 'El cargo', '/cargos', 'Ver los cargos'));
        } elseif ($nombre === '') {
            Aviso::enviar(Aviso::error('No se guardó el cargo', 'El nombre quedó vacío.', 'Escribe el nombre del cargo y vuelve a guardar.'));
        } elseif (mb_strlen($nombre) > 80) {
            Aviso::enviar(Aviso::error('No se guardó el cargo', 'El nombre es más largo de lo que se puede guardar.', 'Acórtalo a 80 caracteres o menos y vuelve a guardar.'));
        } elseif (Cargo::existeNombre($nombre, $id)) {
            Aviso::enviar(Aviso::error('No se guardó el cargo', "Ya existe otro cargo llamado «{$nombre}».",
                'Usa un nombre distinto, o usa el cargo que ya existe.'));
        } else {
            Cargo::renombrar($id, $nombre);
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'editar', 'cargo', $id, $cargo, ['nombre' => $nombre]);
            Aviso::enviar(Aviso::exito("Cargo renombrado a «{$nombre}».", 'Los usuarios con este cargo muestran el nombre nuevo.'));
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
            $activo = !((bool) $cargo['activo']);
            Cargo::setActivo((int) $id, $activo);
            Aviso::enviar($activo
                ? Aviso::exito("Cargo «{$cargo['nombre']}» activado.", 'Se puede elegir de nuevo al registrar o editar un usuario.')
                : Aviso::exito("Cargo «{$cargo['nombre']}» desactivado.", 'Ya no se ofrece al registrar usuarios; quienes lo tienen lo conservan.'));
        } else {
            Aviso::enviar(\App\Helpers\Mensajes::yaNoExiste('No se cambió el estado del cargo', 'El cargo', '/cargos', 'Ver los cargos'));
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
            Aviso::enviar(\App\Helpers\Mensajes::yaNoExiste('No se eliminó el cargo', 'El cargo', '/cargos', 'Ver los cargos'));
        } elseif (Cargo::estaEnUso($id)) {
            Aviso::enviar(Aviso::error('No se eliminó el cargo', "Hay usuarios con el cargo «{$cargo['nombre']}» asignado.",
                'Desactívalo en su lugar: deja de ofrecerse sin afectar a esos usuarios.'));
        } else {
            Cargo::eliminar($id, (int) Auth::id());
            Auditoria::registrar(Auth::id(), Auth::institucionId(), 'eliminar', 'cargo', $id, $cargo);
            Aviso::enviar(Aviso::exito("Cargo «{$cargo['nombre']}» enviado a la papelera.", 'Si fue un error, puedes restaurarlo desde la Papelera.'));
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
