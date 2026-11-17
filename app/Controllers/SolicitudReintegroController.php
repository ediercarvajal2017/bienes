<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\ErrorGuiado;
use App\Core\Csrf;
use App\Helpers\FechaMovimiento;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\SolicitudReintegro;
use App\Services\ReintegroService;

/**
 * Solicitudes de reintegro: quien tiene un bien a cargo (docente, permiso
 * reintegros.solicitar) pide su reintegro con un motivo; quien gestiona reintegros
 * (asignaciones.crear: rector, secretario) la aprueba —y en ese momento se ejecuta el
 * reintegro con la misma regla y los mismos pasos que el reintegro desde la ficha del
 * bien (ReintegroService)— o la rechaza con una respuesta.
 */
final class SolicitudReintegroController
{
    private const POR_PAGINA_DEFECTO = 25;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];
    private const MOTIVO_MINIMO = 10;

    public function formulario(string $token): void
    {
        $bien = $this->bienSolicitable($token);

        View::layout('partials/layout', 'solicitudes_reintegro/form', [
            'title' => 'Solicitar reintegro',
            'bien' => $bien,
            'token' => $token,
            'asignacion' => Asignacion::activaDe((int) $bien['id']),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(string $token): void
    {
        $bien = $this->bienSolicitable($token);
        $request = new Request();
        $motivo = trim((string) $request->input('motivo'));
        Csrf::verificarORedirigir($request, "/qr/{$token}/solicitar-reintegro", ['motivo' => $motivo]);

        if (mb_strlen($motivo) < self::MOTIVO_MINIMO) {
            Aviso::enviar(Aviso::error('No se envió la solicitud', 'El motivo es muy corto: escribe al menos ' . self::MOTIVO_MINIMO . ' caracteres.',
                'Cuenta qué le pasa al bien o por qué ya no se necesita (por ejemplo «Silla con el espaldar partido») y vuelve a enviar.',
                null, ['motivo' => 'Escribe al menos ' . self::MOTIVO_MINIMO . ' caracteres.']));
            Session::flashOld(['motivo' => $motivo]);
            header('Location: ' . Url::to("/qr/{$token}/solicitar-reintegro"));
            exit;
        }

        $id = Database::transaccion(static function () use ($bien, $motivo): int {
            $id = SolicitudReintegro::crear((int) $bien['id'], (int) $bien['institucion_id'], (int) Auth::id(), mb_substr($motivo, 0, 500));
            Auditoria::registrar(Auth::id(), (int) $bien['institucion_id'], 'solicitar', 'solicitud_reintegro', $id, null,
                ['bien_id' => (int) $bien['id'], 'motivo' => $motivo]);

            return $id;
        });

        Aviso::enviar(Aviso::exito('Solicitud de reintegro n.º ' . $id . ' enviada.', 'El rector o el secretario la revisará; aquí verás si la aprueba o la rechaza.'));
        header('Location: ' . Url::to('/reintegros/solicitudes'));
        exit;
    }

    public function index(): void
    {
        $puedeAprobar = $this->puedeAprobar();
        if (!$puedeAprobar && !Auth::tienePermiso('reintegros.solicitar')) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        // Quien aprueba ve todas las de la institución; quien solicita, solo las suyas.
        $soloDe = $puedeAprobar ? null : (int) Auth::id();
        $estado = (string) ($_GET['estado'] ?? '');
        $estado = in_array($estado, SolicitudReintegro::ESTADOS, true) ? $estado : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        $total = SolicitudReintegro::contar($institucionId, $soloDe, $estado);

        View::layout('partials/layout', 'solicitudes_reintegro/index', [
            'title' => 'Solicitudes de reintegro',
            'solicitudes' => SolicitudReintegro::listar($institucionId, $soloDe, $estado, $pagina, $porPagina),
            'puedeAprobar' => $puedeAprobar,
            'estado' => $estado,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    public function aprobar(string $id): void
    {
        $solicitud = $this->solicitudAccesible((int) $id);
        $request = new Request();
        Csrf::verificarORedirigir($request, '/reintegros/solicitudes');

        $fecha = (string) $request->input('fecha');
        $destino = trim((string) $request->input('destino_texto'));
        $respuesta = trim((string) $request->input('respuesta')) ?: null;

        if ($destino === '') {
            $this->volverConError(Aviso::error('No se aprobó la solicitud', 'Falta el destino del reintegro.',
                'Escribe a dónde va el bien (por ejemplo «Almacén de la Secretaría de Educación») y vuelve a pulsar «Aprobar».'));
        }
        if ($errorFecha = FechaMovimiento::error($fecha)) {
            $this->volverConError(Aviso::error('No se aprobó la solicitud', $errorFecha, 'Elige una fecha entre el 01/01/2000 y hoy y vuelve a pulsar «Aprobar».'));
        }

        $bien = Bien::find((int) $solicitud['bien_id']);
        if (!$bien) {
            $this->volverConError(Aviso::error('No se aprobó la solicitud', 'El bien de esta solicitud ya no existe en MIA.',
                'Rechaza la solicitud explicando que el bien ya no está registrado.'));
        }

        try {
            Database::transaccion(static function () use ($solicitud, $bien, $fecha, $destino, $respuesta): void {
                $movimientoId = ReintegroService::reintegrar(
                    $bien,
                    Asignacion::activaDe((int) $bien['id']),
                    $fecha,
                    $destino,
                    'Solicitud de reintegro n.º ' . $solicitud['id'] . ': ' . $solicitud['motivo'],
                    ['solicitud_reintegro_id' => (int) $solicitud['id']]
                );

                // Si otra persona ya la resolvió, se deshace también el reintegro.
                if (!SolicitudReintegro::aprobarSiPendiente((int) $solicitud['id'], (int) Auth::id(), $movimientoId, $respuesta)) {
                    throw new ErrorGuiado('Otra persona ya resolvió esta solicitud.', 'Recarga la lista para ver cómo quedó.');
                }

                Auditoria::registrar(Auth::id(), (int) $solicitud['institucion_id'], 'aprobar', 'solicitud_reintegro', (int) $solicitud['id'],
                    ['estado' => 'pendiente'], ['estado' => 'aprobada', 'movimiento_id' => $movimientoId, 'respuesta' => $respuesta]);
            });
        } catch (\DomainException $e) {
            $this->volverConError(Aviso::desdeExcepcion($e, 'No se aprobó la solicitud', 'Revisa el bien en su ficha y vuelve a intentarlo.'));
        }

        Aviso::enviar(Aviso::exito('Solicitud aprobada: el bien quedó reintegrado.', 'Siguiente paso: agrúpalo en un lote de reintegro para generar el comprobante.',
            Aviso::accion('Generar el lote de reintegro', '/reintegros/lotes/generar')));
        header('Location: ' . Url::to('/reintegros/solicitudes'));
        exit;
    }

    public function rechazar(string $id): void
    {
        $solicitud = $this->solicitudAccesible((int) $id);
        $request = new Request();
        Csrf::verificarORedirigir($request, '/reintegros/solicitudes');

        $respuesta = trim((string) $request->input('respuesta'));
        if ($respuesta === '') {
            $this->volverConError(Aviso::error('No se rechazó la solicitud', 'Falta el motivo del rechazo.', 'Escribe por qué se rechaza: quien la pidió lo verá.'));
        }

        if (!SolicitudReintegro::rechazarSiPendiente((int) $solicitud['id'], (int) Auth::id(), mb_substr($respuesta, 0, 500))) {
            $this->volverConError(Aviso::error('No se rechazó la solicitud', 'Otra persona ya resolvió esta solicitud.', 'Recarga la lista para ver cómo quedó.'));
        }

        Auditoria::registrar(Auth::id(), (int) $solicitud['institucion_id'], 'rechazar', 'solicitud_reintegro', (int) $solicitud['id'],
            ['estado' => 'pendiente'], ['estado' => 'rechazada', 'respuesta' => $respuesta]);

        Aviso::enviar(Aviso::exito('Solicitud rechazada.', 'El bien sigue asignado y quien la pidió verá tu respuesta.'));
        header('Location: ' . Url::to('/reintegros/solicitudes'));
        exit;
    }

    public function cancelar(string $id): void
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, '/reintegros/solicitudes');

        $solicitud = SolicitudReintegro::find((int) $id);
        if (!$solicitud || !SolicitudReintegro::cancelarSiPendiente((int) $id, (int) Auth::id())) {
            $this->volverConError(Aviso::error('No se canceló la solicitud', 'La solicitud no es tuya o ya fue resuelta.', 'Recarga la lista para ver su estado.'));
        }

        Auditoria::registrar(Auth::id(), (int) $solicitud['institucion_id'], 'cancelar', 'solicitud_reintegro', (int) $id,
            ['estado' => 'pendiente'], ['estado' => 'cancelada']);

        Aviso::enviar(Aviso::exito('Solicitud cancelada.', 'El bien sigue asignado como estaba.'));
        header('Location: ' . Url::to('/reintegros/solicitudes'));
        exit;
    }

    /**
     * ¿El usuario puede pedir el reintegro de este bien? Debe ser de su institución,
     * estar a su cargo (responsable del espacio donde está asignado), cumplir la regla de
     * reintegro y no tener ya una solicitud pendiente.
     */
    private function bienSolicitable(string $token): array
    {
        $bien = Bien::findPorToken($token);
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

        if (!Auth::esSuperusuario() && !Bien::esResponsableDe((int) $bien['id'], (int) Auth::id())) {
            Aviso::enviar(Aviso::error('No se puede solicitar el reintegro', 'El bien no está en un espacio a tu cargo.',
                'Pídele al responsable de ese espacio, al rector o al secretario que lo solicite.'));
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        $guia = Bien::guiaNoReintegrable($bien, Asignacion::activaDe((int) $bien['id']) !== null);
        if ($guia !== null) {
            // Quien solicita no puede asignar ni cambiar la categoría: se le dice a quién acudir.
            Aviso::enviar(Aviso::error('No se puede solicitar el reintegro', 'El bien no se puede reintegrar: ' . $guia['motivo'] . '.',
                $this->puedeAprobar() ? $guia['queHacer'] : 'Avísale al rector o al secretario para que lo revise.',
                $this->puedeAprobar() ? $guia['accion'] : null));
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        if (SolicitudReintegro::tienePendiente((int) $bien['id'])) {
            Aviso::enviar(Aviso::error('No se puede solicitar el reintegro', 'El bien ya tiene una solicitud de reintegro esperando respuesta.',
                'Espera la respuesta; la ves en «Solicitudes de reintegro».', Aviso::accion('Ver las solicitudes', '/reintegros/solicitudes')));
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        return $bien;
    }

    private function solicitudAccesible(int $id): array
    {
        $solicitud = SolicitudReintegro::find($id);
        if (!$solicitud) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $solicitud['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        return $solicitud;
    }

    private function puedeAprobar(): bool
    {
        return Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear');
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volverConError(array $aviso): never
    {
        Aviso::enviar($aviso);
        header('Location: ' . Url::to('/reintegros/solicitudes'));
        exit;
    }
}
