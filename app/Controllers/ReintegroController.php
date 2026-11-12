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
use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\LoteReintegro;
use App\Models\Movimiento;
use App\Models\Usuario;
use App\Services\ReintegroService;
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
        $categoriaId = isset($_GET['categoria']) && $_GET['categoria'] !== '' ? (int) $_GET['categoria'] : null;
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

        $total = $institucionId !== null ? Bien::contarReintegrables($institucionId, $termino, $idsSeleccionados, $categoriaId) : 0;

        View::layout('partials/layout', 'reintegros/index', [
            'title' => 'Reintegrar bienes',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'categorias' => $institucionId !== null ? Categoria::activas($institucionId) : [],
            'categoriaId' => $categoriaId,

            'bienes' => $institucionId !== null ? Bien::reintegrables($institucionId, $termino, $pagina, $porPagina, $idsSeleccionados, $categoriaId) : [],
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

        $errorFecha = FechaMovimiento::error($fecha);
        if ($destino === '' || $errorFecha !== null) {
            Session::flash('error', $destino === '' ? 'Indica el destino del reintegro.' : $errorFecha);
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $resultado = $this->reintegrarLote($bienIds, $fecha, $destino, $observaciones);
        $reintegrados = $resultado['reintegrados'] ?? 0;
        $avisoOmitidos = ($resultado['omitidos'] ?? 0) > 0
            ? ' Se omitieron ' . $resultado['omitidos'] . ' bien(es) que ya no cumplían las condiciones (asignado, con categoría, fuera de "' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '").'
            : '';

        if ($resultado === null) {
            Session::flash('error', 'Ocurrió un error al procesar el reintegro masivo. No se aplicó ningún cambio.');
            Session::flashOld($viejo);
        } elseif ($reintegrados === 0) {
            Session::flash('error', 'Ningún bien seleccionado pudo reintegrarse.' . $avisoOmitidos);
            Session::flashOld($viejo);
        } else {
            Session::flash('ok', $reintegrados . ' bien(es) reintegrado(s) correctamente. Cuando quieras, agrúpalos en un lote desde "Lotes de reintegro" para generar el formato.' . $avisoOmitidos);
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

    /**
     * Agrupa bienes en un reintegro masivo dentro de una única transacción (todo o nada
     * ante un error de BD). Aplica la MISMA regla que el reintegro individual
     * (Bien::motivoNoReintegrable); los bienes que no la cumplen se omiten y se cuentan.
     * El lote se genera después, como acción manual aparte.
     *
     * @return array{reintegrados: int, omitidos: int}|null  null si hubo un error de BD
     */
    private function reintegrarLote(array $bienIds, string $fecha, string $destino, ?string $observaciones): ?array
    {
        $resultado = ['reintegrados' => 0, 'omitidos' => 0];

        try {
            // Cada bien pasa por las mismas reglas que el reintegro desde la ficha
            // (ReintegroService), todo en una única transacción.
            return Database::transaccion(static function () use ($bienIds, $fecha, $destino, $observaciones, $resultado): array {
                foreach ($bienIds as $bienId) {
                    $bien = Bien::find($bienId);
                    if (!$bien || (!Auth::esSuperusuario() && (int) $bien['institucion_id'] !== Auth::institucionId())) {
                        $resultado['omitidos']++;
                        continue;
                    }

                    $asignacion = Asignacion::activaDe($bienId);
                    if (Bien::motivoNoReintegrable($bien, $asignacion !== null) !== null) {
                        $resultado['omitidos']++;
                        continue;
                    }

                    ReintegroService::reintegrar($bien, $asignacion, $fecha, $destino, $observaciones, ['masivo' => true]);
                    $resultado['reintegrados']++;
                }

                return $resultado;
            });
        } catch (\Throwable $e) {
            ErrorHandler::reportar($e, __METHOD__);

            return null;
        }
    }

    /**
     * A diferencia de los lotes (LoteReintegroController, que sí pueden listarse "todas
     * las instituciones" para un superusuario), la pantalla de reintegro necesita una
     * institución concreta para operar — igual que /asignaciones.
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
        Csrf::verificarORedirigir($request, $volverA, $datosAConservar);
    }
}
