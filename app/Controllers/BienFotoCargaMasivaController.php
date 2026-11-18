<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
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
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        $archivo = $request->file('archivo');

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            Aviso::enviar(Aviso::error('No se subieron las fotos', 'No elegiste un archivo, o no se pudo recibir completo.',
                'Elige el .zip con las fotos (cada una nombrada con el código del bien, por ejemplo PA-0001.jpg) y pulsa «Subir .zip».'));
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        if (strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION)) !== 'zip') {
            Aviso::enviar(Aviso::error('No se subieron las fotos', 'El archivo no es un .zip.',
                'Comprime las fotos en un archivo .zip (en Windows: clic derecho › Enviar a › Carpeta comprimida) y súbelo.'));
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        if ($archivo['size'] > self::TAMANO_MAXIMO_ZIP) {
            Aviso::enviar(Aviso::error('No se subieron las fotos', 'El .zip pesa más de 200 MB, el máximo permitido.',
                'Divide las fotos en varios .zip más pequeños y súbelos uno por uno.'));
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        $institucionId = (int) Auth::institucionId();

        try {
            $resultado = FotoMasivaService::procesar($archivo['tmp_name'], $institucionId);
        } catch (\Throwable $e) {
            Aviso::enviar(Aviso::error('No se subieron las fotos', 'No se pudo abrir el .zip: ' . $e->getMessage(),
                'Revisa que el archivo no esté dañado (ábrelo en tu computador) y vuelve a subirlo.'));
            header('Location: ' . Url::to('/bienes/carga-masiva-fotos'));
            exit;
        }

        View::layout('partials/layout', 'bienes/carga_masiva_fotos', [
            'title' => 'Carga masiva de fotos',
            'resultado' => $resultado,
        ]);
    }
}
