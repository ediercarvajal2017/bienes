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
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Movimiento;
use App\Models\Verificacion;

final class BienController
{
    private const ESTADOS = ['activo', 'reintegrado', 'en_reparacion', 'dado_de_baja'];

    private const POR_PAGINA_DEFECTO = 50;
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

        $soloPropios = Auth::rol() === 'docente';

        if ($soloPropios) {
            $total = Bien::contarPropios((int) Auth::id(), $institucionId, $terminoBusqueda);
            $bienes = Bien::listarPropios((int) Auth::id(), $institucionId, $terminoBusqueda, $pagina, $porPagina);
        } else {
            $total = Bien::contarListado($institucionId, $terminoBusqueda);
            $bienes = Bien::listar($institucionId, $terminoBusqueda, $pagina, $porPagina);
        }

        View::layout('partials/layout', 'bienes/index', [
            'title' => 'Bienes',
            'bienes' => $bienes,
            'soloPropios' => $soloPropios,
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
        View::layout('partials/layout', 'bienes/form', [
            'title' => 'Registrar bien',
            'bien' => null,
            'categorias' => Categoria::activas(),
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [],
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request, '/bienes/crear');

        $datos = $this->datosDesdeFormulario($request);

        if ($error = $this->validar($datos, null)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to('/bienes/crear'));
            exit;
        }

        $datos['created_by'] = Auth::id();
        $id = Bien::create($datos);

        $this->procesarArchivos($id, $request, $datos['codigo_identificacion']);

        Session::flash('ok', 'Bien registrado correctamente.');
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    public function editar(string $id): void
    {
        $id = (int) $id;
        $bien = Bien::find($id);
        $this->verificarAcceso($bien);

        View::layout('partials/layout', 'bienes/form', [
            'title' => 'Editar bien',
            'bien' => $bien,
            'categorias' => $this->categoriasParaFormulario($bien),
            'asignacionActiva' => Asignacion::activaDe($id),
            'historialMovimientos' => Movimiento::historialDe($id),
            'espaciosInstitucion' => Espacio::listadoParaSelect((int) $bien['institucion_id']),
            'verificacionId' => Verificacion::idValidoParaBien($id, (string) ($_GET['verificacion_id'] ?? '')),
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $bien = Bien::find($id);
        $this->verificarAcceso($bien);

        $request = new Request();
        $this->verificarCsrf($request, "/bienes/{$id}/editar");

        $datos = $this->datosDesdeFormulario($request, (int) $bien['institucion_id']);
        $datos['estado'] = $this->estadoPermitidoDesdeFormulario($bien['estado'], $datos['estado']);

        if ($error = $this->validar($datos, $id)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Bien::update($id, $datos);

        $this->procesarArchivos($id, $request, $datos['codigo_identificacion']);

        Session::flash('ok', 'Bien actualizado.');
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    private function procesarArchivos(int $bienId, Request $request, string $codigoIdentificacion): void
    {
        try {
            if ($archivo = $request->file('foto')) {
                $path = Uploader::storeImage($archivo, 'fotos_bienes', $codigoIdentificacion);
                if ($path) {
                    Bien::updateFoto($bienId, $path);
                }
            }

            if ($archivo = $request->file('factura_pdf')) {
                $path = Uploader::storePdf($archivo, 'facturas');
                if ($path) {
                    Bien::updateFactura($bienId, $path);
                }
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
    }

    private function datosDesdeFormulario(Request $request, ?int $institucionIdExistente = null): array
    {
        $institucionId = $institucionIdExistente
            ?? (Auth::esSuperusuario() ? (int) $request->input('institucion_id') : Auth::institucionId());

        $categoriaId = (string) $request->input('categoria_id');
        $valor = str_replace(',', '', (string) $request->input('valor', '0'));
        $estado = (string) $request->input('estado');

        return [
            'institucion_id' => $institucionId,
            'codigo_identificacion' => trim((string) $request->input('codigo_identificacion')),
            'descripcion' => trim((string) $request->input('descripcion')),
            'marca' => trim((string) $request->input('marca')) ?: null,
            'categoria_id' => $categoriaId !== '' ? (int) $categoriaId : null,
            'fecha_ingreso' => (string) $request->input('fecha_ingreso'),
            'valor' => is_numeric($valor) ? (float) $valor : 0,
            'tiene_factura' => $request->input('tiene_factura') ? 1 : 0,
            'estado' => in_array($estado, self::ESTADOS, true) ? $estado : 'activo',
        ];
    }

    /**
     * 'reintegrado' y 'dado_de_baja' solo se alcanzan a través de sus propios flujos
     * (panel "Reintegrar" y aprobación de bajas), que registran el historial correspondiente.
     * Este formulario general de edición no puede saltarse esos flujos.
     */
    private function estadoPermitidoDesdeFormulario(string $estadoActual, string $estadoSolicitado): string
    {
        if (in_array($estadoActual, ['reintegrado', 'dado_de_baja'], true)) {
            return $estadoActual;
        }

        return in_array($estadoSolicitado, ['activo', 'en_reparacion'], true) ? $estadoSolicitado : $estadoActual;
    }

    private function validar(array $datos, ?int $exceptId): ?string
    {
        if ($datos['codigo_identificacion'] === '' || $datos['descripcion'] === '') {
            return 'El código de identificación y la descripción son obligatorios.';
        }

        if ($datos['fecha_ingreso'] === '' || !strtotime($datos['fecha_ingreso'])) {
            return 'La fecha de ingreso no es válida.';
        }

        if (Bien::existeCodigo($datos['institucion_id'], $datos['codigo_identificacion'], $exceptId)) {
            return 'Ya existe un bien con ese código en la institución.';
        }

        return null;
    }

    private function verificarCsrf(Request $request, string $volverA): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to($volverA));
            exit;
        }
    }

    private function verificarAcceso(?array $bien): void
    {
        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $bien['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /**
     * Categorías activas para el <select>, más la categoría actual del bien si fue
     * desactivada después de asignársela (para no perderla del formulario al guardar).
     */
    private function categoriasParaFormulario(array $bien): array
    {
        $categorias = Categoria::activas();

        if ($bien['categoria_id'] === null) {
            return $categorias;
        }

        foreach ($categorias as $categoria) {
            if ((int) $categoria['id'] === (int) $bien['categoria_id']) {
                return $categorias;
            }
        }

        $categoriaActual = Categoria::find((int) $bien['categoria_id']);
        if ($categoriaActual) {
            $categoriaActual['nombre'] .= ' (inactiva)';
            $categorias[] = $categoriaActual;
        }

        return $categorias;
    }
}
