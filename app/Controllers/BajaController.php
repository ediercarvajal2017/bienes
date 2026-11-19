<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\ErrorGuiado;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Helpers\Uploader;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Verificacion;
use App\Services\CicloVidaBien;

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
        ]);
    }

    public function guardar(string $token): void
    {
        $bien = $this->bienAccesible($token);
        $this->verificarAdmiteBaja($bien);

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to("/qr/{$token}/baja"));
            exit;
        }

        // Datos incompletos: se vuelve al formulario con los campos marcados (las demás reglas
        // de CicloVidaBien::reportarBaja vuelven a la ficha del QR).
        $faltan = [];
        if (trim((string) $request->input('estado_reportado')) === '') {
            $faltan['estado_reportado'] = 'Escribe en qué estado está.';
        }
        if (trim((string) $request->input('descripcion')) === '') {
            $faltan['descripcion'] = 'Cuenta qué pasó.';
        }
        if ($faltan !== []) {
            Aviso::enviar(Aviso::error('No se envió el reporte de baja', 'Faltan el estado del bien o el motivo de la baja.',
                'Escribe en qué estado está (por ejemplo «Dañado») y qué pasó; luego vuelve a enviar.', null, $faltan));
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
            CicloVidaBien::reportarBaja(
                $bien,
                (string) $request->input('estado_reportado'),
                trim((string) $request->input('ubicacion')) ?: null,
                (string) $request->input('descripcion'),
                $fotoPath,
                $verificacionId
            );
        } catch (\DomainException $e) {
            // Ya dado de baja o con un reporte pendiente: se vuelve a la ficha del QR.
            Aviso::enviar(Aviso::desdeExcepcion($e, 'No se envió el reporte de baja', 'Revisa el estado del bien en su ficha.'));
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        } catch (\RuntimeException $e) {
            Aviso::enviar(Aviso::error('No se envió el reporte de baja', 'La foto no se pudo guardar: ' . $e->getMessage(),
                'Elige otra foto que cumpla esa condición y vuelve a enviar.', null, ['foto' => 'Elige otra foto.']));
            header('Location: ' . Url::to("/qr/{$token}/baja"));
            exit;
        }

        Aviso::enviar(Aviso::exito('Reporte de baja enviado.', 'Queda pendiente hasta que el rector lo apruebe en «Bajas».'));
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
        // Quien solo reporta (p. ej. un docente) ve sus propios reportes; quien aprueba
        // bajas ve todos los de la institución. Antes un docente veía todas las bajas.
        $soloDe = (Auth::esSuperusuario() || Auth::tienePermiso('bajas.aprobar')) ? null : Auth::id();
        $total = Baja::contarListado($institucionId, $soloDe);

        View::layout('partials/layout', 'bajas/index', [
            'title' => 'Bajas de bienes',
            'bajas' => Baja::listar($institucionId, $pagina, $porPagina, $soloDe, ...\App\Helpers\OrdenListado::desdeConsulta(Baja::ORDENES)),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
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

        try {
            Database::transaccion(static function () use ($id, $baja, $bien): void {
                // La condición "sigue pendiente" va en el UPDATE: una baja no se aprueba dos veces.
                if (!Baja::aprobarSiPendiente((int) $id, (int) Auth::id())) {
                    throw new ErrorGuiado('Otra persona ya resolvió este reporte de baja.', 'Recarga la lista para ver cómo quedó.');
                }

                $bienId = (int) $baja['bien_id'];
                // El bien deja de existir físicamente: ya no tiene ubicación ni responsable
                // (antes quedaba dado de baja pero todavía asignado a un espacio).
                Asignacion::cerrarActivasDe($bienId);
                Bien::cambiarEstado($bienId, 'dado_de_baja');

                Auditoria::registrar(Auth::id(), (int) $baja['institucion_id'], 'aprobar', 'baja', (int) $id,
                    ['estado' => 'pendiente', 'estado_bien' => $bien['estado'] ?? null],
                    ['estado' => 'aprobada', 'estado_bien' => 'dado_de_baja', 'bien_id' => $bienId]);
            });
        } catch (\DomainException $e) {
            Aviso::enviar(Aviso::desdeExcepcion($e, 'No se aprobó la baja', 'Recarga la lista y vuelve a intentarlo.'));
            header('Location: ' . Url::to('/bajas'));
            exit;
        }

        Aviso::enviar(Aviso::exito('Baja aprobada.', 'El bien quedó dado de baja, sin espacio ni responsable; su historial queda en la ficha.'));
        header('Location: ' . Url::to('/bajas'));
        exit;
    }

    /**
     * Rechazar ya no borra el reporte (antes se perdía el historial): queda "rechazada",
     * con el motivo, quién y cuándo. El bien sigue en su estado.
     */
    public function rechazar(string $id): void
    {
        $baja = $this->bajaAccesible((int) $id);
        $this->verificarCsrf();

        $motivo = trim((string) (new Request())->input('motivo_rechazo'));
        if ($motivo === '') {
            Aviso::enviar(Aviso::error('No se rechazó el reporte de baja', 'Falta el motivo del rechazo.', 'Escribe por qué se rechaza: quien lo reportó lo verá.'));
            header('Location: ' . Url::to('/bajas'));
            exit;
        }

        if (!Baja::rechazarSiPendiente((int) $id, (int) Auth::id(), mb_substr($motivo, 0, 500))) {
            Aviso::enviar(Aviso::error('No se rechazó el reporte de baja', 'Otra persona ya resolvió este reporte.', 'Recarga la lista para ver cómo quedó.'));
            header('Location: ' . Url::to('/bajas'));
            exit;
        }

        Auditoria::registrar(Auth::id(), (int) $baja['institucion_id'], 'rechazar', 'baja', (int) $id,
            ['estado' => 'pendiente'], ['estado' => 'rechazada', 'motivo' => $motivo]);

        Aviso::enviar(Aviso::exito('Reporte de baja rechazado.', 'El bien sigue en su estado actual y quien lo reportó verá el motivo.'));
        header('Location: ' . Url::to('/bajas'));
        exit;
    }

    private function verificarCsrf(): void
    {
        Csrf::verificarORedirigir(new Request(), '/bajas');
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

        Aviso::enviar(Aviso::error('No se puede dar de baja', 'El bien está en la cartera de la Alcaldía, y solo los bienes «'
            . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '» se dan de baja.',
            'Si ya no se usa, reintégralo; si está dañado, cambia su estado a «En reparación» en su ficha.',
            Auth::esSuperusuario() || Auth::tienePermiso('bienes.editar') ? Aviso::accion('Abrir el bien', '/bienes/' . (int) $bien['id'] . '/editar') : null));
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
