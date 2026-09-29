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
use App\Models\Usuario;
use App\Services\ExportacionInstitucion;
use App\Services\ReportesControl;
use App\Services\ReporteService;

final class ReporteController
{
    public function index(): void
    {
        View::layout('partials/layout', 'reportes/index', [
            'title' => 'Reportes',
            'puedeExportarTodo' => $this->puedeExportarTodo(),
            'funcionarios' => $this->puedeExportarTodo() ? $this->funcionariosDelAlcance() : [],
            'error' => Session::pullFlash('error'),
        ]);
    }

    /**
     * Reportes de actividad de los funcionarios en Excel (ver ReportesControl): resumen,
     * registros nuevos, actualizados, movimientos o todo junto, de un período y, si se elige,
     * de un solo funcionario. Rector (su institución y sedes) y superusuario.
     */
    public function actividad(): void
    {
        if (!$this->puedeExportarTodo()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $tipo = (string) ($_GET['tipo'] ?? 'resumen');
        $tipo = isset(ReportesControl::TIPOS_ACTIVIDAD[$tipo]) ? $tipo : 'resumen';
        $periodo = ReportesControl::periodo((string) ($_GET['periodo'] ?? 'hoy'), (string) ($_GET['desde'] ?? ''), (string) ($_GET['hasta'] ?? ''));
        [$ids, $alcance] = $this->alcance();

        // El funcionario debe ser del alcance (un rector no puede pedir el de otra institución).
        $usuarioId = (int) ($_GET['usuario'] ?? 0) ?: null;
        if ($usuarioId !== null) {
            $usuario = Usuario::find($usuarioId);
            if ($usuario === null || ($ids !== null && !in_array((int) $usuario['institucion_id'], $ids, true))) {
                Session::flash('error', 'Ese funcionario no pertenece a la institución elegida.');
                header('Location: ' . Url::to('/reportes'));
                exit;
            }
        }

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        @set_time_limit(0);
        $resultado = ReportesControl::actividadXlsx($tipo, $ids, $usuarioId, $periodo, $alcance,
            (string) Auth::nombreCompleto(), $config['storage_path'] . '/tmp');

        $this->enviarArchivo($resultado['ruta'], $resultado['nombre'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
        $this->enviarArchivo($resultado['ruta'], $resultado['nombre'], 'application/zip');
    }

    /** Envía un archivo temporal para descargar y lo borra (aunque se corte la descarga). */
    private function enviarArchivo(string $ruta, string $nombre, string $tipoContenido): never
    {
        // Se liberan la sesión y la conexión antes de enviar un archivo que puede ser grande.
        session_write_close();
        Database::desconectar();

        header('Content-Type: ' . $tipoContenido);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . (string) filesize($ruta));
        header('Cache-Control: no-store');
        register_shutdown_function(static fn () => @unlink($ruta));
        readfile($ruta);
        exit;
    }

    /**
     * Instituciones que abarcan los reportes de control y su nombre para el Excel: el rector,
     * la suya con sus sedes; el superusuario, la elegida (con sus sedes) o todas (null).
     *
     * @return array{0: list<int>|null, 1: string}
     */
    private function alcance(): array
    {
        $institucionId = Auth::esSuperusuario() ? (int) ($_GET['institucion'] ?? 0) : (int) Auth::institucionId();
        $familia = $institucionId > 0 ? Institucion::familiaDe($institucionId) : [];
        if ($familia === []) {
            return [Auth::esSuperusuario() ? null : [], 'Todas las instituciones'];
        }

        return [
            array_values(array_map(static fn (array $i): int => (int) $i['id'], $familia)),
            $familia[0]['nombre'] . (count($familia) > 1 ? ' y sus sedes' : ''),
        ];
    }

    /** @return list<array{id: int, nombre: string}> Funcionarios para el filtro de los reportes de actividad. */
    private function funcionariosDelAlcance(): array
    {
        $ids = Auth::esSuperusuario()
            ? null
            : array_map(static fn (array $i): int => (int) $i['id'], Institucion::familiaDe((int) Auth::institucionId()));
        $lista = [];
        foreach (Usuario::listarTodos() as $u) {
            if ($ids !== null && !in_array((int) $u['institucion_id'], $ids, true)) {
                continue;
            }
            $nombre = trim($u['nombres'] . ' ' . $u['apellidos']);
            $lista[] = ['id' => (int) $u['id'], 'nombre' => Auth::esSuperusuario() ? "{$nombre} · {$u['institucion_nombre']}" : $nombre];
        }

        return $lista;
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
