<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Aviso que MIA le da al usuario después de una acción, en tres partes: qué pasó (título),
 * por qué (detalle) y qué hacer (solución), con un botón a la pantalla donde se corrige.
 * Redacción: docs/guia-estilo-ui.md, "Mensajes: qué pasó, por qué, qué hacer".
 *
 * El controlador lo deja en la sesión (Aviso::enviar) antes de redirigir. Lo pinta el
 * encabezado de la página de destino (partials/ui/encabezado_pagina.php) o, si la página no
 * tiene encabezado, el layout: ni el controlador tiene que leerlo ni la vista pintarlo, así
 * que ningún aviso se pierde ni reaparece después en otra página.
 *
 * Uso:
 *   Aviso::enviar(Aviso::error('No se guardó el bien', 'La placa 200388980 ya está registrada en tu institución.',
 *       'Revisa el número o abre el bien que ya existe.', Aviso::accion('Ver el bien 200388980', '/bienes/7/editar'),
 *       ['codigo_identificacion' => 'Esta placa ya existe.']));
 *
 * @phpstan-type Accion array{texto: string, url: string}
 * @phpstan-type DatosAviso array{tipo: string, titulo: string, detalle: string, solucion: string, accion: Accion|null, campos: array<string, string>}
 */
final class Aviso
{
    private const CLAVE_SESION = '_avisos';
    private const TIPOS = ['exito', 'info', 'advertencia', 'peligro'];

    /** @var list<DatosAviso>|null avisos de esta petición: se toman de la sesión una sola vez */
    private static ?array $actuales = null;

    private static bool $mostrados = false;

    /**
     * Lo que se hizo y, si lo hay, el siguiente paso: "Bien registrado." + "Siguiente paso: asígnalo a un espacio."
     *
     * @param Accion|null $accion
     * @return DatosAviso
     */
    public static function exito(string $hecho, string $siguiente = '', ?array $accion = null): array
    {
        return self::armar('exito', $hecho, $siguiente, '', $accion, []);
    }

    /**
     * @param Accion|null $accion
     * @return DatosAviso
     */
    public static function info(string $titulo, string $detalle = '', ?array $accion = null): array
    {
        return self::armar('info', $titulo, $detalle, '', $accion, []);
    }

    /**
     * Se hizo, pero con algo que el usuario debe revisar.
     *
     * @param Accion|null $accion
     * @return DatosAviso
     */
    public static function advertencia(string $titulo, string $porQue, string $queHacer, ?array $accion = null): array
    {
        return self::armar('advertencia', $titulo, $porQue, $queHacer, $accion, []);
    }

    /**
     * Qué no se pudo hacer, por qué y qué hacer para lograrlo. "Qué hacer" es obligatorio:
     * un error que no guía no se acepta.
     *
     * @param Accion|null $accion
     * @param array<string, string> $campos campo del formulario => mensaje corto debajo de él
     * @return DatosAviso
     */
    public static function error(string $queNoSe, string $porQue, string $queHacer, ?array $accion = null, array $campos = []): array
    {
        if (trim($queHacer) === '') {
            throw new \InvalidArgumentException('Un aviso de error debe decir qué hacer para corregirlo.');
        }

        return self::armar('peligro', $queNoSe, $porQue, $queHacer, $accion, $campos);
    }

    /**
     * Aviso de error a partir de una excepción de la acción. Una ErrorGuiado trae su porqué
     * y qué hacer; una regla de negocio sin guía (DomainException) usa $queHacer; cualquier
     * otra es un fallo técnico: se registra y el usuario recibe el código del incidente.
     *
     * @return DatosAviso
     */
    public static function desdeExcepcion(\Throwable $e, string $queNoSe, string $queHacer): array
    {
        if ($e instanceof ErrorGuiado) {
            return self::error($queNoSe, $e->getMessage(), $e->queHacer, $e->accion, $e->campos);
        }
        if ($e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
            return self::error($queNoSe, $e->getMessage(), $queHacer);
        }

        return \App\Helpers\Mensajes::errorInesperado($queNoSe, ErrorHandler::reportar($e, $queNoSe));
    }

