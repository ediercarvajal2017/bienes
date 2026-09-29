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
use App\Helpers\Uploader;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Hallazgo;
use App\Models\Institucion;
use App\Models\Movimiento;
use App\Models\Verificacion;
use App\Services\CicloVidaBien;

final class BienController
{
    private const ESTADOS = ['activo', 'reintegrado', 'en_reparacion', 'dado_de_baja'];

    // Tope de 10 dígitos para el valor de un bien -- suficiente para cualquier bien real
    // y evita que un número desproporcionado (por error de tecleo o intencional) se cuele
    // sin control del lado del servidor, más allá del min="0" del HTML, que es trivial de saltarse.
    private const VALOR_MAXIMO = 10_000_000_000;

    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $terminoBusqueda = $busqueda !== '' ? $busqueda : null;
        $categoriaId = ((int) ($_GET['categoria'] ?? 0)) ?: null;
        $estado = (string) ($_GET['estado'] ?? '');
        $estado = in_array($estado, self::ESTADOS, true) ? $estado : null;
        $espacioId = $institucionId !== null ? (((int) ($_GET['espacio'] ?? 0)) ?: null) : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        $soloPropios = Auth::rol() === 'docente';

        // Mientras no se busque ni filtre nada, los bienes que pertenecen a un lote (ej.
        // 250 sillas identicas) se excluyen del listado individual y se muestran agrupados
        // aparte (ver $lotes) — asi la cartera no queda saturada de filas casi identicas.
        // En cuanto el usuario busca o filtra algo, se ve todo, agrupado o no, para que el
        // resultado siga siendo confiable.
        $hayFiltroActivo = $terminoBusqueda !== null || $estado !== null || $espacioId !== null;
        $excluirLotes = !$soloPropios && !$hayFiltroActivo;

        if ($soloPropios) {
            $total = Bien::contarPropios((int) Auth::id(), $institucionId, $terminoBusqueda, $categoriaId, $estado, $espacioId);
            $bienes = Bien::listarPropios((int) Auth::id(), $institucionId, $terminoBusqueda, $pagina, $porPagina, $categoriaId, $estado, $espacioId);
        } else {
            $total = Bien::contarListado($institucionId, $terminoBusqueda, $excluirLotes, $categoriaId, $estado, $espacioId);
            $bienes = Bien::listar($institucionId, $terminoBusqueda, $pagina, $porPagina, $excluirLotes, $categoriaId, $estado, $espacioId);
        }

        // Las filas-resumen de lote se intercalan arriba de los bienes individuales en la
        // MISMA tabla (una sola lista, sin otra tabla aparte) — pero solo tiene sentido
        // mostrarlas en la vista sin filtrar de la primera pagina: en cuanto hay una
        // busqueda o filtro activo, "Ver detalles" ya te trae directamente los bienes reales
        // del lote (ver $excluirLotes arriba), asi que repetir el resumen ahi seria redundante.
        $vistaSinFiltrar = !$soloPropios && $institucionId !== null && !$hayFiltroActivo && $pagina === 1;
        $lotes = $vistaSinFiltrar ? Bien::listarLotes($institucionId, null, $categoriaId) : [];

        // "Bodega Reintegro"/"Bodega de Baja": mismo criterio que $lotes — solo se cuentan
        // en la vista sin filtrar, y llevan al mismo filtro de Estado que ya existe.
        $bodegaReintegroTotal = $vistaSinFiltrar ? Bien::contarListado($institucionId, null, false, null, 'reintegrado') : 0;
        $bodegaBajaTotal = $vistaSinFiltrar ? Bien::contarListado($institucionId, null, false, null, 'dado_de_baja') : 0;

        // Se recuerda la consulta del listado (búsqueda, filtros, página) para volver a
        // ella al guardar un bien o pulsar "Volver" en su ficha.
        Session::put('bienes_listado', http_build_query(array_intersect_key(
            $_GET, array_flip(['q', 'categoria', 'estado', 'espacio', 'pagina', 'porPagina'])
        )));

