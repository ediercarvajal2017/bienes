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
use App\Helpers\Uploader;
use App\Models\Asignacion;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Verificacion;

final class BajaController
{
    public function formulario(string $token): void
    {
        $bien = $this->bienAccesible($token);
        $this->verificarAdmiteBaja($bien);
        $verificacion = $this->verificacionDelBienDesdeQuery($bien);

        View::layout('partials/layout', 'bajas/form', [
            'title' => 'Reportar baja',
            'bien' => $bien,
            'token' => $token,
            'asignacion' => Asignacion::activaDe((int) $bien['id']),
            'verificacionId' => $verificacion['id'] ?? null,
            'descripcionSugerida' => $verificacion['observaciones'] ?? '',
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(string $token): void
    {
        $bien = $this->bienAccesible($token);
        $this->verificarAdmiteBaja($bien);

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/qr/{$token}/baja"));
            exit;
        }

        $estadoReportado = trim((string) $request->input('estado_reportado'));
        $ubicacion = trim((string) $request->input('ubicacion')) ?: null;
        $descripcion = trim((string) $request->input('descripcion'));

        if ($estadoReportado === '' || $descripcion === '') {
            Session::flash('error', 'Describe el estado del bien y el motivo de la baja.');
            header('Location: ' . Url::to("/qr/{$token}/baja"));
            exit;
        }

        // El id de verificacion viaja como campo oculto del formulario; se revalida aqui
        // igual que en formulario() para que nadie pueda vincular la baja a la discrepancia
        // de OTRO bien manipulando el valor a mano.
        $verificacionId = Verificacion::idValidoParaBien((int) $bien['id'], (string) $request->input('verificacion_id'));

        $fotoPath = null;
        try {
            if ($archivo = $request->file('foto')) {
                $fotoPath = Uploader::storeImage($archivo, 'bajas');
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            header('Location: ' . Url::to("/qr/{$token}/baja"));
            exit;
        }

        Baja::crear([
            'bien_id' => $bien['id'],
            'verificacion_id' => $verificacionId,
            'estado_reportado' => $estadoReportado,
            'ubicacion' => $ubicacion,
            'responsable_id' => Auth::id(),
            'descripcion' => $descripcion,
            'foto_path' => $fotoPath,
        ]);

        // Si la baja viene de una discrepancia reportada en una jornada, ya quedó atendida
        // — se ahorra al administrador el paso extra de volver a la jornada a marcarla.
        if ($verificacionId !== null) {
            Verificacion::marcarRevisada($verificacionId, (int) Auth::id());
        }

        Session::flash('ok', 'Reporte de baja enviado. Queda pendiente de aprobación.');
        header('Location: ' . Url::to("/qr/{$token}"));
        exit;
    }

    /**
     * Cuando se llega desde el botón "Dar de baja" de una discrepancia (?verificacion_id=),
     * trae esa verificación para precargar el formulario — solo si de verdad pertenece a
     * este bien, para no confiar ciegamente en un id que llega por la URL.
     */
    private function verificacionDelBienDesdeQuery(array $bien): ?array
    {
        $id = Verificacion::idValidoParaBien((int) $bien['id'], (string) ($_GET['verificacion_id'] ?? ''));

        return $id !== null ? Verificacion::find($id) : null;
    }

    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = Baja::contarListado($institucionId);

        View::layout('partials/layout', 'bajas/index', [
            'title' => 'Bajas de bienes',
            'bajas' => Baja::listar($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function aprobar(string $id): void
    {
        $baja = $this->bajaAccesible((int) $id);
        $this->verificarCsrf();

        $bien = Bien::find((int) $baja['bien_id']);
        if ($bien !== null) {
            $this->verificarAdmiteBaja($bien, redirigirA: '/bajas');
        }

        Baja::aprobar((int) $id);
        Bien::marcarDadoDeBaja((int) $baja['bien_id']);

        Session::flash('ok', 'Baja aprobada. El bien quedó marcado como dado de baja.');
        header('Location: ' . Url::to('/bajas'));
        exit;
    }

    public function rechazar(string $id): void
    {
        $this->bajaAccesible((int) $id);
        $this->verificarCsrf();

        Baja::eliminar((int) $id);

        Session::flash('ok', 'Reporte de baja descartado.');
        header('Location: ' . Url::to('/bajas'));
        exit;
    }

    private function verificarCsrf(): void
    {
        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/bajas'));
            exit;
        }
    }

    /**
     * Solo los bienes de la categoría "Sin cartera" admiten baja directa -- las demás
     * categorías pasan por el proceso formal de reintegro (ver MovimientoController::
     * reintegrar()). Se valida tanto al reportar (para no hacer perder tiempo llenando
     * un formulario que nunca se podrá aprobar) como al aprobar (para cubrir también los
     * reportes que ya estaban pendientes antes de esta regla).
     */
    private function verificarAdmiteBaja(array $bien, string $redirigirA = ''): void
    {
        if ($bien['categoria_nombre'] === Categoria::NOMBRE_CATEGORIA_PROTEGIDA) {
            return;
        }

        $mensaje = 'Este bien no admite baja directa — solo bienes en la categoría "'
            . Categoria::NOMBRE_CATEGORIA_PROTEGIDA
            . '" pueden darse de baja. Use Traslado, Reintegro o marque "En reparación".';

        Session::flash('error', $mensaje);
        header('Location: ' . Url::to($redirigirA !== '' ? $redirigirA : "/qr/{$bien['qr_token']}"));
        exit;
    }

    private function bienAccesible(string $token): array
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

        return $bien;
    }

    private function bajaAccesible(int $id): array
    {
        $baja = Baja::find($id);

        if (!$baja) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $baja['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        return $baja;
    }
}
