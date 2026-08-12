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
use App\Models\CargaMasiva;
use App\Services\UsuarioCargaMasivaService;

final class UsuarioCargaMasivaController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = CargaMasiva::contarListado($institucionId, 'usuarios');

        View::layout('partials/layout', 'usuarios/carga_index', [
            'title' => 'Carga masiva de usuarios',
            'cargas' => CargaMasiva::listar($institucionId, 'usuarios', null, $pagina, $porPagina),
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
        UsuarioCargaMasivaService::enviarPlantilla();
    }

    public function subir(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/usuarios/carga-masiva'));
            exit;
        }

        $archivo = $request->file('archivo');

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Selecciona un archivo .xlsx válido.');
            header('Location: ' . Url::to('/usuarios/carga-masiva'));
            exit;
        }

        $institucionId = Auth::institucionId();

        try {
            $rutaRelativa = Uploader::storeExcel($archivo, 'cargas');
            if ($rutaRelativa === null) {
                Session::flash('error', 'Selecciona un archivo .xlsx válido.');
                header('Location: ' . Url::to('/usuarios/carga-masiva'));
                exit;
            }
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $filas = UsuarioCargaMasivaService::analizar(
                $config['storage_path'] . '/uploads/' . $rutaRelativa,
                $institucionId,
                Auth::esSuperusuario()
            );
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo leer el archivo: ' . $e->getMessage());
            header('Location: ' . Url::to('/usuarios/carga-masiva'));
            exit;
        }

        $totales = ['nuevo' => 0, 'modificado' => 0, 'sin_cambios' => 0, 'invalido' => 0];
        foreach ($filas as $fila) {
            $totales[$fila['tipo']]++;
        }

        $id = CargaMasiva::crear([
            'institucion_id' => $institucionId,
            'tipo' => 'usuarios',
            'usuario_id' => Auth::id(),
            'archivo_path' => $rutaRelativa,
            'total_filas' => count($filas),
            'nuevos' => $totales['nuevo'],
            'modificados' => $totales['modificado'],
            'sin_cambios' => $totales['sin_cambios'],
            'resultado_diff_json' => json_encode($filas, JSON_UNESCAPED_UNICODE),
        ]);

        header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
        exit;
    }

    public function mostrar(string $id): void
    {
        $carga = $this->cargaAccesible((int) $id);

        View::layout('partials/layout', 'usuarios/carga_mostrar', [
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
            header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
            exit;
        }

        if ((int) $carga['aplicada'] === 1) {
            Session::flash('error', 'Esta carga ya fue aplicada anteriormente.');
            header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
            exit;
        }

        $filas = json_decode($carga['resultado_diff_json'], true) ?? [];
        $omitidas = UsuarioCargaMasivaService::aplicar($filas, (int) $carga['institucion_id']);
        CargaMasiva::marcarAplicada($id);

        $nota = ' Los usuarios nuevos deben entrar a "¿Olvidaste tu contraseña?" con su correo registrado para activarse la primera vez.';
        Session::flash('ok', $omitidas > 0
            ? "Carga masiva aplicada. {$omitidas} fila(s) se omitieron por un choque de documento o correo detectado al guardar.{$nota}"
            : 'Carga masiva aplicada correctamente.' . $nota);
        header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
        exit;
    }

    private function cargaAccesible(int $id): array
    {
        $carga = CargaMasiva::find($id);

        if (!$carga || $carga['tipo'] !== 'usuarios') {
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
