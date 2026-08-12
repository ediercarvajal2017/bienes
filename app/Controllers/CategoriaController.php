<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Institucion;

final class CategoriaController
{
    public function index(): void
    {
        $institucionId = $this->institucionSeleccionada();

        View::layout('partials/layout', 'categorias/index', [
            'title' => 'Categorías de bienes',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'categorias' => $institucionId !== null ? Categoria::all($institucionId) : [],
            'mensaje' => Session::pullFlash('ok'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request, null);

        $institucionId = $this->institucionSeleccionada();
        if ($institucionId === null) {
            Session::flash('error', 'Selecciona una institución.');
            header('Location: ' . Url::to('/categorias'));
            exit;
        }

        $nombre = trim((string) $request->input('nombre'));

        if ($nombre === '') {
            Session::flash('error', 'El nombre de la categoría es obligatorio.');
        } elseif (Categoria::existeNombre($institucionId, $nombre)) {
            Session::flash('error', 'Esa categoría ya existe en esta institución.');
        } else {
            $id = Categoria::create($institucionId, $nombre);
            Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'categoria', $id, null, ['nombre' => $nombre]);
            Session::flash('ok', 'Categoría creada correctamente.');
        }

        header('Location: ' . Url::to($this->volverA($institucionId)));
        exit;
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $categoria = Categoria::find($id);
        $this->verificarPertenencia($categoria);
        $this->verificarNoProtegida($categoria);

        $request = new Request();
        $this->verificarCsrf($request, (int) $categoria['institucion_id']);

        $nombre = trim((string) $request->input('nombre'));
        $institucionId = (int) $categoria['institucion_id'];

        if ($nombre === '') {
            Session::flash('error', 'El nombre de la categoría es obligatorio.');
        } elseif (Categoria::existeNombre($institucionId, $nombre, $id)) {
            Session::flash('error', 'Ya existe otra categoría con ese nombre en esta institución.');
        } else {
            Categoria::renombrar($id, $nombre);
            Auditoria::registrar(Auth::id(), $institucionId, 'editar', 'categoria', $id, $categoria, ['nombre' => $nombre]);
            Session::flash('ok', 'Categoría actualizada.');
        }

        header('Location: ' . Url::to($this->volverA($institucionId)));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $id = (int) $id;
        $categoria = Categoria::find($id);
        $this->verificarPertenencia($categoria);
        $this->verificarNoProtegida($categoria);

        $request = new Request();
        $this->verificarCsrf($request, (int) $categoria['institucion_id']);

        Categoria::setActivo($id, !((bool) $categoria['activo']));
        Session::flash('ok', 'Estado de la categoría actualizado.');

        header('Location: ' . Url::to($this->volverA((int) $categoria['institucion_id'])));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $categoria = Categoria::find($id);
        $this->verificarPertenencia($categoria);
        $this->verificarNoProtegida($categoria);

        $request = new Request();
        $this->verificarCsrf($request, (int) $categoria['institucion_id']);

        $institucionId = (int) $categoria['institucion_id'];

        if (Categoria::estaEnUso($id)) {
            Session::flash('error', 'No se puede eliminar: hay bienes registrados en esta categoría. Desactívala en su lugar.');
        } else {
            Categoria::eliminar($id, Auth::id());
            Auditoria::registrar(Auth::id(), $institucionId, 'eliminar', 'categoria', $id, $categoria);
            Session::flash('ok', 'Categoría enviada a la papelera. Un superusuario puede restaurarla si fue un error.');
        }

        header('Location: ' . Url::to($this->volverA($institucionId)));
        exit;
    }

    /**
     * Usado por JavaScript en "Registrar bien"/"Alta masiva" cuando el superusuario elige
     * (o cambia) la institución dentro del mismo formulario — las categorías ahora son
     * propias de cada institución, así que el combo no puede quedar fijo desde que carga
     * la página como antes.
     */
    public function porInstitucion(): void
    {
        $institucionId = (int) ($_GET['institucion_id'] ?? 0);

        header('Content-Type: application/json');

        if ($institucionId <= 0 || (!Auth::esSuperusuario() && $institucionId !== Auth::institucionId())) {
            echo json_encode(['categorias' => []]);
            exit;
        }

        $categorias = array_map(
            static fn (array $c) => ['id' => (int) $c['id'], 'nombre' => $c['nombre']],
            Categoria::activas($institucionId)
        );

        echo json_encode(['categorias' => $categorias]);
        exit;
    }

    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $valor = (int) ($_GET['institucion'] ?? $_POST['institucion_id'] ?? 0);

        return $valor > 0 ? $valor : null;
    }

    private function volverA(?int $institucionId): string
    {
        return '/categorias' . (Auth::esSuperusuario() && $institucionId !== null ? '?institucion=' . $institucionId : '');
    }

    /**
     * Un rector solo puede gestionar las categorías de su propia institución — antes de
     * este cambio no existía ninguna verificación de este tipo, porque el catálogo era
     * compartido por todo el sistema.
     */
    private function verificarPertenencia(?array $categoria): void
    {
        if (!$categoria) {
            Session::flash('error', 'Esa categoría ya no existe.');
            header('Location: ' . Url::to('/categorias'));
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $categoria['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /**
     * "Sin cartera" no se puede renombrar, desactivar ni eliminar desde aquí — ni
     * siquiera el superusuario —, sin excepción: es la categoría de la que depende el
     * código automático al registrar un bien nuevo, y romperla por accidente (un
     * renombre, una desactivación) rompería esa función en silencio para toda la
     * institución. Si alguna vez hace falta tocarla de verdad, es un cambio de código
     * puntual, no algo disponible en la pantalla normal.
     */
    private function verificarNoProtegida(array $categoria): void
    {
        if (Categoria::esProtegida($categoria)) {
            Session::flash('error', 'La categoría "' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA . '" no se puede modificar ni eliminar.');
            header('Location: ' . Url::to($this->volverA((int) $categoria['institucion_id'])));
            exit;
        }
    }

    private function verificarCsrf(Request $request, ?int $institucionId): void
    {
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
            header('Location: ' . Url::to($this->volverA($institucionId)));
            exit;
        }
    }
}
