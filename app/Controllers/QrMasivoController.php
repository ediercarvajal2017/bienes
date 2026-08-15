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
use App\Models\Institucion;

final class QrMasivoController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];
    private const FORMATOS_VALIDOS = ['hoja', 'etiqueta'];

    public function formulario(): void
    {
        $this->verificarAcceso();

        $institucionId = $this->institucionSeleccionada();
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $terminoBusqueda = $busqueda !== '' ? $busqueda : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        // "Imprimir QR ahora" (ver BienController) llega con los bienes recién
        // creados/editados ya elegidos en la URL, para no tener que buscarlos a mano.
        $idsSeleccionados = isset($_GET['seleccionados'])
            ? array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['seleccionados'])))))
            : null;
        $soloPendientes = isset($_GET['pendientes']);

        $total = $institucionId !== null
            ? Bien::contarListado($institucionId, $terminoBusqueda, false, null, null, null, $soloPendientes, $idsSeleccionados)
            : 0;
        $totalPendientes = $institucionId !== null ? Bien::contarListado($institucionId, null, false, null, null, null, true) : 0;

        View::layout('partials/layout', 'bienes/qr_masivo', [
            'title' => 'Generar QR masivo',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'bienes' => $institucionId !== null
                ? Bien::listar($institucionId, $terminoBusqueda, $pagina, $porPagina, false, null, null, null, $soloPendientes, $idsSeleccionados)
                : [],
            'busqueda' => $busqueda,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'error' => Session::pullFlash('error'),
            'idsSeleccionados' => $idsSeleccionados ?? [],
            'soloPendientes' => $soloPendientes,
            'totalPendientes' => $totalPendientes,
        ]);
    }

    public function generar(): void
    {
        $this->verificarAcceso();

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/bienes/qr-masivo'));
            exit;
        }

        $institucionId = $this->institucionSeleccionada();
        if ($institucionId === null) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/bienes/qr-masivo'));
            exit;
        }

        if ((string) $request->input('todos_filtrados') === '1') {
            $busqueda = trim((string) $request->input('busqueda'));
            $bienes = Bien::listar($institucionId, $busqueda !== '' ? $busqueda : null, 1, 0);
        } else {
            $idsSeleccionados = array_map('intval', (array) $request->input('bienes', []));
            $bienes = Bien::listarPorIds($institucionId, $idsSeleccionados);
        }

        if (empty($bienes)) {
            Session::flash('error', 'Selecciona al menos un bien para generar sus códigos QR.');
            header('Location: ' . Url::to('/bienes/qr-masivo'));
            exit;
        }

        $formato = (string) $request->input('formato', 'hoja');
        if (!in_array($formato, self::FORMATOS_VALIDOS, true)) {
            $formato = 'hoja';
        }

        // El nombre se trae una sola vez por lote (no por bien): esta pantalla siempre
        // trabaja con una única institución a la vez.
        $institucion = $formato === 'etiqueta' ? Institucion::find($institucionId) : null;

        // Generar la hoja/etiqueta es lo que cuenta como "impreso" — no pisa la fecha si
        // ya se había marcado antes (ver Bien::marcarQrImpreso).
        Bien::marcarQrImpreso(array_column($bienes, 'id'));

        View::render('bienes/qr_masivo_imprimir', [
            'bienes' => $bienes,
            'formato' => $formato,
            'institucionNombre' => $institucion['nombre'] ?? '',
        ]);
    }

    /**
     * La generación masiva de QR queda fuera del alcance del rol docente (aunque tenga
     * el permiso bienes.ver): igual que el filtro de "solo mis bienes" en /bienes, se
     * revisa el rol directamente en vez de crear un permiso aparte, para no tener que
     * tocar la ruta, el menú y el botón por separado.
     */
    private function verificarAcceso(): void
    {
        if (Auth::rol() === 'docente') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $valor = (int) ($_GET['institucion'] ?? $_POST['institucion_id'] ?? 0);

        return $valor > 0 ? $valor : null;
    }
}
