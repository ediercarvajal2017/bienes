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
        $this->verificarCsrf($request);

        $institucionId = Auth::esSuperusuario() ? (int) $request->input('institucion_id') : Auth::institucionId();
        $volverA = '/asignaciones' . (Auth::esSuperusuario() ? '?institucion=' . $institucionId : '');

        $bienIds = array_unique(array_map('intval', (array) $request->input('bienes', [])));
        $espacioIdRaw = (string) $request->input('espacio_id');
        $fecha = (string) $request->input('fecha_asignacion');
        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        $viejo = ['bienes' => $bienIds, 'espacio_id' => $espacioIdRaw, 'fecha_asignacion' => $fecha, 'observaciones' => $observaciones];

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
        $asignados = $this->asignarLote($bienIds, $institucionId, $espacioId, $fecha, $observaciones);

        if ($asignados === null) {
            Session::flash('error', 'Ocurrió un error al procesar la asignación masiva. No se aplicó ningún cambio.');
            Session::flashOld($viejo);
        } elseif ($asignados === 0) {
            Session::flash('error', 'Ningún bien seleccionado pudo asignarse.');
            Session::flashOld($viejo);
        } else {
            Session::flash('ok', $asignados . ' bien(es) asignado(s) correctamente.');
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    /**
     * Cierra cualquier asignación activa remanente (por seguridad) y crea la nueva
     * para cada bien del lote, dentro de una única transacción. Si un bien venía
     * 'reintegrado' lo vuelve a poner 'activo' (igual que la asignación individual).
     * Los bienes fuera de la institución seleccionada o dados de baja se omiten.
     */
    private function asignarLote(array $bienIds, int $institucionId, int $espacioId, string $fecha, ?string $observaciones): ?int
    {
        if (!Auth::esSuperusuario() && $institucionId !== Auth::institucionId()) {
            return 0;
        }

        $pdo = Database::connection();
        $asignados = 0;

        $pdo->beginTransaction();
        try {
            foreach ($bienIds as $bienId) {
                $bien = Bien::find($bienId);
                if (!$bien || $bien['estado'] === 'dado_de_baja') {
                    continue;
                }

                if ((int) $bien['institucion_id'] !== $institucionId) {
                    continue;
                }

                Asignacion::cerrarActivasDe($bienId);
                Asignacion::crear([
                    'bien_id' => $bienId,
                    'espacio_id' => $espacioId,
                    'fecha_asignacion' => $fecha,
                    'observaciones' => $observaciones,
                    'asignado_por' => Auth::id(),
                ]);

                if ($bien['estado'] === 'reintegrado') {
                    Bien::update($bienId, [
                        'codigo_identificacion' => $bien['codigo_identificacion'],
                        'descripcion' => $bien['descripcion'],
                        'marca' => $bien['marca'],
                        'categoria_id' => $bien['categoria_id'],
                        'fecha_ingreso' => $bien['fecha_ingreso'],
                        'valor' => $bien['valor'],
                        'tiene_factura' => $bien['tiene_factura'],
                        'estado' => 'activo',
                    ]);
                }

                $asignados++;
            }

            $pdo->commit();

            return $asignados;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return null;
        }
    }

    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $solicitada = $_GET['institucion'] ?? '';

        return $solicitada !== '' ? (int) $solicitada : null;
    }

    private function verificarCsrf(Request $request): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to('/asignaciones'));
            exit;
        }
    }
}
