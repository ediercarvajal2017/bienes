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
use App\Models\CarteraEnvio;
use App\Models\Institucion;

final class CarteraController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function formulario(): void
    {
        View::layout('partials/layout', 'cartera/formulario', [
            'title' => 'Cartera enviada a la Alcaldía',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $this->institucionSeleccionada(),
            'nombreFuncionarioPorDefecto' => Auth::nombreCompleto(),
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $institucionId = $this->institucionSeleccionada();
        $correoRemitente = trim((string) $request->input('correo_remitente'));
        $nombreFuncionario = trim((string) $request->input('nombre_funcionario'));
        $fechaEnvio = trim((string) $request->input('fecha_envio'));

        if ($institucionId === 0) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/cartera/enviar'));
            exit;
        }

        if ($nombreFuncionario === '' || !filter_var($correoRemitente, FILTER_VALIDATE_EMAIL) || $fechaEnvio === '') {
            Session::flash('error', 'Indica el correo del remitente, el funcionario y la fecha de envío.');
            header('Location: ' . Url::to('/cartera/enviar'));
            exit;
        }

        try {
            $archivoPath = Uploader::storeExcel($_FILES['archivo'] ?? [], 'cartera');
            if ($archivoPath === null) {
                Session::flash('error', 'Adjunta el archivo de la cartera enviada.');
                header('Location: ' . Url::to('/cartera/enviar'));
                exit;
            }

            CarteraEnvio::create([
                'institucion_id' => $institucionId,
                'archivo_path' => $archivoPath,
                'correo_remitente' => $correoRemitente,
                'nombre_funcionario' => $nombreFuncionario,
                'fecha_envio' => $fechaEnvio,
                'registrado_por' => Auth::id(),
            ]);

            Session::flash('ok', 'Registro guardado en la biblioteca de evidencia.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        header('Location: ' . Url::to('/cartera/enviar'));
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
        $total = CarteraEnvio::contarListado($institucionId);

        View::layout('partials/layout', 'cartera/historial', [
            'title' => 'Histórico de cartera enviada',
            'envios' => CarteraEnvio::listar($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    public function formularioEditar(string $id): void
    {
        $registro = CarteraEnvio::find((int) $id);
        Evidencia::verificarAcceso($registro);

        View::layout('partials/layout', 'cartera/editar', [
            'title' => 'Editar registro de cartera',
            'registro' => $registro,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = CarteraEnvio::find($id);
        Evidencia::verificarAcceso($registro);

        $correoRemitente = trim((string) $request->input('correo_remitente'));
        $nombreFuncionario = trim((string) $request->input('nombre_funcionario'));
        $fechaEnvio = trim((string) $request->input('fecha_envio'));

        if ($nombreFuncionario === '' || !filter_var($correoRemitente, FILTER_VALIDATE_EMAIL) || $fechaEnvio === '') {
            Session::flash('error', 'Indica el correo del remitente, el funcionario y la fecha de envío.');
            header('Location: ' . Url::to("/cartera/{$id}/editar"));
            exit;
        }

        try {
            $archivoPath = $registro['archivo_path'];
            $nuevoArchivo = Uploader::storeExcel($_FILES['archivo'] ?? [], 'cartera');
            if ($nuevoArchivo !== null) {
                Evidencia::eliminarArchivoFisico($archivoPath);
                $archivoPath = $nuevoArchivo;
            }

            CarteraEnvio::actualizar($id, [
                'correo_remitente' => $correoRemitente,
                'nombre_funcionario' => $nombreFuncionario,
                'fecha_envio' => $fechaEnvio,
                'archivo_path' => $archivoPath,
            ]);

            Session::flash('ok', 'Registro actualizado.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/cartera/{$id}/editar"));
            exit;
        }

        header('Location: ' . Url::to('/cartera/enviados'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = CarteraEnvio::find($id);
        Evidencia::verificarAcceso($registro);

        CarteraEnvio::eliminar($id, Auth::id());
        Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'eliminar', 'cartera_envio', $id, $registro);

        Session::flash('ok', 'Registro enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
        header('Location: ' . Url::to('/cartera/enviados'));
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
            header('Location: ' . Url::to('/cartera/enviar'));
            exit;
        }
    }
}
