<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Uploader;
use App\Models\Auditoria;
use App\Models\Categoria;
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
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/instituciones/crear', $datos);

        if ($falla = $this->validar($datos, null)) {
            Aviso::enviar(Aviso::error('No se creó la institución', $falla['porQue'], $falla['queHacer'], null, [$falla['campo'] => $falla['enCampo']]));
            Session::flashOld($datos);
            header('Location: ' . Url::to('/instituciones/crear'));
            exit;
        }

        $id = Institucion::create($datos);
        Auditoria::registrar(Auth::id(), $id, 'crear', 'institucion', $id, null, $datos);

        // Cada institución nueva arranca con su propio set base de categorías (ver
        // Categoria::sembrarPorDefecto) — así no empieza con el catálogo vacío.
        Categoria::sembrarPorDefecto($id);

        if ($archivo = $request->file('logo')) {
            $this->subirLogo($id, $archivo);
        }

        Aviso::enviar(Aviso::exito("Institución {$datos['nombre']} creada, con sus categorías base.",
            'Siguiente paso: registra a su rector en Usuarios para que pueda entrar a MIA.', Aviso::accion('Ir a Usuarios', '/usuarios')));
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
            'viejo' => Session::pullOld(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $this->verificarAcceso($id);

        $antes = Institucion::find($id);
        if (!$antes) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);

        // La estructura de la red de sedes (principal/sección y su institución padre) y el
        // código DANE solo los cambia el superusuario. Si un rector pudiera declarar su
        // institución como "sección" de cualquier otra, Institucion::familiaDe() pasaría a
        // incluir a esa otra institución y, con /sede-activa, podría entrar a ella con
        // todos sus permisos. Por eso aquí se ignora lo que llegue en el formulario.
        if (!Auth::esSuperusuario()) {
            $datos['codigo_dane'] = (string) $antes['codigo_dane'];
            $datos['tipo_sede'] = (string) $antes['tipo_sede'];
            $datos['institucion_padre_id'] = $antes['institucion_padre_id'] !== null
                ? (int) $antes['institucion_padre_id']
                : null;
        }

        $this->verificarCsrf($request, "/instituciones/{$id}/editar", $datos);

        if ($falla = $this->validar($datos, $id, $antes)) {
            Aviso::enviar(Aviso::error('No se guardó la institución', $falla['porQue'], $falla['queHacer'], null, [$falla['campo'] => $falla['enCampo']]));
            Session::flashOld($datos);
            header('Location: ' . Url::to("/instituciones/{$id}/editar"));
            exit;
        }

        Institucion::update($id, $datos);
        Auditoria::registrar(Auth::id(), $id, 'editar', 'institucion', $id, $antes, $datos);

        if ($archivo = $request->file('logo')) {
            $this->subirLogo($id, $archivo);
        }

        Aviso::enviar(Aviso::exito("Institución {$datos['nombre']} actualizada."));
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
            $nuevoEstado = !((bool) $institucion['activo']);
            Institucion::setActivo($id, $nuevoEstado);
            Auditoria::registrar(Auth::id(), $id, $nuevoEstado ? 'activar' : 'desactivar', 'institucion', $id, $institucion, null);
            Aviso::enviar($nuevoEstado
                ? Aviso::exito("Institución {$institucion['nombre']} activada.", 'Sus usuarios pueden volver a entrar a MIA.')
                : Aviso::exito("Institución {$institucion['nombre']} desactivada.", 'Sus usuarios ya no pueden entrar; sus datos se conservan.'));
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
            // Los datos ya se guardaron: solo faltó el logo.
            Aviso::enviar(Aviso::advertencia('El logo no se guardó', $e->getMessage(),
                'Abre la institución y vuelve a subir el logo (JPG o PNG).', Aviso::accion('Abrir la institución', "/instituciones/{$institucionId}/editar")));
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

    /**
     * $antes: la institución tal como está guardada (solo al editar). La institución padre
     * se valida únicamente si cambió, para no impedir editar el nombre o la dirección de
     * una sección ya existente cuya principal fue desactivada después.
     */
    /** @return array{campo: string, porQue: string, queHacer: string, enCampo: string}|null */
    private function validar(array $datos, ?int $exceptId, ?array $antes = null): ?array
    {
        if ($datos['codigo_dane'] === '') {
            return ['campo' => 'codigo_dane', 'porQue' => 'Falta el código DANE.', 'enCampo' => 'Escribe el código DANE.',
                'queHacer' => 'Escribe el código DANE de la institución (solo números) y vuelve a guardar.'];
        }
        if ($datos['nombre'] === '') {
            return ['campo' => 'nombre', 'porQue' => 'Falta el nombre de la institución.', 'enCampo' => 'Escribe el nombre.',
                'queHacer' => 'Escribe el nombre completo de la institución y vuelve a guardar.'];
        }

        if (Institucion::existeCodigoDane($datos['codigo_dane'], $exceptId)) {
            return ['campo' => 'codigo_dane', 'porQue' => "Ya existe una institución con el código DANE {$datos['codigo_dane']}.",
                'enCampo' => 'Este código DANE ya existe.', 'queHacer' => 'Revisa el número; si es la misma institución, edita la que ya existe.'];
        }

        $estructuraSinCambios = $antes !== null
            && $antes['tipo_sede'] === $datos['tipo_sede']
            && (int) ($antes['institucion_padre_id'] ?? 0) === (int) ($datos['institucion_padre_id'] ?? 0);

        if ($datos['tipo_sede'] === 'seccion' && !$estructuraSinCambios) {
            return $this->validarPadre($datos['institucion_padre_id'], $exceptId);
        }

        return null;
    }

    /**
     * Una sección debe colgar de una institución principal existente y activa, distinta
     * de ella misma. Una institución que ya tiene secciones no puede volverse sección de
     * otra (dejaría a sus secciones colgando de una sección, algo que familiaDe() no
     * contempla).
     */
    /** @return array{campo: string, porQue: string, queHacer: string, enCampo: string}|null */
    private function validarPadre(?int $padreId, ?int $exceptId): ?array
    {
        $campo = 'institucion_padre_id';
        if ($padreId === null) {
            return ['campo' => $campo, 'porQue' => 'Falta la institución principal de esta sección.', 'enCampo' => 'Elige la institución principal.',
                'queHacer' => 'Elige en «Institución principal» la sede a la que pertenece esta sección.'];
        }

        if ($exceptId !== null && $padreId === $exceptId) {
            return ['campo' => $campo, 'porQue' => 'Una institución no puede ser sección de sí misma.', 'enCampo' => 'Elige otra institución.',
                'queHacer' => 'Elige otra institución principal, o cambia el tipo a «Principal».'];
        }

        $padre = Institucion::find($padreId);
        if (!$padre || !(int) $padre['activo'] || $padre['tipo_sede'] !== 'principal') {
            return ['campo' => $campo, 'porQue' => 'La institución principal elegida está inactiva o también es una sección.',
                'enCampo' => 'Elige una sede principal activa.', 'queHacer' => 'Elige una institución principal que esté activa.'];
        }

        if ($exceptId !== null && Institucion::tieneSecciones($exceptId)) {
            return ['campo' => 'tipo_sede', 'porQue' => 'Esta institución tiene secciones a su cargo, así que no puede convertirse en sección de otra.',
                'enCampo' => 'Tiene secciones a su cargo.', 'queHacer' => 'Déjala como «Principal», o pasa antes sus secciones a otra institución.'];
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
