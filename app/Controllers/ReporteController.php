<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\LimiteIntentos;
use App\Models\Auditoria;
use App\Models\Institucion;
use App\Services\ExportacionInstitucion;
use App\Services\ReporteService;

final class ReporteController
{
    public function index(): void
    {
        View::layout('partials/layout', 'reportes/index', [
            'title' => 'Reportes',
            'puedeExportarTodo' => $this->puedeExportarTodo(),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function carteraXlsx(): void
    {
        ReporteService::enviarXlsx(ReporteService::carteraBienes($this->institucionAExportar()), 'cartera_bienes');
    }

    public function carteraCsv(): void
    {
        ReporteService::enviarCsv(ReporteService::carteraBienes($this->institucionAExportar()), 'cartera_bienes');
    }

    public function reintegrosXlsx(): void
    {
        ReporteService::enviarXlsx(ReporteService::planillaReintegros($this->institucionAExportar()), 'planilla_reintegros');
    }

    public function reintegrosHistorialXlsx(): void
    {
        ReporteService::enviarXlsx(ReporteService::historialReintegros($this->institucionAExportar()), 'historial_reintegros');
    }

    /**
     * "Descargar toda la información": ZIP con un CSV por tipo de dato de la institución y sus
     * sedes y, con ?archivos=1, sus fotos y documentos (ver ExportacionInstitucion). Solo el
     * rector (de su institución) y el superusuario (de la que elija): incluye datos personales
     * de todos los usuarios y la auditoría.
     */
    public function exportacionCompleta(): void
    {
        if (!$this->puedeExportarTodo()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $institucionId = $this->institucionAExportar();
        if ($institucionId === null || Institucion::find($institucionId) === null) {
            Session::flash('error', 'Elige la institución que quieres descargar.');
            header('Location: ' . Url::to('/reportes'));
            exit;
        }

        // Con fotos puede pesar cientos de MB: máximo MAX_EXPORTACIONES_POR_HORA por usuario.
        $clave = 'usuario-' . (int) Auth::id();
        if (LimiteIntentos::sobreClave('exportacion', $clave, 60) >= self::MAX_EXPORTACIONES_POR_HORA) {
            Session::flash('error', 'Ya descargaste la información varias veces en la última hora. Intenta más tarde.');
            header('Location: ' . Url::to('/reportes'));
            exit;
        }
        LimiteIntentos::registrar('exportacion', $clave);

        $conArchivos = ($_GET['archivos'] ?? '') === '1';
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        @set_time_limit(0);

        $resultado = ExportacionInstitucion::generar($institucionId, $conArchivos, (string) Auth::nombreCompleto(), $config['storage_path'] . '/tmp');
        Auditoria::registrar((int) Auth::id(), $institucionId, 'exportar_todo', 'institucion', $institucionId, null,
            ['con_archivos' => $conArchivos, 'archivos' => $resultado['archivos']] + $resultado['resumen']);

        // Se liberan la sesión y la conexión antes de enviar un archivo que puede ser grande.
        session_write_close();
        Database::desconectar();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $resultado['nombre'] . '"');
        header('Content-Length: ' . (string) filesize($resultado['ruta']));
        header('Cache-Control: no-store');
        // Se borra aunque el usuario corte la descarga a la mitad.
        register_shutdown_function(static fn () => @unlink($resultado['ruta']));
        readfile($resultado['ruta']);
        exit;
    }

    private const MAX_EXPORTACIONES_POR_HORA = 10;

    private function puedeExportarTodo(): bool
    {
        return Auth::esSuperusuario() || Auth::rol() === 'rector';
    }

    private function institucionAExportar(): ?int
    {
        if (Auth::esSuperusuario()) {
            $solicitada = $_GET['institucion'] ?? '';

            return $solicitada !== '' ? (int) $solicitada : null;
        }

        return Auth::institucionId();
    }
}
