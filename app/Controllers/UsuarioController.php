<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Helpers\Uploader;
use App\Models\Cargo;
use App\Models\Institucion;
use App\Models\Usuario;

final class UsuarioController
{
    private const POR_PAGINA_DEFECTO = 25;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $terminoBusqueda = $busqueda !== '' ? $busqueda : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        $total = Usuario::contarListado($institucionId, $terminoBusqueda);

        View::layout('partials/layout', 'usuarios/index', [
            'title' => 'Usuarios',
            'usuarios' => Usuario::listar($institucionId, $terminoBusqueda, $pagina, $porPagina),
            'busqueda' => $busqueda,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function crear(): void
    {
        View::layout('partials/layout', 'usuarios/form', [
            'title' => 'Nuevo usuario',
            'usuario' => null,
            'cargos' => Cargo::activos(),
            'roles' => Usuario::rolesParaSelect(Auth::esSuperusuario()),
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request, '/usuarios/crear');

        $datos = $this->datosDesdeFormulario($request);
        $password = (string) $request->input('password');

        if ($error = $this->validar($datos, $password, null, false)) {
            Session::flash('error', $error);
            header('Location: ' . Url::to('/usuarios/crear'));
            exit;
        }

        $datos['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $id = Usuario::create($datos);

        if ($archivo = $request->file('foto')) {
            $this->subirFoto($id, $archivo);
        }

        Session::flash('ok', 'Usuario creado correctamente.');
        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    public function editar(string $id): void
    {
        $usuario = Usuario::find((int) $id);
        $this->verificarAcceso($usuario);

        View::layout('partials/layout', 'usuarios/form', [
            'title' => 'Editar usuario',
            'usuario' => $usuario,
            'cargos' => $this->cargosParaFormulario($usuario),
            'roles' => Usuario::rolesParaSelect(Auth::esSuperusuario()),
            'instituciones' => Auth::esSuperusuario() ? $this->institucionesParaFormulario($usuario) : [],
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        $request = new Request();
        $this->verificarCsrf($request, "/usuarios/{$id}/editar");

        $datos = $this->datosDesdeFormulario($request);
        $password = (string) $request->input('password');

        if ($error = $this->validar($datos, $password, $id, true)) {
            Session::flash('error', $error);
            header('Location: ' . Url::to("/usuarios/{$id}/editar"));
            exit;
        }

        Usuario::update($id, $datos);

        if ($password !== '') {
            Usuario::updatePassword($id, password_hash($password, PASSWORD_BCRYPT));
        }

        if ($archivo = $request->file('foto')) {
            $this->subirFoto($id, $archivo);
        }

        Session::flash('ok', 'Usuario actualizado.');
        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        if ($id === Auth::id()) {
            Session::flash('error', 'No puedes cambiar el estado de tu propia cuenta.');
            header('Location: ' . Url::to('/usuarios'));
            exit;
        }

        $request = new Request();
        $this->verificarCsrf($request, '/usuarios');

        Usuario::setActivo($id, !((bool) $usuario['activo']));

        Session::flash('ok', 'Estado del usuario actualizado.');
        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        if ($id === Auth::id()) {
            Session::flash('error', 'No puedes eliminar tu propia cuenta.');
            header('Location: ' . Url::to('/usuarios'));
            exit;
        }

        $request = new Request();
        $this->verificarCsrf($request, '/usuarios');

        if (Usuario::estaEnUso($id)) {
            Session::flash('error', 'No se puede eliminar: el usuario tiene movimientos o registros asociados. Desactívalo en su lugar.');
        } else {
            Usuario::eliminar($id);
            Session::flash('ok', 'Usuario eliminado.');
        }

        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    private function subirFoto(int $usuarioId, array $archivo): void
    {
        try {
            $path = Uploader::storeImage($archivo, 'fotos_usuarios');
            if ($path) {
                Usuario::updateFoto($usuarioId, $path);
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
    }

    private function datosDesdeFormulario(Request $request): array
    {
        $institucionId = Auth::esSuperusuario()
            ? (int) $request->input('institucion_id')
            : Auth::institucionId();

        return [
            'documento' => trim((string) $request->input('documento')),
            'nombres' => trim((string) $request->input('nombres')),
            'apellidos' => trim((string) $request->input('apellidos')),
            'cargo_id' => (int) $request->input('cargo_id'),
            'email' => trim((string) $request->input('email')),
            'institucion_id' => $institucionId,
            'rol_id' => (int) $request->input('rol_id'),
        ];
    }

    private function validar(array $datos, string $password, ?int $exceptId, bool $esEdicion): ?string
    {
        if ($datos['documento'] === '' || $datos['nombres'] === '' || $datos['apellidos'] === '' || $datos['email'] === '') {
            return 'Documento, nombres, apellidos y correo son obligatorios.';
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo electrónico no es válido.';
        }

        if (!$esEdicion && strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }

        if ($esEdicion && $password !== '' && strlen($password) < 8) {
            return 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        if (!$this->rolPermitido($datos['rol_id'])) {
            return 'No tienes permiso para asignar ese rol.';
        }

        if (Usuario::existeDocumento($datos['documento'], $exceptId)) {
            return 'Ya existe un usuario con ese número de documento.';
        }

        if (Usuario::existeEmail($datos['email'], $exceptId)) {
            return 'Ya existe un usuario con ese correo electrónico.';
        }

        return null;
    }

    private function rolPermitido(int $rolId): bool
    {
        foreach (Usuario::rolesParaSelect(Auth::esSuperusuario()) as $rol) {
            if ((int) $rol['id'] === $rolId) {
                return true;
            }
        }

        return false;
    }

    private function verificarCsrf(Request $request, string $volverA): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to($volverA));
            exit;
        }
    }

    private function verificarAcceso(?array $usuario): void
    {
        if (!$usuario) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $usuario['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /**
     * Cargos activos para el <select>, más el cargo actual del usuario si fue
     * desactivado después de asignárselo (para no perderlo del formulario al guardar).
     */
    private function cargosParaFormulario(array $usuario): array
    {
        $cargos = Cargo::activos();

        foreach ($cargos as $cargo) {
            if ((int) $cargo['id'] === (int) $usuario['cargo_id']) {
                return $cargos;
            }
        }

        $cargoActual = Cargo::find((int) $usuario['cargo_id']);
        if ($cargoActual) {
            $cargoActual['nombre'] .= ' (inactivo)';
            $cargos[] = $cargoActual;
        }

        return $cargos;
    }

    /**
     * Instituciones activas para el <select>, más la institución actual del usuario si fue
     * desactivada después de asignársela (para no perderla del formulario al guardar).
     */
    private function institucionesParaFormulario(array $usuario): array
    {
        $instituciones = Institucion::listadoParaSelect(true);

        foreach ($instituciones as $institucion) {
            if ((int) $institucion['id'] === (int) $usuario['institucion_id']) {
                return $instituciones;
            }
        }

        $institucionActual = Institucion::find((int) $usuario['institucion_id']);
        if ($institucionActual) {
            $institucionActual['nombre'] .= ' (inactiva)';
            $instituciones[] = $institucionActual;
        }

        return $instituciones;
    }
}
