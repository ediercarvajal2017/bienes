<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Database;
use App\Core\ErrorGuiado;
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
 * Cada método valida sus reglas y lanza ErrorGuiado (qué pasó y qué hacer) si algo no se
 * cumple; las escrituras van en Database::transaccion (si el llamador ya abrió
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
            // Una unidad de un código con varias unidades (alta masiva) se puede reintegrar
            // aunque no esté asignada (ver Bien::motivoNoReintegrable). El renglón de la cartera
            // de esa placa no: sus unidades se reintegran por cantidad.
            $esRenglonDePlaca = empty($bien['lote']) && Bien::existeLote((int) $bien['institucion_id'], (string) $bien['codigo_identificacion']);
            if (($asignacionActiva || !empty($bien['lote'])) && !$sinCartera && !$esRenglonDePlaca) {
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
     * Acciones que el usuario esperaría y no aparecen en la ficha de este bien, con el porqué
     * y qué hacer en su lugar ("¿Por qué no aparecen otras acciones?"). Las mismas reglas de
     * accionesDisponibles(); solo se explica lo que el rol del usuario sí le permitiría.
     *
     * @return array<string, array{titulo: string, porQue: string, queHacer: string, accion: array{texto: string, url: string}|null}>
     */
    public static function accionesNoDisponibles(array $bien, ?array $asignacionActiva): array
    {
        $no = [];
        $estado = (string) $bien['estado'];
        $enCirculacion = !in_array($estado, ['dado_de_baja', 'reintegrado'], true);
        $puedeMover = Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear');
        $sinCartera = ($bien['categoria_nombre'] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA;
        $esRector = Auth::esSuperusuario() || Auth::rol() === 'rector';

        if (!$enCirculacion && $puedeMover) {
            $no['mover'] = ['titulo' => 'Asignar, trasladar o reintegrar',
                'porQue' => 'El bien está «' . Bien::etiquetaEstado($estado) . '»: ya no está en la institución.',
                'queHacer' => $estado === 'reintegrado'
                    ? ($esRector ? 'Si volvió, elige «Reactivar» y luego podrás asignarlo.' : 'Si volvió, pídele al rector que lo reactive desde esta ficha.')
                    : 'Un bien dado de baja ya no se mueve; su historial queda en esta ficha.',
                'accion' => null];
        }
        if ($estado === 'reintegrado' && !$esRector) {
            $no['reactivar'] = ['titulo' => 'Reactivar', 'porQue' => 'Solo el rector puede reactivar un bien reintegrado.',
                'queHacer' => 'Pídele al rector que lo reactive desde esta ficha.', 'accion' => null];
        }
        if ($enCirculacion && $puedeMover) {
            $esRenglonDePlaca = empty($bien['lote']) && Bien::existeLote((int) $bien['institucion_id'], (string) $bien['codigo_identificacion']);
            if ($esRenglonDePlaca) {
                $no['reintegrar'] = ['titulo' => 'Reintegrar',
                    'porQue' => 'Es el renglón de la cartera de la placa ' . $bien['codigo_identificacion'] . ': sus unidades se reintegran por cantidad y este renglón se actualiza solo.',
                    'queHacer' => 'Usa «Reintegrar por cantidad».',
                    'accion' => Aviso::accion('Reintegrar por cantidad', '/reintegros/unidades?' . http_build_query(['q' => (string) $bien['codigo_identificacion']]))];
            } elseif ($sinCartera) {
                $no['reintegrar'] = ['titulo' => 'Reintegrar', 'porQue' => 'Los bienes «' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '» no se reintegran.',
                    'queHacer' => 'Si ya no sirve, elige «Reportar baja».', 'accion' => null];
            } elseif (!$asignacionActiva && empty($bien['lote'])) {
                $no['reintegrar'] = ['titulo' => 'Reintegrar', 'porQue' => 'El bien no está asignado a un espacio ni a una persona.',
                    'queHacer' => 'Asígnalo primero («Asignar a un espacio o a una persona») y luego podrás reintegrarlo.', 'accion' => null];
            }
        }
        if ($enCirculacion && !$sinCartera && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear'))) {
            $no['reportar_baja'] = ['titulo' => 'Reportar baja',
                'porQue' => 'El bien está en la cartera de la Alcaldía, y solo los bienes «' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '» se dan de baja.',
                'queHacer' => 'Si ya no se usa, reintégralo; si está dañado, cambia su estado a «En reparación».',
                'accion' => null];
        }

        return $no;
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
    /**
     * Lo que cabe en la base: un texto más largo se rechaza con su motivo (antes hacía fallar
     * el guardado). $campo: el del formulario de la ficha, para marcarlo.
     */
    private static function verificarLargo(?string $texto, int $maximo, string $queTexto, string $campo): void
    {
        if (mb_strlen(trim((string) $texto)) > $maximo) {
            throw new ErrorGuiado("{$queTexto} es más largo de lo que se puede guardar (máximo {$maximo} caracteres).",
                'Acórtalo y vuelve a guardar.', null, [$campo => "Máximo {$maximo} caracteres."]);
        }
    }

    /**
     * El destino elegido es donde el bien ya está: mismo espacio y misma persona (o ninguna,
     * si es Grupal). Sin asignación activa nunca lo es (sería una asignación nueva).
     */
    public static function esMismoDestino(?array $anterior, ?int $espacioId, ?int $personaId): bool
    {
        return $anterior !== null
            && (int) ($anterior['espacio_id'] ?? 0) === (int) $espacioId
            && (int) ($anterior['usuario_responsable_id'] ?? 0) === (int) $personaId;
    }

    public static function asignarResponsabilidad(array $bien, ?int $espacioId, ?int $personaId, string $fecha, ?string $observaciones, ?int $verificacionId = null): string
    {
        self::verificarLargo($observaciones, 500, 'El texto de la observación', 'accion_observaciones');
        self::verificarEnCirculacion($bien);
        self::verificarFecha($fecha);
        $id = (int) $bien['id'];
        $institucionId = (int) $bien['institucion_id'];
        $espacioId = $espacioId !== null && $espacioId > 0 ? $espacioId : null;
        $personaId = $personaId !== null && $personaId > 0 ? $personaId : null;

        if ($personaId === null && $espacioId === null) {
            throw new ErrorGuiado('No elegiste dónde queda el bien ni quién responde por él.',
                'Elige el espacio (responsabilidad Grupal) o la persona responsable (Individual) y vuelve a guardar.');
        }
        if ($espacioId !== null && !Espacio::perteneceYActivo($espacioId, $institucionId)) {
            throw new ErrorGuiado('El espacio elegido está inactivo o es de otra institución.',
                'Elige otro espacio de la lista. Si falta, créalo o actívalo en Espacios.');
        }
        $persona = null;
        if ($personaId !== null && ($persona = Usuario::elegibleACargo($personaId, $institucionId)) === null) {
            throw new ErrorGuiado('La persona elegida está inactiva o es de otra institución, así que no puede tener el bien a cargo.',
                'Elige otra persona de la lista, o marca la responsabilidad Grupal y elige un espacio.');
        }

        $anterior = Asignacion::activaDe($id);
        if (self::esMismoDestino($anterior, $espacioId, $personaId)) {
            throw new ErrorGuiado($personaId !== null ? 'El bien ya está a cargo de esa persona.' : 'El bien ya está en ese espacio.',
                'Elige otro destino. Si el bien no se movió, no hace falta registrar el traslado.');
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
        self::verificarLargo($observaciones, 500, 'El texto de la observación', 'accion_observaciones');
        self::verificarEnCirculacion($bien);
        self::verificarFecha($fecha);
        $id = (int) $bien['id'];

        $asignacionActiva = Asignacion::activaDe($id);
        if (!$asignacionActiva) {
            throw new ErrorGuiado('El bien no está asignado a ningún espacio ni persona.',
                'Asígnalo primero en esta sede y luego trasládalo a la otra.');
        }
        if ($institucionDestinoId <= 0 || $espacioId <= 0) {
            throw new ErrorGuiado('Faltan la sede o el espacio de destino.',
                'Elige la sede destino y un espacio de esa sede y vuelve a guardar.');
        }

        $sedeDestino = null;
        foreach (Institucion::familiaDe((int) $bien['institucion_id']) as $sede) {
            if ((int) $sede['id'] === $institucionDestinoId) {
                $sedeDestino = $sede;
                break;
            }
        }
        if ($sedeDestino === null || $institucionDestinoId === (int) $bien['institucion_id']) {
            throw new ErrorGuiado('Esa sede no pertenece a tu institución.',
                'Elige una de las sedes de la lista.');
        }

        $espacio = Espacio::find($espacioId);
        if (!$espacio || (int) $espacio['institucion_id'] !== $institucionDestinoId) {
            throw new ErrorGuiado('El espacio elegido no es de la sede destino.',
                'Elige primero la sede y luego uno de sus espacios.');
        }
        if (Bien::existeCodigo($institucionDestinoId, $bien['codigo_identificacion'], $id)) {
            throw new ErrorGuiado('La sede destino ya tiene un bien con la placa ' . $bien['codigo_identificacion'] . '.',
                'Revisa en esa sede si es el mismo bien registrado dos veces; corrige la placa de uno de los dos antes de trasladarlo.');
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
        self::verificarLargo($destino, 200, 'El destino', 'accion_destino');
        self::verificarLargo($observaciones, 500, 'El texto de la observación', 'accion_observaciones');
        self::verificarEnCirculacion($bien);
        $asignacionActiva = Asignacion::activaDe((int) $bien['id']);
        // Misma regla que el reintegro masivo, el escáner y el reintegro por cantidad (una
        // unidad de un lote puede no estar asignada).
        if ($guia = Bien::guiaNoReintegrable($bien, $asignacionActiva !== null)) {
            throw new ErrorGuiado('No se puede reintegrar: ' . $guia['motivo'] . '.', $guia['queHacer'], $guia['accion']);
        }
        self::verificarFecha($fecha);
        if (trim($destino) === '') {
            throw new ErrorGuiado('Falta el destino del reintegro.',
                'Escribe a dónde va el bien (por ejemplo «Almacén de la Secretaría de Educación») y vuelve a guardar.',
                null, ['accion_destino' => 'Escribe el destino.']);
        }

        ReintegroService::reintegrar($bien, $asignacionActiva, $fecha, trim($destino), $observaciones);

        return 'Reintegro registrado. Siguiente paso: agrúpalo en un lote desde «Lotes de reintegro» para generar el comprobante.';
    }

    /**
     * Reactiva un bien reintegrado (la Alcaldía lo devolvió, o el reintegro fue un error).
     * Solo rector o superusuario, y siempre con un motivo.
     */
    public static function reactivar(array $bien, string $fecha, string $motivo): string
    {
        if (!Auth::esSuperusuario() && Auth::rol() !== 'rector') {
            throw new ErrorGuiado('Solo el rector puede reactivar un bien.',
                'Pídele al rector de la institución que lo reactive desde la ficha del bien.');
        }
        if ($bien['estado'] !== 'reintegrado') {
            throw new ErrorGuiado('Solo se reactiva un bien reintegrado, y este está «' . Bien::etiquetaEstado($bien['estado']) . '».',
                'No hace falta reactivarlo: ya está en la institución.');
        }
        $motivo = trim($motivo);
        self::verificarLargo($motivo, 500, 'El motivo', 'accion_motivo');
        self::verificarFecha($fecha);
        if ($motivo === '') {
            throw new ErrorGuiado('Falta el motivo de la reactivación.',
                'Escribe por qué vuelve (por ejemplo «La Alcaldía lo devolvió») y vuelve a guardar.',
                null, ['accion_motivo' => 'Escribe el motivo.']);
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
            RenglonDePlaca::sincronizarDe([$bien], 'Reactivación de la unidad ' . $bien['codigo_identificacion']);
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
            throw new ErrorGuiado('El bien está en la cartera de la Alcaldía, y solo los bienes «'
                . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '» se dan de baja.',
                'Si ya no se usa, reintégralo; si está dañado, márcalo «En reparación».');
        }
        if ($bien['estado'] === 'dado_de_baja') {
            throw new ErrorGuiado('El bien ya está dado de baja.', 'No hace falta reportarlo otra vez; su historial queda en la ficha.');
        }
        if (Baja::tienePendiente((int) $bien['id'])) {
            throw new ErrorGuiado('El bien ya tiene un reporte de baja esperando aprobación.',
                'Espera a que el rector lo apruebe o rechace en «Bajas».', Aviso::accion('Ver las bajas', '/bajas'));
        }
        $estadoReportado = trim($estadoReportado);
        $descripcion = trim($descripcion);
        self::verificarLargo($estadoReportado, 100, 'El estado del bien', 'accion_estado_reportado');
        self::verificarLargo($descripcion, 500, 'El texto de qué pasó', 'accion_descripcion_baja');
        if ($estadoReportado === '' || $descripcion === '') {
            throw new ErrorGuiado('Faltan el estado del bien o el motivo de la baja.',
                'Elige en qué estado está y describe por qué se da de baja; luego vuelve a guardar.');
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
            throw new ErrorGuiado('El bien está «' . Bien::etiquetaEstado($bien['estado']) . '» y ya no está en la institución, así que no se mueve.',
                $bien['estado'] === 'reintegrado'
                    ? 'Si volvió, el rector debe reactivarlo primero desde su ficha.'
                    : 'Un bien dado de baja ya no se mueve; su historial queda en la ficha.');
        }
    }

    private static function verificarFecha(string $fecha): void
    {
        if ($error = FechaMovimiento::error($fecha)) {
            throw new ErrorGuiado($error, 'Elige una fecha entre el 01/01/2000 y hoy y vuelve a guardar.');
        }
    }
}
