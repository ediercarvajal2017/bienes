<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Helpers\Evidencia;
use App\Helpers\FechaMovimiento;
use App\Helpers\Paginador;
use App\Helpers\Uploader;
use App\Models\Auditoria;
use App\Models\CarteraEnvio;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Services\VerificacionCartera;

/**
 * @phpstan-import-type DatosAviso from Aviso
 *
 * "Cartera recibida de la Alcaldía": la institución solicita la cartera por correo (fuera
 * del sistema), la Alcaldía la envía y aquí se guarda la evidencia: quién la solicitó y
 * desde qué correo, quién la envió en la Alcaldía y desde qué correo, la fecha en que se
 * recibió y el archivo.
 *
 * Todo en UNA ventana (/cartera/enviar): el formulario (registrar, o editar con ?editar=ID)
 * y debajo los registros con Descargar, Editar y Eliminar (ver Evidencia). Las direcciones
 * viejas /cartera/enviados y /cartera/{id}/editar llevan a esa ventana.
 */
final class CarteraController
{
    private const VENTANA = '/cartera/enviar';

    public function formulario(): void
    {
        $registro = isset($_GET['editar']) ? CarteraEnvio::find((int) $_GET['editar']) : null;
        if (isset($_GET['editar'])) {
            Evidencia::verificarAcceso($registro);
        }
        $institucionId = $registro !== null ? (int) $registro['institucion_id'] : Evidencia::institucionDeVentana();
        $funcionarios = $registro !== null ? $this->funcionariosParaRegistro($registro)
            : ($institucionId > 0 ? Usuario::elegiblesACargo($institucionId) : []);
        $viejo = Session::pullOld();
        [$pagina, $porPagina] = Evidencia::paginacion();
        $alcance = $institucionId > 0 ? $institucionId : null;
        $total = CarteraEnvio::contarListado($alcance);

        View::layout('partials/layout', 'cartera/formulario', [
            'title' => 'Cartera recibida de la Alcaldía',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'registro' => $registro,
            'funcionarios' => $funcionarios,
            'valores' => $viejo !== [] ? $viejo : ($registro !== null
                ? $this->valoresDeRegistro($registro, $funcionarios)
                : $this->valoresPorDefecto($funcionarios)),
            'envios' => CarteraEnvio::listar($alcance, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => Evidencia::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    /**
     * Registrar: por defecto, quien registra (si es de la institución) y su correo.
     *
     * @param list<array<string, mixed>> $funcionarios
     * @return array<string, mixed>
     */
    private function valoresPorDefecto(array $funcionarios): array
    {
        $porDefecto = null;
        foreach ($funcionarios as $f) {
            if ((int) $f['id'] === (int) Auth::id()) {
                $porDefecto = $f;
            }
        }

        return [
            'funcionario_id' => $porDefecto['id'] ?? '',
            'correo_solicitante' => $porDefecto['email'] ?? '',
            'correo_remitente' => '',
            'nombre_remitente' => '',
            'fecha_envio' => date('Y-m-d'),
        ];
    }

    /**
     * Editar: los datos del registro. Uno de antes (sin funcionario enlazado) preselecciona
     * el usuario cuyo nombre coincide con el que se escribió, si lo hay.
     *
     * @param list<array<string, mixed>> $funcionarios
     * @return array<string, mixed>
     */
    private function valoresDeRegistro(array $registro, array $funcionarios): array
    {
        $funcionarioId = (int) ($registro['funcionario_id'] ?? 0);
        if ($funcionarioId === 0) {
            foreach ($funcionarios as $f) {
                if (mb_strtolower(trim($f['nombres'] . ' ' . $f['apellidos'])) === mb_strtolower(trim((string) $registro['nombre_funcionario']))) {
                    $funcionarioId = (int) $f['id'];
                }
            }
        }

        return [
            'funcionario_id' => $funcionarioId ?: '',
            'correo_solicitante' => (string) ($registro['correo_solicitante'] ?? ''),
            'correo_remitente' => (string) $registro['correo_remitente'],
            'nombre_remitente' => (string) ($registro['nombre_remitente'] ?? ''),
            'fecha_envio' => (string) $registro['fecha_envio'],
        ];
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $institucionId = Evidencia::institucionDeVentana();
        $volverA = Evidencia::ruta(self::VENTANA, $institucionId);

        if ($institucionId === 0) {
            Aviso::enviar(\App\Helpers\Mensajes::eligeInstitucion('No se guardó la cartera recibida'));
            Evidencia::redirigir(self::VENTANA);
        }

        [$error, $datos] = $this->leerFormulario($request, $institucionId, null);
        if ($error !== null) {
            $this->volverConError($volverA, $error, $request);
        }

        try {
            $archivoPath = Uploader::storeExcel($_FILES['archivo'] ?? [], 'cartera');
            if ($archivoPath === null) {
                $this->volverConError($volverA, 'Adjunta el archivo de la cartera recibida.', $request);
            }

            $datos = [
                'institucion_id' => $institucionId,
                'archivo_path' => $archivoPath,
                'registrado_por' => Auth::id(),
            ] + $datos;
            $id = CarteraEnvio::create($datos);
            Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'cartera_envio', $id, null, $datos);

            Aviso::enviar(Aviso::exito('Cartera recibida registrada en la biblioteca de evidencia.', 'Aparece en la lista de abajo; desde allí puedes descargar el archivo o editarlo.'));
        } catch (\RuntimeException $e) {
            $this->volverConError($volverA, $e->getMessage(), $request);
        }

        Evidencia::redirigir($volverA);
    }

    /**
     * Verificar un lote en esta cartera (App\Services\VerificacionCartera): el usuario elige un
     * lote entregado y MIA muestra solo sus bienes que siguen en la cartera. Solo lee.
     */
    public function verificacion(string $id): void
    {
        $envio = CarteraEnvio::find((int) $id);
        Evidencia::verificarAcceso($envio);

        View::layout('partials/layout', 'cartera/verificacion', ['title' => 'Verificar un lote en la cartera'] + $this->verificar($envio));
    }

    /** Los bienes del lote que siguen en la cartera, en Excel, para enviarlo a la Alcaldía. */
    public function verificacionXlsx(string $id): void
    {
        $envio = CarteraEnvio::find((int) $id);
        Evidencia::verificarAcceso($envio);
        $datos = $this->verificar($envio);

        if ($datos['lote'] === null || $datos['resultado'] === null || $datos['resultado']['filas'] === []) {
            // Si hubo un problema, la pantalla lo vuelve a mostrar con su aviso guiado.
            if ($datos['problema'] === null) {
                Aviso::enviar(Aviso::info('No hay nada que descargar', $datos['lote'] === null
                    ? 'Elige primero el lote que quieres verificar.'
                    : 'Ningún bien de ese lote sigue en la cartera.'));
            }
            Evidencia::redirigir('/cartera/' . (int) $envio['id'] . '/verificacion?' . http_build_query(array_filter([
                'lote' => $datos['loteId'] ?: null, 'columna' => $datos['columnaPlaca'], 'cantidad' => $datos['columnaCantidad'],
            ])));
        }

        \App\Services\ReporteService::enviarXlsx(
            \App\Services\ReporteService::bienesSinDescargar($envio, $datos['lote'], $datos['resultado']['filas'], $datos['resultado']['total_lote']),
            'siguen-en-cartera-lote-' . (int) $datos['lote']['id']
        );
    }

    /**
     * Lo que muestra la verificación: los lotes entregados para elegir y, con ?lote=, el
     * resultado o el problema que lo impidió (aviso guiado). ?columna= y ?cantidad=: las columnas
     * de placa y cantidad elegidas a mano cuando MIA no reconoce los títulos de la cartera.
     *
     * @return array{envio: array<string, mixed>, lotes: list<array<string, mixed>>, loteId: int, lote: ?array<string, mixed>,
     *     problema: ?DatosAviso, lectura: ?array<string, mixed>, resultado: ?array{filas: list<array<string, mixed>>, total_lote: int, total_siguen: int},
     *     columnaPlaca: ?string, columnaCantidad: ?string}
     */
    private function verificar(array $envio): array
    {
        $institucionId = (int) $envio['institucion_id'];
        $texto = static fn (string $clave): ?string => is_string($_GET[$clave] ?? null) && trim($_GET[$clave]) !== '' ? trim($_GET[$clave]) : null;
        $datos = [
            'envio' => $envio,
            'lotes' => array_values(\App\Models\LoteReintegro::listar($institucionId, 1, 0, ['estado' => 'reintegrado'])),
            'loteId' => max(0, (int) ($_GET['lote'] ?? 0)),
            'lote' => null,
            'problema' => null,
            'lectura' => null,
            'resultado' => null,
            'columnaPlaca' => $texto('columna'),
            'columnaCantidad' => $texto('cantidad'),
        ];
        if ($datos['loteId'] === 0) {
            return $datos;
        }

        $numero = '#' . $datos['loteId'];
        $lote = \App\Models\LoteReintegro::find($datos['loteId']);
        if ($lote === null || (int) $lote['institucion_id'] !== $institucionId || $lote['estado'] !== 'reintegrado') {
            return ['problema' => Aviso::error('No se verificó el lote',
                "El lote {$numero} no está entre los lotes entregados a la Alcaldía de esta institución.",
                'Elige uno de la lista «¿Qué lote quieres verificar?».')] + $datos;
        }
        $datos['lote'] = $lote;

        if ((string) $lote['reintegrado_fecha'] > (string) $envio['fecha_envio']) {
            return ['problema' => Aviso::advertencia('Esta cartera es anterior a la entrega del lote',
                "El lote {$numero} se entregó el {$lote['reintegrado_fecha']} y esta cartera se recibió el {$envio['fecha_envio']}: todavía no podía mostrar el descargo.",
                'Pide a la Alcaldía una cartera más reciente, regístrala y verifica el lote con ella.',
                Aviso::accion('Registrar otra cartera', Evidencia::ruta(self::VENTANA, $institucionId)))] + $datos;
        }

        $editar = Aviso::accion('Editar el registro', Evidencia::ruta(self::VENTANA, $institucionId, ['editar' => (int) $envio['id']]) . '#formularioEvidencia');
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $ruta = $config['storage_path'] . '/uploads/' . $envio['archivo_path'];
        if (!is_file($ruta)) {
            return ['problema' => Aviso::error('No se pudo leer la cartera', 'El archivo de esta cartera no está en el servidor.',
                'Edita el registro y vuelve a adjuntar el Excel que envió la Alcaldía.', $editar)] + $datos;
        }

        try {
            $lectura = VerificacionCartera::leerCartera($ruta, $datos['columnaPlaca'], $datos['columnaCantidad']);
        } catch (\Throwable $e) {
            $incidente = \App\Core\ErrorHandler::reportar($e, __METHOD__);

            return ['problema' => Aviso::error('No se pudo leer la cartera', 'MIA no pudo abrir el archivo como Excel: puede estar dañado o tener otro formato.',
                "Ábrelo en Excel, guárdalo de nuevo como .xlsx y reemplázalo en el registro. Si se repite, comparte el código {$incidente} con soporte.", $editar)] + $datos;
        }
        $datos['lectura'] = $lectura;

        if (!$lectura['encontrada']) {
            return ['problema' => Aviso::advertencia('No se encontró la columna de la placa',
                $datos['columnaPlaca'] !== null
                    ? 'La columna elegida no existe en el archivo.'
                    : 'Ninguna columna de la cartera tiene un título como «Placa», «Código» o «Inventario».',
                'Elige abajo en qué columna está la placa y vuelve a verificar.')] + $datos;
        }

        $datos['resultado'] = VerificacionCartera::verificar($institucionId, $datos['loteId'], (string) $envio['fecha_envio'], $lectura['placas']);

        return $datos;
    }

    /** La lista ya está en la ventana única: la dirección vieja lleva allí. */
    public function historial(): void
    {
        Evidencia::redirigir(Evidencia::ruta(self::VENTANA, Evidencia::institucionDeVentana()));
    }

    /** Se edita en la ventana única: la dirección vieja lleva allí. */
    public function formularioEditar(string $id): void
    {
        Evidencia::redirigir(self::VENTANA . '?editar=' . (int) $id . '#formularioEvidencia');
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = CarteraEnvio::find($id);
        Evidencia::verificarAcceso($registro);
        $volverA = Evidencia::ruta(self::VENTANA, (int) $registro['institucion_id'], ['editar' => $id]);

        [$error, $datos] = $this->leerFormulario($request, (int) $registro['institucion_id'], $registro);
        if ($error !== null) {
            $this->volverConError($volverA, $error, $request);
        }

        try {
            $archivoPath = $registro['archivo_path'];
            $nuevoArchivo = Uploader::storeExcel($_FILES['archivo'] ?? [], 'cartera');
            if ($nuevoArchivo !== null) {
                Evidencia::eliminarArchivoFisico($archivoPath);
                $archivoPath = $nuevoArchivo;
            }

            $datosNuevos = $datos + ['archivo_path' => $archivoPath];
            CarteraEnvio::actualizar($id, $datosNuevos);
            Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'editar', 'cartera_envio', $id, $registro, $datosNuevos);

            Aviso::enviar(Aviso::exito('Registro actualizado.'));
        } catch (\RuntimeException $e) {
            $this->volverConError($volverA, $e->getMessage(), $request);
        }

        Evidencia::redirigir(Evidencia::ruta(self::VENTANA, (int) $registro['institucion_id']));
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = CarteraEnvio::find($id);
        Evidencia::verificarAcceso($registro);

        CarteraEnvio::eliminar($id, (int) Auth::id());
        Auditoria::registrar(Auth::id(), (int) $registro['institucion_id'], 'eliminar', 'cartera_envio', $id, $registro);

        Aviso::enviar(Aviso::exito('Registro enviado a la papelera.', 'Si fue un error, un superusuario puede restaurarlo desde la Papelera.'));
        Evidencia::redirigir(Evidencia::ruta(self::VENTANA, (int) $registro['institucion_id']));
    }

    /**
     * Valida los datos del formulario. El funcionario debe ser un usuario activo de la
     * institución (al editar, también vale el que ya tenía el registro).
     *
     * @return array{0: ?string, 1: array{funcionario_id: int, nombre_funcionario: string, correo_solicitante: string, correo_remitente: string, nombre_remitente: string, fecha_envio: string}}
     */
    private function leerFormulario(Request $request, int $institucionId, ?array $registro): array
    {
        $funcionarioId = (int) $request->input('funcionario_id');
        $correoSolicitante = trim((string) $request->input('correo_solicitante'));
        $correoRemitente = trim((string) $request->input('correo_remitente'));
        $nombreRemitente = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('nombre_remitente')));
        $fecha = trim((string) $request->input('fecha_envio'));

        $funcionario = null;
        foreach ($registro !== null ? $this->funcionariosParaRegistro($registro) : Usuario::elegiblesACargo($institucionId) as $f) {
            if ((int) $f['id'] === $funcionarioId) {
                $funcionario = $f;
            }
        }

        $error = match (true) {
            $funcionario === null => 'Elige el funcionario de la institución que solicitó la cartera.',
            !filter_var($correoSolicitante, FILTER_VALIDATE_EMAIL) => 'Escribe un correo válido del funcionario que solicitó la cartera.',
            $nombreRemitente === '' => 'Escribe los nombres completos del funcionario de la Alcaldía que envió la cartera.',
            mb_strlen($nombreRemitente) > 150 => 'El nombre de quien envió la cartera es muy largo (máximo 150 caracteres).',
            !filter_var($correoRemitente, FILTER_VALIDATE_EMAIL) => 'Escribe un correo válido desde el que llegó la cartera.',
            FechaMovimiento::error($fecha) !== null => 'Fecha en que se recibió: ' . mb_strtolower((string) FechaMovimiento::error($fecha)),
            default => null,
        };

        return [$error, [
            'funcionario_id' => $funcionarioId,
            'nombre_funcionario' => $funcionario !== null ? trim($funcionario['nombres'] . ' ' . $funcionario['apellidos']) : '',
            'correo_solicitante' => $correoSolicitante,
            'correo_remitente' => $correoRemitente,
            'nombre_remitente' => $nombreRemitente,
            'fecha_envio' => $fecha,
        ]];
    }

