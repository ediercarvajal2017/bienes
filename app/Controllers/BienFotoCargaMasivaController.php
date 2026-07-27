<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Services\FotoMasivaService;

final class BienFotoCargaMasivaController
{
    private const TAMANO_MAXIMO_ZIP = 200 * 1024 * 1024;

    public function index(): void
    {
        View::layout('partials/layout', 'bienes/carga_masiva_fotos', [
            'title' => 'Carga masiva de fotos',
            'resultado' => null,
            'error' => Session::pullFlash('error'),
        ]);
    }

    /**
     * Procesa el .zip en la misma petición (sin persistirlo ni pasar por un paso de
     * "confirmar"): el emparejamiento por nombre de archivo = código del bien es
     * determinístico y no sobrescribe fotos existentes, así que es seguro reintentar
     * o repetir el envío si algo falla a mitad de camino.
     */
    public function subir(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        $archivo = $request->file('archivo');

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            Session::flash('error', 'Selecciona un archivo .zip válido.');
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        if (strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION)) !== 'zip') {
            Session::flash('error', 'El archivo debe tener extensión .zip.');
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        if ($archivo['size'] > self::TAMANO_MAXIMO_ZIP) {
            Session::flash('error', 'El .zip supera el tamaño máximo permitido (200 MB). Divídelo en lotes más pequeños y sube varios.');
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        $institucionId = Auth::institucionId();

        try {
            $resultado = FotoMasivaService::procesar($archivo['tmp_name'], $institucionId);
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo procesar el .zip: ' . $e->getMessage());
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        View::layout('partials/layout', 'bienes/carga_masiva_fotos', [
            'title' => 'Carga masiva de fotos',
            'resultado' => $resultado,
            'error' => null,
        ]);
    }
}
