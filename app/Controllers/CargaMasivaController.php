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
use App\Models\CargaMasiva;
use App\Services\CargaMasivaService;

final class CargaMasivaController
{
    private const POR_PAGINA_DEFECTO = 25;
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

        $total = CargaMasiva::contarListado($institucionId, 'bienes', $terminoBusqueda);

        View::layout('partials/layout', 'cargas/index', [
            'title' => 'Carga masiva de bienes',
            'cargas' => CargaMasiva::listar($institucionId, 'bienes', $terminoBusqueda, $pagina, $porPagina),
            'busqueda' => $busqueda,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function plantilla(): void
    {
        CargaMasivaService::enviarPlantilla();
    }

    public function subir(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/cargas-masivas'));
            exit;
        }

        $archivo = $request->file('archivo');

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Selecciona un archivo .xlsx válido.');
            header('Location: ' . Url::to('/cargas-masivas'));
            exit;
        }

        $institucionId = Auth::institucionId();

        try {
            $rutaRelativa = $this->guardarArchivo($archivo);
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $filas = CargaMasivaService::analizar($config['storage_path'] . '/uploads/' . $rutaRelativa, $institucionId);
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo leer el archivo: ' . $e->getMessage());
            header('Location: ' . Url::to('/cargas-masivas'));
            exit;
        }

        $totales = ['nuevo' => 0, 'modificado' => 0, 'sin_cambios' => 0, 'invalido' => 0];
        foreach ($filas as $fila) {
            $totales[$fila['tipo']]++;
        }

        $id = CargaMasiva::crear([
            'institucion_id' => $institucionId,
            'usuario_id' => Auth::id(),
            'archivo_path' => $rutaRelativa,
            'total_filas' => count($filas),
            'nuevos' => $totales['nuevo'],
            'modificados' => $totales['modificado'],
            'sin_cambios' => $totales['sin_cambios'],
            'resultado_diff_json' => json_encode($filas, JSON_UNESCAPED_UNICODE),
        ]);

        header('Location: ' . Url::to("/cargas-masivas/{$id}"));
        exit;
    }

    public function mostrar(string $id): void
    {
        $carga = $this->cargaAccesible((int) $id);

        View::layout('partials/layout', 'cargas/mostrar', [
            'title' => 'Resultado de la carga',
            'carga' => $carga,
            'filas' => json_decode($carga['resultado_diff_json'], true) ?? [],
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function confirmar(string $id): void
    {
        $id = (int) $id;
        $carga = $this->cargaAccesible($id);

        $request = new Request();
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/cargas-masivas/{$id}"));
            exit;
        }

        if ((int) $carga['aplicada'] === 1) {
            Session::flash('error', 'Esta carga ya fue aplicada anteriormente.');
            header('Location: ' . Url::to("/cargas-masivas/{$id}"));
            exit;
        }

        $filas = json_decode($carga['resultado_diff_json'], true) ?? [];
        $omitidas = CargaMasivaService::aplicar($filas, (int) $carga['institucion_id'], Auth::id());
        CargaMasiva::marcarAplicada($id);

        Session::flash('ok', $omitidas > 0
            ? "Carga masiva aplicada. {$omitidas} fila(s) se omitieron por un choque de código detectado al guardar."
            : 'Carga masiva aplicada correctamente.');
        header('Location: ' . Url::to("/cargas-masivas/{$id}"));
        exit;
    }

    private function guardarArchivo(array $archivo): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $subdir = 'cargas';
        $targetDir = $config['storage_path'] . '/uploads/' . $subdir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de almacenamiento.');
        }

        $nombre = bin2hex(random_bytes(16)) . '.xlsx';

        if (!move_uploaded_file($archivo['tmp_name'], $targetDir . '/' . $nombre)) {
            throw new \RuntimeException('No se pudo guardar el archivo subido.');
        }

        return $subdir . '/' . $nombre;
    }

    private function cargaAccesible(int $id): array
    {
        $carga = CargaMasiva::find($id);

        if (!$carga) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $carga['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        return $carga;
    }
}
