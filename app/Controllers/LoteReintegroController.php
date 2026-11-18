<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Database;
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
        ]);
    }

    public function loteDetalle(string $id): void
    {
        $lote = LoteReintegro::find((int) $id);
        $this->verificarAccesoLote($lote);

        $bienes = LoteReintegro::bienesDe((int) $id);
        $categorias = array_unique(array_map(static fn (array $b): string => (string) ($b['categoria_nombre'] ?? ''), $bienes));

        View::layout('partials/layout', 'reintegros/lote_detalle', [
            'title' => 'Lote de reintegro',
            'lote' => $lote,
            'bienes' => $bienes,
            // Un lote pendiente con varias categorías se puede separar (un lote por categoría).
            'separable' => $lote['estado'] === 'pendiente' && count($categorias) > 1,
            'origenes' => LoteReintegro::origenesDe((int) $id),
            'destinos' => LoteReintegro::destinosDe((int) $id),
            'puedeReabrir' => self::puedeReabrir(),
        ]);
    }

    /**
     * Vista previa de la unificación de los lotes elegidos (?lotes[]=3&lotes[]=5): qué
     * lotes nuevos saldrían, uno por categoría. No cambia nada.
     */
    public function unificarPrevia(): void
    {
        $loteIds = LoteReintegroService::idsUnicos((array) ($_GET['lotes'] ?? []));

        try {
            $previa = LoteReintegroService::previsualizarUnificacion($loteIds);
        } catch (\DomainException $e) {
            Aviso::enviar(Aviso::desdeExcepcion($e, 'No se pudo preparar la unificación', 'Marca de nuevo los lotes en la lista y vuelve a intentarlo.'));
            header('Location: ' . Url::to('/reintegros/lotes'));
            exit;
        }

        View::layout('partials/layout', 'reintegros/lote_unificar', [
            'title' => count($loteIds) === 1 ? 'Separar lote por categoría' : 'Unificar lotes',
            'loteIds' => $loteIds,
            'lotes' => $previa['lotes'],
            'grupos' => $previa['grupos'],
            'bienesPorLote' => $previa['bienesPorLote'],
            'descripcionSugerida' => LoteReintegroService::descripcionUnificacion($loteIds),
            'viejo' => Session::pullOld(),
        ]);
    }

    /** Confirma la unificación: lotes nuevos por categoría; los de origen quedan "unificado". */
    public function unificar(): void
    {
        $request = new Request();
        $loteIds = LoteReintegroService::idsUnicos((array) $request->input('lotes', []));
        $descripcion = (string) $request->input('descripcion');
        $observaciones = (string) $request->input('observaciones');
        $volverA = '/reintegros/lotes/unificar?' . http_build_query(['lotes' => $loteIds]);
        Csrf::verificarORedirigir($request, $volverA);

        try {
            $creados = LoteReintegroService::unificar($loteIds, $descripcion, $observaciones);
        } catch (\Throwable $e) {
            $this->volverAUnificar($volverA, Aviso::desdeExcepcion($e, 'No se unificaron los lotes', 'Revisa los lotes marcados y vuelve a intentarlo.'), $descripcion, $observaciones);
        }

        if (count($creados) === 1) {
            Aviso::enviar(Aviso::exito('Lotes unificados en el lote ' . LoteReintegroService::resumenLotes($creados) . '.',
                'Siguiente paso: descarga su comprobante en Excel para entregarlo.'));
            header('Location: ' . Url::to('/reintegros/lotes/' . $creados[0]['id']));
            exit;
        }

        Aviso::enviar(Aviso::exito(count($creados) . ' lotes nuevos, uno por categoría: ' . LoteReintegroService::resumenLotes($creados) . '.',
            'Siguiente paso: abre cada lote y descarga su comprobante en Excel.'));
        header('Location: ' . Url::to('/reintegros/lotes?estado=pendiente'));
        exit;
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volverAUnificar(string $volverA, array $aviso, string $descripcion, string $observaciones): never
    {
        Aviso::enviar($aviso);
        Session::flashOld(['descripcion' => $descripcion, 'observaciones' => $observaciones]);
        header('Location: ' . Url::to($volverA));
        exit;
    }

    public function loteFormato(string $id): void
    {
        $lote = LoteReintegro::find((int) $id);
        $this->verificarAccesoLote($lote);

        if ($lote['estado'] === 'unificado') {
            Aviso::enviar(Aviso::error('No se descargó el comprobante', 'Este lote se unificó: sus bienes están ahora en los lotes nuevos.',
                'Abre los lotes nuevos (aparecen en este detalle) y descarga el comprobante de cada uno.'));
            header('Location: ' . Url::to('/reintegros/lotes/' . $id));
            exit;
        }

        $rector = Usuario::rectorDe((int) $lote['institucion_id']);
        if ($rector === null) {
            Aviso::enviar(Aviso::error('No se descargó el comprobante', 'El comprobante oficial lleva la firma del rector, y la institución no tiene un rector activo.',
                'Registra o activa en Usuarios un usuario con rol Rector y vuelve a descargarlo.',
                Auth::esSuperusuario() || Auth::tienePermiso('usuarios.ver') ? Aviso::accion('Ir a Usuarios', '/usuarios') : null));
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
            Aviso::enviar(Aviso::exito($marcados === 1 ? 'Lote marcado como reintegrado.' : "{$marcados} lotes marcados como reintegrados.",
                'Ya no aparecen entre los pendientes; los encuentras en la pestaña «Reintegrados».'));
        } catch (\Throwable $e) {
            Aviso::enviar(Aviso::desdeExcepcion($e, 'No se marcaron los lotes', 'Revisa los lotes marcados y la fecha de entrega y vuelve a intentarlo.'));
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
            Aviso::enviar(Aviso::exito('El lote volvió a quedar pendiente.', 'Corrige lo que haga falta y vuelve a marcarlo como reintegrado cuando lo entregues.'));
        } catch (\Throwable $e) {
            Aviso::enviar(Aviso::desdeExcepcion($e, 'No se reabrió el lote', 'Revisa el motivo y vuelve a intentarlo.'));
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
            'viejo' => Session::pullOld(),
        ]);
    }

    /**
     * Agrupa los reintegros sueltos seleccionados en lotes: uno por categoría (Muebles,
     * Tecnología…), cada uno con su comprobante FO-ADMI-009.
     */
    public function generarLote(): void
    {
        $request = new Request();

        $movimientoIds = LoteReintegroService::idsUnicos((array) $request->input('movimientos', []));
        $descripcion = trim((string) $request->input('descripcion'));
        $observaciones = trim((string) $request->input('observaciones'));
        $viejo = ['movimientos' => $movimientoIds, 'descripcion' => $descripcion, 'observaciones' => $observaciones];

        Csrf::verificarORedirigir($request, '/reintegros/lotes/generar', $viejo);

        try {
            $creados = LoteReintegroService::generarPorCategoria($this->institucionAListar(), $movimientoIds, $descripcion, $observaciones);
        } catch (\Throwable $e) {
            $this->volverAGenerar(Aviso::desdeExcepcion($e, 'No se generó el lote', 'Revisa los reintegros marcados y vuelve a pulsar «Generar lote».'), $viejo);
        }

        if (count($creados) === 1) {
            Aviso::enviar(Aviso::exito('Lote ' . LoteReintegroService::resumenLotes($creados) . ' generado.',
                'Siguiente paso: descarga el comprobante en Excel y, cuando entregues los bienes, márcalo como reintegrado.'));
            header('Location: ' . Url::to('/reintegros/lotes/' . $creados[0]['id']));
            exit;
        }

        Aviso::enviar(Aviso::exito('Se generaron ' . count($creados) . ' lotes, uno por categoría: ' . LoteReintegroService::resumenLotes($creados) . '.',
            'Siguiente paso: abre cada lote y descarga su comprobante en Excel.'));
        header('Location: ' . Url::to('/reintegros/lotes?estado=pendiente'));
        exit;
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volverAGenerar(array $aviso, array $viejo): never
    {
        Aviso::enviar($aviso);
        Session::flashOld($viejo);
        header('Location: ' . Url::to('/reintegros/lotes/generar'));
        exit;
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
     * Usada por pendientesDeLote() y generarLote() — a diferencia de
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

}
