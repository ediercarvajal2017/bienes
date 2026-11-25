<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encabezado Server-Timing en cada respuesta de PHP: cuánto tardó PHP en empezar a
 * ejecutar MIA (arranque), en conectarse a la base (bd), en abrir la sesión (sesion) y en
 * total hasta responder (app). Lo muestran las herramientas del navegador (Red → Tiempos)
 * y permite medir producción desde fuera, con curl.
 *
 * Origen: la prueba de velocidad del 2026-11-24 encontró que en el hosting cada página
 * tardaba unos 135 ms fijos antes de hacer su trabajo (20 ms en el equipo local). Solo
 * lleva duraciones en milisegundos enteros, ningún dato.
 */
final class TiemposServidor
{
    private static ?int $inicio = null;

    /** @var array<string, float> nombre => milisegundos */
    private static array $medidas = [];

    /**
     * @param int $inicioScript hrtime(true) de la primera línea de public/index.php.
     */
    public static function iniciar(int $inicioScript): void
    {
        self::$inicio = $inicioScript;
        // REQUEST_TIME_FLOAT: cuando el servidor le entregó la petición a PHP. Lo que pasa
        // entre ese momento y la primera línea de MIA es el arranque de PHP.
        $recibida = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        if (is_float($recibida)) {
            $transcurrido = (hrtime(true) - $inicioScript) / 1e6;
            self::$medidas['arranque'] = max(0.0, (microtime(true) - $recibida) * 1000 - $transcurrido);
        }
        header_register_callback([self::class, 'enviar']);
    }

    /** Suma a la medida $nombre los milisegundos desde $desde (un hrtime(true)). */
    public static function sumar(string $nombre, int $desde): void
    {
        self::$medidas[$nombre] = (self::$medidas[$nombre] ?? 0.0) + (hrtime(true) - $desde) / 1e6;
    }

    /** Lo llama PHP justo antes de enviar los encabezados. */
    public static function enviar(): void
    {
        if (self::$inicio === null) {
            return;
        }
        $medidas = self::$medidas + ['app' => (hrtime(true) - self::$inicio) / 1e6];
        header('Server-Timing: ' . self::valor($medidas));
    }

    /**
     * @param array<string, float> $medidas
     */
    public static function valor(array $medidas): string
    {
        $partes = [];
        foreach ($medidas as $nombre => $ms) {
            $partes[] = $nombre . ';dur=' . (int) round($ms);
        }

        return implode(', ', $partes);
    }
}
