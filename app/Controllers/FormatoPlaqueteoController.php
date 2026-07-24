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
use App\Helpers\Uploader;
use App\Models\FormatoPlaqueteo;
use App\Models\Institucion;

final class FormatoPlaqueteoController
{
    private const POR_PAGINA = 50;

    public function formulario(): void
    {
        View::layout('partials/layout', 'formatos_plaqueteo/formulario', [
            'title' => 'Formatos de plaqueteo',
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
        $fechaPlaqueteo = trim((string) $request->input('fecha_plaqueteo'));
        $funcionario = trim((string) $request->input('funcionario_asistio'));

        if ($institucionId === 0) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/formatos-plaqueteo'));
            exit;
        }

        if ($fechaPlaqueteo === '' || $funcionario === '') {
            Session::flash('error', 'Indica la fecha del plaqueteo y el funcionario que asistió.');
            header('Location: ' . Url::to('/formatos-plaqueteo'));
            exit;
        }

        try {
            $archivoPath = Uploader::storePdf($_FILES['archivo'] ?? [], 'plaqueteo');
            if ($archivoPath === null) {
                Session::flash('error', 'Adjunta el formato de plaqueteo en PDF.');
                header('Location: ' . Url::to('/formatos-plaqueteo'));
                exit;
            }

            FormatoPlaqueteo::create([
                'institucion_id' => $institucionId,
                'fecha_plaqueteo' => $fechaPlaqueteo,
                'funcionario_asistio' => $funcionario,
                'archivo_path' => $archivoPath,
                'registrado_por' => Auth::id(),
            ]);

            Session::flash('ok', 'Formato de plaqueteo guardado en la biblioteca de evidencia.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        header('Location: ' . Url::to('/formatos-plaqueteo'));
        exit;
    }

    public function historial(): void
    {
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $total = FormatoPlaqueteo::contarListado($institucionId);

        View::layout('partials/layout', 'formatos_plaqueteo/historial', [
            'title' => 'Histórico de formatos de plaqueteo',
            'formatos' => FormatoPlaqueteo::listar($institucionId, $pagina, self::POR_PAGINA),
            'pagina' => $pagina,
            'porPagina' => self::POR_PAGINA,
            'total' => $total,
            'totalPaginas' => (int) max(1, ceil($total / self::POR_PAGINA)),
        ]);
    }

    public function formularioEditar(string $id): void
    {
        $registro = FormatoPlaqueteo::find((int) $id);
        Evidencia::verificarAcceso($registro);

        View::layout('partials/layout', 'formatos_plaqueteo/editar', [
            'title' => 'Editar formato de plaqueteo',
            'registro' => $registro,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FormatoPlaqueteo::find($id);
        Evidencia::verificarAcceso($registro);

        $fechaPlaqueteo = trim((string) $request->input('fecha_plaqueteo'));
        $funcionario = trim((string) $request->input('funcionario_asistio'));

        if ($fechaPlaqueteo === '' || $funcionario === '') {
            Session::flash('error', 'Indica la fecha del plaqueteo y el funcionario que asistió.');
            header('Location: ' . Url::to("/formatos-plaqueteo/{$id}/editar"));
            exit;
        }

        try {
            $archivoPath = $registro['archivo_path'];
            $nuevoArchivo = Uploader::storePdf($_FILES['archivo'] ?? [], 'plaqueteo');
            if ($nuevoArchivo !== null) {
                Evidencia::eliminarArchivoFisico($archivoPath);
                $archivoPath = $nuevoArchivo;
            }

            FormatoPlaqueteo::actualizar($id, [
                'fecha_plaqueteo' => $fechaPlaqueteo,
                'funcionario_asistio' => $funcionario,
                'archivo_path' => $archivoPath,
            ]);

            Session::flash('ok', 'Registro actualizado.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/formatos-plaqueteo/{$id}/editar"));
            exit;
        }

        header('Location: ' . Url::to('/formatos-plaqueteo/historial'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FormatoPlaqueteo::find($id);
        Evidencia::verificarAcceso($registro);

        Evidencia::eliminarArchivoFisico($registro['archivo_path']);
        FormatoPlaqueteo::eliminar($id);

        Session::flash('ok', 'Registro eliminado.');
        header('Location: ' . Url::to('/formatos-plaqueteo/historial'));
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
            header('Location: ' . Url::to('/formatos-plaqueteo'));
            exit;
        }
    }
}
