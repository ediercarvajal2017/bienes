<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Movimiento;

final class MovimientoController
{
    public function asignar(string $id): void
    {
        $id = (int) $id;
        $bien = $this->bienDeLaInstitucion($id);

        $request = new Request();
        $this->verificarCsrf($request, $id);

        $espacioId = (string) $request->input('espacio_id');
        $fecha = (string) $request->input('fecha_asignacion');
        $observaciones = trim((string) $request->input('observaciones')) ?: null;

        if ($espacioId === '' || $fecha === '' || !strtotime($fecha)) {
            Session::flash('error', 'Selecciona un espacio y una fecha válida para la asignación.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Asignacion::cerrarActivasDe($id);
        Asignacion::crear([
            'bien_id' => $id,
            'espacio_id' => (int) $espacioId,
            'fecha_asignacion' => $fecha,
            'observaciones' => $observaciones,
            'asignado_por' => Auth::id(),
        ]);

        if ($bien['estado'] === 'reintegrado') {
            Bien::update($id, array_merge($this->camposSinCambiar($bien), ['estado' => 'activo']));
        }

        Session::flash('ok', 'Bien asignado correctamente.');
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    public function trasladar(string $id): void
    {
        $id = (int) $id;
        $this->bienDeLaInstitucion($id);
        $asignacionActiva = $this->verificarAutoridadSobreMovimiento($id);

        $request = new Request();
        $this->verificarCsrf($request, $id);

        $fecha = (string) $request->input('fecha');
        $espacioId = (string) $request->input('espacio_destino_id');
        $observaciones = trim((string) $request->input('observaciones')) ?: null;

        if ($fecha === '' || !strtotime($fecha) || $espacioId === '') {
            Session::flash('error', 'Selecciona una fecha y un espacio de destino válidos.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Movimiento::crear([
            'bien_id' => $id,
            'tipo' => 'traslado',
            'fecha' => $fecha,
            'responsable_id' => Auth::id(),
            'espacio_origen_id' => $asignacionActiva['espacio_id'] ?? null,
            'espacio_destino_id' => (int) $espacioId,
            'destino_texto' => null,
            'observaciones' => $observaciones,
        ]);

        Asignacion::cerrarActivasDe($id);
        Asignacion::crear([
            'bien_id' => $id,
            'espacio_id' => (int) $espacioId,
            'fecha_asignacion' => $fecha,
            'observaciones' => $observaciones,
            'asignado_por' => Auth::id(),
        ]);

        Session::flash('ok', 'Traslado registrado.');
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    public function reintegrar(string $id): void
    {
        $id = (int) $id;
        $bien = $this->bienDeLaInstitucion($id);
        $asignacionActiva = $this->verificarAutoridadSobreMovimiento($id);

        $request = new Request();
        $this->verificarCsrf($request, $id);

        $fecha = (string) $request->input('fecha');
        $destino = trim((string) $request->input('destino_texto'));
        $observaciones = trim((string) $request->input('observaciones')) ?: null;

        if ($fecha === '' || !strtotime($fecha) || $destino === '') {
            Session::flash('error', 'Indica la fecha y el destino del reintegro.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Movimiento::crear([
            'bien_id' => $id,
            'tipo' => 'reintegro',
            'fecha' => $fecha,
            'responsable_id' => Auth::id(),
            'espacio_origen_id' => $asignacionActiva['espacio_id'] ?? null,
            'espacio_destino_id' => null,
            'destino_texto' => $destino,
            'observaciones' => $observaciones,
        ]);

        Asignacion::cerrarActivasDe($id);
        Bien::update($id, array_merge($this->camposSinCambiar($bien), ['estado' => 'reintegrado']));

        Session::flash('ok', 'Reintegro registrado. Cuando quieras, agrúpalo en un lote desde "Lotes de reintegro" para generar el formato.');
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    private function camposSinCambiar(array $bien): array
    {
        return [
            'codigo_identificacion' => $bien['codigo_identificacion'],
            'descripcion' => $bien['descripcion'],
            'marca' => $bien['marca'],
            'categoria_id' => $bien['categoria_id'],
            'fecha_ingreso' => $bien['fecha_ingreso'],
            'valor' => $bien['valor'],
            'tiene_factura' => $bien['tiene_factura'],
        ];
    }

    private function bienDeLaInstitucion(int $id): array
    {
        $bien = Bien::find($id);

        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $bien['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        return $bien;
    }

    /**
     * Trasladar y reintegrar requieren asignaciones.crear (ya lo exige la ruta); aquí solo
     * se confirma que el bien de verdad tiene una asignación activa de la cual partir.
     */
    private function verificarAutoridadSobreMovimiento(int $bienId): array
    {
        $asignacion = Asignacion::activaDe($bienId);

        if (!$asignacion) {
            Session::flash('error', 'Este bien no tiene una asignación activa.');
            header('Location: ' . Url::to("/bienes/{$bienId}/editar"));
            exit;
        }

        return $asignacion;
    }

    private function verificarCsrf(Request $request, int $bienId): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/bienes/{$bienId}/editar"));
            exit;
        }
    }
}
