<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Helpers\Uploader;
use App\Models\Auditoria;
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
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to('/usuarios/carga-masiva'));
            exit;
        }

        $archivo = $request->file('archivo');

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            Aviso::enviar(\App\Helpers\Mensajes::excelNoValido('/usuarios/carga-masiva/plantilla.xlsx'));
            header('Location: ' . Url::to('/usuarios/carga-masiva'));
            exit;
        }

        $institucionId = (int) Auth::institucionId();

        try {
            $rutaRelativa = Uploader::storeExcel($archivo, 'cargas');
            if ($rutaRelativa === null) {
                Aviso::enviar(\App\Helpers\Mensajes::excelNoValido('/usuarios/carga-masiva/plantilla.xlsx'));
                header('Location: ' . Url::to('/usuarios/carga-masiva'));
                exit;
            }
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $filas = UsuarioCargaMasivaService::analizar(
                $config['storage_path'] . '/uploads/' . $rutaRelativa,
                $institucionId,
                Auth::esSuperusuario()
            );
        } catch (\RuntimeException $e) {
            Aviso::enviar(\App\Helpers\Mensajes::excelConProblema($e->getMessage(), '/usuarios/carga-masiva/plantilla.xlsx'));
            header('Location: ' . Url::to('/usuarios/carga-masiva'));
            exit;
        } catch (\Throwable $e) {
            $incidente = ErrorHandler::reportar($e, __METHOD__);
            Aviso::enviar(\App\Helpers\Mensajes::excelIlegible($incidente, '/usuarios/carga-masiva/plantilla.xlsx'));
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
        ]);
    }

    public function confirmar(string $id): void
    {
        $id = (int) $id;
        $carga = $this->cargaAccesible($id);

        $request = new Request();
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
            exit;
        }

        if ((int) $carga['aplicada'] === 1) {
            Aviso::enviar(Aviso::error('No se aplicó la carga', 'Esta carga ya se había aplicado, así que sus cambios ya están en MIA.',
                'Revisa el resultado más abajo. Para cargar más datos, sube un archivo nuevo.'));
            header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
            exit;
        }

        $filas = json_decode($carga['resultado_diff_json'], true) ?? [];
        try {
            $omitidas = CargaMasiva::aplicarUnaVez($id, static function () use ($filas, $carga, $id): int {
                $omitidas = UsuarioCargaMasivaService::aplicar($filas, (int) $carga['institucion_id']);
                Auditoria::registrar(Auth::id(), (int) $carga['institucion_id'], 'aplicar', 'carga_masiva', $id, null, [
                    'tipo' => 'usuarios',
                    'filas' => count($filas),
                    'nuevos' => count(array_filter($filas, static fn (array $f): bool => $f['tipo'] === 'nuevo')),
                    'modificados' => count(array_filter($filas, static fn (array $f): bool => $f['tipo'] === 'modificado')),
                    'omitidas' => $omitidas,
                ]);

                return $omitidas;
            });
        } catch (\DomainException $e) {
            Aviso::enviar(Aviso::desdeExcepcion($e, 'No se aplicó la carga', 'Recarga la página para ver el estado de la carga.'));
            header('Location: ' . Url::to("/usuarios/carga-masiva/{$id}"));
            exit;
        }

        $nota = 'Siguiente paso: los usuarios nuevos entran por primera vez con «¿Olvidaste tu contraseña?» y su correo registrado.';
        Aviso::enviar($omitidas > 0
            ? Aviso::advertencia('Carga masiva aplicada.', ($omitidas === 1 ? '1 fila se omitió' : "{$omitidas} filas se omitieron")
                . ' porque su documento o correo ya estaba en uso al guardar.', 'Revisa esas filas, corrígelas en el Excel y súbelo de nuevo. ' . $nota)
            : Aviso::exito('Carga masiva aplicada correctamente.', $nota));
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
