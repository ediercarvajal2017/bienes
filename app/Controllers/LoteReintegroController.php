<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\FechaMovimiento;
use App\Helpers\Paginador;
use App\Models\Auditoria;
use App\Models\LoteReintegro;
use App\Models\Movimiento;
use App\Models\Usuario;
use App\Services\LoteReintegroService;
use App\Services\ReporteService;

/**
 * Lotes de reintegro: agrupan reintegros ya registrados para entregarlos a la Alcaldía
 * con el comprobante FO-ADMI-009 (Excel). Cada lote pasa de "pendiente" a "reintegrado"
 * cuando el usuario confirma la entrega. Las URL siguen bajo /reintegros/lotes.
 */
final class LoteReintegroController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];
    private const PESTANAS = ['pendiente', 'reintegrado', 'unificado', 'todos'];

    public function lotes(): void
    {
        // A diferencia de institucionAListar() (ver más abajo), aquí sí se puede aplicar
        // el filtro del superusuario: esta pantalla solo LISTA lotes ya creados, no
        // alimenta la creación de uno nuevo.
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();

        $pestana = in_array($_GET['estado'] ?? '', self::PESTANAS, true) ? (string) $_GET['estado'] : 'pendiente';
        $categoria = mb_substr(trim((string) ($_GET['categoria'] ?? '')), 0, 100);
        $desde = trim((string) ($_GET['desde'] ?? ''));
        $hasta = trim((string) ($_GET['hasta'] ?? ''));
        $filtrosComunes = [
            'categoria' => $categoria !== '' ? $categoria : null,
            'desde' => FechaMovimiento::esValida($desde) ? $desde : null,
            'hasta' => FechaMovimiento::esValida($hasta) ? $hasta : null,
        ];
        $filtros = ['estado' => $pestana === 'todos' ? null : $pestana] + $filtrosComunes;

        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = LoteReintegro::contarListado($institucionId, $filtros);

        View::layout('partials/layout', 'reintegros/lotes', [
            'title' => 'Lotes de reintegro',
            'lotes' => LoteReintegro::listar($institucionId, $pagina, $porPagina, $filtros),
            'pestana' => $pestana,
            'filtros' => $filtrosComunes,
            'conteos' => LoteReintegro::contarPorEstado($institucionId, $filtrosComunes),
            'categorias' => LoteReintegro::categoriasUsadas($institucionId),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'pendientesDeLote' => Movimiento::contarPendientesDeLote($institucionId),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
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
            'puedeReabrir' => self::puedeReabrir(),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function loteFormato(string $id): void
    {
        $lote = LoteReintegro::find((int) $id);
        $this->verificarAccesoLote($lote);

        if ($lote['estado'] === 'unificado') {
            Session::flash('error', 'Este lote se unificó: sus bienes están ahora en los lotes nuevos. Descarga el comprobante de esos lotes.');
            header('Location: ' . Url::to('/reintegros/lotes/' . $id));
            exit;
        }

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

    /**
     * Marca como reintegrados (entregados a la Alcaldía) los lotes seleccionados, desde la
     * lista o desde el detalle de un lote. Todo o nada.
     */
    public function marcarReintegrados(): void
    {
        $request = new Request();
        $volverA = $this->volverA((string) $request->input('volver'));
        Csrf::verificarORedirigir($request, $volverA);

        $loteIds = LoteReintegroService::idsUnicos((array) $request->input('lotes', []));
        $fecha = trim((string) $request->input('fecha_entrega'));
        $nota = (string) $request->input('nota');

        try {
            $marcados = LoteReintegroService::marcarReintegrados($loteIds, $fecha, $nota);
            Session::flash('ok', $marcados === 1
                ? 'Lote marcado como reintegrado. Ya no aparece entre los pendientes.'
                : "{$marcados} lotes marcados como reintegrados. Ya no aparecen entre los pendientes.");
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $incidente = ErrorHandler::reportar($e, __METHOD__);
            Session::flash('error', 'Ocurrió un error al marcar los lotes. No se aplicó ningún cambio.' . self::textoIncidente($incidente));
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    /**
     * Devuelve a "pendiente" un lote marcado como reintegrado por error. Solo rector o
     * superusuario (la ruta exige asignaciones.crear, que también tiene el secretario; por
     * eso el rol se valida aquí, igual que al reactivar un bien).
     */
    public function reabrir(string $id): void
    {
        if (!self::puedeReabrir()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $request = new Request();
        $volverA = '/reintegros/lotes/' . (int) $id;
        Csrf::verificarORedirigir($request, $volverA);

        try {
            LoteReintegroService::reabrir((int) $id, (string) $request->input('motivo'));
            Session::flash('ok', 'El lote volvió a quedar pendiente.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $incidente = ErrorHandler::reportar($e, __METHOD__);
            Session::flash('error', 'Ocurrió un error al reabrir el lote. No se aplicó ningún cambio.' . self::textoIncidente($incidente));
        }

        header('Location: ' . Url::to($volverA));
        exit;
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

        Csrf::verificarORedirigir($request, '/reintegros/lotes/generar', $viejo);

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

        try {
            return Database::transaccion(static function () use ($gruposPorInstitucion, $descripcion, $observaciones): array {
                $lotesCreados = [];

                foreach ($gruposPorInstitucion as $institucionId => $idsMovimientos) {
                    $loteId = LoteReintegro::create([
                        'institucion_id' => $institucionId,
                        'fecha' => date('Y-m-d'),
                        'destino_texto' => $descripcion,
                        'observaciones' => $observaciones,
                        'registrado_por' => Auth::id(),
                    ]);

                    // Si otra solicitud ya agrupó alguno de estos reintegros entre la lectura y
                    // ahora, se deshace todo: nunca un reintegro en dos lotes ni un lote a medias.
                    if (LoteReintegro::asignarMovimientos($loteId, $idsMovimientos) !== count($idsMovimientos)) {
                        throw new \RuntimeException('Alguno de los reintegros ya fue agrupado en otro lote.');
                    }
                    Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'lote_reintegro', $loteId, null,
                        ['movimientos' => $idsMovimientos, 'descripcion' => $descripcion, 'observaciones' => $observaciones]);
                    $lotesCreados[] = $loteId;
                }

                return $lotesCreados;
            });
        } catch (\Throwable $e) {
            ErrorHandler::reportar($e, __METHOD__);

            return null;
        }
    }

    /**
     * @phpstan-assert array $lote
     */
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

    /**
     * Usada por pendientesDeLote() y crearLotesDesdeMovimientos() — a diferencia de
     * lotes() (arriba), estas alimentan la CREACIÓN de un lote nuevo, así que
     * deliberadamente no respetan el filtro del superusuario: si lo hicieran, un
     * movimiento fuera del filtro activo podría quedar descartado en silencio de la
     * agrupación en vez de agruparse, en vez de solo dejar de mostrarse.
     */
    private function institucionAListar(): ?int
    {
        return Auth::esSuperusuario() ? null : Auth::institucionId();
    }

    /** Reabrir un lote reintegrado: solo rector o superusuario. */
    public static function puedeReabrir(): bool
    {
        return Auth::esSuperusuario() || Auth::rol() === 'rector';
    }

    /**
     * A dónde volver después de marcar: a la lista (conservando los filtros) o al detalle
     * del lote. Solo rutas de /reintegros/lotes, nunca una URL externa.
     */
    private function volverA(string $volver): string
    {
        return preg_match('#^/reintegros/lotes(/\d+)?(\?[A-Za-z0-9_=&%+\-.\[\]]*)?$#', $volver) === 1 ? $volver : '/reintegros/lotes';
    }

    private static function textoIncidente(string $incidente): string
    {
        return " (código de incidente: {$incidente})";
    }
}
