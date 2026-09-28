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
use App\Models\JornadaVerificacion;
use App\Models\SolicitudReintegro;
use App\Models\Verificacion;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;

final class QrController
{
    public function mostrar(string $token): void
    {
        $bien = Bien::findPorToken($token);

        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $puedeGestionar = Auth::check()
            && (Auth::esSuperusuario() || (int) $bien['institucion_id'] === Auth::institucionId());

        $jornadaActiva = $puedeGestionar ? JornadaVerificacion::activaPara((int) $bien['institucion_id']) : null;
        $puedeVerificar = $jornadaActiva !== null
            && (Auth::rol() !== 'docente' || Bien::esResponsableDe((int) $bien['id'], (int) Auth::id()));
        $verificacionActual = $jornadaActiva !== null
            ? Verificacion::deBienEnJornada((int) $jornadaActiva['id'], (int) $bien['id'])
            : null;

        View::render('qr/mostrar', [
            'bien' => $bien,
            'token' => $token,
            'asignacion' => Asignacion::activaDe((int) $bien['id']),
            'puedeGestionar' => $puedeGestionar,
            'puedeReportarBaja' => Auth::check()
                && (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear'))
                && $bien['categoria_nombre'] === Categoria::NOMBRE_CATEGORIA_PROTEGIDA,
            'puedeSolicitarReintegro' => $this->puedeSolicitarReintegro($bien, $puedeGestionar),
            'solicitudReintegroPendiente' => $puedeGestionar && SolicitudReintegro::tienePendiente((int) $bien['id']),
            'jornadaActiva' => $jornadaActiva,
            'puedeVerificar' => $puedeVerificar,
            'verificacionActual' => $verificacionActual,
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    /**
     * "Solicitar reintegro" se muestra a quien puede pedirlo pero no reintegrar
     * directamente (el docente): si el bien está a su cargo, cumple la regla de reintegro
     * y no tiene ya una solicitud pendiente. Quien sí reintegra (rector, secretario) lo
     * hace desde "Gestionar este bien".
     */
    private function puedeSolicitarReintegro(array $bien, bool $puedeGestionar): bool
    {
        if (!$puedeGestionar || Auth::esSuperusuario() || !Auth::tienePermiso('reintegros.solicitar')
            || Auth::tienePermiso('asignaciones.crear')) {
            return false;
        }

        $bienId = (int) $bien['id'];

        return Bien::esResponsableDe($bienId, (int) Auth::id())
            && Bien::motivoNoReintegrable($bien, Asignacion::activaDe($bienId) !== null) === null
            && !SolicitudReintegro::tienePendiente($bienId);
    }

    /**
     * Botón "Confirmar etiquetado" en /qr/{token}: quien acaba de pegar la etiqueta
     * física la escanea y, si lo que ve en pantalla coincide con lo que tiene enfrente,
     * confirma — deja constancia de que ESA etiqueta específica quedó pegada en el bien
     * correcto (no solo que el QR fue generado). Ver Bien::confirmarEtiqueta().
     */
    public function confirmarEtiqueta(string $token): void
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

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        if ($bien['qr_impreso_en'] === null) {
            Session::flash('error', 'Todavía no se ha generado la etiqueta de este bien — imprímela primero desde "Generar QR masivo".');
            header('Location: ' . Url::to("/qr/{$token}"));
            exit;
        }

        if ($bien['qr_confirmado_en'] === null) {
            Bien::confirmarEtiqueta((int) $bien['id'], (int) Auth::id());
            Auditoria::registrar(Auth::id(), $institucionId, 'confirmar_qr', 'bien', (int) $bien['id'], null, [
                'codigo_identificacion' => $bien['codigo_identificacion'],
            ]);
        }

        Session::flash('ok', 'Etiqueta confirmada: coincide con este bien.');
        header('Location: ' . Url::to("/qr/{$token}"));
        exit;
    }

    /**
     * PNG del código QR (ruta pública, sin sesión). Se genera una sola vez y se guarda en
     * storage/cache/qr: antes cada visita consultaba la base y volvía a dibujarlo, y muchas
     * peticiones seguidas podían agotar los procesos del hosting. El token es un UUID
     * (se valida el formato antes de usarlo como nombre de archivo).
     */
    public function imagen(string $token): void
    {
        session_write_close();

        if (!preg_match('/^[a-f0-9-]{36}$/', $token)) {
            http_response_code(404);
            exit;
        }

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $carpeta = $config['storage_path'] . '/cache/qr';
        $archivo = "{$carpeta}/{$token}.png";

        if (!is_file($archivo)) {
            if (!Bien::findPorToken($token)) {
                http_response_code(404);
                exit;
            }
            Database::desconectar();

            $png = (new Builder(
                writer: new PngWriter(),
                data: Url::absoluta("/qr/{$token}"),
                size: 400,
                margin: 12,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255),
            ))->build()->getString();

            if (!is_dir($carpeta)) {
                @mkdir($carpeta, 0755, true);
            }
            // Escritura atómica: otro proceso nunca lee un PNG a medio escribir.
            $temporal = $archivo . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($temporal, $png) !== false) {
                @rename($temporal, $archivo);
            }

            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=86400');
            echo $png;
            exit;
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($archivo));
        readfile($archivo);
        exit;
    }
}
