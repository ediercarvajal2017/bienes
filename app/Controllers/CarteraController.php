<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
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

/**
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
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
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
            Session::flash('error', 'Selecciona una institución.');
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

            Session::flash('ok', 'Cartera recibida registrada en la biblioteca de evidencia.');
        } catch (\RuntimeException $e) {
            $this->volverConError($volverA, $e->getMessage(), $request);
        }

        Evidencia::redirigir($volverA);
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

            Session::flash('ok', 'Registro actualizado.');
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

        Session::flash('ok', 'Registro enviado a la papelera. Un superusuario puede restaurarlo si fue un error.');
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

    private function volverConError(string $volverA, string $mensaje, Request $request): never
    {
        Session::flash('error', $mensaje);
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
