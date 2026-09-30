<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Helpers\FechaMovimiento;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Movimiento;
use App\Models\Usuario;
use App\Models\Verificacion;

/**
 * Acciones del ciclo de vida de UN bien: asignar o trasladar, trasladar a otra sede,
 * reintegrar, reactivar y reportar baja. Antes vivían en MovimientoController y
 * BajaController; ahora las usan ellos y también el formulario único de edición del bien
 * (BienController::actualizar), que guarda los datos y la acción elegida a la vez.
 *
 * Cada método valida sus reglas y lanza \DomainException con un mensaje para el usuario si
 * algo no se cumple; las escrituras van en Database::transaccion (si el llamador ya abrió
 * una, se reutiliza: así "guardar datos + acción" es todo o nada). El control de acceso al
 * bien (institución, permiso de la ruta) lo hace el controlador antes de llamar aquí.
 */
final class CicloVidaBien
{
    /**
     * Acciones que se pueden ofrecer para este bien y este usuario (clave => texto del menú),
     * con las mismas reglas que decidían qué paneles mostraba antes la pantalla del bien.
     *
     * @param array<int, array> $familiaSedesDestino sedes hermanas a las que se puede trasladar
     * @return array<string, string>
     */
    public static function accionesDisponibles(array $bien, ?array $asignacionActiva, array $familiaSedesDestino): array
    {
        $acciones = [];
        $enCirculacion = !in_array($bien['estado'], ['dado_de_baja', 'reintegrado'], true);
        $puedeMover = Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear');
        $sinCartera = ($bien['categoria_nombre'] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA;

        if ($enCirculacion && $puedeMover) {
            if ($asignacionActiva) {
                $acciones['trasladar'] = 'Cambiar de espacio o de responsable (traslado)';
            } else {
                $acciones['asignar'] = 'Asignar a un espacio o a una persona';
            }
            if ($familiaSedesDestino !== []) {
                $acciones['trasladar_sede'] = 'Trasladar a otra sede';
            }
            if ($asignacionActiva && !$sinCartera) {
                $acciones['reintegrar'] = 'Reintegrar';
            }
        }
        if ($bien['estado'] !== 'dado_de_baja' && $sinCartera && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear'))) {
            $acciones['reportar_baja'] = 'Reportar baja';
        }
        if ($bien['estado'] === 'reintegrado' && (Auth::esSuperusuario() || Auth::rol() === 'rector')) {
            $acciones['reactivar'] = 'Reactivar';
        }

        return $acciones;
    }

    /**
     * Responsabilidad grupal: asigna el bien a un espacio (si no tenía) o lo traslada.
     * Se conserva para los llamadores que solo manejan espacios (hallazgos, verificación,
     * rutas antiguas). Devuelve el mensaje de éxito.
     */
    public static function asignarOTrasladar(array $bien, int $espacioId, string $fecha, ?string $observaciones, ?int $verificacionId = null): string
    {
        return self::asignarResponsabilidad($bien, $espacioId, null, $fecha, $observaciones, $verificacionId);
    }

    /**
     * Asigna o cambia la responsabilidad del bien:
     *  - Grupal ($personaId null): en un espacio (obligatorio); responden sus responsables.
     *  - Individual ($personaId): a cargo de esa persona, un usuario activo de la misma
     *    institución; el espacio es opcional y solo indica dónde está guardado.
     * Si el bien ya tenía una asignación, el cambio es un traslado y queda como movimiento
     * con el espacio y la persona de origen y de destino. Devuelve el mensaje de éxito.
     */
    public static function asignarResponsabilidad(array $bien, ?int $espacioId, ?int $personaId, string $fecha, ?string $observaciones, ?int $verificacionId = null): string
    {
        self::verificarEnCirculacion($bien);
        self::verificarFecha($fecha);
        $id = (int) $bien['id'];
        $institucionId = (int) $bien['institucion_id'];
        $espacioId = $espacioId !== null && $espacioId > 0 ? $espacioId : null;
        $personaId = $personaId !== null && $personaId > 0 ? $personaId : null;

        if ($personaId === null && $espacioId === null) {
            throw new \DomainException('Selecciona el espacio (responsabilidad grupal) o la persona responsable (individual).');
        }
        if ($espacioId !== null && !Espacio::perteneceYActivo($espacioId, $institucionId)) {
            throw new \DomainException('El espacio seleccionado no es válido para este bien (debe ser un espacio activo de su misma institución).');
        }
        $persona = null;
        if ($personaId !== null && ($persona = Usuario::elegibleACargo($personaId, $institucionId)) === null) {
            throw new \DomainException('La persona seleccionada no puede tener este bien a cargo (debe ser un usuario activo de la misma institución).');
        }

        $anterior = Asignacion::activaDe($id);
        if ($anterior
            && (int) ($anterior['espacio_id'] ?? 0) === (int) $espacioId
            && (int) ($anterior['usuario_responsable_id'] ?? 0) === (int) $personaId) {
            throw new \DomainException($personaId !== null ? 'El bien ya está a cargo de esa persona.' : 'El bien ya está en ese espacio.');
        }

        Database::transaccion(static function () use ($id, $institucionId, $espacioId, $personaId, $fecha, $observaciones, $anterior): void {
            if ($anterior) {
                Movimiento::crear([
                    'bien_id' => $id,
                    'tipo' => 'traslado',
                    'fecha' => $fecha,
                    'responsable_id' => Auth::id(),
                    'espacio_origen_id' => $anterior['espacio_id'] ?? null,
                    'persona_origen_id' => $anterior['usuario_responsable_id'] ?? null,
                    'espacio_destino_id' => $espacioId,
                    'persona_destino_id' => $personaId,
                    'destino_texto' => null,
                    'observaciones' => $observaciones,
                ]);
            }
            Asignacion::cerrarActivasDe($id);
            Asignacion::crear([
                'bien_id' => $id,
                'usuario_responsable_id' => $personaId,
                'espacio_id' => $espacioId,
                'fecha_asignacion' => $fecha,
                'observaciones' => $observaciones,
                'asignado_por' => Auth::id(),
            ]);
            Auditoria::registrar(Auth::id(), $institucionId, $anterior ? 'trasladar' : 'asignar', 'bien', $id,
                ['espacio_id' => $anterior['espacio_id'] ?? null, 'usuario_responsable_id' => $anterior['usuario_responsable_id'] ?? null],
                ['espacio_id' => $espacioId, 'usuario_responsable_id' => $personaId, 'fecha' => $fecha, 'observaciones' => $observaciones]);
        });

        // Si viene de una discrepancia de la verificación física ("no está aquí, se movió"),
        // corregir la ubicación ES la resolución.
        if ($verificacionId !== null) {
            Verificacion::marcarRevisada($verificacionId, (int) Auth::id());
        }

        if ($persona !== null) {
            $nombre = trim($persona['nombres'] . ' ' . $persona['apellidos']);

            return $anterior ? "Ahora está a cargo de {$nombre} (responsabilidad individual)." : "Queda a cargo de {$nombre} (responsabilidad individual).";
        }
        $nombre = Espacio::find((int) $espacioId)['nombre'] ?? 'el espacio elegido';

        return $anterior ? "Trasladado a {$nombre}." : "Asignado a {$nombre}.";
    }

    /**
     * Traslado entre sedes de una misma familia (principal + secciones): a diferencia del
     * traslado normal, también cambia el "dueño" del bien (institucion_id). La pantalla solo
     * ofrece sedes destino al rector (ver BienController::editar()).
     */
    public static function trasladarSede(array $bien, int $institucionDestinoId, int $espacioId, string $fecha, ?string $observaciones): string
    {
        self::verificarEnCirculacion($bien);
        self::verificarFecha($fecha);
        $id = (int) $bien['id'];

        $asignacionActiva = Asignacion::activaDe($id);
        if (!$asignacionActiva) {
            throw new \DomainException('Este bien no tiene una asignación activa.');
        }
        if ($institucionDestinoId <= 0 || $espacioId <= 0) {
            throw new \DomainException('Selecciona una fecha, una sede destino y un espacio válidos.');
        }

        $sedeDestino = null;
        foreach (Institucion::familiaDe((int) $bien['institucion_id']) as $sede) {
            if ((int) $sede['id'] === $institucionDestinoId) {
                $sedeDestino = $sede;
                break;
            }
        }
        if ($sedeDestino === null || $institucionDestinoId === (int) $bien['institucion_id']) {
            throw new \DomainException('Esa sede no pertenece a la familia de tu institución.');
        }

        $espacio = Espacio::find($espacioId);
        if (!$espacio || (int) $espacio['institucion_id'] !== $institucionDestinoId) {
            throw new \DomainException('Ese espacio no pertenece a la sede destino seleccionada.');
        }
        if (Bien::existeCodigo($institucionDestinoId, $bien['codigo_identificacion'], $id)) {
            throw new \DomainException('Ya existe un bien con ese código de identificación en la sede destino.');
        }

        $nota = 'Traslado entre sedes: ' . $sedeDestino['nombre'] . '.';
        $observacionesFinal = $observaciones !== null ? $nota . ' ' . $observaciones : $nota;

        // Cuatro escrituras que van juntas: el bien no puede quedar a medio camino entre sedes.
        Database::transaccion(static function () use ($id, $bien, $fecha, $espacioId, $observacionesFinal, $asignacionActiva, $institucionDestinoId): void {
            Movimiento::crear([
                'bien_id' => $id,
                'tipo' => 'traslado',
                'fecha' => $fecha,
                'responsable_id' => Auth::id(),
                'espacio_origen_id' => $asignacionActiva['espacio_id'] ?? null,
                'persona_origen_id' => $asignacionActiva['usuario_responsable_id'] ?? null,
                'espacio_destino_id' => $espacioId,
                'destino_texto' => null,
                'observaciones' => $observacionesFinal,
            ]);
            Asignacion::cerrarActivasDe($id);
            Asignacion::crear([
                'bien_id' => $id,
                'espacio_id' => $espacioId,
                'fecha_asignacion' => $fecha,
                'observaciones' => $observacionesFinal,
                'asignado_por' => Auth::id(),
            ]);
            Bien::cambiarInstitucion($id, $institucionDestinoId);
            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'trasladar_sede', 'bien', $id,
                ['institucion_id' => (int) $bien['institucion_id'], 'espacio_id' => $asignacionActiva['espacio_id'] ?? null,
                    'usuario_responsable_id' => $asignacionActiva['usuario_responsable_id'] ?? null],
                ['institucion_id' => $institucionDestinoId, 'espacio_id' => $espacioId, 'fecha' => $fecha]);
        });

        return 'Trasladado a ' . $sedeDestino['nombre'] . '.';
    }

