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
use App\Helpers\Paginador;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Espacio;
use App\Models\Institucion;

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

        if ($espacioIdRaw === '' || $fecha === '' || !strtotime($fecha)) {
            Session::flash('error', 'Selecciona un espacio y una fecha de asignación válida.');
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
     * Cierra cualquier asignación activa remanente (por seguridad) y crea la nueva
     * para cada bien del lote, dentro de una única transacción. Se omiten los bienes de
     * otra institución, los dados de baja y los reintegrados: igual que en la asignación
     * individual y en el listado de esta pantalla, un bien reintegrado solo vuelve a
     * circular con "Reactivar" (rector o superusuario, con motivo). Antes la asignación
     * masiva lo reactivaba en silencio si el id llegaba en el formulario.
     *
     * @return array{asignados: int, reintegrados: int, otros: int}|null  null si falló todo
     */
    private function asignarLote(array $bienIds, int $institucionId, int $espacioId, string $fecha, ?string $observaciones): ?array
    {
        $resultado = ['asignados' => 0, 'reintegrados' => 0, 'otros' => 0];

        if (!Auth::esSuperusuario() && $institucionId !== Auth::institucionId()) {
            return $resultado;
        }

        $pdo = Database::connection();
        $asignados = 0;

        $pdo->beginTransaction();
        try {
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

                $anterior = Asignacion::activaDe($bienId);
                Asignacion::cerrarActivasDe($bienId);
                Asignacion::crear([
                    'bien_id' => $bienId,
                    'espacio_id' => $espacioId,
                    'fecha_asignacion' => $fecha,
                    'observaciones' => $observaciones,
                    'asignado_por' => Auth::id(),
                ]);
                Auditoria::registrar(Auth::id(), $institucionId, 'asignar', 'bien', $bienId,
                    ['espacio_id' => $anterior['espacio_id'] ?? null],
                    ['espacio_id' => $espacioId, 'fecha' => $fecha, 'masivo' => true]);

                $asignados++;
            }

            $pdo->commit();
            $resultado['asignados'] = $asignados;

            return $resultado;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            ErrorHandler::reportar($e, __METHOD__);

            return null;
        }
    }

    private function textoOmitidos(array $resultado): string
    {
        $texto = '';
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