        View::layout('partials/layout', 'bienes/index', [
            'title' => 'Bienes',
            'bienes' => $bienes,
            'lotes' => $lotes,
            'bodegaReintegroTotal' => $bodegaReintegroTotal,
            'bodegaBajaTotal' => $bodegaBajaTotal,
            'soloPropios' => $soloPropios,
            'busqueda' => $busqueda,
            'categorias' => $institucionId !== null ? Categoria::activas($institucionId) : [],
            'categoriaId' => $categoriaId,
            'estado' => $estado,
            'espacioId' => $espacioId,
            'espacios' => $institucionId !== null
                ? ($soloPropios ? Espacio::propiosDe((int) Auth::id(), $institucionId) : Espacio::listadoParaSelect($institucionId))
                : [],
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function crear(): void
    {
        $hallazgo = Hallazgo::pendienteAccesible(
            (int) ($_GET['hallazgo_id'] ?? 0),
            Auth::esSuperusuario() ? null : Auth::institucionId()
        );

        $viejo = Session::pullOld();
        if (empty($viejo) && $hallazgo !== null) {
            $viejo = ['descripcion' => $hallazgo['descripcion']];
        }

        View::layout('partials/layout', 'bienes/form', [
            'title' => 'Registrar bien',
            'bien' => null,
            'espaciosInstitucion' => !Auth::esSuperusuario() && Auth::tienePermiso('asignaciones.crear') && $hallazgo === null
                ? Espacio::listadoParaSelect((int) Auth::institucionId()) : [],
            'categorias' => Auth::esSuperusuario() ? [] : Categoria::activas((int) Auth::institucionId()),
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [],
            'error' => Session::pullFlash('error'),
            'errorCampo' => Session::pullFlash('error_campo'),
            'viejo' => $viejo,
            'hallazgo' => $hallazgo,
        ]);
    }

    /**
     * Usado por JavaScript en "Registrar bien" cada vez que se elige una categoría:
     * devuelve el siguiente código de 10 dígitos si esa categoría es "Sin cartera", o
     * null para cualquier otra (ver Bien::siguienteCodigoSinCartera).
     *
     * Quien decide si la categoría es la protegida es el SERVIDOR, a partir del id que
     * llega — no el navegador comparando el texto de la opción. Antes se comparaba en
     * JavaScript contra el nombre "Sin cartera", pero Tom Select guarda el texto de un
     * <option> renderizado por el servidor con los saltos de línea e indentación del
     * HTML alrededor ("\n    Sin cartera\n  "), así que esa comparación nunca daba
     * verdadera y la consulta ni siquiera se disparaba — el bug que reportó el usuario.
     */
    public function siguienteCodigoSinCartera(): void
    {
        $categoriaId = (int) ($_GET['categoria_id'] ?? 0);

        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $categoria = $categoriaId > 0 ? Categoria::find($categoriaId) : null;

        if (!$categoria || !Categoria::esProtegida($categoria)) {
            echo json_encode(['codigo' => null]);
            exit;
        }

        $institucionId = (int) $categoria['institucion_id'];

        if (!Auth::esSuperusuario() && $institucionId !== Auth::institucionId()) {
            echo json_encode(['codigo' => null]);
            exit;
        }

        echo json_encode(['codigo' => Bien::siguienteCodigoSinCartera($institucionId, $categoriaId)]);
        exit;
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/bienes/crear', $datos);

        $hallazgo = Hallazgo::pendienteAccesible(
            (int) $request->input('hallazgo_id'),
            Auth::esSuperusuario() ? null : Auth::institucionId()
        );
        $volverA = '/bienes/crear' . ($hallazgo !== null ? '?hallazgo_id=' . $hallazgo['id'] : '');

        if ($resultado = $this->validar($datos, null)) {
            Session::flash('error', $resultado['mensaje']);
            if ($resultado['campo'] !== null) {
                Session::flash('error_campo', $resultado['campo']);
            }
            Session::flashOld($datos);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        // No es una columna de bienes: se saca aquí (después del flashOld() de más arriba,
        // que sí necesita conservarla) para que Bien::create() no reciba una clave sin su
        // ":imprimir_qr" en la consulta -- con PDO::ATTR_EMULATE_PREPARES en false eso
        // revienta con "Invalid parameter number", no se ignora en silencio.
        $imprimirQr = $datos['imprimir_qr'] === '1';
        $espacioId = $hallazgo === null ? (int) $request->input('espacio_id') : 0;
        $datosParaReintentar = $datos + ['espacio_id' => $espacioId ?: ''];
        unset($datos['imprimir_qr']);

        // Ubicación elegida al registrar (opcional): se asigna en el mismo guardado.
        if ($espacioId > 0 && (Auth::esSuperusuario() || !Auth::tienePermiso('asignaciones.crear')
                || !Espacio::perteneceYActivo($espacioId, (int) $datos['institucion_id']))) {
            Session::flash('error', 'El espacio seleccionado no es válido (debe ser un espacio activo de la institución).');
            Session::flashOld($datosParaReintentar);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $datos['created_by'] = Auth::id();

        try {
            // Crear el bien, auditarlo y (si viene de un hallazgo) asignarlo y cerrar el
            // hallazgo van juntos: antes podía quedar el bien creado con el hallazgo todavía
            // pendiente, o sin su asignación, si algo fallaba a mitad de camino.
            $id = Database::transaccion(static function () use ($datos, $hallazgo, $espacioId): int {
                $id = Bien::create($datos);
                Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'crear', 'bien', $id, null, $datos);

                if ($espacioId > 0) {
                    CicloVidaBien::asignarOTrasladar((array) Bien::find($id), $espacioId, date('Y-m-d'), null);
                }

                if ($hallazgo !== null) {
                    Asignacion::crear([
                        'bien_id' => $id,
                        'espacio_id' => $hallazgo['espacio_id'],
                        'fecha_asignacion' => date('Y-m-d'),
                        'observaciones' => 'Bien registrado a partir de un hallazgo reportado durante una jornada de verificación física.',
                        'asignado_por' => Auth::id(),
                    ]);
                    Hallazgo::marcarRegistrado((int) $hallazgo['id'], $id, (int) Auth::id());
                }

                return $id;
            });
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            Session::flashOld($datosParaReintentar);
            header('Location: ' . Url::to($volverA));
            exit;
        } catch (\PDOException $e) {
            // La comprobación de Bien::existeCodigo() en validar() ya pasó, pero entre
            // ese chequeo y este INSERT otra petición pudo registrar el mismo código
            // (dos personas guardando casi al mismo tiempo) -- sin este catch, la
            // restricción UNIQUE de la base de datos revienta como un error 500 crudo en
            // vez de mostrar el mismo mensaje amigable que ya existe para este caso.
            if (!$this->esViolacionCodigoDuplicado($e)) {
                throw $e;
            }

            Session::flash('error', 'Ya existe un bien con ese código en la institución.');
            Session::flash('error_campo', 'codigo_identificacion');
            Session::flashOld($datosParaReintentar);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $this->procesarArchivos($id, $request, $datos['codigo_identificacion']);
        $this->procesarSolicitudQr($id, $imprimirQr);

        if ($hallazgo !== null) {
            Session::flash('ok', 'Bien registrado y asignado a ' . $hallazgo['espacio_nombre'] . '.');
        } elseif ($espacioId > 0) {
            Session::flash('ok', 'Bien registrado y asignado a ' . (Espacio::find($espacioId)['nombre'] ?? 'el espacio elegido') . '.');
        } else {
            Session::flash('ok', 'Bien registrado correctamente.');
        }

        // Se abre la ficha del bien recién creado, para seguir con él (asignarlo, imprimir
        // su QR...) sin tener que buscarlo en el listado.
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
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
            'categorias' => Auth::esSuperusuario() ? [] : Categoria::activas((int) Auth::institucionId()),
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

        // No es una columna de bienes: se saca aquí (después del flashOld() de más
        // arriba) para que crearBienesEnLote() no la pase a Bien::create() -- mismo
        // motivo que en guardar()/actualizar() para un bien individual.
        $imprimirQr = $datos['imprimir_qr'] === '1';
        unset($datos['imprimir_qr']);

        $idsCreados = $this->crearBienesEnLote($datos);

        if ($idsCreados === null) {
            Session::flash('error', 'Ocurrió un error al crear los bienes del lote. No se aplicó ningún cambio.');
            Session::flashOld($datos);
            header('Location: ' . Url::to('/bienes/alta-masiva'));
            exit;
        }

        foreach ($idsCreados as $id) {
            $this->procesarSolicitudQr($id, $imprimirQr);
        }

        $creados = count($idsCreados);
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
            // Mayúscula o minúscula según la categoría (ver Bien::descripcionSegunCategoria).
            'descripcion' => Bien::descripcionSegunCategoria(trim((string) $request->input('descripcion')), ((int) $request->input('categoria_id')) ?: null),
            'marca' => trim((string) $request->input('marca')) ?: null,
            'categoria_id' => ((int) $request->input('categoria_id')) ?: null,
            'fecha_ingreso' => (string) $request->input('fecha_ingreso'),
            'valor' => (string) $request->input('valor'),
            'cantidad' => (int) $request->input('cantidad'),
            'imprimir_qr' => $request->input('imprimir_qr') ? '1' : '0',
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

        if (!is_numeric($datos['valor']) || (float) $datos['valor'] < 0 || (float) $datos['valor'] >= self::VALOR_MAXIMO) {
            return 'Indica un valor unitario válido (no puede ser negativo ni tener más de 10 dígitos).';
        }

        if (Bien::existeLote($datos['institucion_id'], $datos['lote'])) {
            return 'Ya existe un lote con ese código en esta institución.';
        }

        if ($datos['categoria_id'] !== null) {
            $categoria = Categoria::find((int) $datos['categoria_id']);
            if (!$categoria || (int) $categoria['institucion_id'] !== (int) $datos['institucion_id']) {
                return 'La categoría seleccionada no pertenece a esta institución.';
            }
        }

        return null;
    }

    /**
     * Todo o nada dentro de una única transacción: si algún código consecutivo ya
     * existiera a mitad de camino, se revierte por completo (no deja el lote a medias).
     *
     * @return int[]|null ids de los bienes creados (para poder solicitarles QR después),
     * o null si la transacción se revirtió.
     */
    private function crearBienesEnLote(array $datos): ?array
    {
        $codigos = [];
        for ($i = 1; $i <= $datos['cantidad']; $i++) {
            $codigos[] = $datos['lote'] . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Un solo SELECT para los hasta 500 códigos del lote, y un solo INSERT
            // multi-fila para crearlos -- antes eran hasta 1000 consultas secuenciales
            // (un SELECT + un INSERT por unidad).
            $choque = Bien::existeAlgunCodigo($datos['institucion_id'], $codigos);
            if ($choque !== null) {
                throw new \RuntimeException("El código {$choque} ya existe.");
            }

            Bien::crearVarios(
                $datos['institucion_id'],
                $datos['lote'],
                $codigos,
                $datos['descripcion'],
                $datos['marca'],
                $datos['categoria_id'],
                $datos['fecha_ingreso'],
                (float) $datos['valor'],
                Auth::id()
            );

            // Se leen de vuelta en vez de asumir ids consecutivos desde lastInsertId():
            // el código de lote ya se validó como único antes de llegar aquí (existeLote()
            // en validarLote()), así que esta consulta solo puede traer los que se acaban
            // de crear.
            $stmt = $pdo->prepare('SELECT id FROM bienes WHERE institucion_id = ? AND lote = ? ORDER BY id');
            $stmt->execute([$datos['institucion_id'], $datos['lote']]);
            $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            $pdo->commit();

            return $ids;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            ErrorHandler::reportar($e, __METHOD__);

            return null;
        }
    }

    public function editar(string $id): void
    {
        $id = (int) $id;
        $bien = Bien::find($id);
        $this->verificarAcceso($bien);

        $familiaSedesDestino = [];
        $espaciosPorSedeDestino = [];
        if (Auth::rol() === 'rector') {
            foreach (Institucion::familiaDe((int) $bien['institucion_id']) as $sede) {
                if ((int) $sede['id'] === (int) $bien['institucion_id']) {
                    continue;
                }
                $familiaSedesDestino[] = $sede;
                $espaciosPorSedeDestino[(int) $sede['id']] = Espacio::listadoParaSelect((int) $sede['id']);
            }
        }

        $asignacionActiva = Asignacion::activaDe($id);

        View::layout('partials/layout', 'bienes/form', [
            'title' => 'Editar bien',
            'bien' => $bien,
            'acciones' => CicloVidaBien::accionesDisponibles($bien, $asignacionActiva, $familiaSedesDestino),
            'urlVolver' => self::urlListado($id),
            'categorias' => $this->categoriasParaFormulario($bien),
            'asignacionActiva' => $asignacionActiva,
            'historialMovimientos' => Movimiento::historialDe($id),
            'espaciosInstitucion' => Espacio::listadoParaSelect((int) $bien['institucion_id']),
            'familiaSedesDestino' => $familiaSedesDestino,
            'espaciosPorSedeDestino' => $espaciosPorSedeDestino,
            'verificacionId' => Verificacion::idValidoParaBien($id, (string) ($_GET['verificacion_id'] ?? '')),
            'error' => Session::pullFlash('error'),
            'errorCampo' => Session::pullFlash('error_campo'),
            'mensaje' => Session::pullFlash('ok'),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $bien = Bien::find($id);
        $this->verificarAcceso($bien);

        // Dado de baja es el estado final del ciclo de vida: sus datos (valor, categoría,
        // código) quedan como estaban al darse de baja, para que el historial y los
        // reportes no cambien después.
        if ($bien['estado'] === 'dado_de_baja') {
            Session::flash('error', 'Este bien está dado de baja: sus datos ya no se pueden modificar.');
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request, (int) $bien['institucion_id']);
        $datos['estado'] = $this->estadoPermitidoDesdeFormulario($bien['estado'], $datos['estado']);
        $this->verificarCsrf($request, "/bienes/{$id}/editar", $datos);

        if ($resultado = $this->validar($datos, $id)) {
            Session::flash('error', $resultado['mensaje']);
            if ($resultado['campo'] !== null) {
                Session::flash('error_campo', $resultado['campo']);
            }
            Session::flashOld($datos);
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
            exit;
        }

        $imprimirQr = $datos['imprimir_qr'] === '1';
        $datosParaReintentar = $datos + $this->camposAccion($request);
        unset($datos['imprimir_qr']);

        // Acción elegida en "¿Qué desea hacer con este bien?" (por defecto, ninguna).
        $accion = (string) $request->input('accion');
        $asignacionActiva = Asignacion::activaDe($id);
        $familia = Auth::rol() === 'rector' ? array_filter(
            Institucion::familiaDe((int) $bien['institucion_id']),
            static fn (array $sede): bool => (int) $sede['id'] !== (int) $bien['institucion_id']
        ) : [];
        if ($accion !== '' && $accion !== 'ninguna'
            && !array_key_exists($accion, CicloVidaBien::accionesDisponibles($bien, $asignacionActiva, $familia))) {
            $this->volverConError($id, 'Esa acción no está disponible para este bien.', $datosParaReintentar);
        }

        // La foto de un reporte de baja se guarda antes (fuera de la transacción).
        $fotoBaja = null;
        if ($accion === 'reportar_baja' && ($archivo = $request->file('accion_foto_baja'))) {
            try {
                $fotoBaja = Uploader::storeImage($archivo, 'bajas');
            } catch (\RuntimeException $e) {
                $this->volverConError($id, $e->getMessage(), $datosParaReintentar);
            }
        }

        // Datos y acción van juntos: si la acción no se puede hacer, tampoco se guardan los
        // datos (y viceversa), y el formulario vuelve con lo que se había escrito.
        try {
            $mensajeAccion = Database::transaccion(function () use ($id, $datos, $accion, $request, $fotoBaja, $asignacionActiva): string {
                Bien::update($id, $datos);
                $actualizado = (array) Bien::find($id);
                $fecha = (string) ($request->input('accion_fecha') ?: date('Y-m-d'));
                $observaciones = trim((string) $request->input('accion_observaciones')) ?: null;
                $verificacionId = Verificacion::idValidoParaBien($id, (string) $request->input('verificacion_id'));

                return match ($accion) {
                    'asignar', 'trasladar' => CicloVidaBien::asignarOTrasladar(
                        $actualizado, (int) $request->input('accion_espacio_id'), $fecha, $observaciones, $verificacionId),
                    'trasladar_sede' => CicloVidaBien::trasladarSede(
                        $actualizado, (int) $request->input('accion_sede_id'), (int) $request->input('accion_espacio_sede_id'), $fecha, $observaciones),
                    'reintegrar' => CicloVidaBien::reintegrar(
                        $actualizado, $fecha, (string) $request->input('accion_destino'), $observaciones),
                    'reactivar' => CicloVidaBien::reactivar(
                        $actualizado, $fecha, (string) $request->input('accion_motivo')),
                    'reportar_baja' => CicloVidaBien::reportarBaja(
                        $actualizado, (string) $request->input('accion_estado_reportado'),
                        $asignacionActiva['espacio_nombre'] ?? null, (string) $request->input('accion_descripcion_baja'),
                        $fotoBaja, $verificacionId),
                    default => '',
                };
            });
        } catch (\DomainException $e) {
            $this->volverConError($id, $e->getMessage(), $datosParaReintentar);
        } catch (\PDOException $e) {
            if (!$this->esViolacionCodigoDuplicado($e)) {
                throw $e;
            }
            Session::flash('error_campo', 'codigo_identificacion');
            $this->volverConError($id, 'Ya existe un bien con ese código en la institución.', $datosParaReintentar);
        }

        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'editar', 'bien', $id, $bien, $datos);

        $this->procesarArchivos($id, $request, $datos['codigo_identificacion']);
        $this->procesarSolicitudQr($id, $imprimirQr, $request->input('quitar_qr') === '1');

        Session::flash('ok', trim('Bien actualizado. ' . $mensajeAccion));

        // Se queda en la ficha del bien para seguir trabajando con él. Excepciones: al
        // asignar un bien de un lote de alta masiva se va al listado del lote (para asignar
        // el resto), y tras trasladarlo a otra sede el bien ya es de esa sede.
        if ($accion === 'asignar' && !empty($bien['lote'])) {
            header('Location: ' . Url::to('/bienes?q=' . urlencode($bien['lote'])));
        } elseif ($accion === 'trasladar_sede') {
            header('Location: ' . self::urlListado());
        } else {
            header('Location: ' . Url::to("/bienes/{$id}/editar"));
        }
        exit;
    }

    /**
     * Dirección del listado de bienes con la búsqueda, filtros y página que tenía (ver
     * index()). Con $editado, el listado resalta ese bien.
     */
    public static function urlListado(?int $editado = null): string
    {
        parse_str((string) Session::get('bienes_listado', ''), $consulta);
        if ($editado !== null) {
            $consulta['editado'] = $editado;
        }

        return Url::to('/bienes' . ($consulta !== [] ? '?' . http_build_query($consulta) : ''));
    }

    /** Campos de la acción elegida, para devolverlos al formulario si algo falla. */
    private function camposAccion(Request $request): array
    {
        $campos = [];
        foreach (['accion', 'accion_espacio_id', 'accion_sede_id', 'accion_espacio_sede_id', 'accion_fecha',
                  'accion_observaciones', 'accion_destino', 'accion_motivo', 'accion_estado_reportado',
                  'accion_descripcion_baja'] as $campo) {
            $campos[$campo] = (string) $request->input($campo);
        }

        return $campos;
    }

    private function volverConError(int $id, string $mensaje, array $datosParaReintentar): never
    {
        Session::flash('error', $mensaje);
        Session::flashOld($datosParaReintentar);
        header('Location: ' . Url::to("/bienes/{$id}/editar"));
        exit;
    }

    /**
     * Distingue la violación de la restricción UNIQUE de código duplicado (una carrera
     * entre dos peticiones casi simultáneas) de cualquier otro error de base de datos,
     * que sí debe seguir propagándose como un fallo real.
     */
    private function esViolacionCodigoDuplicado(\PDOException $e): bool
    {
        // SQLSTATE 23000 agrupa varias restricciones (p. ej. 1452, una llave foránea
        // inválida); solo el 1062 es "valor duplicado". Antes cualquier 23000 se mostraba
        // como "ya existe un bien con ese código", un mensaje engañoso.
        return $e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062;
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

    /**
     * Casilla "Imprimir QR" del formulario: marcada agrega el bien a la Bodega de
     * impresión de QR (/bienes/qr-masivo); desmarcada lo retira si estaba ahí. No hace
     * nada si el bien no tenía autorización de edición (formulario deshabilitado).
     */
    /**
     * La casilla "Imprimir QR" es una orden, no un estado: marcada, manda el bien a la Bodega
     * de QR; desmarcada, no cambia nada. Antes, desmarcada lo sacaba de la Bodega y al editar
     * venía marcada si ya estaba en ella, así que guardar lo devolvía a "por imprimir" y se
     * reimprimía. Para sacarlo de la Bodega está la casilla aparte "Quitar de la Bodega".
     */
    private function procesarSolicitudQr(int $bienId, bool $solicitado, bool $quitar = false): void
    {
        if ($quitar) {
            Bien::cancelarSolicitudQr($bienId);
        } elseif ($solicitado) {
            Bien::solicitarQr($bienId, (int) Auth::id());
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
            // Mayúscula o minúscula según la categoría (ver Bien::descripcionSegunCategoria).
            'descripcion' => Bien::descripcionSegunCategoria(trim((string) $request->input('descripcion')), $categoriaId !== '' ? (int) $categoriaId : null),
            'marca' => trim((string) $request->input('marca')) ?: null,
            'categoria_id' => $categoriaId !== '' ? (int) $categoriaId : null,
            'fecha_ingreso' => (string) $request->input('fecha_ingreso'),
            'valor' => is_numeric($valor) ? (float) $valor : 0,
            'tiene_factura' => $request->input('tiene_factura') ? 1 : 0,
            'estado' => in_array($estado, self::ESTADOS, true) ? $estado : 'activo',
            // No es una columna de bienes -- viaja en $datos solo para que
            // Session::flashOld() la conserve si falla la validación; Bien::create()/
            // update() la ignoran (usan placeholders con nombre, no todo el arreglo).
            // Ver procesarSolicitudQr().
            'imprimir_qr' => $request->input('imprimir_qr') ? '1' : '0',
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

    /**
     * @return array{campo: ?string, mensaje: string}|null "campo" es el name del input a
     * resaltar en el formulario (ver BienController::guardar()/actualizar() y
     * bienes/form.php) — null cuando el error no corresponde a un campo puntual.
     */
    private function validar(array $datos, ?int $exceptId): ?array
    {
        if ($datos['codigo_identificacion'] === '') {
            return ['campo' => 'codigo_identificacion', 'mensaje' => 'El código de identificación es obligatorio.'];
        }

        if ($datos['descripcion'] === '') {
            return ['campo' => 'descripcion', 'mensaje' => 'La descripción es obligatoria.'];
        }

        if ($datos['fecha_ingreso'] === '' || !strtotime($datos['fecha_ingreso'])) {
            return ['campo' => 'fecha_ingreso', 'mensaje' => 'La fecha de ingreso no es válida.'];
        }

        if ($datos['valor'] < 0 || $datos['valor'] >= self::VALOR_MAXIMO) {
            return ['campo' => 'valor', 'mensaje' => 'El valor no puede ser negativo ni tener más de 10 dígitos.'];
        }

        if (Bien::existeCodigo($datos['institucion_id'], $datos['codigo_identificacion'], $exceptId)) {
            return ['campo' => 'codigo_identificacion', 'mensaje' => 'Ya existe un bien con ese código en la institución.'];
        }

        if ($datos['categoria_id'] !== null) {
            $categoria = Categoria::find((int) $datos['categoria_id']);
            if (!$categoria || (int) $categoria['institucion_id'] !== (int) $datos['institucion_id']) {
                return ['campo' => 'categoria_id', 'mensaje' => 'La categoría seleccionada no pertenece a esta institución.'];
            }

            // Mientras el bien esté en "Sin cartera", el código es obligatoriamente de 10
            // dígitos numéricos (ver Bien::siguienteCodigoSinCartera) — al cambiarlo a
            // cualquier otra categoría esta regla deja de aplicar. No aplica a la alta
            // masiva (validarLote()): ahí el código sigue el formato "{lote}-001", que
            // nunca puede ser de 10 dígitos puros.
            if (Categoria::esProtegida($categoria) && !preg_match('/^[0-9]{10}$/', $datos['codigo_identificacion'])) {
                return [
                    'campo' => 'codigo_identificacion',
                    'mensaje' => 'El código debe tener exactamente 10 dígitos numéricos para bienes en la categoría "' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '".',
                ];
            }
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
        Csrf::verificarORedirigir($request, $volverA, $datosAConservar);
    }

    /**
     * @phpstan-assert array $bien
     */
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
        $categorias = Categoria::activas((int) $bien['institucion_id']);

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