    /**
     * Funcionarios que se pueden elegir para un registro: los usuarios activos de su
     * institución y, si el que ya tenía ya no está entre ellos (p. ej. se desactivó), él también.
     *
     * @return list<array<string, mixed>>
     */
    private function funcionariosParaRegistro(array $registro): array
    {
        $funcionarios = Usuario::elegiblesACargo((int) $registro['institucion_id']);
        $actualId = (int) ($registro['funcionario_id'] ?? 0);
        if ($actualId > 0 && !in_array($actualId, array_map(static fn (array $f): int => (int) $f['id'], $funcionarios), true)) {
            $actual = Usuario::find($actualId);
            if ($actual !== null && (int) $actual['institucion_id'] === (int) $registro['institucion_id']) {
                $funcionarios[] = $actual;
            }
        }

        return $funcionarios;
    }

    /** $mensaje: el dato que falta o está mal; el formulario conserva lo escrito. */
    private function volverConError(string $volverA, string $mensaje, Request $request): never
    {
        Aviso::enviar(Aviso::error('No se guardó la cartera recibida', $mensaje, 'Corrige ese dato y vuelve a guardar: lo demás que escribiste sigue en el formulario.'));
        Session::flashOld([
            'funcionario_id' => (string) $request->input('funcionario_id'),
            'correo_solicitante' => (string) $request->input('correo_solicitante'),
            'correo_remitente' => (string) $request->input('correo_remitente'),
            'nombre_remitente' => (string) $request->input('nombre_remitente'),
            'fecha_envio' => (string) $request->input('fecha_envio'),
        ]);
        Evidencia::redirigir($volverA);
    }

    private function verificarCsrf(Request $request): void
    {
        Csrf::verificarORedirigir($request, self::VENTANA);
    }
}
