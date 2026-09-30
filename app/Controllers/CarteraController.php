<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
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
 * desde qué correo, desde qué correo llegó, la fecha en que se recibió y el archivo.
 * (Las direcciones /cartera/enviar y /cartera/enviados se conservan por los enlaces ya
 * guardados.)
 */
final class CarteraController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function formulario(): void
    {
        $institucionId = $this->institucionSeleccionada();
        $funcionarios = $institucionId > 0 ? Usuario::elegiblesACargo($institucionId) : [];
        $viejo = Session::pullOld();
        // Por defecto, quien registra (si es de la institución) y su correo.
        $porDefecto = null;
        foreach ($funcionarios as $f) {
            if ((int) $f['id'] === (int) Auth::id()) {
                $porDefecto = $f;
            }
        }

        View::layout('partials/layout', 'cartera/formulario', [
            'title' => 'Cartera recibida de la Alcaldía',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'funcionarios' => $funcionarios,
            'valores' => $viejo !== [] ? $viejo : [
                'funcionario_id' => $porDefecto['id'] ?? '',
                'correo_solicitante' => $porDefecto['email'] ?? '',
                'correo_remitente' => '',
                'fecha_envio' => date('Y-m-d'),
            ],
            'error' => Session::pullFlash('error'),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request);

        $institucionId = $this->institucionSeleccionada();
        $volverA = '/cartera/enviar' . (Auth::esSuperusuario() && $institucionId > 0 ? '?institucion=' . $institucionId : '');

        if ($institucionId === 0) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/cartera/enviar'));
            exit;
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

        header('Location: ' . Url::to($volverA));
        exit;
    }

    public function historial(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = CarteraEnvio::contarListado($institucionId);

        View::layout('partials/layout', 'cartera/historial', [
            'title' => 'Histórico de cartera recibida',
            'envios' => CarteraEnvio::listar($institucionId, $pagina, $porPagina),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    public function formularioEditar(string $id): void
    {
        $registro = CarteraEnvio::find((int) $id);
        Evidencia::verificarAcceso($registro);
        $funcionarios = $this->funcionariosParaRegistro($registro);

        // Un registro de antes (sin funcionario enlazado): se preselecciona el usuario cuyo
        // nombre coincide con el que se escribió, si lo hay.
        $funcionarioId = (int) ($registro['funcionario_id'] ?? 0);
        if ($funcionarioId === 0) {
            foreach ($funcionarios as $f) {
                if (mb_strtolower(trim($f['nombres'] . ' ' . $f['apellidos'])) === mb_strtolower(trim((string) $registro['nombre_funcionario']))) {
                    $funcionarioId = (int) $f['id'];
                }
            }
        }
        $viejo = Session::pullOld();

        View::layout('partials/layout', 'cartera/editar', [
            'title' => 'Editar registro de cartera recibida',
            'registro' => $registro,
            'funcionarios' => $funcionarios,
            'valores' => $viejo !== [] ? $viejo : [
                'funcionario_id' => $funcionarioId ?: '',
                'correo_solicitante' => (string) ($registro['correo_solicitante'] ?? ''),
                'correo_remitente' => (string) $registro['correo_remitente'],
                'fecha_envio' => (string) $registro['fecha_envio'],
            ],
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request);

        $registro = CarteraEnvio::find($id);
        Evidencia::verificarAcceso($registro);
        $volverA = "/cartera/{$id}/editar";

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

        header('Location: ' . Url::to('/cartera/enviados'));
        exit;
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
        header('Location: ' . Url::to('/cartera/enviados'));
        exit;
    }

    /**
     * Valida los datos del formulario. El funcionario debe ser un usuario activo de la
     * institución (al editar, también vale el que ya tenía el registro).
     *
     * @return array{0: ?string, 1: array{funcionario_id: int, nombre_funcionario: string, correo_solicitante: string, correo_remitente: string, fecha_envio: string}}
     */
    private function leerFormulario(Request $request, int $institucionId, ?array $registro): array
    {
        $funcionarioId = (int) $request->input('funcionario_id');
        $correoSolicitante = trim((string) $request->input('correo_solicitante'));
        $correoRemitente = trim((string) $request->input('correo_remitente'));
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
            !filter_var($correoRemitente, FILTER_VALIDATE_EMAIL) => 'Escribe un correo válido desde el que llegó la cartera.',
            FechaMovimiento::error($fecha) !== null => 'Fecha en que se recibió: ' . mb_strtolower((string) FechaMovimiento::error($fecha)),
            default => null,
        };

        return [$error, [
            'funcionario_id' => $funcionarioId,
            'nombre_funcionario' => $funcionario !== null ? trim($funcionario['nombres'] . ' ' . $funcionario['apellidos']) : '',
            'correo_solicitante' => $correoSolicitante,
            'correo_remitente' => $correoRemitente,
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
            'fecha_envio' => (string) $request->input('fecha_envio'),
        ]);
        header('Location: ' . Url::to($volverA));
        exit;
    }

    private function institucionSeleccionada(): int
    {
        if (!Auth::esSuperusuario()) {
            return (int) Auth::institucionId();
        }

        return (int) ($_GET['institucion'] ?? $_POST['institucion_id'] ?? 0);
    }

    private function verificarCsrf(Request $request): void
    {
        Csrf::verificarORedirigir($request, '/cartera/enviar');
    }
}
