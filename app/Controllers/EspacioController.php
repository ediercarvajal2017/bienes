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
use App\Helpers\Paginador;
use App\Models\Auditoria;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Usuario;

final class EspacioController
{
    private const POR_PAGINA_DEFECTO = 50;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $terminoBusqueda = $busqueda !== '' ? $busqueda : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }
        $total = Espacio::contarListado($institucionId, $terminoBusqueda);

        View::layout('partials/layout', 'espacios/index', [
            'title' => 'Espacios',
            'espacios' => Espacio::listar($institucionId, $terminoBusqueda, $pagina, $porPagina, ...\App\Helpers\OrdenListado::desdeConsulta(Espacio::ORDENES)),
            'busqueda' => $busqueda,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            // Por qué no se pudo eliminar un espacio, con los bienes que lo usan (explicarPorQueNoSeElimina).
            'espacioEnUso' => json_decode((string) Session::pullFlash('espacio_en_uso'), true) ?: null,
        ]);
    }

    public function crear(): void
    {
        $institucionId = Auth::institucionId();
        $viejo = Session::pullOld();

        View::layout('partials/layout', 'espacios/form', [
            'title' => 'Nuevo espacio',
            'espacio' => null,
            'usuarios' => Auth::esSuperusuario() ? Usuario::listarTodos(null) : Usuario::listarTodos($institucionId),
            'responsablesSeleccionados' => $viejo['responsables'] ?? [],
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [],
            'viejo' => $viejo,
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/espacios/crear', $datos);

        if ($falla = $this->validar($datos, null)) {
            Aviso::enviar(Aviso::error('No se creó el espacio', $falla['porQue'], $falla['queHacer'], null, [$falla['campo'] => $falla['enCampo']]));
            Session::flashOld($datos);
            header('Location: ' . Url::to('/espacios/crear'));
            exit;
        }

        $id = Espacio::create($datos);
        Espacio::sincronizarResponsables($id, $datos['responsables']);
        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'crear', 'espacio', $id, null, $datos);

        Aviso::enviar(Aviso::exito("Espacio {$datos['codigo']} {$datos['nombre']} creado.", 'Siguiente paso: asígnale bienes desde Bienes o desde Asignar bienes.'));
        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    public function editar(string $id): void
    {
        $espacio = Espacio::find((int) $id);
        $this->verificarAcceso($espacio);
        $viejo = Session::pullOld();

        View::layout('partials/layout', 'espacios/form', [
            'title' => 'Editar espacio',
            'espacio' => $espacio,
            'usuarios' => Usuario::listarTodos($espacio['institucion_id']),
            'responsablesSeleccionados' => $viejo['responsables'] ?? array_column(Espacio::responsablesDe((int) $id), 'id'),
            'viejo' => $viejo,
        ]);
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $espacio = Espacio::find($id);
        $this->verificarAcceso($espacio);

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request, (int) $espacio['institucion_id']);
        $this->verificarCsrf($request, "/espacios/{$id}/editar", $datos);

        if ($falla = $this->validar($datos, $id)) {
            Aviso::enviar(Aviso::error('No se guardó el espacio', $falla['porQue'], $falla['queHacer'], null, [$falla['campo'] => $falla['enCampo']]));
            Session::flashOld($datos);
            header('Location: ' . Url::to("/espacios/{$id}/editar"));
            exit;
        }

        Espacio::update($id, $datos);
        Espacio::sincronizarResponsables($id, $datos['responsables']);
        Auditoria::registrar(Auth::id(), (int) $espacio['institucion_id'], 'editar', 'espacio', $id, $espacio, $datos);

        Aviso::enviar(Aviso::exito("Cambios del espacio {$datos['codigo']} {$datos['nombre']} guardados."));
        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $id = (int) $id;
        $espacio = Espacio::find($id);
        $this->verificarAcceso($espacio);

        $request = new Request();
        $this->verificarCsrf($request, '/espacios');

        $activo = !((bool) $espacio['activo']);
        Espacio::setActivo($id, $activo);

        Aviso::enviar($activo
            ? Aviso::exito("Espacio «{$espacio['nombre']}» activado.", 'Se puede elegir de nuevo al asignar bienes.')
            : Aviso::exito("Espacio «{$espacio['nombre']}» desactivado.", 'Ya no se ofrece al asignar bienes; su historial queda intacto.'));
        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $espacio = Espacio::find($id);
        $this->verificarAcceso($espacio);

        $request = new Request();
        $this->verificarCsrf($request, '/espacios');

        if (Espacio::estaEnUso($id)) {
            $this->explicarPorQueNoSeElimina($id, (string) $espacio['nombre']);
        } else {
            Espacio::eliminar($id, (int) Auth::id());
            Auditoria::registrar(Auth::id(), (int) $espacio['institucion_id'], 'eliminar', 'espacio', $id, $espacio);
            Aviso::enviar(Aviso::exito("Espacio «{$espacio['nombre']}» enviado a la papelera.", 'Si fue un error, un superusuario puede restaurarlo desde la Papelera.'));
        }

        header('Location: ' . Url::to('/espacios'));
        exit;
    }

    /**
     * Aviso para la lista de espacios: por qué no se puede eliminar y qué bienes lo usan
     * (los asignados ahora o, si no hay, los de su historial), con enlace a cada uno.
     */
    private function explicarPorQueNoSeElimina(int $id, string $nombre): void
    {
        $uso = Espacio::usoDetallado($id);
        $plural = static fn (int $n, string $uno, string $varios): string => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";

        if ($uso['actuales'] > 0) {
            $mensaje = "El espacio «{$nombre}» tiene "
                . $plural($uso['actuales'], 'bien asignado', 'bienes asignados') . ' en este momento.';
            $consejo = 'Traslada o reintegra esos bienes, o desactiva el espacio para que no se use más.';
            $total = $uso['actuales'];
        } elseif ($uso['historial'] > 0) {
            $mensaje = "El espacio «{$nombre}» ya no tiene bienes asignados, pero aparece en el historial de "
                . $plural($uso['historial'], 'bien', 'bienes') . ' (asignaciones y movimientos anteriores) que debe conservarse.';
            $consejo = 'Desactiva el espacio para que no se use más; su historial queda intacto.';
            $total = $uso['historial'];
        } else {
            $mensaje = "El espacio «{$nombre}» tiene "
                . $plural($uso['hallazgos'], 'hallazgo', 'hallazgos') . ' de verificación física registrados en él.';
            $consejo = 'Desactiva el espacio para que no se use más.';
            $total = 0;
        }

        Session::flash('espacio_en_uso', (string) json_encode([
            'porQue' => $mensaje,
            'bienes' => $uso['bienes'],
            'mas' => max(0, $total - count($uso['bienes'])),
            'consejo' => $consejo,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function datosDesdeFormulario(Request $request, ?int $institucionIdExistente = null): array
    {
        $institucionId = $institucionIdExistente
            ?? (Auth::esSuperusuario() ? (int) $request->input('institucion_id') : Auth::institucionId());

        return [
            'institucion_id' => $institucionId,
            'nombre' => trim((string) $request->input('nombre')),
            'codigo' => trim((string) $request->input('codigo')),
            'responsables' => array_unique(array_map('intval', (array) $request->input('responsables', []))),
        ];
    }

    /** @return array{campo: string, porQue: string, queHacer: string, enCampo: string}|null */
    private function validar(array $datos, ?int $exceptId): ?array
    {
        if ($datos['codigo'] === '') {
            return ['campo' => 'codigo', 'porQue' => 'Falta el número del espacio.', 'enCampo' => 'Escribe el número.',
                'queHacer' => 'Escribe el número o código del espacio (por ejemplo «A-101») y vuelve a guardar.'];
        }
        if ($datos['nombre'] === '') {
            return ['campo' => 'nombre', 'porQue' => 'Falta el nombre del espacio.', 'enCampo' => 'Escribe el nombre.',
                'queHacer' => 'Escribe el nombre (por ejemplo «Aula 101») y vuelve a guardar.'];
        }

        if (empty($datos['responsables'])) {
            return ['campo' => 'responsables[]', 'porQue' => 'El espacio no tiene responsable: cada espacio necesita al menos uno.',
                'enCampo' => 'Elige al menos un responsable.', 'queHacer' => 'Elige en «Responsables» a la persona (o personas) que responde por los bienes del espacio.'];
        }

        if (Espacio::existeCodigo($datos['institucion_id'], $datos['codigo'], $exceptId)) {
            return ['campo' => 'codigo', 'porQue' => "Ya existe un espacio con el número {$datos['codigo']} en la institución.",
                'enCampo' => 'Este número ya existe.', 'queHacer' => 'Usa otro número, o edita el espacio que ya existe.'];
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
     * @phpstan-assert array $espacio
     */
    private function verificarAcceso(?array $espacio): void
    {
        if (!$espacio) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $espacio['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }
}