    public static function reintegrar(array $bien, string $fecha, string $destino, ?string $observaciones): string
    {
        self::verificarEnCirculacion($bien);
        $asignacionActiva = Asignacion::activaDe((int) $bien['id']);
        if (!$asignacionActiva) {
            throw new \DomainException('Este bien no tiene una asignación activa.');
        }
        // Misma regla que el reintegro masivo y el escáner.
        if ($motivo = Bien::motivoNoReintegrable($bien, true)) {
            throw new \DomainException('No se puede reintegrar: ' . $motivo . '.');
        }
        self::verificarFecha($fecha);
        if (trim($destino) === '') {
            throw new \DomainException('Indica el destino del reintegro.');
        }

        ReintegroService::reintegrar($bien, $asignacionActiva, $fecha, trim($destino), $observaciones);

        return 'Reintegro registrado. Cuando quieras, agrúpalo en un lote desde "Lotes de reintegro" para generar el formato.';
    }

    /**
     * Reactiva un bien reintegrado (la Alcaldía lo devolvió, o el reintegro fue un error).
     * Solo rector o superusuario, y siempre con un motivo.
     */
    public static function reactivar(array $bien, string $fecha, string $motivo): string
    {
        if (!Auth::esSuperusuario() && Auth::rol() !== 'rector') {
            throw new \DomainException('Solo el rector o el superusuario pueden reactivar un bien.');
        }
        if ($bien['estado'] !== 'reintegrado') {
            throw new \DomainException('Solo se puede reactivar un bien reintegrado.');
        }
        $motivo = trim($motivo);
        self::verificarFecha($fecha);
        if ($motivo === '') {
            throw new \DomainException('Indica el motivo de la reactivación.');
        }

        $id = (int) $bien['id'];
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

        return 'Bien reactivado. Ahora puedes asignarlo a un espacio o a una persona.';
    }

