<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Bien;
use App\Models\Verificacion;
use App\Services\CicloVidaBien;

/**
 * Rutas de cada acción del ciclo de vida de un bien (asignar, trasladar, trasladar a otra
 * sede, reintegrar, reactivar). La lógica y sus reglas viven en App\Services\CicloVidaBien,
 * que también usa el formulario único de edición del bien (BienController::actualizar).
 */
final class MovimientoController
{
    public function asignar(string $id): void
    {
        $bien = $this->bienDeLaInstitucion((int) $id);
        $request = $this->request($bien);
        $verificacionId = Verificacion::idValidoParaBien((int) $bien['id'], (string) $request->input('verificacion_id'));

        $this->ejecutar($bien, static fn () => CicloVidaBien::asignarOTrasladar(
            $bien,
            (int) $request->input('espacio_id'),
            (string) $request->input('fecha_asignacion'),
            trim((string) $request->input('observaciones')) ?: null,
            $verificacionId
        ));

        // Si el bien pertenece a un lote de alta masiva idéntica, se vuelve al listado
        // filtrado por ese lote para seguir asignando el resto sin volver a buscar.
        $this->redirigir(!empty($bien['lote']) ? '/bienes?q=' . urlencode($bien['lote']) : "/bienes/{$bien['id']}/editar");
    }

    public function trasladar(string $id): void
    {
        $bien = $this->bienDeLaInstitucion((int) $id);
        $request = $this->request($bien);
        $verificacionId = Verificacion::idValidoParaBien((int) $bien['id'], (string) $request->input('verificacion_id'));

        $this->ejecutar($bien, static fn () => CicloVidaBien::asignarOTrasladar(
            $bien,
            (int) $request->input('espacio_destino_id'),
            (string) $request->input('fecha'),
            trim((string) $request->input('observaciones')) ?: null,
            $verificacionId
        ));
        $this->redirigir("/bienes/{$bien['id']}/editar");
    }

    /**
     * Traslado entre sedes de una misma familia (principal + secciones): también cambia el
     * "dueño" del bien. Tras el traslado el bien ya es de otra sede, así que se vuelve al
     * listado.
     */
    public function trasladarSede(string $id): void
    {
        $bien = $this->bienDeLaInstitucion((int) $id);
        $request = $this->request($bien);

        $this->ejecutar($bien, static fn () => CicloVidaBien::trasladarSede(
            $bien,
            (int) $request->input('institucion_destino_id'),
            (int) $request->input('espacio_destino_id'),
            (string) $request->input('fecha'),
            trim((string) $request->input('observaciones')) ?: null
        ));
        $this->redirigir('/bienes');
    }

    public function reintegrar(string $id): void
    {
        $bien = $this->bienDeLaInstitucion((int) $id);
        $request = $this->request($bien);

        $this->ejecutar($bien, static fn () => CicloVidaBien::reintegrar(
            $bien,
            (string) $request->input('fecha'),
            (string) $request->input('destino_texto'),
            trim((string) $request->input('observaciones')) ?: null
        ));
        $this->redirigir("/bienes/{$bien['id']}/editar");
    }

    /**
     * Reactiva un bien reintegrado: solo rector o superusuario (la ruta exige
     * asignaciones.crear, que también tiene el secretario; por eso el rol se valida aquí).
     */
    public function reactivar(string $id): void
    {
        $bien = $this->bienDeLaInstitucion((int) $id);
        if (!Auth::esSuperusuario() && Auth::rol() !== 'rector') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
        $request = $this->request($bien);

        $this->ejecutar($bien, static fn () => CicloVidaBien::reactivar(
            $bien,
            (string) $request->input('fecha'),
            (string) $request->input('motivo')
        ));
        $this->redirigir("/bienes/{$bien['id']}/editar");
    }

    /** Ejecuta la acción: si una regla no se cumple, vuelve a la ficha del bien con el mensaje. */
    private function ejecutar(array $bien, callable $accion): void
    {
        try {
            Session::flash('ok', $accion());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirigir("/bienes/{$bien['id']}/editar");
        }
    }

    private function request(array $bien): Request
    {
        $request = new Request();
        Csrf::verificarORedirigir($request, "/bienes/{$bien['id']}/editar");

        return $request;
    }

    private function redirigir(string $ruta): never
    {
        header('Location: ' . Url::to($ruta));
        exit;
    }

    private function bienDeLaInstitucion(int $id): array
    {
        $bien = Bien::find($id);

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

        return $bien;
    }
}
