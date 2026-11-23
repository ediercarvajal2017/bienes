<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
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
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $this->verificarCsrf($request, null);

        $institucionId = $this->institucionSeleccionada();
        if ($institucionId === null) {
            Aviso::enviar(\App\Helpers\Mensajes::eligeInstitucion('No se creó la categoría'));
            header('Location: ' . Url::to('/categorias'));
            exit;
        }

        $nombre = trim((string) $request->input('nombre'));

        if ($nombre === '') {
            Aviso::enviar(Aviso::error('No se creó la categoría', 'Falta el nombre de la categoría.', 'Escribe el nombre (por ejemplo «Muebles») y vuelve a intentarlo.'));
        } elseif (mb_strlen($nombre) > 80) {
            Aviso::enviar(Aviso::error('No se creó la categoría', 'El nombre es más largo de lo que se puede guardar.', 'Acórtalo a 80 caracteres o menos y vuelve a intentarlo.'));
        } elseif (Categoria::existeNombre($institucionId, $nombre)) {
            Aviso::enviar(Aviso::error('No se creó la categoría', "La categoría «{$nombre}» ya existe en esta institución.",
                'Búscala en la lista; si está inactiva, actívala en lugar de crearla otra vez.'));
        } else {
            $id = Categoria::create($institucionId, $nombre);
            Auditoria::registrar(Auth::id(), $institucionId, 'crear', 'categoria', $id, null, ['nombre' => $nombre]);
            Aviso::enviar(Aviso::exito("Categoría «{$nombre}» creada.", 'Ya se puede elegir al registrar un bien.'));
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
            Aviso::enviar(Aviso::error('No se guardó la categoría', 'El nombre quedó vacío.', 'Escribe el nombre de la categoría y vuelve a guardar.'));
        } elseif (mb_strlen($nombre) > 80) {
            Aviso::enviar(Aviso::error('No se guardó la categoría', 'El nombre es más largo de lo que se puede guardar.', 'Acórtalo a 80 caracteres o menos y vuelve a guardar.'));
        } elseif (Categoria::existeNombre($institucionId, $nombre, $id)) {
            Aviso::enviar(Aviso::error('No se guardó la categoría', "Ya existe otra categoría llamada «{$nombre}» en esta institución.",
                'Usa un nombre distinto, o usa la categoría que ya existe.'));
        } else {
            Categoria::renombrar($id, $nombre);
            Auditoria::registrar(Auth::id(), $institucionId, 'editar', 'categoria', $id, $categoria, ['nombre' => $nombre]);
            Aviso::enviar(Aviso::exito("Categoría renombrada a «{$nombre}».", 'Los bienes de esta categoría muestran el nombre nuevo.'));
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

        $activa = !((bool) $categoria['activo']);
        Categoria::setActivo($id, $activa);
        Aviso::enviar($activa
            ? Aviso::exito("Categoría «{$categoria['nombre']}» activada.", 'Se puede elegir de nuevo al registrar bienes.')
            : Aviso::exito("Categoría «{$categoria['nombre']}» desactivada.", 'Ya no se ofrece al registrar bienes; los que la tienen la conservan.'));

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
            Aviso::enviar(Aviso::error('No se eliminó la categoría', "Hay bienes registrados en la categoría «{$categoria['nombre']}».",
                'Desactívala en su lugar: deja de ofrecerse al registrar bienes sin afectar a los que ya la tienen.'));
        } else {
            Categoria::eliminar($id, (int) Auth::id());
            Auditoria::registrar(Auth::id(), $institucionId, 'eliminar', 'categoria', $id, $categoria);
            Aviso::enviar(Aviso::exito("Categoría «{$categoria['nombre']}» enviada a la papelera.", 'Si fue un error, un superusuario puede restaurarla desde la Papelera.'));
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
     * @phpstan-assert array $categoria
     */
    private function verificarPertenencia(?array $categoria): void
    {
        if (!$categoria) {
            Aviso::enviar(\App\Helpers\Mensajes::yaNoExiste('No se hizo el cambio', 'La categoría', '/categorias', 'Ver las categorías'));
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
            Aviso::enviar(Aviso::error('No se hizo el cambio', 'La categoría «' . Categoria::NOMBRE_CATEGORIA_PROTEGIDA
                . '» no se puede renombrar, desactivar ni eliminar: de ella depende el código automático de los bienes que no están en la cartera.',
                'Si necesitas otra categoría, créala con otro nombre.'));
            header('Location: ' . Url::to($this->volverA((int) $categoria['institucion_id'])));
            exit;
        }
    }

    private function verificarCsrf(Request $request, ?int $institucionId): void
    {
        Csrf::verificarORedirigir($request, $this->volverA($institucionId));
    }
}