    /**
     * Botón del aviso. $ruta es interna ("/bienes/7/editar", con su consulta si la tiene).
     *
     * @return Accion
     */
    public static function accion(string $texto, string $ruta): array
    {
        return ['texto' => $texto, 'url' => Url::to($ruta)];
    }

    /** @param DatosAviso $aviso */
    public static function enviar(array $aviso): void
    {
        $_SESSION[self::CLAVE_SESION][] = $aviso;
    }

    /**
     * Los avisos de esta petición. También recoge los mensajes con el formato anterior
     * (flash de sesión "ok" o "error") que la página no leyó: antes quedaban guardados y
     * salían después en otra página.
     *
     * @return list<DatosAviso>
     */
    public static function pendientes(): array
    {
        if (self::$actuales !== null) {
            return self::$actuales;
        }

        $avisos = [];
        $guardados = $_SESSION[self::CLAVE_SESION] ?? [];
        foreach (is_array($guardados) ? $guardados : [] as $aviso) {
            if (is_array($aviso) && in_array($aviso['tipo'] ?? null, self::TIPOS, true) && is_string($aviso['titulo'] ?? null)) {
                $avisos[] = self::armar((string) $aviso['tipo'], $aviso['titulo'], (string) ($aviso['detalle'] ?? ''),
                    (string) ($aviso['solucion'] ?? ''), self::accionValida($aviso['accion'] ?? null), self::camposValidos($aviso['campos'] ?? []));
            }
        }
        unset($_SESSION[self::CLAVE_SESION]);

        foreach (['ok' => 'exito', 'error' => 'peligro'] as $clave => $tipo) {
            $mensaje = Session::pullFlash($clave);
            if ($mensaje !== null && trim($mensaje) !== '') {
                $avisos[] = self::armar($tipo, $mensaje, '', '', null, []);
            }
        }

        return self::$actuales = $avisos;
    }

    /**
     * Los avisos para pintar, una sola vez por página: el primero que pregunta (el encabezado
     * de la página o, si no hay, el layout) los recibe; los demás, una lista vacía.
     *
     * @return list<DatosAviso>
     */
    public static function paraMostrar(): array
    {
        if (self::$mostrados) {
            return [];
        }
        self::$mostrados = true;

        return self::pendientes();
    }

    /** Mensaje del campo $campo en los avisos de error de esta petición (lo usa ui('campo_formulario')). */
    public static function errorDeCampo(string $campo): ?string
    {
        foreach (self::pendientes() as $aviso) {
            if (isset($aviso['campos'][$campo])) {
                return $aviso['campos'][$campo];
            }
        }

        return null;
    }

    /** Solo para pruebas: olvida los avisos tomados en esta petición. */
    public static function reiniciar(): void
    {
        self::$actuales = null;
        self::$mostrados = false;
    }

    /**
     * @param Accion|null $accion
     * @param array<string, string> $campos
     * @return DatosAviso
     */
    private static function armar(string $tipo, string $titulo, string $detalle, string $solucion, ?array $accion, array $campos): array
    {
        if (trim($titulo) === '') {
            throw new \InvalidArgumentException('El aviso debe decir qué pasó.');
        }

        return ['tipo' => $tipo, 'titulo' => trim($titulo), 'detalle' => trim($detalle), 'solucion' => trim($solucion),
            'accion' => $accion, 'campos' => $campos];
    }

    /** @return Accion|null */
    private static function accionValida(mixed $accion): ?array
    {
        return is_array($accion) && is_string($accion['texto'] ?? null) && is_string($accion['url'] ?? null)
            ? ['texto' => $accion['texto'], 'url' => $accion['url']] : null;
    }

    /** @return array<string, string> */
    private static function camposValidos(mixed $campos): array
    {
        $validos = [];
        foreach (is_array($campos) ? $campos : [] as $campo => $mensaje) {
            if (is_string($campo) && is_string($mensaje)) {
                $validos[$campo] = $mensaje;
            }
        }

        return $validos;
    }
}
