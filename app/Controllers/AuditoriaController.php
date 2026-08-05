<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Models\Auditoria;
use App\Models\Cargo;
use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\Usuario;

/**
 * Solo para superusuario (ver PapeleraController): la bitácora de qué se creó, editó,
 * eliminó, restauró o purgó, quién lo hizo y cuándo. Es la traza que queda incluso
 * después de que el script de purga borre algo de la papelera para siempre.
 */
final class AuditoriaController
{
    private const POR_PAGINA = 50;

    public function index(): void
    {
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $filtros = $this->filtrosDesdeQuery();
        $total = Auditoria::contar($filtros);

        // Se resuelve una sola vez por página cada catálogo referenciado por los IDs
        // guardados en datos_antes/datos_despues, en vez de una consulta por fila.
        $mapas = [
            'categoria_id' => Categoria::mapaIdNombre(),
            'cargo_id' => Cargo::mapaIdNombre(),
            'rol_id' => Usuario::mapaRoles(),
            'institucion_id' => Institucion::mapaIdNombre(),
            'institucion_padre_id' => Institucion::mapaIdNombre(),
            'registrado_por' => Usuario::mapaIdNombreCompleto(),
            'created_by' => Usuario::mapaIdNombreCompleto(),
            'responsables' => Usuario::mapaIdNombreCompleto(),
        ];

        View::layout('partials/layout', 'auditoria/index', [
            'title' => 'Auditoría',
            'registros' => Auditoria::listar($filtros, $pagina, self::POR_PAGINA),
            'mapas' => $mapas,
            'pagina' => $pagina,
            'porPagina' => self::POR_PAGINA,
            'totalPaginas' => Paginador::totalPaginas($total, self::POR_PAGINA),
            'total' => $total,
            'urlBase' => Url::to('/auditoria') . ($filtros !== [] ? '?' . http_build_query($filtros) : ''),
            'filtros' => $filtros,
            'instituciones' => Institucion::listadoParaSelect(),
        ]);
    }

    private function filtrosDesdeQuery(): array
    {
        return array_filter([
            'institucion_id' => (int) ($_GET['institucion_id'] ?? 0) ?: null,
            'entidad' => trim((string) ($_GET['entidad'] ?? '')) ?: null,
            'accion' => trim((string) ($_GET['accion'] ?? '')) ?: null,
            'desde' => trim((string) ($_GET['desde'] ?? '')) ?: null,
            'hasta' => trim((string) ($_GET['hasta'] ?? '')) ?: null,
        ]);
    }
}
