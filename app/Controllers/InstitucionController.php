<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Uploader;
use App\Models\Institucion;

final class InstitucionController
{
    public function index(): void
    {
        if (!Auth::esSuperusuario()) {
            header('Location: ' . Url::to('/instituciones/' . Auth::institucionId() . '/editar'));
            exit;
        }

        View::layout('partials/layout', 'instituciones/index', [
            'title' => 'Instituciones',
            'instituciones' => Institucion::all(),
            'mensaje' => Session::pullFlash('ok'),
        ]);
    }

    public function crear(): void
    {
        if (!Auth::esSuperusuario()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        View::layout('partials/layout', 'instituciones/form', [
            'title' => 'Nueva institución',
            'institucion' => null,
            'instituciones' => Institucion::listadoParaSelect(true),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request, '/instituciones/crear');

        $datos = $this->datosDesdeFormulario($request);

        if ($error = $this->validar($datos, null)) {
            Session::flash('error', $error);
            header('Location: ' . Url::to('/instituciones/crear'));
            exit;
        }

        $id = Institucion::create($datos);

        if ($archivo = $request->file('logo')) {
            $this->subirLogo($id, $archivo);
        }

        Session::flash('ok', 'Institución creada correctamente.');
        header('Location: ' . Url::to('/instituciones'));
        exit;
    }

    public function editar(string $id): void
    {
        $id = (int) $id;
        $this->verificarAcceso($id);

        $institucion = Institucion::find($id);
        if (!$institucion) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        View::layout('partials/layout', 'instituciones/form', [
            'title' => 'Editar institución',
            'institucion' => $institucion,
            'instituciones' => $this->institucionesParaFormulario($institucion),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $this->verificarAcceso($id);

        $request = new Request();
        $this->verificarCsrf($request, "/instituciones/{$id}/editar");

        $datos = $this->datosDesdeFormulario($request);

        if ($error = $this->validar($datos, $id)) {
            Session::flash('error', $error);
            header('Location: ' . Url::to("/instituciones/{$id}/editar"));
            exit;
        }

        Institucion::update($id, $datos);

        if ($archivo = $request->file('logo')) {
            $this->subirLogo($id, $archivo);
        }

        Session::flash('ok', 'Institución actualizada.');
        header('Location: ' . Url::to(Auth::esSuperusuario() ? '/instituciones' : "/instituciones/{$id}/editar"));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $id = (int) $id;
        $request = new Request();
        $this->verificarCsrf($request, '/instituciones');

        $institucion = Institucion::find($id);

        if ($institucion) {
            Institucion::setActivo($id, !((bool) $institucion['activo']));
            Session::flash('ok', 'Estado de la institución actualizado.');
        }

        header('Location: ' . Url::to('/instituciones'));
        exit;
    }

    private function subirLogo(int $institucionId, array $archivo): void
    {
        try {
            $path = Uploader::storeImage($archivo, 'logos');
            if ($path) {
                Institucion::updateLogo($institucionId, $path);
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
    }

    private function datosDesdeFormulario(Request $request): array
    {
        $tipoSede = $request->input('tipo_sede') === 'seccion' ? 'seccion' : 'principal';
        $padreId = $request->input('institucion_padre_id');

        return [
            'codigo_dane' => trim((string) $request->input('codigo_dane')),
            'nombre' => trim((string) $request->input('nombre')),
            'direccion' => trim((string) $request->input('direccion')) ?: null,
            'tipo_sede' => $tipoSede,
            'institucion_padre_id' => ($tipoSede === 'seccion' && $padreId) ? (int) $padreId : null,
            'email_institucional' => trim((string) $request->input('email_institucional')) ?: null,
        ];
    }

    private function validar(array $datos, ?int $exceptId): ?string
    {
        if ($datos['codigo_dane'] === '' || $datos['nombre'] === '') {
            return 'El código DANE y el nombre son obligatorios.';
        }

        if (Institucion::existeCodigoDane($datos['codigo_dane'], $exceptId)) {
            return 'Ya existe una institución con ese código DANE.';
        }

        return null;
    }

    private function verificarCsrf(Request $request, string $volverA): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to($volverA));
            exit;
        }
    }

    private function verificarAcceso(int $id): void
    {
        if (!Auth::esSuperusuario() && Auth::institucionId() !== $id) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /**
     * Instituciones activas para el <select> de "institución principal", más la actual
     * si fue desactivada después de asignársela (para no perderla del formulario al guardar).
     */
    private function institucionesParaFormulario(array $institucion): array
    {
        $instituciones = Institucion::listadoParaSelect(true);

        if ($institucion['institucion_padre_id'] === null) {
            return $instituciones;
        }

        foreach ($instituciones as $opcion) {
            if ((int) $opcion['id'] === (int) $institucion['institucion_padre_id']) {
                return $instituciones;
            }
        }

        $padreActual = Institucion::find((int) $institucion['institucion_padre_id']);
        if ($padreActual) {
            $padreActual['nombre'] .= ' (inactiva)';
            $instituciones[] = $padreActual;
        }

        return $instituciones;
    }
}
