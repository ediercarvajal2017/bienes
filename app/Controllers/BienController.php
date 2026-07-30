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
use App\Helpers\Uploader;
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Movimiento;
use App\Models\Verificacion;

final class BienController
{
    private const ESTADOS = ['activo', 'reintegrado', 'en_reparacion', 'dado_de_baja'];

    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? null : Auth::institucionId();
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $terminoBusqueda = $busqueda !== '' ? $busqueda : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        $soloPropios = Auth::rol() === 'docente';

        // Mientras no se busque nada, los bienes que pertenecen a un lote (ej. 250 sillas
        // identicas) se excluyen del listado individual y se muestran agrupados aparte
        // (ver $lotes) — asi la cartera no queda saturada de filas casi identicas. En
        // cuanto el usuario busca algo, se ve todo, agrupado o no, para que la busqueda
        // siga siendo confiable.
        $excluirLotes = !$soloPropios && $terminoBusqueda === null;

        if ($soloPropios) {
            $total = Bien::contarPropios((int) Auth::id(), $institucionId, $terminoBusqueda);
            $bienes = Bien::listarPropios((int) Auth::id(), $institucionId, $terminoBusqueda, $pagina, $porPagina);
        } else {
            $total = Bien::contarListado($institucionId, $terminoBusqueda, $excluirLotes);
            $bienes = Bien::listar($institucionId, $terminoBusqueda, $pagina, $porPagina, $excluirLotes);
        }

        $lotes = (!$soloPropios && $institucionId !== null)
            ? Bien::listarLotes($institucionId, $terminoBusqueda)
            : [];

