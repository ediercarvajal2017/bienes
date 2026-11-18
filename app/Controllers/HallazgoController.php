<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Uploader;
use App\Models\Auditoria;
use App\Models\Espacio;
use App\Models\Hallazgo;
use App\Models\JornadaVerificacion;

final class HallazgoController
{
    public function crear(): void
    {
        $institucionId = Auth::institucionId();

        if ($institucionId === null || !JornadaVerificacion::activaPara($institucionId)) {
            Aviso::enviar(Aviso::error('No se puede reportar el hallazgo', 'No hay una jornada de verificación en progreso para tu institución.',
                'Los hallazgos se reportan durante una jornada: pídele al rector o al secretario que la inicie.'));
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        $espacios = $this->espaciosPermitidos($institucionId);

        if (empty($espacios)) {
            Aviso::enviar(Aviso::error('No se puede reportar el hallazgo', 'No eres responsable de ningún espacio, y el hallazgo se reporta en uno de tus espacios.',
                'Pídele al rector o al secretario que te asigne como responsable del espacio donde está el bien.'));
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        View::layout('partials/layout', 'hallazgos/crear', [
            'title' => 'Reportar bien no registrado',
            'espacios' => $espacios,
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
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(true));
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
            Aviso::enviar(Aviso::error('No se puede reportar el hallazgo', 'No hay una jornada de verificación en progreso para tu institución.',
                'Los hallazgos se reportan durante una jornada: pídele al rector o al secretario que la inicie.'));
            header('Location: ' . Url::to('/escanear'));
            exit;
        }

        $espaciosPermitidos = array_column($this->espaciosPermitidos($institucionId), 'id');
        if (!in_array($datos['espacio_id'], $espaciosPermitidos, true)) {
            Aviso::enviar(Aviso::error('No se reportó el hallazgo', 'No elegiste un espacio de la lista.',
                'Elige el espacio donde encontraste el bien y vuelve a enviar.', null, ['espacio_id' => 'Elige el espacio.']));
            Session::flashOld($datos);
            header('Location: ' . Url::to('/hallazgos/crear'));
            exit;
        }

        if ($datos['descripcion'] === '') {
            Aviso::enviar(Aviso::error('No se reportó el hallazgo', 'Falta describir el bien encontrado.',
                'Escribe qué es (por ejemplo «Silla plástica azul sin placa») y vuelve a enviar.', null, ['descripcion' => 'Describe el bien.']));
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
            Aviso::enviar(Aviso::error('No se reportó el hallazgo', 'La foto no se pudo guardar: ' . $e->getMessage(),
                'Toma o elige otra foto y vuelve a enviar; la foto es opcional.', null, ['foto' => 'Elige otra foto.']));
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

        Aviso::enviar(Aviso::exito('Hallazgo reportado.', 'Quien administra la verificación lo revisará y decidirá si se registra como bien.'));
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
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to('/verificaciones/' . $hallazgo['jornada_id']) . '#seccion-hallazgos');
            exit;
        }

        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        Hallazgo::marcarDescartado((int) $hallazgo['id'], (int) Auth::id(), $observaciones);
        Auditoria::registrar(Auth::id(), (int) $hallazgo['institucion_id'], 'descartar', 'hallazgo', (int) $hallazgo['id'],
            ['estado' => 'pendiente'], ['estado' => 'descartado', 'observaciones' => $observaciones]);

        Aviso::enviar(Aviso::exito('Hallazgo descartado.', 'Queda en la lista como descartado, con tu observación.'));
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
