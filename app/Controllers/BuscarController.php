<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Bien;
use App\Models\Espacio;
use App\Models\Usuario;

/**
 * Buscador global del encabezado: un solo cuadro de texto que consulta bienes, espacios
 * y usuarios a la vez y agrupa los resultados por módulo, en vez de obligar a saber de
 * antemano en qué pantalla buscar. Cada sección respeta el mismo permiso y alcance de
 * institución que ya usa el índice de ese módulo (ver BienController/EspacioController/
 * UsuarioController::index()) — este buscador no otorga acceso nuevo, solo lo agrupa.
 */
final class BuscarController
{
    private const LIMITE_POR_SECCION = 8;

    public function index(): void
    {
        $termino = trim((string) ($_GET['q'] ?? ''));
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();

        $puedeVerBienes = Auth::esSuperusuario() || Auth::tienePermiso('bienes.ver');
        $puedeVerEspacios = Auth::esSuperusuario() || Auth::tienePermiso('espacios.ver');
        $puedeVerUsuarios = Auth::esSuperusuario() || Auth::tienePermiso('usuarios.ver');
        // /usuarios/{id}/editar exige 'usuarios.editar' (no basta con 'usuarios.ver', a
        // diferencia de bienes/espacios) — sin este permiso el enlace no debe ofrecerse,
        // o lleva a un 403.
        $puedeEditarUsuarios = Auth::esSuperusuario() || Auth::tienePermiso('usuarios.editar');

        $bienes = [];
        $totalBienes = 0;
        $espacios = [];
        $totalEspacios = 0;
        $usuarios = [];
        $totalUsuarios = 0;

        if ($termino !== '' && $institucionId !== null) {
            if ($puedeVerBienes) {
                // El docente solo ve sus propios bienes en /bienes (ver
                // BienController::index()) — el buscador global respeta el mismo recorte.
                if (Auth::rol() === 'docente') {
                    $totalBienes = Bien::contarPropios((int) Auth::id(), $institucionId, $termino);
                    $bienes = Bien::listarPropios((int) Auth::id(), $institucionId, $termino, 1, self::LIMITE_POR_SECCION);
                } else {
                    $totalBienes = Bien::contarListado($institucionId, $termino);
                    $bienes = Bien::listar($institucionId, $termino, 1, self::LIMITE_POR_SECCION);
                }
            }

            if ($puedeVerEspacios) {
                $totalEspacios = Espacio::contarListado($institucionId, $termino);
                $espacios = Espacio::listar($institucionId, $termino, 1, self::LIMITE_POR_SECCION);
            }

            if ($puedeVerUsuarios) {
                $totalUsuarios = Usuario::contarListado($institucionId, $termino);
                $usuarios = Usuario::listar($institucionId, $termino, 1, self::LIMITE_POR_SECCION);
            }
        }

        View::layout('partials/layout', 'buscar/index', [
            'title' => 'Buscar',
            'q' => $termino,
            'institucionId' => $institucionId,
            'puedeVerBienes' => $puedeVerBienes,
            'bienes' => $bienes,
            'totalBienes' => $totalBienes,
            'puedeVerEspacios' => $puedeVerEspacios,
            'espacios' => $espacios,
            'totalEspacios' => $totalEspacios,
            'puedeVerUsuarios' => $puedeVerUsuarios,
            'puedeEditarUsuarios' => $puedeEditarUsuarios,
            'usuarios' => $usuarios,
            'totalUsuarios' => $totalUsuarios,
        ]);
    }
}
