<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Helpers\Evidencia;
use App\Helpers\Paginador;
use App\Helpers\Uploader;
use App\Models\Auditoria;
use App\Models\FormatoReintegro;
use App\Models\Institucion;

/**
 * Formatos de reintegro: una sola ventana con el formulario (registrar, o editar con
 * ?editar=ID) y debajo los registros con Descargar, Editar y Eliminar (ver Evidencia).
 */
final class FormatoReintegroController
{
    private const VENTANA = '/formatos-reintegro';

    public function formulario(): void
    {
        $registro = isset($_GET['editar']) ? FormatoReintegro::find((int) $_GET['editar']) : null;
        if (isset($_GET['editar'])) {
            Evidencia::verificarAcceso($registro);
        }
        $institucionId = $registro !== null ? (int) $registro['institucion_id'] : Evidencia::institucionDeVentana();
        [$pagina, $porPagina] = Evidencia::paginacion();
        $alcance = $institucionId > 0 ? $institucionId : null;
        $total = FormatoReintegro::contarListado($alcance);
        $viejo = Session::pullOld();

        View::layout('partials/layout', 'formatos_reintegro/formulario', [
            'title' => 'Formatos de reintegro',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'registro' => $registro,
            'valores' => $viejo !== [] ? $viejo : [
                'fecha_reintegro' => $registro['fecha_reintegro'] ?? date('Y-m-d'),
                'descripcion' => $registro['descripcion'] ?? '',
            ],
            'formatos' => FormatoReintegro::listar($alcance, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => Evidencia::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $institucionId = Evidencia::institucionDeVentana();
        $ventana = Evidencia::ruta(self::VENTANA, $institucionId);
        if ($institucionId === 0) {
            $this->volverConError(self::VENTANA, 'Selecciona una institución.', $request);
        }
        [$error, $datos] = $this->leerFormulario($request);
        if ($error !== null) {
            $this->volverConError($ventana, $error, $request);
        }

        try {
            $archivoPath = Uploader::storePdf($_FILES['archivo'] ?? [], 'reintegros');
            if ($archivoPath === null) {
                $this->volverConError($ventana, 'Adjunta el formato de reintegro en PDF.', $request);
            }

            $datos = ['institucion_id' => $institucionId] + $datos + ['archivo_path' => $archivoPath, 'registrado_por' => Auth::id()];
            $id = FormatoReintegro::create($datos);
            Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'formato_reintegro', $id, null, $datos);

            Session::flash('ok', 'Formato de reintegro guardado en la biblioteca de evidencia.');
        } catch (\RuntimeException $e) {
            $this->volverConError($ventana, $e->getMessage(), $request);
        }

        Evidencia::redirigir($ventana);
    }

    /** La lista ya está en la ventana única: la dirección vieja lleva allí. */
    public function historial(): void
    {
        Evidencia::redirigir(Evidencia::ruta(self::VENTANA, Evidencia::institucionDeVentana()));
    }

    /** Se edita en la ventana única: la dirección vieja lleva allí. */
    public function formularioEditar(string $id): void
    {
        Evidencia::redirigir(self::VENTANA . '?editar=' . (int) $id . '#formularioEvidencia');
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FormatoReintegro::find($id);
        Evidencia::verificarAcceso($registro);
        $institucionId = (int) $registro['institucion_id'];
        $editar = Evidencia::ruta(self::VENTANA, $institucionId, ['editar' => $id]);

        [$error, $datos] = $this->leerFormulario($request);
        if ($error !== null) {
            $this->volverConError($editar, $error, $request);
        }

        try {
            $archivoPath = $registro['archivo_path'];
            $nuevoArchivo = Uploader::storePdf($_FILES['archivo'] ?? [], 'reintegros');
            if ($nuevoArchivo !== null) {
                Evidencia::eliminarArchivoFisico($archivoPath);
                $archivoPath = $nuevoArchivo;
            }

            $datosNuevos = $datos + ['archivo_path' => $archivoPath];
            FormatoReintegro::actualizar($id, $datosNuevos);
            Auditoria::registrar(Auth::id(), $institucionId, 'editar', 'formato_reintegro', $id, $registro, $datosNuevos);

            Session::flash('ok', 'Registro actualizado.');
        } catch (\RuntimeException $e) {
            $this->volverConError($editar, $e->getMessage(), $request);
        }

        Evidencia::redirigir(Evidencia::ruta(self::VENTANA, $institucionId));
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FormatoReintegro::find($id);
        Evidencia::verificarAcceso($registro);

        FormatoReintegro::eliminar($id, (int) Auth::id());
        Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'eliminar', 'formato_reintegro', $id, $registro);

        Session::flash('ok', 'Registro enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
        Evidencia::redirigir(Evidencia::ruta(self::VENTANA, (int) $registro['institucion_id']));
    }

    /** @return array{0: ?string, 1: array{fecha_reintegro: string, descripcion: ?string}} */
    private function leerFormulario(Request $request): array
    {
        $datos = [
            'fecha_reintegro' => trim((string) $request->input('fecha_reintegro')),
            'descripcion' => trim((string) $request->input('descripcion')) ?: null,
        ];
        $error = $datos['fecha_reintegro'] === '' ? 'Indica la fecha del reintegro.' : null;

        return [$error, $datos];
    }

    private function volverConError(string $ruta, string $mensaje, Request $request): never
    {
        Session::flash('error', $mensaje);
        Session::flashOld([
            'fecha_reintegro' => (string) $request->input('fecha_reintegro'),
            'descripcion' => (string) $request->input('descripcion'),
        ]);
        Evidencia::redirigir($ruta);
    }

    private function verificarCsrf(Request $request): void
    {
        Csrf::verificarORedirigir($request, self::VENTANA);
    }
}
