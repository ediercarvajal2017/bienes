<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Aviso;

/**
 * Avisos que se repiten en muchas pantallas, redactados una sola vez (qué pasó, por qué y
 * qué hacer; docs/guia-estilo-ui.md). Cada uno devuelve el aviso para Aviso::enviar().
 *
 * @phpstan-import-type DatosAviso from Aviso
 */
final class Mensajes
{
    /**
     * El token de seguridad del formulario venció (la página estuvo abierta mucho tiempo o
     * se abrió en otra pestaña después de cerrar sesión). Nada se guardó.
     *
     * @return DatosAviso
     */
    public static function sesionVencida(bool $datosConservados): array
    {
        return Aviso::error(
            'No se guardó ningún cambio',
            'La página estuvo abierta mucho tiempo y, por seguridad, el formulario venció.',
            $datosConservados
                ? 'Tus datos siguen en el formulario: revísalos y vuelve a enviarlo.'
                : 'Vuelve a hacer la acción; esta vez se guardará.'
        );
    }

    /**
     * Una acción sobre una lista sin ninguna casilla marcada.
     *
     * @return DatosAviso
     */
    public static function seleccionaAlMenosUno(string $queNoSe, string $elemento, string $boton): array
    {
        return Aviso::error($queNoSe, "No marcaste ningún {$elemento}.",
            "Marca la casilla de cada {$elemento} en la lista y pulsa «{$boton}».");
    }

    /** @return DatosAviso */
    public static function eligeInstitucion(string $queNoSe): array
    {
        return Aviso::error($queNoSe, 'No elegiste la institución.',
            'Elígela en la lista «Institución» y vuelve a intentarlo.');
    }

    /**
     * El registro del enlace ya no está (se eliminó o el enlace es antiguo).
     *
     * @return DatosAviso
     */
    public static function yaNoExiste(string $queNoSe, string $que, string $rutaLista, string $textoLista): array
    {
        return Aviso::error($queNoSe, "{$que} ya no existe: se eliminó o el enlace es antiguo.",
            'Búscalo de nuevo en la lista; si se eliminó por error, un administrador puede restaurarlo desde la Papelera.',
            Aviso::accion($textoLista, $rutaLista));
    }

    /**
     * Fallo técnico: nada se aplicó y el usuario recibe el código del incidente para soporte.
     *
     * @return DatosAviso
     */
    public static function errorInesperado(string $queNoSe, string $incidente): array
    {
        return Aviso::error($queNoSe, 'Ocurrió un error inesperado y no se aplicó ningún cambio.',
            "Intenta de nuevo. Si se repite, comparte el código {$incidente} con soporte.");
    }
}
