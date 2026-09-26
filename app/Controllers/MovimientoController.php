<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
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
        $this->verificarAsignable($bien);

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

        $this->verificarEspacio((int) $espacioId, $bien);

        // Cerrar la asignación anterior y crear la nueva van juntas: si una falla, ninguna
        // queda aplicada (antes podía quedar el bien con dos asignaciones activas o ninguna).
        $anterior = Asignacion::activaDe($id);
        Database::transaccion(static function () use ($id, $bien, $espacioId, $fecha, $observaciones, $anterior): void {
            Asignacion::cerrarActivasDe($id);
            Asignacion::crear([
                'bien_id' => $id,
                'espacio_id' => (int) $espacioId,
                'fecha_asignacion' => $fecha,
                'observaciones' => $observaciones,
                'asignado_por' => Auth::id(),
            ]);
            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'asignar', 'bien', $id,
                ['espacio_id' => $anterior['espacio_id'] ?? null],
                ['espacio_id' => (int) $espacioId, 'fecha' => $fecha, 'observaciones' => $observaciones]);
        });

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
        $bien = $this->bienDeLaInstitucion($id);
        $this->verificarAsignable($bien);
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

        $this->verificarEspacio((int) $espacioId, $bien);

        Database::transaccion(static function () use ($id, $bien, $espacioId, $fecha, $observaciones, $asignacionActiva): void {
            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'trasladar', 'bien', $id,
                ['espacio_id' => $asignacionActiva['espacio_id'] ?? null],
                ['espacio_id' => (int) $espacioId, 'fecha' => $fecha, 'observaciones' => $observaciones]);
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
        });

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
        $this->verificarAsignable($bien);
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

        // Cuatro escrituras que van juntas: si una falla, el bien no puede quedar a medio
        // camino entre dos sedes.
        Database::transaccion(static function () use ($id, $bien, $fecha, $espacioId, $observacionesFinal, $asignacionActiva, $institucionDestinoId): void {
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

            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'trasladar_sede', 'bien', $id,
                ['institucion_id' => (int) $bien['institucion_id'], 'espacio_id' => $asignacionActiva['espacio_id'] ?? null],
                ['institucion_id' => $institucionDestinoId, 'espacio_id' => (int) $espacioId, 'fecha' => $fecha]);
        });

        Session::flash('ok', 'Bien trasladado a ' . $sedeDestino['nombre'] . '.');
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    public function reintegrar(string $id): void
    {
        $id = (int) $id;
        $bien = $this->bienDeLaInstitucion($id);
        $this->verificarAsignable($bien);
        $asignacionActiva = $this->verificarAutoridadSobreMovimiento($id);

        // Misma regla que el reintegro masivo y el escáner (Bien::motivoNoReintegrable).
        if ($motivo = Bien::motivoNoReintegrable($bien, $asignacionActiva !== null)) {
            Session::flash('error', 'No se puede reintegrar: ' . $motivo . '.');
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

        try {
            Database::transaccion(static function () use ($id, $bien, $fecha, $destino, $observaciones, $asignacionActiva): void {
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
                Bien::cambiarEstado($id, 'reintegrado');
                Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'reintegrar', 'bien', $id,
                    ['estado' => $bien['estado'], 'espacio_id' => $asignacionActiva['espacio_id'] ?? null],
                    ['estado' => 'reintegrado', 'destino' => $destino, 'fecha' => $fecha]);
            });
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Session::flash('ok', 'Reintegro registrado. Cuando quieras, agrúpalo en un lote desde "Lotes de reintegro" para generar el formato.');
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    /**
     * Reactiva un bien reintegrado, para el caso legítimo pero excepcional (la Alcaldía
     * lo devolvió, o el reintegro original fue un error). A diferencia de "Asignar",
     * queda restringido a rector/superusuario y exige un motivo — nunca es un clic
     * accidental dentro del flujo normal de trabajo (ver verificarAsignable() y
     * Bien::condicionesOperables(), que ya excluyen los reintegrados de ese flujo).
     */
    public function reactivar(string $id): void
    {
        $id = (int) $id;
        $bien = $this->bienDeLaInstitucion($id);
        $this->verificarPuedeReactivar();

        if ($bien['estado'] !== 'reintegrado') {
            Session::flash('error', 'Solo se puede reactivar un bien reintegrado.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        $request = new Request();
        $this->verificarCsrf($request, $id);

        $fecha = (string) $request->input('fecha');
        $motivo = trim((string) $request->input('motivo'));

        if ($fecha === '' || !strtotime($fecha) || $motivo === '') {
            Session::flash('error', 'Indica la fecha y el motivo de la reactivación.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        try {
            Database::transaccion(static function () use ($id, $bien, $fecha, $motivo): void {
                Movimiento::crear([
                    'bien_id' => $id,
                    'tipo' => 'reactivacion',
                    'fecha' => $fecha,
                    'responsable_id' => Auth::id(),
                    'espacio_origen_id' => null,
                    'espacio_destino_id' => null,
                    'destino_texto' => null,
                    'observaciones' => $motivo,
                ]);

                Bien::cambiarEstado($id, 'activo');
                Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'reactivar', 'bien', $id,
                    ['estado' => 'reintegrado'], ['estado' => 'activo', 'motivo' => $motivo, 'fecha' => $fecha]);
            });
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Session::flash('ok', 'Bien reactivado. Ahora puedes asignarlo a un espacio.');
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    /**
     * Solo rector o superusuario pueden reactivar un reintegro — la ruta ya exige
     * asignaciones.crear, pero ese permiso también lo puede tener un secretario, así que
     * el rol se valida aparte, igual que CargoController::verificarSuperusuario().
     */
    private function verificarPuedeReactivar(): void
    {
        if (!Auth::esSuperusuario() && Auth::rol() !== 'rector') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
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
     * Un bien dado de baja o reintegrado ya no está físicamente en la institución — no
     * admite ningún movimiento por el flujo normal (asignar, trasladar, trasladar a otra
     * sede, reintegrar), sin importar si el envío viene del botón de la pantalla o de una
     * petición directa. Se llama al inicio de las cuatro acciones, antes de cualquier otra
     * validación, para que ninguna reviva por accidente un bien retirado — ni siquiera un
     * bien que haya quedado con una asignación activa contradictoria de antes de este
     * candado. Reactivar un reintegro (el único de los dos casos con sentido de
     * revertirse) requiere la acción explícita y restringida reactivar(), no estas.
     */
    /**
     * El espacio de destino debe ser de la misma institución del bien, estar activo y
     * fuera de la papelera (el formulario solo ofrece esos, pero el id llega del navegador).
     */
    private function verificarEspacio(int $espacioId, array $bien): void
    {
        if (!Espacio::perteneceYActivo($espacioId, (int) $bien['institucion_id'])) {
            Session::flash('error', 'El espacio seleccionado no es válido para este bien (debe ser un espacio activo de su misma institución).');
            header('Location: ' . Url::to("/bienes/{$bien['id']}/editar"));
            exit;
        }
    }

    private function verificarAsignable(array $bien): void
    {
        if (in_array($bien['estado'], ['dado_de_baja', 'reintegrado'], true)) {
            $estado = str_replace('_', ' ', $bien['estado']);
            Session::flash('error', "Este bien está {$estado} y no admite movimientos.");
            header('Location: ' . Url::to("/bienes/{$bien['id']}/editar"));
            exit;
        }
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
        Csrf::verificarORedirigir($request, "/bienes/{$bienId}/editar");
    }
}
