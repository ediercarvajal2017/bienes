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
use App\Helpers\Paginador;
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Institucion;
use App\Models\LoteReintegro;
use App\Models\Movimiento;
use App\Models\Usuario;
use App\Services\ReporteService;

final class ReintegroController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = $this->institucionSeleccionada();

        $q = trim((string) ($_GET['q'] ?? ''));
        $termino = $q !== '' ? $q : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        // "Ver solo seleccionados": la seleccion vive en sessionStorage del navegador
        // (abarca varias paginas), asi que el filtro llega como una lista de ids en la URL.
        $soloSeleccionados = isset($_GET['seleccionados']);
        $idsSeleccionados = $soloSeleccionados
            ? array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['seleccionados'])))))
            : null;

        $total = $institucionId !== null ? Bien::contarReintegrables($institucionId, $termino, $idsSeleccionados) : 0;

        View::layout('partials/layout', 'reintegros/index', [
            'title' => 'Reintegrar bienes',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,

            'bienes' => $institucionId !== null ? Bien::reintegrables($institucionId, $termino, $pagina, $porPagina, $idsSeleccionados) : [],
            'q' => $q,
            'pagina' => $pagina,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'soloSeleccionados' => $soloSeleccionados,
            'idsSeleccionados' => $idsSeleccionados ?? [],

            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $volverA = '/reintegros' . (Auth::esSuperusuario() ? '?institucion=' . (int) $request->input('institucion_id') : '');

        $bienIds = array_unique(array_map('intval', (array) $request->input('bienes', [])));
        $fecha = (string) $request->input('fecha');
        $destino = trim((string) $request->input('destino_texto'));
        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        $viejo = ['fecha' => $fecha, 'destino_texto' => $destino, 'observaciones' => $observaciones];

        $this->verificarCsrf($request, $volverA, $viejo);

        if (empty($bienIds)) {
            Session::flash('error', 'Selecciona al menos un bien para reintegrar.');
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        if ($fecha === '' || !strtotime($fecha) || $destino === '') {
            Session::flash('error', 'Indica la fecha y el destino del reintegro.');
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $reintegrados = $this->reintegrarLote($bienIds, $fecha, $destino, $observaciones);

        if ($reintegrados === null) {
            Session::flash('error', 'Ocurrió un error al procesar el reintegro masivo. No se aplicó ningún cambio.');
            Session::flashOld($viejo);
        } elseif ($reintegrados === 0) {
            Session::flash('error', 'Ningún bien seleccionado pudo reintegrarse. Verifica que sigan asignados.');
            Session::flashOld($viejo);
        } else {
            Session::flash('ok', $reintegrados . ' bien(es) reintegrado(s) correctamente. Cuando quieras, agrúpalos en un lote desde "Lotes de reintegro" para generar el formato.');
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    /**
     * Usado por el escaneo de QR en /reintegros: dado un token (o la URL completa que
     * trae el QR impreso), busca el bien y valida que sea reintegrable antes de dejar que
     * el frontend lo agregue a la selección. Respuesta JSON, sin CSRF por ser de solo
     * lectura (no modifica nada).
     */
    public function buscarPorQr(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $institucionId = $this->institucionSeleccionada();
        if ($institucionId === null) {
            echo json_encode(['ok' => false, 'mensaje' => 'Selecciona una institución primero.']);
            return;
        }

        $crudo = trim((string) ($_GET['token'] ?? ''));
        if ($crudo === '') {
            echo json_encode(['ok' => false, 'mensaje' => 'Código vacío.']);
            return;
        }

        $bien = Bien::buscarReintegrablePorToken($this->extraerToken($crudo), $institucionId);
        if (!$bien) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Ese código no corresponde a un bien reintegrable de esta institución (verifica que tenga una asignación activa).',
            ]);
            return;
        }

        echo json_encode([
            'ok' => true,
            'id' => (int) $bien['id'],
            'codigo' => $bien['codigo_identificacion'],
            'descripcion' => $bien['descripcion'],
            'espacio' => $bien['espacio_nombre'],
            'responsables' => $bien['responsables_nombres'],
        ]);
    }

    public function lotes(): void
    {
        $institucionId = $this->institucionAListar();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = LoteReintegro::contarListado($institucionId);

        View::layout('partials/layout', 'reintegros/lotes', [
            'title' => 'Lotes de reintegro',
            'lotes' => LoteReintegro::listar($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'pendientesDeLote' => Movimiento::contarPendientesDeLote($institucionId),
        ]);
    }

    public function loteDetalle(string $id): void
    {
        $lote = LoteReintegro::find((int) $id);
        $this->verificarAccesoLote($lote);

        View::layout('partials/layout', 'reintegros/lote_detalle', [
            'title' => 'Lote de reintegro',
            'lote' => $lote,
            'bienes' => LoteReintegro::bienesDe((int) $id),
        ]);
    }

    public function loteFormato(string $id): void
    {
        $lote = LoteReintegro::find((int) $id);
        $this->verificarAccesoLote($lote);

        $rector = Usuario::rectorDe((int) $lote['institucion_id']);
        if ($rector === null) {
            Session::flash('error', 'Esta institución no tiene un rector activo registrado; no se puede generar el comprobante oficial. Registra o activa un usuario con rol Rector antes de descargarlo.');
            header('Location: ' . Url::to('/reintegros/lotes/' . $id));
            exit;
        }

        $bienes = LoteReintegro::bienesDe((int) $id);
        ReporteService::enviarXlsx(
            ReporteService::comprobanteReintegroLote($lote, $bienes, $rector),
            'comprobante_reintegro_lote_' . $id
        );
    }

    public function pendientesDeLote(): void
    {
        $institucionId = $this->institucionAListar();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = Movimiento::contarPendientesDeLote($institucionId);

        View::layout('partials/layout', 'reintegros/generar_lote', [
            'title' => 'Generar lote de reintegro',
            'pendientes' => Movimiento::pendientesDeLote($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function generarLote(): void
    {
        $request = new Request();

        $movimientoIds = array_unique(array_map('intval', (array) $request->input('movimientos', [])));
        $descripcion = trim((string) $request->input('descripcion')) ?: null;
        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        $viejo = ['movimientos' => $movimientoIds, 'descripcion' => $descripcion, 'observaciones' => $observaciones];

        $this->verificarCsrf($request, '/reintegros/lotes/generar', $viejo);

        if (empty($movimientoIds)) {
            Session::flash('error', 'Selecciona al menos un reintegro para agrupar en el lote.');
            Session::flashOld($viejo);
            header('Location: ' . Url::to('/reintegros/lotes/generar'));
            exit;
        }

        $lotesCreados = $this->crearLotesDesdeMovimientos($movimientoIds, $descripcion, $observaciones);

        if ($lotesCreados === null) {
            Session::flash('error', 'Ocurrió un error al generar el lote. No se aplicó ningún cambio.');
            Session::flashOld($viejo);
            header('Location: ' . Url::to('/reintegros/lotes/generar'));
            exit;
        }

        if (empty($lotesCreados)) {
            Session::flash('error', 'Ningún reintegro seleccionado pudo agruparse (verifica que sigan sin lote y que tengas acceso).');
            Session::flashOld($viejo);
            header('Location: ' . Url::to('/reintegros/lotes/generar'));
            exit;
        }

        Session::flash('ok', count($lotesCreados) === 1 ? 'Lote de reintegro generado. Ya puedes descargar el formato.' : count($lotesCreados) . ' lotes generados (uno por institución).');
        header('Location: ' . Url::to(
            count($lotesCreados) === 1 ? '/reintegros/lotes/' . $lotesCreados[0] : '/reintegros/lotes'
        ));
        exit;
    }

    /**
     * Agrupa bienes en un reintegro masivo dentro de una única transacción (todo o nada
     * ante un error de BD). Los bienes que ya no cumplen las condiciones (no asignados,
     * de otra institución, sin categoría) se omiten en silencio; se cuenta solo lo que sí
     * se procesó. El lote se genera después, como acción manual aparte.
     */
    private function reintegrarLote(array $bienIds, string $fecha, string $destino, ?string $observaciones): ?int
    {
        $pdo = Database::connection();
        $reintegrados = 0;

        $pdo->beginTransaction();
        try {
            foreach ($bienIds as $bienId) {
                $bien = Bien::find($bienId);
                if (!$bien || $bien['estado'] !== 'activo') {
                    continue;
                }

                if (!Auth::esSuperusuario() && (int) $bien['institucion_id'] !== Auth::institucionId()) {
                    continue;
                }

                if ($bien['categoria_id'] === null) {
                    continue;
                }

                $asignacion = Asignacion::activaDe($bienId);
                if (!$asignacion) {
                    continue;
                }

                Movimiento::crear([
                    'bien_id' => $bienId,
                    'tipo' => 'reintegro',
                    'fecha' => $fecha,
                    'responsable_id' => Auth::id(),
                    'espacio_origen_id' => $asignacion['espacio_id'] ?? null,
                    'espacio_destino_id' => null,
                    'destino_texto' => $destino,
                    'observaciones' => $observaciones,
                ]);

                Asignacion::cerrarActivasDe($bienId);
                Bien::update($bienId, [
                    'codigo_identificacion' => $bien['codigo_identificacion'],
                    'descripcion' => $bien['descripcion'],
                    'marca' => $bien['marca'],
                    'categoria_id' => $bien['categoria_id'],
                    'fecha_ingreso' => $bien['fecha_ingreso'],
                    'valor' => $bien['valor'],
                    'tiene_factura' => $bien['tiene_factura'],
                    'estado' => 'reintegrado',
                ]);

                $reintegrados++;
            }

            $pdo->commit();

            return $reintegrados;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return null;
        }
    }

    /**
     * Crea un lote y reasigna los movimientos de reintegro seleccionados que todavía no
     * pertenecen a ningún lote y a los que el usuario tiene acceso. Los agrupa por
     * institución (un superusuario podría, en teoría, seleccionar movimientos de varias)
     * y crea un lote por cada grupo. Si ninguno califica, devuelve un arreglo vacío
     * (no null: null solo indica un error real de BD).
     */
    private function crearLotesDesdeMovimientos(array $movimientoIds, ?string $descripcion, ?string $observaciones): ?array
    {
        $pendientes = Movimiento::pendientesDeLoteTodos($this->institucionAListar());
        $pendientesPorId = [];
        foreach ($pendientes as $p) {
            $pendientesPorId[(int) $p['id']] = $p;
        }

        $validos = array_values(array_intersect_key($pendientesPorId, array_flip($movimientoIds)));
        if (empty($validos)) {
            return [];
        }

        $gruposPorInstitucion = [];
        foreach ($validos as $movimiento) {
            $gruposPorInstitucion[(int) $movimiento['institucion_id']][] = (int) $movimiento['id'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $lotesCreados = [];

            foreach ($gruposPorInstitucion as $institucionId => $idsMovimientos) {
                $loteId = LoteReintegro::create([
                    'institucion_id' => $institucionId,
                    'fecha' => date('Y-m-d'),
                    'destino_texto' => $descripcion,
                    'observaciones' => $observaciones,
                    'registrado_por' => Auth::id(),
                ]);

                LoteReintegro::asignarMovimientos($loteId, $idsMovimientos);
                $lotesCreados[] = $loteId;
            }

            $pdo->commit();

            return $lotesCreados;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return null;
        }
    }

    private function verificarAccesoLote(?array $lote): void
    {
        if (!$lote) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $lote['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    private function institucionAListar(): ?int
    {
        return Auth::esSuperusuario() ? null : Auth::institucionId();
    }

    /**
     * A diferencia de institucionAListar() (usada por los lotes, que sí pueden listarse
     * "todas las instituciones" para un superusuario), la pantalla de reintegro necesita
     * una institución concreta para operar — igual que /asignaciones.
     */
    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $solicitada = $_GET['institucion'] ?? '';

        return $solicitada !== '' ? (int) $solicitada : null;
    }

    /**
     * El QR impreso codifica la URL pública completa (/qr/{token}); el escáner también
     * admite escribir o pegar solo el token a mano, así que se acepta cualquiera de las
     * dos formas.
     */
    private function extraerToken(string $texto): string
    {
        if (!str_contains($texto, '/qr/')) {
            return $texto;
        }

        $resto = substr($texto, strpos($texto, '/qr/') + 4);
        $token = explode('/', trim($resto, '/'))[0];

        return $token !== '' ? $token : $texto;
    }

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso cada llamador ya construye $viejo ANTES de este chequeo y lo pasa aquí, para
     * que el usuario no pierda los datos solo porque el token expiró mientras llenaba
     * el formulario.
     */
    private function verificarCsrf(Request $request, string $volverA = '/reintegros', array $datosAConservar = []): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            if (!empty($datosAConservar)) {
                Session::flashOld($datosAConservar);
            }
            header('Location: ' . Url::to($volverA));
            exit;
        }
    }
}
