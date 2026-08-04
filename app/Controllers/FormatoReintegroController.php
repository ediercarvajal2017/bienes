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
use App\Models\FormatoReintegro;
use App\Models\Institucion;

final class FormatoReintegroController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function formulario(): void
    {
        View::layout('partials/layout', 'formatos_reintegro/formulario', [
            'title' => 'Formatos de reintegro',
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
        $fechaReintegro = trim((string) $request->input('fecha_reintegro'));
        $descripcion = trim((string) $request->input('descripcion')) ?: null;

        if ($institucionId === 0) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/formatos-reintegro'));
            exit;
        }

        if ($fechaReintegro === '') {
            Session::flash('error', 'Indica la fecha del reintegro.');
            header('Location: ' . Url::to('/formatos-reintegro'));
            exit;
        }

        try {
            $archivoPath = Uploader::storePdf($_FILES['archivo'] ?? [], 'reintegros');
            if ($archivoPath === null) {
                Session::flash('error', 'Adjunta el formato de reintegro en PDF.');
                header('Location: ' . Url::to('/formatos-reintegro'));
                exit;
            }

            FormatoReintegro::create([
                'institucion_id' => $institucionId,
                'fecha_reintegro' => $fechaReintegro,
                'descripcion' => $descripcion,
                'archivo_path' => $archivoPath,
                'registrado_por' => Auth::id(),
            ]);

            Session::flash('ok', 'Formato de reintegro guardado en la biblioteca de evidencia.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        header('Location: ' . Url::to('/formatos-reintegro'));
        exit;
    }

    public function historial(): void
    {
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = FormatoReintegro::contarListado($institucionId);

        View::layout('partials/layout', 'formatos_reintegro/historial', [
            'title' => 'Histórico de formatos de reintegro',
            'formatos' => FormatoReintegro::listar($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    public function formularioEditar(string $id): void
    {
        $registro = FormatoReintegro::find((int) $id);
        Evidencia::verificarAcceso($registro);

        View::layout('partials/layout', 'formatos_reintegro/editar', [
            'title' => 'Editar formato de reintegro',
            'registro' => $registro,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FormatoReintegro::find($id);
        Evidencia::verificarAcceso($registro);

        $fechaReintegro = trim((string) $request->input('fecha_reintegro'));
        $descripcion = trim((string) $request->input('descripcion')) ?: null;

        if ($fechaReintegro === '') {
            Session::flash('error', 'Indica la fecha del reintegro.');
            header('Location: ' . Url::to("/formatos-reintegro/{$id}/editar"));
            exit;
        }

        try {
            $archivoPath = $registro['archivo_path'];
            $nuevoArchivo = Uploader::storePdf($_FILES['archivo'] ?? [], 'reintegros');
            if ($nuevoArchivo !== null) {
                Evidencia::eliminarArchivoFisico($archivoPath);
                $archivoPath = $nuevoArchivo;
            }

            FormatoReintegro::actualizar($id, [
                'fecha_reintegro' => $fechaReintegro,
                'descripcion' => $descripcion,
                'archivo_path' => $archivoPath,
            ]);

            Session::flash('ok', 'Registro actualizado.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/formatos-reintegro/{$id}/editar"));
            exit;
        }

        header('Location: ' . Url::to('/formatos-reintegro/historial'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = FormatoReintegro::find($id);
        Evidencia::verificarAcceso($registro);

        FormatoReintegro::eliminar($id, Auth::id());
        Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'eliminar', 'formato_reintegro', $id, $registro);

        Session::flash('ok', 'Registro enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
        header('Location: ' . Url::to('/formatos-reintegro/historial'));
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
            header('Location: ' . Url::to('/formatos-reintegro'));
            exit;
        }
    }
}
