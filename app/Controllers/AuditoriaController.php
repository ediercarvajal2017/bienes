<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Models\Auditoria;

/**
 * Solo para superusuario (ver PapeleraController): la bitácora de qué se eliminó y
 * qué se restauró, quién lo hizo y cuándo. Es la traza que queda incluso después de
 * que el script de purga borre algo de la papelera para siempre.
 */
final class AuditoriaController
{
    private const POR_PAGINA = 50;

    public function index(): void
    {
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $total = Auditoria::contar();

        View::layout('partials/layout', 'auditoria/index', [
            'title' => 'Auditoría',
            'registros' => Auditoria::listar($pagina, self::POR_PAGINA),
            'pagina' => $pagina,
            'porPagina' => self::POR_PAGINA,
            'totalPaginas' => Paginador::totalPaginas($total, self::POR_PAGINA),
            'total' => $total,
            'urlBase' => Url::to('/auditoria'),
        ]);
    }
}