    /**
     * Crea el reporte de baja, PENDIENTE de aprobación (no da de baja el bien). Solo para
     * bienes "Sin cartera". La foto (si hay) ya viene guardada por el llamador.
     */
    public static function reportarBaja(array $bien, string $estadoReportado, ?string $ubicacion, string $descripcion, ?string $fotoPath, ?int $verificacionId = null): string
    {
        if (($bien['categoria_nombre'] ?? null) !== Categoria::NOMBRE_CATEGORIA_PROTEGIDA) {
            throw new \DomainException('Este bien no admite baja directa — solo bienes en la categoría "'
                . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '" pueden darse de baja. Use Traslado, Reintegro o marque "En reparación".');
        }
        if ($bien['estado'] === 'dado_de_baja') {
            throw new \DomainException('Este bien ya está dado de baja.');
        }
        if (Baja::tienePendiente((int) $bien['id'])) {
            throw new \DomainException('Este bien ya tiene un reporte de baja pendiente de aprobación.');
        }
        $estadoReportado = trim($estadoReportado);
        $descripcion = trim($descripcion);
        if ($estadoReportado === '' || $descripcion === '') {
            throw new \DomainException('Describe el estado del bien y el motivo de la baja.');
        }

        Database::transaccion(static function () use ($bien, $verificacionId, $estadoReportado, $ubicacion, $descripcion, $fotoPath): void {
            $bajaId = Baja::crear([
                'bien_id' => $bien['id'],
                'verificacion_id' => $verificacionId,
                'estado_reportado' => $estadoReportado,
                'ubicacion' => $ubicacion,
                'responsable_id' => Auth::id(),
                'descripcion' => $descripcion,
                'foto_path' => $fotoPath,
            ]);
            // Si viene de una discrepancia reportada en una jornada, ya quedó atendida.
            if ($verificacionId !== null) {
                Verificacion::marcarRevisada($verificacionId, (int) Auth::id());
            }
            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'reportar', 'baja', $bajaId, null, [
                'bien_id' => (int) $bien['id'],
                'estado_reportado' => $estadoReportado,
                'descripcion' => $descripcion,
            ]);
        });

        return 'Reporte de baja enviado. Queda pendiente de aprobación.';
    }

    /**
     * Un bien dado de baja o reintegrado ya no está físicamente en la institución: no admite
     * movimientos por el flujo normal (reactivar un reintegro es una acción aparte).
     */
    private static function verificarEnCirculacion(array $bien): void
    {
        if (in_array($bien['estado'], ['dado_de_baja', 'reintegrado'], true)) {
            $estado = str_replace('_', ' ', $bien['estado']);
            throw new \DomainException("Este bien está {$estado} y no admite movimientos.");
        }
    }

    private static function verificarFecha(string $fecha): void
    {
        if ($error = FechaMovimiento::error($fecha)) {
            throw new \DomainException($error);
        }
    }
}
