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
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Services\CicloVidaBien;

final class AsignacionController
{
    private const POR_PAGINA_DEFECTO = 30;
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

        $total = $institucionId !== null ? Bien::contarOperables($institucionId, $termino) : 0;

        View::layout('partials/layout', 'asignaciones/index', [
            'title' => 'Asignar bienes',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'espacios' => $institucionId !== null ? Espacio::listadoParaSelect($institucionId) : [],

            'bienes' => $institucionId !== null ? Bien::operables($institucionId, $termino, $pagina, $porPagina) : [],
            'q' => $q,
            'pagina' => $pagina,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,

            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();

        $bienIds = array_unique(array_map('intval', (array) $request->input('bienes', [])));
        $espacioIdRaw = (string) $request->input('espacio_id');
        $fecha = (string) $request->input('fecha_asignacion');
        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        $viejo = ['bienes' => $bienIds, 'espacio_id' => $espacioIdRaw, 'fecha_asignacion' => $fecha, 'observaciones' => $observaciones];

        $this->verificarCsrf($request, $viejo);

        $institucionId = Auth::esSuperusuario() ? (int) $request->input('institucion_id') : Auth::institucionId();
        $volverA = '/asignaciones' . (Auth::esSuperusuario() ? '?institucion=' . $institucionId : '');

        if (empty($bienIds)) {
            Session::flash('error', 'Selecciona al menos un bien para asignar.');
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $errorFecha = FechaMovimiento::error($fecha);
        if ($espacioIdRaw === '' || $errorFecha !== null) {
            Session::flash('error', $espacioIdRaw === '' ? 'Selecciona un espacio.' : $errorFecha);
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $espacioId = (int) $espacioIdRaw;

        if (!Espacio::perteneceYActivo($espacioId, (int) $institucionId)) {
            Session::flash('error', 'El espacio seleccionado no es válido (debe ser un espacio activo de la institución).');
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $resultado = $this->asignarLote($bienIds, $institucionId, $espacioId, $fecha, $observaciones);

        if ($resultado === null) {
            Session::flash('error', 'Ocurrió un error al procesar la asignación masiva. No se aplicó ningún cambio.');
            Session::flashOld($viejo);
        } elseif ($resultado['asignados'] === 0) {
            Session::flash('error', 'Ningún bien seleccionado pudo asignarse.' . $this->textoOmitidos($resultado));
            Session::flashOld($viejo);
        } else {
            Session::flash('ok', $resultado['asignados'] . ' bien(es) asignado(s) correctamente.' . $this->textoOmitidos($resultado));
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    /**
     * Asigna (o traslada, si ya tenía espacio) cada bien del lote con las MISMAS reglas que
     * la ficha del bien (CicloVidaBien::asignarOTrasladar): así un bien que cambia de
     * espacio deja su movimiento de traslado en el historial, y no se repite la asignación
     * si ya estaba en ese espacio. Todo en una única transacción. Se omiten los bienes de
     * otra institución, los dados de baja y los reintegrados (un reintegrado solo vuelve a
     * circular con "Reactivar").
     *
     * @return array{asignados: int, reintegrados: int, mismoEspacio: int, otros: int}|null  null si falló todo
     */
    private function asignarLote(array $bienIds, int $institucionId, int $espacioId, string $fecha, ?string $observaciones): ?array
    {
        $resultado = ['asignados' => 0, 'reintegrados' => 0, 'mismoEspacio' => 0, 'otros' => 0];

        try {
            return Database::transaccion(static function () use ($bienIds, $institucionId, $espacioId, $fecha, $observaciones, $resultado): array {
                foreach ($bienIds as $bienId) {
                    $bien = Bien::find($bienId);
                    if (!$bien || $bien['estado'] === 'dado_de_baja' || (int) $bien['institucion_id'] !== $institucionId) {
                        $resultado['otros']++;
                        continue;
                    }
                    if ($bien['estado'] === 'reintegrado') {
                        $resultado['reintegrados']++;
                        continue;
                    }
                    $actual = Asignacion::activaDe((int) $bienId);
                    if ($actual && (int) $actual['espacio_id'] === $espacioId) {
                        $resultado['mismoEspacio']++;
                        continue;
                    }

                    CicloVidaBien::asignarOTrasladar((array) $bien, $espacioId, $fecha, $observaciones);
                    $resultado['asignados']++;
                }

                return $resultado;
            });
        } catch (\Throwable $e) {
            ErrorHandler::reportar($e, __METHOD__);

            return null;
        }
    }

    private function textoOmitidos(array $resultado): string
    {
        $texto = '';
        if ($resultado['mismoEspacio'] > 0) {
            $texto .= " {$resultado['mismoEspacio']} bien(es) ya estaban en ese espacio.";
        }
        if ($resultado['reintegrados'] > 0) {
            $texto .= " Se omitieron {$resultado['reintegrados']} bien(es) reintegrado(s): para volver a asignarlos use \"Reactivar\" en la ficha del bien.";
        }
        if ($resultado['otros'] > 0) {
            $texto .= " Se omitieron {$resultado['otros']} bien(es) dados de baja o de otra institución.";
        }

        return $texto;
    }

    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $solicitada = $_GET['institucion'] ?? '';

        return $solicitada !== '' ? (int) $solicitada : null;
    }

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso el llamador ya construye $viejo ANTES de este chequeo y lo pasa aquí, para
     * que el usuario no pierda la selección y los datos solo porque el token expiró
     * mientras llenaba el formulario.
     */
    private function verificarCsrf(Request $request, array $datosAConservar = []): void
    {
        Csrf::verificarORedirigir($request, '/asignaciones', $datosAConservar);
    }
}
