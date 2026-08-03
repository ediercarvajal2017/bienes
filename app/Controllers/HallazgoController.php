<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Uploader;
use App\Models\Espacio;
use App\Models\Hallazgo;
use App\Models\JornadaVerificacion;

final class HallazgoController
{
    public function crear(): void
    {
        $institucionId = Auth::institucionId();

        if ($institucionId === null || !JornadaVerificacion::activaPara($institucionId)) {
            Session::flash('error', 'No hay una jornada de verificación en progreso para tu institución.');
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        $espacios = $this->espaciosPermitidos($institucionId);

        if (empty($espacios)) {
            Session::flash('error', 'No tienes espacios asignados donde reportar un hallazgo.');
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        View::layout('partials/layout', 'hallazgos/crear', [
            'title' => 'Reportar bien no registrado',
            'espacios' => $espacios,
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $institucionId = Auth::institucionId();

        $datos = [
            'espacio_id' => (int) $request->input('espacio_id'),
            'descripcion' => trim((string) $request->input('descripcion')),
        ];

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            Session::flashOld($datos);
            header('Location: ' . Url::to('/hallazgos/crear'));
            exit;
        }

        if ($institucionId === null) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $jornada = JornadaVerificacion::activaPara($institucionId);
        if (!$jornada) {
            Session::flash('error', 'No hay una jornada de verificación en progreso para tu institución.');
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        $espaciosPermitidos = array_column($this->espaciosPermitidos($institucionId), 'id');
        if (!in_array($datos['espacio_id'], $espaciosPermitidos, true)) {
            Session::flash('error', 'Selecciona un espacio válido de la lista.');
            Session::flashOld($datos);
            header('Location: ' . Url::to('/hallazgos/crear'));
            exit;
        }

        if ($datos['descripcion'] === '') {
            Session::flash('error', 'Describe brevemente el bien encontrado.');
            Session::flashOld($datos);
            header('Location: ' . Url::to('/hallazgos/crear'));
            exit;
        }

        $fotoPath = null;
        try {
            if ($archivo = $request->file('foto')) {
                $fotoPath = Uploader::storeImage($archivo, 'hallazgos');
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flashOld($datos);
            header('Location: ' . Url::to('/hallazgos/crear'));
            exit;
        }

        Hallazgo::crear([
            'jornada_id' => (int) $jornada['id'],
            'institucion_id' => $institucionId,
            'espacio_id' => $datos['espacio_id'],
            'descripcion' => $datos['descripcion'],
            'foto_path' => $fotoPath,
            'reportado_por' => Auth::id(),
        ]);

        Session::flash('ok', 'Hallazgo reportado. Quien administre la verificación lo revisará.');
        header('Location: ' . Url::to('/escanear'));
        exit;
    }

    public function descartar(string $id): void
    {
        $request = new Request();
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $hallazgo = Hallazgo::pendienteAccesible((int) $id, $institucionId);

        if (!$hallazgo) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/verificaciones/' . $hallazgo['jornada_id']) . '#seccion-hallazgos');
            exit;
        }

        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        Hallazgo::marcarDescartado((int) $hallazgo['id'], (int) Auth::id(), $observaciones);

        Session::flash('ok', 'Hallazgo descartado.');
        header('Location: ' . Url::to('/verificaciones/' . $hallazgo['jornada_id']) . '#seccion-hallazgos');
        exit;
    }

    /**
     * El docente solo puede reportar en sus propios espacios (donde figura como
     * responsable); el resto del personal que administra verificaciones puede reportar
     * en cualquier espacio de la institución, por si encuentra algo fuera de su recorrido.
     */
    private function espaciosPermitidos(int $institucionId): array
    {
        return Auth::rol() === 'docente'
            ? Espacio::propiosDe((int) Auth::id(), $institucionId)
            : Espacio::listadoParaSelect($institucionId);
    }
}
