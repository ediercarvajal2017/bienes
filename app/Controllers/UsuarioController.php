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
use App\Models\Auditoria;
use App\Models\Cargo;
use App\Models\Institucion;
use App\Models\Usuario;

final class UsuarioController
{
    private const POR_PAGINA_DEFECTO = 25;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
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
            'familiaSedes' => $this->familiaSedesDelRector(),
            'error' => Session::pullFlash('error'),
            'errorCampo' => Session::pullFlash('error_campo'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/usuarios/crear', $datos);

        $password = (string) $request->input('password');

        if ($resultado = $this->validar($datos, $password, null, false)) {
            Session::flash('error', $resultado['mensaje']);
            if ($resultado['campo'] !== null) {
                Session::flash('error_campo', $resultado['campo']);
            }
            Session::flashOld($datos);
            header('Location: ' . Url::to('/usuarios/crear'));
            exit;
        }

        $snapshot = $datos;
        $datos['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $id = Usuario::create($datos);
        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'crear', 'usuario', $id, null, $snapshot);

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
            'familiaSedes' => $this->familiaSedesDelRector(),
            'error' => Session::pullFlash('error'),
            'errorCampo' => Session::pullFlash('error_campo'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, "/usuarios/{$id}/editar", $datos);

        $password = (string) $request->input('password');

        if ($resultado = $this->validar($datos, $password, $id, true)) {
            Session::flash('error', $resultado['mensaje']);
            if ($resultado['campo'] !== null) {
                Session::flash('error_campo', $resultado['campo']);
            }
            Session::flashOld($datos);
            header('Location: ' . Url::to("/usuarios/{$id}/editar"));
            exit;
        }

        $antes = $usuario;
        unset($antes['password_hash']);
        Usuario::update($id, $datos);
        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'editar', 'usuario', $id, $antes, $datos);

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
            $snapshot = $usuario;
            unset($snapshot['password_hash']);
            Usuario::eliminar($id, Auth::id());
            Auditoria::registrar(Auth::id(), (int) $usuario['institucion_id'], 'eliminar', 'usuario', $id, $snapshot);
            Session::flash('ok', 'Usuario enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
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
        $institucionId = $this->institucionIdDesdeFormulario($request);

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

    /**
     * El superusuario elige libremente. Un rector con más de una sede en su familia
     * también puede elegir, pero limitado a esa familia (nunca a una institución ajena) —
     * así puede crear o mover un usuario a cualquiera de sus sedes sin necesitar una
     * cuenta de superusuario. Cualquier otro caso (secretario, docente, o un valor que no
     * pertenece a la familia) se queda fijo en la sede activa, igual que antes.
     */
    private function institucionIdDesdeFormulario(Request $request): int
    {
        if (Auth::esSuperusuario()) {
            return (int) $request->input('institucion_id');
        }

        if (Auth::rol() === 'rector') {
            $solicitada = (int) $request->input('institucion_id');
            foreach ($this->familiaSedesDelRector() as $sede) {
                if ((int) $sede['id'] === $solicitada) {
                    return $solicitada;
                }
            }
        }

        return (int) Auth::institucionId();
    }

    /**
     * La familia (principal + secciones) de la sede activa del rector, o un arreglo
     * vacío para cualquier otro rol — usado tanto para armar el selector de sede en el
     * formulario como para validar lo que llegue en institucionIdDesdeFormulario().
     */
    private function familiaSedesDelRector(): array
    {
        if (Auth::rol() !== 'rector' || !Auth::institucionId()) {
            return [];
        }

        return Institucion::familiaDe((int) Auth::institucionId());
    }

    /**
     * @return array{campo: ?string, mensaje: string}|null "campo" es el name del input a
     * resaltar en el formulario (ver UsuarioController::guardar()/actualizar() y
     * usuarios/form.php) — null cuando el error no corresponde a un campo puntual.
     */
    private function validar(array $datos, string $password, ?int $exceptId, bool $esEdicion): ?array
    {
        if ($datos['documento'] === '') {
            return ['campo' => 'documento', 'mensaje' => 'El documento es obligatorio.'];
        }

        if ($datos['nombres'] === '') {
            return ['campo' => 'nombres', 'mensaje' => 'Los nombres son obligatorios.'];
        }

        if ($datos['apellidos'] === '') {
            return ['campo' => 'apellidos', 'mensaje' => 'Los apellidos son obligatorios.'];
        }

        if ($datos['email'] === '') {
            return ['campo' => 'email', 'mensaje' => 'El correo es obligatorio.'];
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return ['campo' => 'email', 'mensaje' => 'El correo electrónico no es válido.'];
        }

        if (!$esEdicion && strlen($password) < 8) {
            return ['campo' => 'password', 'mensaje' => 'La contraseña debe tener al menos 8 caracteres.'];
        }

        if ($esEdicion && $password !== '' && strlen($password) < 8) {
            return ['campo' => 'password', 'mensaje' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
        }

        if (!$this->rolPermitido($datos['rol_id'])) {
            return ['campo' => 'rol_id', 'mensaje' => 'No tienes permiso para asignar ese rol.'];
        }

        if (Usuario::existeDocumento($datos['documento'], $exceptId)) {
            return ['campo' => 'documento', 'mensaje' => 'Ya existe un usuario con ese número de documento.'];
        }

        if (Usuario::existeEmail($datos['email'], $exceptId)) {
            return ['campo' => 'email', 'mensaje' => 'Ya existe un usuario con ese correo electrónico.'];
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

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso cada llamador ya construye sus $datos ANTES de este chequeo y los pasa aquí,
     * para que el usuario no pierda todo lo que había escrito solo porque se demoró
     * llenando el formulario y el token expiró mientras tanto. Nunca incluye la contraseña
     * (datosDesdeFormulario() no la trae; se lee aparte y nunca se flashea).
     */
    private function verificarCsrf(Request $request, string $volverA, array $datosAConservar = []): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            if (!empty($datosAConservar)) {
                Session::flashOld($datosAConservar);
            }
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
