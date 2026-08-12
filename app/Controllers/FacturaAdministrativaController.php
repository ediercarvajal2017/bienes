<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Evidencia;
use App\Helpers\Paginador;
use App\Helpers\Uploader;
use App\Models\Auditoria;
use App\Models\FacturaAdministrativa;
use App\Models\Institucion;

final class FacturaAdministrativaController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function formulario(): void
    {
        View::layout('partials/layout', 'facturas_admin/formulario', [
            'title' => 'Facturas',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $this->institucionSeleccionada(),
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $institucionId = $this->institucionSeleccionada();
        $fechaFactura = trim((string) $request->input('fecha_factura'));
        $descripcion = trim((string) $request->input('descripcion'));

        if ($institucionId === 0) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/facturas'));
            exit;
        }

        if ($fechaFactura === '' || $descripcion === '') {
            Session::flash('error', 'Indica la fecha de la factura y una descripción breve.');
            header('Location: ' . Url::to('/facturas'));
            exit;
        }

        try {
            $archivoPath = Uploader::storeDocumento($_FILES['archivo'] ?? [], 'facturas');
            if ($archivoPath === null) {
                Session::flash('error', 'Adjunta el archivo de la factura.');
                header('Location: ' . Url::to('/facturas'));
                exit;
            }

            $datos = [
                'institucion_id' => $institucionId,
                'fecha_factura' => $fechaFactura,
                'descripcion' => $descripcion,
                'archivo_path' => $archivoPath,
                'registrado_por' => Auth::id(),
            ];
            $id = FacturaAdministrativa::create($datos);
            Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'factura_administrativa', $id, null, $datos);

            Session::flash('ok', 'Factura guardada en la biblioteca de evidencia.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        header('Location: ' . Url::to('/facturas'));
        exit;
    }

    public function historial(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = FacturaAdministrativa::contarListado($institucionId);

        View::layout('partials/layout', 'facturas_admin/historial', [
            'title' => 'Histórico de facturas',
            'facturas' => FacturaAdministrativa::listar($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    public function formularioEditar(string $id): void
    {
        $registro = FacturaAdministrativa::find((int) $id);
        Evidencia::verificarAcceso($registro);

        View::layout('partials/layout', 'facturas_admin/editar', [
            'title' => 'Editar factura',
            'registro' => $registro,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FacturaAdministrativa::find($id);
        Evidencia::verificarAcceso($registro);

        $fechaFactura = trim((string) $request->input('fecha_factura'));
        $descripcion = trim((string) $request->input('descripcion'));

        if ($fechaFactura === '' || $descripcion === '') {
            Session::flash('error', 'Indica la fecha de la factura y una descripción breve.');
            header('Location: ' . Url::to("/facturas/{$id}/editar"));
            exit;
        }

        try {
            $archivoPath = $registro['archivo_path'];
            $nuevoArchivo = Uploader::storeDocumento($_FILES['archivo'] ?? [], 'facturas');
            if ($nuevoArchivo !== null) {
                Evidencia::eliminarArchivoFisico($archivoPath);
                $archivoPath = $nuevoArchivo;
            }

            $datosNuevos = [
                'fecha_factura' => $fechaFactura,
                'descripcion' => $descripcion,
                'archivo_path' => $archivoPath,
            ];
            FacturaAdministrativa::actualizar($id, $datosNuevos);
            Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'editar', 'factura_administrativa', $id, $registro, $datosNuevos);

            Session::flash('ok', 'Registro actualizado.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/facturas/{$id}/editar"));
            exit;
        }

        header('Location: ' . Url::to('/facturas/historial'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FacturaAdministrativa::find($id);
        Evidencia::verificarAcceso($registro);

        FacturaAdministrativa::eliminar($id, Auth::id());
        Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'eliminar', 'factura_administrativa', $id, $registro);

        Session::flash('ok', 'Registro enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
        header('Location: ' . Url::to('/facturas/historial'));
        exit;
    }

    private function institucionSeleccionada(): int
    {
        if (!Auth::esSuperusuario()) {
            return (int) Auth::institucionId();
        }

        return (int) ($_GET['institucion'] ?? $_POST['institucion_id'] ?? 0);
    }

    private function verificarCsrf(Request $request): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/facturas'));
            exit;
        }
    }
}