        View::layout('partials/layout', 'bienes/index', [
            'title' => 'Bienes',
            'bienes' => $bienes,
            'lotes' => $lotes,
            'soloPropios' => $soloPropios,
            'busqueda' => $busqueda,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function crear(): void
    {
        View::layout('partials/layout', 'bienes/form', [
            'title' => 'Registrar bien',
            'bien' => null,
            'categorias' => Categoria::activas(),
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [],
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/bienes/crear', $datos);

        if ($error = $this->validar($datos, null)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to('/bienes/crear'));
            exit;
        }

        $datos['created_by'] = Auth::id();
        $id = Bien::create($datos);

        $this->procesarArchivos($id, $request, $datos['codigo_identificacion']);

        Session::flash('ok', 'Bien registrado correctamente.');
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    /**
     * Alta masiva de bienes idénticos (ej. 250 sillas): cada uno queda como un bien de
     * pleno derecho, con su propio código consecutivo y QR, pero comparten la etiqueta
     * "lote" para que /bienes los muestre agrupados en vez de saturar la cartera.
     */
    public function crearLote(): void
    {
        View::layout('partials/layout', 'bienes/form_lote', [
            'title' => 'Alta masiva de bienes idénticos',
            'categorias' => Categoria::activas(),
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [],
            'error' => Session::pullFlash('error'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardarLote(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormularioLote($request);
        $this->verificarCsrf($request, '/bienes/alta-masiva', $datos);

        if ($error = $this->validarLote($datos)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to('/bienes/alta-masiva'));
            exit;
        }

        $creados = $this->crearBienesEnLote($datos);

        if ($creados === null) {
            Session::flash('error', 'Ocurrió un error al crear los bienes del lote. No se aplicó ningún cambio.');
            Session::flashOld($datos);
            header('Location: ' . Url::to('/bienes/alta-masiva'));
            exit;
        }

        Session::flash('ok', "{$creados} bienes creados correctamente en el lote \"{$datos['lote']}\".");
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    private function datosDesdeFormularioLote(Request $request): array
    {
        $institucionId = Auth::esSuperusuario()
            ? (int) $request->input('institucion_id')
            : Auth::institucionId();

        return [
            'institucion_id' => $institucionId,
            'lote' => trim((string) $request->input('lote')),
            'descripcion' => trim((string) $request->input('descripcion')),
            'marca' => trim((string) $request->input('marca')) ?: null,
            'categoria_id' => ((int) $request->input('categoria_id')) ?: null,
            'fecha_ingreso' => (string) $request->input('fecha_ingreso'),
            'valor' => (string) $request->input('valor'),
            'cantidad' => (int) $request->input('cantidad'),
        ];
    }

    private function validarLote(array $datos): ?string
    {
        if ($datos['lote'] === '' || $datos['descripcion'] === '' || $datos['fecha_ingreso'] === '' || !strtotime($datos['fecha_ingreso'])) {
            return 'Indica el código de lote, la descripción y una fecha de ingreso válida.';
        }

        if (!preg_match('/^[A-Za-z0-9\-]+$/', $datos['lote'])) {
            return 'El código de lote solo puede tener letras, números y guiones, sin espacios.';
        }

        if ($datos['cantidad'] < 2 || $datos['cantidad'] > 500) {
            return 'La cantidad debe estar entre 2 y 500 (para un solo bien, usa "Registrar bien").';
        }

        if (!is_numeric($datos['valor']) || (float) $datos['valor'] < 0) {
            return 'Indica un valor unitario válido.';
        }

        if (Bien::existeLote($datos['institucion_id'], $datos['lote'])) {
            return 'Ya existe un lote con ese código en esta institución.';
        }

        return null;
    }

    /**
     * Todo o nada dentro de una única transacción: si algún código consecutivo ya
     * existiera a mitad de camino, se revierte por completo (no deja el lote a medias).
     */
    private function crearBienesEnLote(array $datos): ?int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            for ($i = 1; $i <= $datos['cantidad']; $i++) {
                $codigo = $datos['lote'] . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);

                if (Bien::existeCodigo($datos['institucion_id'], $codigo)) {
                    throw new \RuntimeException("El código {$codigo} ya existe.");
                }

                Bien::create([
                    'institucion_id' => $datos['institucion_id'],
                    'codigo_identificacion' => $codigo,
                    'descripcion' => $datos['descripcion'],
                    'lote' => $datos['lote'],
                    'marca' => $datos['marca'],
                    'categoria_id' => $datos['categoria_id'],
                    'fecha_ingreso' => $datos['fecha_ingreso'],
                    'valor' => $datos['valor'],
                    'tiene_factura' => 0,
                    'estado' => 'activo',
                    'created_by' => Auth::id(),
                ]);
            }

            $pdo->commit();

            return $datos['cantidad'];
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return null;
        }
    }

    public function editar(string $id): void
    {
        $id = (int) $id;
        $bien = Bien::find($id);
        $this->verificarAcceso($bien);

        View::layout('partials/layout', 'bienes/form', [
            'title' => 'Editar bien',
            'bien' => $bien,
            'categorias' => $this->categoriasParaFormulario($bien),
            'asignacionActiva' => Asignacion::activaDe($id),
            'historialMovimientos' => Movimiento::historialDe($id),
            'espaciosInstitucion' => Espacio::listadoParaSelect((int) $bien['institucion_id']),
            'verificacionId' => Verificacion::idValidoParaBien($id, (string) ($_GET['verificacion_id'] ?? '')),
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $bien = Bien::find($id);
        $this->verificarAcceso($bien);

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request, (int) $bien['institucion_id']);
        $datos['estado'] = $this->estadoPermitidoDesdeFormulario($bien['estado'], $datos['estado']);
        $this->verificarCsrf($request, "/bienes/{$id}/editar", $datos);

        if ($error = $this->validar($datos, $id)) {
            Session::flash('error', $error);
            Session::flashOld($datos);
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        Bien::update($id, $datos);

        $this->procesarArchivos($id, $request, $datos['codigo_identificacion']);

        Session::flash('ok', 'Bien actualizado.');
        header('Location: ' . Url::to('/bienes'));
        exit;
    }

    private function procesarArchivos(int $bienId, Request $request, string $codigoIdentificacion): void
    {
        try {
            if ($archivo = $request->file('foto')) {
                $path = Uploader::storeImage($archivo, 'fotos_bienes', $codigoIdentificacion);
                if ($path) {
                    Bien::updateFoto($bienId, $path);
                }
            }

            if ($archivo = $request->file('factura_pdf')) {
                $path = Uploader::storePdf($archivo, 'facturas');
                if ($path) {
                    Bien::updateFactura($bienId, $path);
                }
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
    }

    private function datosDesdeFormulario(Request $request, ?int $institucionIdExistente = null): array
    {
        $institucionId = $institucionIdExistente
            ?? (Auth::esSuperusuario() ? (int) $request->input('institucion_id') : Auth::institucionId());

        $categoriaId = (string) $request->input('categoria_id');
        $valor = str_replace(',', '', (string) $request->input('valor', '0'));
        $estado = (string) $request->input('estado');

        return [
            'institucion_id' => $institucionId,
            'codigo_identificacion' => trim((string) $request->input('codigo_identificacion')),
            'descripcion' => trim((string) $request->input('descripcion')),
            'marca' => trim((string) $request->input('marca')) ?: null,
            'categoria_id' => $categoriaId !== '' ? (int) $categoriaId : null,
            'fecha_ingreso' => (string) $request->input('fecha_ingreso'),
            'valor' => is_numeric($valor) ? (float) $valor : 0,
            'tiene_factura' => $request->input('tiene_factura') ? 1 : 0,
            'estado' => in_array($estado, self::ESTADOS, true) ? $estado : 'activo',
        ];
    }

    /**
     * 'reintegrado' y 'dado_de_baja' solo se alcanzan a través de sus propios flujos
     * (panel "Reintegrar" y aprobación de bajas), que registran el historial correspondiente.
     * Este formulario general de edición no puede saltarse esos flujos.
     */
    private function estadoPermitidoDesdeFormulario(string $estadoActual, string $estadoSolicitado): string
    {
        if (in_array($estadoActual, ['reintegrado', 'dado_de_baja'], true)) {
            return $estadoActual;
        }

        return in_array($estadoSolicitado, ['activo', 'en_reparacion'], true) ? $estadoSolicitado : $estadoActual;
    }

    private function validar(array $datos, ?int $exceptId): ?string
    {
        if ($datos['codigo_identificacion'] === '' || $datos['descripcion'] === '') {
            return 'El código de identificación y la descripción son obligatorios.';
        }

        if ($datos['fecha_ingreso'] === '' || !strtotime($datos['fecha_ingreso'])) {
            return 'La fecha de ingreso no es válida.';
        }

        if (Bien::existeCodigo($datos['institucion_id'], $datos['codigo_identificacion'], $exceptId)) {
            return 'Ya existe un bien con ese código en la institución.';
        }

        return null;
    }

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso cada llamador ya construye sus $datos ANTES de este chequeo y los pasa aquí,
     * para que el usuario no pierda todo lo que había escrito solo porque se demoró
     * llenando el formulario y el token expiró mientras tanto.
     */
    private function verificarCsrf(Request $request, string $volverA, array $datosAConservar = []): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo. Revisa los datos e inténtalo otra vez.');
            if (!empty($datosAConservar)) {
                Session::flashOld($datosAConservar);
            }
            header('Location: ' . Url::to($volverA));
            exit;
        }
    }

    private function verificarAcceso(?array $bien): void
    {
        if (!$bien) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $bien['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /**
     * Categorías activas para el <select>, más la categoría actual del bien si fue
     * desactivada después de asignársela (para no perderla del formulario al guardar).
     */
    private function categoriasParaFormulario(array $bien): array
    {
        $categorias = Categoria::activas();

        if ($bien['categoria_id'] === null) {
            return $categorias;
        }

        foreach ($categorias as $categoria) {
            if ((int) $categoria['id'] === (int) $bien['categoria_id']) {
                return $categorias;
            }
        }

        $categoriaActual = Categoria::find((int) $bien['categoria_id']);
        if ($categoriaActual) {
            $categoriaActual['nombre'] .= ' (inactiva)';
            $categorias[] = $categoriaActual;
        }

        return $categorias;
    }
}
