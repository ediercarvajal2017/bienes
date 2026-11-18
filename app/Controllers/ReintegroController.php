<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
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
use App\Services\RenglonDePlaca;
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

            // Tras un reintegro que salió bien, la selección guardada en el navegador ya cumplió.
            'limpiarSeleccion' => Session::pullFlash('reintegro_aplicado') !== null,
            'sinResultados' => $institucionId !== null && $total === 0 && $q !== '' ? $this->explicarSinResultados($institucionId, $q) : null,
            'viejo' => Session::pullOld(),
        ]);
    }

    /**
     * Por qué la búsqueda no encontró nada para reintegrar y qué hacer. Si la placa existe, la
     * razón es la regla de reintegro (Bien::guiaNoReintegrable): sin asignación, "Sin cartera",
     * ya reintegrado, unidad de una placa…
     *
     * @return array{titulo: string, detalle: string, acciones: list<array{texto: string, url: string}>}
     */
    private function explicarSinResultados(int $institucionId, string $q): array
    {
        $sufijo = Auth::esSuperusuario() ? ['institucion' => $institucionId] : [];
        $encontrado = Bien::buscarPorCodigoInstitucion($institucionId, $q);
        $bien = $encontrado !== null ? Bien::find((int) $encontrado['id']) : null;
        if ($bien !== null) {
            $codigo = (string) $bien['codigo_identificacion'];
            if (!empty($bien['lote']) && in_array($bien['estado'], Bien::ESTADOS_REINTEGRABLES, true)) {
                return ['titulo' => "El bien {$codigo} no aparece aquí porque es una unidad de la placa {$bien['lote']}.",
                    'detalle' => 'Las unidades de una placa se reintegran por cantidad: eliges cuántas y MIA toma las que siguen en cartera.',
                    'acciones' => [['texto' => 'Reintegrar por cantidad', 'url' => Url::to('/reintegros/unidades') . '?' . http_build_query(['q' => $bien['lote']] + $sufijo)]]];
            }
            $guia = Bien::guiaNoReintegrable($bien, Asignacion::activaDe((int) $bien['id']) !== null);
            if ($guia !== null) {
                return ['titulo' => "El bien {$codigo} no aparece porque {$guia['motivo']}.", 'detalle' => $guia['queHacer'],
                    'acciones' => $guia['accion'] !== null ? [$guia['accion']] : []];
            }
        }

        return ['titulo' => "Ningún bien para reintegrar coincide con «{$q}».",
            'detalle' => 'Aquí solo aparecen los bienes asignados que se pueden reintegrar. Revisa cómo está escrita la placa, o búscala en Bienes para ver su estado.',
            'acciones' => [
                ['texto' => 'Buscar en Bienes', 'url' => Url::to('/bienes') . '?' . http_build_query(['q' => $q] + $sufijo)],
                ['texto' => 'Quitar la búsqueda', 'url' => Url::to('/reintegros') . ($sufijo !== [] ? '?' . http_build_query($sufijo) : '')],
            ]];
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
            Aviso::enviar(\App\Helpers\Mensajes::seleccionaAlMenosUno('No se reintegró ningún bien', 'bien', 'Reintegro'));
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $errorFecha = FechaMovimiento::error($fecha);
        if ($destino === '' || $errorFecha !== null) {
            Aviso::enviar($destino === ''
                ? Aviso::error('No se reintegró ningún bien', 'Falta el destino del reintegro.',
                    'Escribe a dónde van los bienes (por ejemplo «Almacén de la Secretaría de Educación») y vuelve a pulsar «Reintegro».',
                    null, ['destino_texto' => 'Escribe el destino.'])
                : Aviso::error('No se reintegró ningún bien', (string) $errorFecha, 'Elige una fecha entre el 01/01/2000 y hoy y vuelve a pulsar «Reintegro».',
                    null, ['fecha' => 'Elige una fecha válida.']));
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $resultado = $this->reintegrarLote($bienIds, $fecha, $destino, $observaciones);
        $reintegrados = $resultado['reintegrados'] ?? 0;
        $omitidos = $resultado['omitidos'] ?? 0;
        $porQueOmitidos = 'ya no cumplían las condiciones: estar asignados, tener categoría y no ser «' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '»';
        $irALotes = Aviso::accion('Generar el lote de reintegro', '/reintegros/lotes/generar');

        if ($resultado === null) {
            Aviso::enviar(Aviso::error('No se reintegró ningún bien', 'Ocurrió un error al procesar el reintegro y no se aplicó ningún cambio.',
                'Vuelve a pulsar «Reintegro». Si se repite, avisa a soporte.'));
            Session::flashOld($viejo);
        } elseif ($reintegrados === 0) {
            Aviso::enviar(Aviso::error('No se reintegró ningún bien', "Los {$omitidos} bienes elegidos {$porQueOmitidos}.",
                'Recarga la lista: solo quedan los que se pueden reintegrar. Si falta alguno, ábrelo en Bienes para ver su estado.'));
            Session::flashOld($viejo);
        } else {
            Session::flash('reintegro_aplicado', '1');
            $hecho = $reintegrados === 1 ? '1 bien reintegrado.' : "{$reintegrados} bienes reintegrados.";
            Aviso::enviar($omitidos > 0
                ? Aviso::advertencia($hecho, ($omitidos === 1 ? '1 bien se omitió porque ' : "{$omitidos} bienes se omitieron porque ") . $porQueOmitidos . '.',
                    'Revisa en Bienes los que no se reintegraron. Siguiente paso: agrupa los reintegrados en un lote para generar el comprobante.', $irALotes)
                : Aviso::exito($hecho, 'Siguiente paso: agrúpalos en un lote de reintegro para generar el comprobante.', $irALotes));
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

        $token = $this->extraerToken($crudo);
        $bien = Bien::buscarReintegrablePorToken($token, $institucionId);
        if (!$bien) {
            echo json_encode(['ok' => false, 'mensaje' => $this->porQueNoSeAgrega($token, $institucionId)]);
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

    /** Por qué el código escaneado no se agregó a la selección, y qué hacer. */
    private function porQueNoSeAgrega(string $token, int $institucionId): string
    {
        $bien = Bien::findPorToken($token);
        if ($bien === null) {
            return 'Ese código QR no es de ningún bien de MIA. Revisa que sea la etiqueta del bien, o busca su placa en la lista.';
        }
        if ((int) $bien['institucion_id'] !== $institucionId) {
            return "El bien {$bien['codigo_identificacion']} es de otra institución ({$bien['institucion_nombre']}): no se puede reintegrar desde aquí.";
        }
        if (!empty($bien['lote']) && in_array($bien['estado'], Bien::ESTADOS_REINTEGRABLES, true)) {
            return "El bien {$bien['codigo_identificacion']} es una unidad de la placa {$bien['lote']}: reintégralo en «Reintegrar por cantidad».";
        }
        $guia = Bien::guiaNoReintegrable($bien, Asignacion::activaDe((int) $bien['id']) !== null);

        return $guia !== null
            ? "No se agregó {$bien['codigo_identificacion']}: {$guia['motivo']}. {$guia['queHacer']}"
            : "No se agregó {$bien['codigo_identificacion']}. Búscalo por su placa en la lista.";
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
                $reintegrados = [];
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

                    ReintegroService::reintegrar($bien, $asignacion, $fecha, $destino, $observaciones, ['masivo' => true], false);
                    $reintegrados[] = $bien;
                    $resultado['reintegrados']++;
                }

                // El renglón de la cartera de cada placa tocada, una sola vez (no por unidad).
                RenglonDePlaca::sincronizarDe($reintegrados, 'Reintegro de bienes seleccionados');

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
