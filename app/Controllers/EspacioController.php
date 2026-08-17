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
use App\Models\Auditoria;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Usuario;

final class EspacioController
{
    private const POR_PAGINA_DEFECTO = 50;
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
        $total = Espacio::contarListado($institucionId, $terminoBusqueda);

        View::layout('partials/layout', 'espacios/index', [
            'title' => 'Espacios',
            'espacios' => Espacio::listar($institucionId, $terminoBusqueda, $pagina, $porPagina),
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
        $institucionId = Auth::institucionId();
        $viejo = Session::pullOld();

        View::layout('partials/layout', 'espacios/form', [
            'title' => 'Nuevo espacio',
            'espacio' => null,
            'usuarios' => Auth::esSuperusuario() ? Usuario::listarTodos(null) : Usuario::listarTodos($institucionId),
            'responsablesSeleccionados' => $viejo['responsables'] ?? [],
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [],
            'error' => Session::pullFlash('error'),
            'viejo' => $viejo,
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/espacios/crear', $datos);

        if ($error = $this->validar($datos, null)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to('/espacios/crear'));
            exit;
        }

        $id = Espacio::create($datos);
        Espacio::sincronizarResponsables($id, $datos['responsables']);
        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'crear', 'espacio', $id, null, $datos);

        Session::flash('ok', 'Espacio creado correctamente.');
        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    public function editar(string $id): void
    {
        $espacio = Espacio::find((int) $id);
        $this->verificarAcceso($espacio);
        $viejo = Session::pullOld();

        View::layout('partials/layout', 'espacios/form', [
            'title' => 'Editar espacio',
            'espacio' => $espacio,
            'usuarios' => Usuario::listarTodos($espacio['institucion_id']),
            'responsablesSeleccionados' => $viejo['responsables'] ?? array_column(Espacio::responsablesDe((int) $id), 'id'),
            'error' => Session::pullFlash('error'),
            'viejo' => $viejo,
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $espacio = Espacio::find($id);
        $this->verificarAcceso($espacio);

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request, (int) $espacio['institucion_id']);
        $this->verificarCsrf($request, "/espacios/{$id}/editar", $datos);

        if ($error = $this->validar($datos, $id)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to("/espacios/{$id}/editar"));
            exit;
        }

        Espacio::update($id, $datos);
        Espacio::sincronizarResponsables($id, $datos['responsables']);
        Auditoria::registrar(Auth::id(), (int) $espacio['institucion_id'], 'editar', 'espacio', $id, $espacio, $datos);

        Session::flash('ok', 'Espacio actualizado.');
        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $id = (int) $id;
        $espacio = Espacio::find($id);
        $this->verificarAcceso($espacio);

        $request = new Request();
        $this->verificarCsrf($request, '/espacios');

        Espacio::setActivo($id, !((bool) $espacio['activo']));

        Session::flash('ok', 'Estado del espacio actualizado.');
        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $espacio = Espacio::find($id);
        $this->verificarAcceso($espacio);

        $request = new Request();
        $this->verificarCsrf($request, '/espacios');

        if (Espacio::estaEnUso($id)) {
            Session::flash('error', 'No se puede eliminar: el espacio tiene asignaciones o movimientos registrados. Desactívalo en su lugar.');
        } else {
            Espacio::eliminar($id, Auth::id());
            Auditoria::registrar(Auth::id(), (int) $espacio['institucion_id'], 'eliminar', 'espacio', $id, $espacio);
            Session::flash('ok', 'Espacio enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
        }

        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    private function datosDesdeFormulario(Request $request, ?int $institucionIdExistente = null): array
    {
        $institucionId = $institucionIdExistente
            ?? (Auth::esSuperusuario() ? (int) $request->input('institucion_id') : Auth::institucionId());

        return [
            'institucion_id' => $institucionId,
            'nombre' => trim((string) $request->input('nombre')),
            'codigo' => trim((string) $request->input('codigo')),
            'responsables' => array_unique(array_map('intval', (array) $request->input('responsables', []))),
        ];
    }

    private function validar(array $datos, ?int $exceptId): ?string
    {
        if ($datos['nombre'] === '' || $datos['codigo'] === '') {
            return 'El número y el nombre del espacio son obligatorios.';
        }

        if (empty($datos['responsables'])) {
            return 'Cada espacio debe tener al menos un responsable.';
        }

        if (Espacio::existeCodigo($datos['institucion_id'], $datos['codigo'], $exceptId)) {
            return 'Ya existe un espacio con ese número en la institución.';
        }

        return null;
    }

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso cada llamador ya construye sus $datos ANTES de este chequeo y los pasa aquí,
     * para que el usuario no pierda todo lo que había escrito solo porque se demoró
     * llenando el formulario y el token expiró mientras tanto.
     */
    private function verificarCsrf(Request $request, string $volverA, array $datosAConservar = []): void
    {
        Csrf::verificarORedirigir($request, $volverA, $datosAConservar);
    }

    private function verificarAcceso(?array $espacio): void
    {
        if (!$espacio) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $espacio['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }
}
