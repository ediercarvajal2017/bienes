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
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Movimiento;
use App\Models\Verificacion;

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
        $verificacionId = Verificacion::idValidoParaBien($id, (string) $request->input('verificacion_id'));

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

        // Si la asignacion viene de una discrepancia (el bien no tenia ubicacion y ahora se
        // le asigno una), esa discrepancia ya quedo atendida.
        if ($verificacionId !== null) {
            Verificacion::marcarRevisada($verificacionId, (int) Auth::id());
        }

        Session::flash('ok', 'Bien asignado correctamente.');

        // Si el bien pertenece a un lote de alta masiva idéntica, se vuelve al listado
        // filtrado por ese lote (la misma pantalla de "Ver detalles" en /bienes) en vez
        // de quedarse en este bien puntual — así se puede seguir asignando el resto de
        // los idénticos sin tener que volver a buscar el código cada vez.
        if (!empty($bien['lote'])) {
            header('Location: ' . Url::to('/bienes?q=' . urlencode($bien['lote'])));
        } else {
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
        }
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
        $verificacionId = Verificacion::idValidoParaBien($id, (string) $request->input('verificacion_id'));

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

        // Si el traslado viene de una discrepancia ("no esta aqui, se movio"), esa
        // discrepancia ya quedo atendida — corregir la ubicacion ES la resolucion.
        if ($verificacionId !== null) {
            Verificacion::marcarRevisada($verificacionId, (int) Auth::id());
        }

        Session::flash('ok', 'Traslado registrado.');
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    /**
     * Traslado entre sedes de una misma familia (principal + secciones) — a diferencia
     * de trasladar(), aquí también cambia el "dueño" del bien (institucion_id), porque
     * el espacio destino pertenece a OTRA institución. Solo rectores llegan aquí: la
     * ruta exige asignaciones.crear, pero el rol adicional se valida igual porque un
     * secretario nunca tiene más de una sede en su familia para elegir.
     */
    public function trasladarSede(string $id): void
    {
        $id = (int) $id;
        $bien = $this->bienDeLaInstitucion($id);
        $asignacionActiva = $this->verificarAutoridadSobreMovimiento($id);

        $request = new Request();
        $this->verificarCsrf($request, $id);

        $fecha = (string) $request->input('fecha');
        $institucionDestinoId = (int) $request->input('institucion_destino_id');
        $espacioId = (string) $request->input('espacio_destino_id');
        $observaciones = trim((string) $request->input('observaciones')) ?: null;

        if ($fecha === '' || !strtotime($fecha) || $institucionDestinoId <= 0 || $espacioId === '') {
            Session::flash('error', 'Selecciona una fecha, una sede destino y un espacio válidos.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        $familia = Institucion::familiaDe((int) $bien['institucion_id']);
        $sedeDestino = null;
        foreach ($familia as $sede) {
            if ((int) $sede['id'] === $institucionDestinoId) {
                $sedeDestino = $sede;
                break;
            }
        }

        if ($sedeDestino === null || $institucionDestinoId === (int) $bien['institucion_id']) {
            Session::flash('error', 'Esa sede no pertenece a la familia de tu institución.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        $espacio = Espacio::find((int) $espacioId);
        if (!$espacio || (int) $espacio['institucion_id'] !== $institucionDestinoId) {
            Session::flash('error', 'Ese espacio no pertenece a la sede destino seleccionada.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        if (Bien::existeCodigo($institucionDestinoId, $bien['codigo_identificacion'], $id)) {
            Session::flash('error', 'Ya existe un bien con ese código de identificación en la sede destino.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        $nota = 'Traslado entre sedes: ' . $sedeDestino['nombre'] . '.';
        $observacionesFinal = $observaciones !== null ? $nota . ' ' . $observaciones : $nota;

        Movimiento::crear([
            'bien_id' => $id,
            'tipo' => 'traslado',
            'fecha' => $fecha,
            'responsable_id' => Auth::id(),
            'espacio_origen_id' => $asignacionActiva['espacio_id'] ?? null,
            'espacio_destino_id' => (int) $espacioId,
            'destino_texto' => null,
            'observaciones' => $observacionesFinal,
        ]);

        Asignacion::cerrarActivasDe($id);
        Asignacion::crear([
            'bien_id' => $id,
            'espacio_id' => (int) $espacioId,
            'fecha_asignacion' => $fecha,
            'observaciones' => $observacionesFinal,
            'asignado_por' => Auth::id(),
        ]);

        Bien::cambiarInstitucion($id, $institucionDestinoId);

        Session::flash('ok', 'Bien trasladado a ' . $sedeDestino['nombre'] . '.');
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    public function reintegrar(string $id): void
    {
        $id = (int) $id;
        $bien = $this->bienDeLaInstitucion($id);
        $asignacionActiva = $this->verificarAutoridadSobreMovimiento($id);

        if ($bien['categoria_id'] === null) {
            Session::flash('error', 'Este bien no tiene categoría asignada; asígnale una antes de reintegrarlo.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

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
