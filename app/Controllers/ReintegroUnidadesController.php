<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\FechaMovimiento;
use App\Models\Bien;
use App\Models\Institucion;
use App\Services\LoteReintegroService;
use App\Services\ReintegroUnidadesService;

/**
 * Reintegro POR CANTIDAD de los códigos con varias unidades (alta masiva): p. ej. 3 de las
 * 178 sillas de la placa 200033042. Ver ReintegroUnidadesService.
 */
final class ReintegroUnidadesController
{
    /** Mismo formato que exige el alta masiva para el código (BienController::validarLote). */
    private const PATRON_CODIGO = '/^[A-Za-z0-9\-]{1,100}$/';

    /** Códigos con varias unidades de la institución, con lo que queda en cartera de cada uno. */
    public function index(): void
    {
        $institucionId = $this->institucionSeleccionada();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $codigos = $institucionId !== null ? Bien::listarLotes($institucionId, $q !== '' ? $q : null) : [];

        View::layout('partials/layout', 'reintegros/unidades_index', [
            'title' => 'Reintegrar por cantidad',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'q' => $q,
            'codigos' => $codigos,
            // Sin resultados: por qué no aparece el código y qué hacer (antes no se decía nada).
            'sinResultados' => $institucionId !== null && $codigos === []
                ? ReintegroUnidadesService::explicarSinResultados($institucionId, $q, Auth::esSuperusuario() ? '?institucion=' . $institucionId : '')
                : null,
        ]);
    }

    public function formulario(string $lote): void
    {
        [$institucionId, $resumen] = $this->codigoAccesible($lote);

        View::layout('partials/layout', 'reintegros/unidades_form', [
            'title' => 'Reintegrar por cantidad',
            'institucionId' => $institucionId,
            'resumen' => $resumen,
            'hoy' => FechaMovimiento::hoy(),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function reintegrar(string $lote): void
    {
        [$institucionId] = $this->codigoAccesible($lote);
        $request = new Request();
        $cantidades = (array) $request->input('cantidad', []);
        $fecha = trim((string) $request->input('fecha'));
        $destino = (string) $request->input('destino_texto');
        $observaciones = (string) $request->input('observaciones');
        $generarLote = (string) $request->input('generar_lote') === '1';
        $viejo = ['cantidad' => $cantidades, 'fecha' => $fecha, 'destino_texto' => $destino, 'observaciones' => $observaciones, 'generar_lote' => $generarLote ? '1' : '0'];
        $volverA = $this->urlFormulario($lote, $institucionId);
        Csrf::verificarORedirigir($request, $volverA, $viejo);

        try {
            $resultado = ReintegroUnidadesService::reintegrar($institucionId, $lote, $cantidades, $fecha, $destino, $observaciones, $generarLote);
        } catch (\Throwable $e) {
            // Una regla trae su porqué y qué hacer; un fallo técnico, el código del incidente.
            $this->volver($volverA, Aviso::desdeExcepcion($e, 'No se reintegró ninguna unidad', 'Revisa los datos y vuelve a pulsar «Reintegrar».'), $viejo);
        }

        $n = $resultado['reintegradas'];
        $hecho = $n === 1 ? "Se reintegró 1 unidad de la placa {$lote}." : "Se reintegraron {$n} unidades de la placa {$lote}.";
        $renglon = '';
        if ($resultado['resumen'] !== null) {
            $renglon = "El bien {$lote} de la cartera quedó como \"{$resultado['resumen']['despues']}\""
                . ($resultado['resumen']['reintegrado'] ? ' y pasó a Reintegrado: ya no queda ninguna unidad en cartera. ' : '. ');
        }
        $lotes = $resultado['lotes'];

        if ($lotes === []) {
            Aviso::enviar(Aviso::exito($hecho, $renglon . 'Siguiente paso: marca las unidades en esta lista y pulsa «Generar lote» para el comprobante.'));
            header('Location: ' . Url::to('/reintegros/lotes/generar'));
            exit;
        }

        Aviso::enviar(Aviso::exito($hecho, $renglon . 'Lote de reintegro ' . LoteReintegroService::resumenLotes($lotes)
            . ' generado. Siguiente paso: descarga el comprobante en Excel para entregarlo.'));
        header('Location: ' . Url::to(count($lotes) === 1 ? '/reintegros/lotes/' . $lotes[0]['id'] : '/reintegros/lotes?estado=pendiente'));
        exit;
    }

    /**
     * Valida el código de la URL y la institución, y devuelve [institución, resumen]. Sin
     * institución elegida (superusuario) vuelve a la lista; código inexistente: 404.
     *
     * @return array{0: int, 1: array}
     */
    private function codigoAccesible(string $lote): array
    {
        $institucionId = $this->institucionSeleccionada();
        if ($institucionId === null) {
            header('Location: ' . Url::to('/reintegros/unidades'));
            exit;
        }

        $resumen = preg_match(self::PATRON_CODIGO, $lote) === 1 ? ReintegroUnidadesService::resumen($institucionId, $lote) : null;
        if ($resumen === null) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        return [$institucionId, $resumen];
    }

    /**
     * La institución con la que se trabaja: la del usuario; el superusuario la elige
     * (?institucion= en la URL o institucion_id en el formulario), igual que en /reintegros.
     */
    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $solicitada = (int) ($_POST['institucion_id'] ?? $_GET['institucion'] ?? 0);

        return $solicitada > 0 ? $solicitada : null;
    }

    private function urlFormulario(string $lote, int $institucionId): string
    {
        return '/reintegros/unidades/' . rawurlencode($lote) . (Auth::esSuperusuario() ? '?institucion=' . $institucionId : '');
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volver(string $volverA, array $aviso, array $viejo): never
    {
        Aviso::enviar($aviso);
        Session::flashOld($viejo);
        header('Location: ' . Url::to($volverA));
        exit;
    }
}
