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
use App\Services\EspacioCargaMasivaService;

final class EspacioCargaMasivaController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = CargaMasiva::contarListado($institucionId, 'espacios');

        View::layout('partials/layout', 'espacios/carga_index', [
            'title' => 'Carga masiva de espacios',
            'cargas' => CargaMasiva::listar($institucionId, 'espacios', null, $pagina, $porPagina),
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
        EspacioCargaMasivaService::enviarPlantilla();
    }

    public function subir(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/espacios/carga-masiva'));
            exit;
        }

        $archivo = $request->file('archivo');

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Selecciona un archivo .xlsx válido.');
            header('Location: ' . Url::to('/espacios/carga-masiva'));
            exit;
        }

        $institucionId = Auth::institucionId();

        try {
            $rutaRelativa = $this->guardarArchivo($archivo);
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $filas = EspacioCargaMasivaService::analizar($config['storage_path'] . '/uploads/' . $rutaRelativa, $institucionId);
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo leer el archivo: ' . $e->getMessage());
            header('Location: ' . Url::to('/espacios/carga-masiva'));
            exit;
        }

        $totales = ['nuevo' => 0, 'modificado' => 0, 'sin_cambios' => 0, 'invalido' => 0];
        foreach ($filas as $fila) {
            $totales[$fila['tipo']]++;
        }

        $id = CargaMasiva::crear([
            'institucion_id' => $institucionId,
            'tipo' => 'espacios',
            'usuario_id' => Auth::id(),
            'archivo_path' => $rutaRelativa,
            'total_filas' => count($filas),
            'nuevos' => $totales['nuevo'],
            'modificados' => $totales['modificado'],
            'sin_cambios' => $totales['sin_cambios'],
            'resultado_diff_json' => json_encode($filas, JSON_UNESCAPED_UNICODE),
        ]);

        header('Location: ' . Url::to("/espacios/carga-masiva/{$id}"));
        exit;
    }

    public function mostrar(string $id): void
    {
        $carga = $this->cargaAccesible((int) $id);

        View::layout('partials/layout', 'espacios/carga_mostrar', [
            'title' => 'Resultado de la carga',
            'carga' => $carga,
            'filas' => json_decode($carga['resultado_diff_json'], true) ?? [],
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function confirmar(string $id): void
    {
        $id = (int) $id;
        $carga = $this->cargaAccesible($id);

        $request = new Request();
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/espacios/carga-masiva/{$id}"));
            exit;
        }

        if ((int) $carga['aplicada'] === 1) {
            Session::flash('error', 'Esta carga ya fue aplicada anteriormente.');
            header('Location: ' . Url::to("/espacios/carga-masiva/{$id}"));
            exit;
        }

        $filas = json_decode($carga['resultado_diff_json'], true) ?? [];
        $omitidas = EspacioCargaMasivaService::aplicar($filas, (int) $carga['institucion_id']);
        CargaMasiva::marcarAplicada($id);

        $invalidas = count(array_filter($filas, static fn (array $f): bool => $f['tipo'] === 'invalido'));
        $this->flashResultado($invalidas, $omitidas);

        header('Location: ' . Url::to("/espacios/carga-masiva/{$id}"));
        exit;
    }

    /**
     * Antes siempre decía "aplicada correctamente" aunque el archivo trajera filas
     * inválidas que nunca se llegaron a intentar guardar (analizar() las descarta desde
     * el análisis, aplicar() ni las toca) — el usuario veía un mensaje de éxito sin
     * enterarse de que parte del archivo no entró. Ahora, si algo quedó sin aplicar
     * (inválidas o chocadas), el mensaje lo dice explícitamente y se muestra en rojo.
     */
    private function flashResultado(int $invalidas, int $omitidas): void
    {
        if ($invalidas === 0 && $omitidas === 0) {
            Session::flash('ok', 'Carga masiva aplicada correctamente.');

            return;
        }

        $partes = [];
        if ($invalidas > 0) {
            $partes[] = $invalidas === 1
                ? '1 fila no se cargó por tener un error (revisa el detalle abajo)'
                : "{$invalidas} filas no se cargaron por tener errores (revisa el detalle abajo)";
        }
        if ($omitidas > 0) {
            $partes[] = $omitidas === 1
                ? '1 fila se omitió por un choque de número de espacio detectado al guardar'
                : "{$omitidas} filas se omitieron por un choque de número de espacio detectado al guardar";
        }

        Session::flash('error', 'Carga masiva aplicada parcialmente: ' . implode('; ', $partes) . '.');
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

        if (!$carga || $carga['tipo'] !== 'espacios') {
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
