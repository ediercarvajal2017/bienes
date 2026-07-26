<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Models\Bien;
use App\Models\JornadaVerificacion;
use App\Models\Verificacion;

final class VerificacionController
{
    private const POR_PAGINA_DEFECTO = 25;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = JornadaVerificacion::contarListado($institucionId);

        View::layout('partials/layout', 'verificaciones/index', [
            'title' => 'Verificación física de bienes',
            'jornadas' => JornadaVerificacion::listar($institucionId, $pagina, $porPagina),
            'jornadaActiva' => Auth::institucionId() !== null ? JornadaVerificacion::activaPara(Auth::institucionId()) : null,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function crear(): void
    {
        if (Auth::institucionId() !== null && JornadaVerificacion::activaPara(Auth::institucionId())) {
            Session::flash('error', 'Ya hay una jornada de verificación en progreso. Ciérrala antes de iniciar otra.');
            header('Location: ' . Url::to('/verificaciones'));
            exit;
        }

        View::layout('partials/layout', 'verificaciones/crear', [
            'title' => 'Nueva jornada de verificación',
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/verificaciones/crear'));
            exit;
        }

        $institucionId = Auth::institucionId();
        if ($institucionId === null) {
            Session::flash('error', 'Tu usuario no tiene una institución asignada.');
            header('Location: ' . Url::to('/verificaciones'));
            exit;
        }

        if (JornadaVerificacion::activaPara($institucionId)) {
            Session::flash('error', 'Ya hay una jornada de verificación en progreso. Ciérrala antes de iniciar otra.');
            header('Location: ' . Url::to('/verificaciones'));
            exit;
        }

        $nombre = trim((string) $request->input('nombre'));
        $fechaInicio = (string) $request->input('fecha_inicio');

        if ($nombre === '' || $fechaInicio === '' || !strtotime($fechaInicio)) {
            Session::flash('error', 'Indica un nombre y una fecha de inicio válidos.');
            header('Location: ' . Url::to('/verificaciones/crear'));
            exit;
        }

        $id = JornadaVerificacion::crear([
            'institucion_id' => $institucionId,
            'nombre' => $nombre,
            'fecha_inicio' => $fechaInicio,
            'creada_por' => Auth::id(),
        ]);

        Session::flash('ok', 'Jornada de verificación iniciada. Ya puedes escanear los QR de los bienes para verificarlos.');
        header('Location: ' . Url::to("/verificaciones/{$id}"));
        exit;
    }

    public function mostrar(string $id): void
    {
        $jornada = $this->jornadaAccesible((int) $id);
        $institucionId = (int) $jornada['institucion_id'];

        View::layout('partials/layout', 'verificaciones/mostrar', [
            'title' => 'Jornada de verificación',
            'jornada' => $jornada,
            'universo' => Verificacion::contarUniverso($institucionId),
            'verificadosOk' => Verificacion::contarPorResultado((int) $jornada['id'], 'ok'),
            'verificadosDiscrepancia' => Verificacion::contarPorResultado((int) $jornada['id'], 'discrepancia'),
            'discrepancias' => Verificacion::listarDiscrepancias((int) $jornada['id']),
            'pendientes' => Verificacion::listarPendientes((int) $jornada['id'], $institucionId),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function cerrar(string $id): void
    {
        $id = (int) $id;
        $jornada = $this->jornadaAccesible($id);

        $request = new Request();
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/verificaciones/{$id}"));
            exit;
        }

        if ($jornada['estado'] === 'cerrada') {
            Session::flash('error', 'Esta jornada ya estaba cerrada.');
            header('Location: ' . Url::to("/verificaciones/{$id}"));
            exit;
        }

        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        JornadaVerificacion::cerrar($id, $observaciones);

        Session::flash('ok', 'Jornada de verificación cerrada.');
        header('Location: ' . Url::to("/verificaciones/{$id}"));
        exit;
    }

    /**
     * Registra el resultado de verificar un bien, desde su ficha pública /qr/{token}.
     * Requiere una jornada en progreso para la institución del bien, y — si quien
     * verifica es docente — que sea responsable del espacio donde el bien está asignado
     * (mismo criterio que el filtro de "solo mis bienes" en /bienes).
     */
    public function verificar(string $token): void
    {
        $bien = Bien::findPorToken($token);

        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $institucionId = (int) $bien['institucion_id'];

        if (!Auth::esSuperusuario() && $institucionId !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        if (Auth::rol() === 'docente' && !Bien::esResponsableDe((int) $bien['id'], (int) Auth::id())) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        $jornada = JornadaVerificacion::activaPara($institucionId);
        if (!$jornada) {
            Session::flash('error', 'No hay una jornada de verificación en progreso para esta institución.');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        $resultado = (string) $request->input('resultado');
        if (!in_array($resultado, ['ok', 'discrepancia'], true)) {
            Session::flash('error', 'Resultado de verificación no válido.');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        if ($resultado === 'discrepancia' && $observaciones === null) {
            Session::flash('error', 'Describe brevemente la discrepancia encontrada.');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        Verificacion::registrar((int) $jornada['id'], (int) $bien['id'], (int) Auth::id(), $resultado, $observaciones);

        Session::flash('ok', $resultado === 'ok'
            ? 'Bien verificado: coincide con el sistema.'
            : 'Discrepancia registrada para este bien.');
        header('Location: ' . Url::to("/qr/{$token}"));
        exit;
    }

    private function jornadaAccesible(int $id): array
    {
        $jornada = JornadaVerificacion::find($id);

        if (!$jornada) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $jornada['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        return $jornada;
    }
}
