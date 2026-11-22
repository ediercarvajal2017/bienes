<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\LectorExcel;
use App\Models\Bien;
use App\Models\LoteReintegro;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Verificar un lote de reintegro en la cartera de la Alcaldía: el colegio entrega el lote, la
 * Alcaldía debe descargar esos bienes de la cartera del rector y, cuando el colegio recibe la
 * cartera nueva (registrada en /cartera/enviar), MIA dice cuáles bienes del lote SIGUEN en ella.
 * Solo lee: no cambia bienes, lotes ni carteras.
 *
 * La cartera trae una fila por placa. Una placa con varias unidades (178 sillas) sigue en la
 * cartera si la cantidad que dice es mayor que la que debe quedar (ver calcular()).
 *
 * @phpstan-type Lectura array{encontrada: bool, placas: array<string, int>, columnaPlaca: ?string, tituloPlaca: ?string,
 *     columnaCantidad: ?string, tituloCantidad: ?string, columnas: array<string, string>}
 * @phpstan-type Fila array{placa: string, descripcion: string, con_unidades: bool, cantidad_lote: int, siguen: int,
 *     cartera_dice: int, deberian_quedar: ?int, en_otros_lotes: int, otros_lotes: list<int>, fecha_desde: string, fecha_hasta: string}
 */
final class VerificacionCartera
{
    /** Títulos de la columna de la placa, del más seguro al menos (se comparan sin tildes ni mayúsculas). */
    private const TITULOS_PLACA = ['PLACA', 'INVENTARIO', 'CODIGO'];
    private const TITULOS_DESCRIPCION = ['DESCRIPCION', 'ELEMENTO', 'NOMBRE DEL BIEN'];
    /** Hasta dónde se buscan los títulos y cuántas columnas se ofrecen para elegir a mano. */
    private const FILAS_DE_TITULOS = 30;
    private const COLUMNAS_MAXIMAS = 40;
    /** Un título es una celda corta: así un texto largo arriba ("Cartera de bienes por código…") no cuenta. */
    private const LARGO_MAXIMO_TITULO = 40;

    /**
     * Lee la cartera: placa (normalizada) => cantidad. Si $columnaPlaca es null, busca la fila de
     * títulos y las columnas de placa y cantidad por su nombre; si no encuentra la de la placa,
     * 'encontrada' es false y 'columnas' trae las columnas del archivo para elegirla a mano.
     *
     * @return Lectura
     */
    public static function leerCartera(string $ruta, ?string $columnaPlaca = null, ?string $columnaCantidad = null): array
    {
        $hoja = LectorExcel::hojaActiva($ruta, null, true);
        $ultimaFila = $hoja->getHighestDataRow();
        $ultimaColumna = min(self::COLUMNAS_MAXIMAS, Coordinate::columnIndexFromString($hoja->getHighestDataColumn()));
        $columnas = self::columnasDelArchivo($hoja, $ultimaFila, $ultimaColumna);

        [$filaTitulos, $titulos] = self::filaDeTitulos($hoja, $ultimaFila, $ultimaColumna);
        $porTitulo = $filaTitulos > 0 ? self::columnasPorTitulo($titulos) : ['placa' => null, 'cantidad' => null, 'descripcion' => null];

        $manual = $columnaPlaca !== null;
        $columnaPlaca = $manual ? self::columnaValida($columnaPlaca, $ultimaColumna) : $porTitulo['placa'];
        $columnaCantidad = $columnaCantidad !== null ? self::columnaValida($columnaCantidad, $ultimaColumna) : ($manual ? null : $porTitulo['cantidad']);
        $columnaDescripcion = $porTitulo['descripcion'];

        $lectura = [
            'encontrada' => $columnaPlaca !== null,
            'placas' => [],
            'columnaPlaca' => $columnaPlaca,
            'tituloPlaca' => $columnaPlaca !== null ? ($titulos[$columnaPlaca] ?? null) : null,
            'columnaCantidad' => $columnaCantidad,
            'tituloCantidad' => $columnaCantidad !== null ? ($titulos[$columnaCantidad] ?? null) : null,
            'columnas' => $columnas,
        ];
        if ($columnaPlaca === null) {
            return $lectura;
        }

        // Con la columna elegida a mano se lee desde la fila 1 si no hay títulos reconocibles: la
        // fila de títulos ("PLACA") no coincide con ninguna placa de MIA, así que no estorba.
        for ($fila = $filaTitulos + 1; $fila <= $ultimaFila; $fila++) {
            $placa = self::normalizarPlaca(self::valor($hoja, $columnaPlaca . $fila));
            if ($placa === '') {
                continue;
            }
            $cantidad = $columnaCantidad !== null ? self::leerCantidad(self::valor($hoja, $columnaCantidad . $fila)) : null;
            if ($cantidad === null && $columnaDescripcion !== null) {
                $cantidad = RenglonDePlaca::cantidadQueDice((string) self::valor($hoja, $columnaDescripcion . $fila));
            }
            // Una fila por placa; si una placa viene en varias filas, se suman.
            $lectura['placas'][$placa] = ($lectura['placas'][$placa] ?? 0) + ($cantidad ?? 1);
        }

        return $lectura;
    }

    /**
     * Los bienes del lote que siguen en la cartera ($placasCartera: lo que devolvió leerCartera).
     *
     * @param array<string, int> $placasCartera
     * @return array{filas: list<Fila>, total_lote: int, total_siguen: int}
     */
    public static function verificar(int $institucionId, int $loteId, string $fechaCartera, array $placasCartera): array
    {
        $bienes = LoteReintegro::bienesParaVerificarCartera($loteId);
        $placasConUnidades = array_values(array_unique(array_filter(array_map(static fn (array $b): ?string => $b['lote'], $bienes))));

        return self::calcular($bienes, Bien::unidadesDePlacasParaCartera($institucionId, $placasConUnidades, $loteId, $fechaCartera), $placasCartera);
    }

    /**
     * El cálculo, sin base de datos:
     *  - Bien con placa propia: sigue si su placa aparece en la cartera.
     *  - Unidades de una placa: deben quedar las que MIA tiene en cartera más las reintegradas que
     *    la Alcaldía aún no tenía para descargar. Lo que la cartera diga de más son las que siguen,
     *    nunca más que las del lote (ej.: el lote reintegró 8 de 178, la cartera dice 174 y deben
     *    quedar 170 → siguen 4).
     *
     * @param list<array{id: int, codigo_identificacion: string, lote: ?string, descripcion: string, fecha_reintegro: string}> $bienes
     * @param array<string, array{en_cartera: int, aun_sin_descargar: int, en_otros_lotes: int, otros_lotes: list<int>}> $unidades
     * @param array<string, int> $placasCartera
     * @return array{filas: list<Fila>, total_lote: int, total_siguen: int}
     */
    public static function calcular(array $bienes, array $unidades, array $placasCartera): array
    {
        $grupos = [];
        foreach ($bienes as $bien) {
            $placa = $bien['lote'] ?? $bien['codigo_identificacion'];
            $grupos[$placa] ??= ['con_unidades' => $bien['lote'] !== null, 'descripcion' => $bien['descripcion'], 'cantidad' => 0, 'fechas' => []];
            $grupos[$placa]['cantidad']++;
            $grupos[$placa]['fechas'][] = $bien['fecha_reintegro'];
        }

        $filas = [];
        $totalLote = 0;
        $totalSiguen = 0;
        foreach ($grupos as $placa => $grupo) {
            $placa = (string) $placa;
            $totalLote += $grupo['cantidad'];
            $carteraDice = $placasCartera[self::normalizarPlaca($placa)] ?? 0;
            if ($carteraDice <= 0) {
                continue;
            }

            $deberianQuedar = null;
            $estado = $unidades[$placa] ?? ['en_cartera' => 0, 'aun_sin_descargar' => 0, 'en_otros_lotes' => 0, 'otros_lotes' => []];
            if ($grupo['con_unidades']) {
                $deberianQuedar = $estado['en_cartera'] + $estado['aun_sin_descargar'];
                $siguen = min(max(0, $carteraDice - $deberianQuedar), $grupo['cantidad']);
            } else {
                $siguen = $grupo['cantidad'];
            }
            if ($siguen <= 0) {
                continue;
            }

            $totalSiguen += $siguen;
            $filas[] = [
                'placa' => $placa,
                'descripcion' => $grupo['descripcion'],
                'con_unidades' => $grupo['con_unidades'],
                'cantidad_lote' => $grupo['cantidad'],
                'siguen' => $siguen,
                'cartera_dice' => $carteraDice,
                'deberian_quedar' => $deberianQuedar,
                'en_otros_lotes' => $grupo['con_unidades'] ? $estado['en_otros_lotes'] : 0,
                'otros_lotes' => $grupo['con_unidades'] ? $estado['otros_lotes'] : [],
                'fecha_desde' => min($grupo['fechas']),
                'fecha_hasta' => max($grupo['fechas']),
            ];
        }

        return ['filas' => $filas, 'total_lote' => $totalLote, 'total_siguen' => $totalSiguen];
    }

    /**
     * La placa como se compara: sin espacios, en mayúscula y, si es solo números, sin los ceros
     * de la izquierda y sin ".0" (Excel guarda 200033042 como número: 200033042.0).
     */
    public static function normalizarPlaca(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15) {
            $valor = sprintf('%.0f', $valor);
        }
        $texto = mb_strtoupper((string) preg_replace('/\s+/u', '', (string) (is_scalar($valor) ? $valor : '')));
        $texto = (string) preg_replace('/^(\d+)\.0+$/', '$1', $texto);
        if ($texto !== '' && ctype_digit($texto)) {
            $texto = ltrim($texto, '0');

            return $texto === '' ? '0' : $texto;
        }

        return $texto;
    }

    /** La cantidad de una celda: un número entero sin signo ("178", 178, "1.200"); null si no lo es. */
    private static function leerCantidad(mixed $valor): ?int
    {
        if (is_int($valor) || (is_float($valor) && $valor >= 0)) {
            return (int) round((float) $valor);
        }
        $texto = trim((string) (is_scalar($valor) ? $valor : ''));
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto) === 1) {
            $texto = str_replace('.', '', $texto);
        }

        return preg_match('/^\d{1,7}$/', $texto) === 1 ? (int) $texto : null;
    }

    /**
     * La primera fila (de las 30 primeras) con una celda corta que nombra la placa, y sus títulos
     * (letra => texto). [0, []] si no hay.
     *
     * @return array{0: int, 1: array<string, string>}
     */
    private static function filaDeTitulos(Worksheet $hoja, int $ultimaFila, int $ultimaColumna): array
    {
        for ($fila = 1; $fila <= min(self::FILAS_DE_TITULOS, $ultimaFila); $fila++) {
            $titulos = [];
            for ($c = 1; $c <= $ultimaColumna; $c++) {
                $letra = Coordinate::stringFromColumnIndex($c);
                $texto = trim((string) self::valor($hoja, $letra . $fila));
                if ($texto !== '' && mb_strlen($texto) <= self::LARGO_MAXIMO_TITULO) {
                    $titulos[$letra] = $texto;
                }
            }
            if (self::columnasPorTitulo($titulos)['placa'] !== null) {
                return [$fila, $titulos];
            }
        }

        return [0, []];
    }

    /**
     * @param array<string, string> $titulos letra => título
     * @return array{placa: ?string, cantidad: ?string, descripcion: ?string}
     */
    private static function columnasPorTitulo(array $titulos): array
    {
        $normales = array_map([self::class, 'normalizarTitulo'], $titulos);
        $placa = null;
        foreach (self::TITULOS_PLACA as $buscado) {
            foreach ($normales as $letra => $titulo) {
                if ($placa === null && preg_match('/\b' . $buscado . '\b/', $titulo) === 1) {
                    $placa = (string) $letra;
                }
            }
        }
        $cantidad = null;
        $descripcion = null;
        foreach ($normales as $letra => $titulo) {
            if ($cantidad === null && (string) $letra !== $placa && preg_match('/^(CANTIDAD|CANT)\b/', $titulo) === 1) {
                $cantidad = (string) $letra;
            }
            foreach (self::TITULOS_DESCRIPCION as $buscado) {
                if ($descripcion === null && (string) $letra !== $placa && str_contains($titulo, $buscado)) {
                    $descripcion = (string) $letra;
                }
            }
        }

        return ['placa' => $placa, 'cantidad' => $cantidad, 'descripcion' => $descripcion];
    }

    /** "N.º de Placa" → "N DE PLACA": mayúsculas, sin tildes y sin signos. */
    private static function normalizarTitulo(string $titulo): string
    {
        $titulo = strtr(mb_strtoupper($titulo), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $titulo));
    }

    /**
     * Las columnas que tienen algo en las primeras filas, con lo primero que traen, para elegir la
     * de la placa a mano: letra => "C · PLACA".
     *
     * @return array<string, string>
     */
    private static function columnasDelArchivo(Worksheet $hoja, int $ultimaFila, int $ultimaColumna): array
    {
        $columnas = [];
        for ($c = 1; $c <= $ultimaColumna; $c++) {
            $letra = Coordinate::stringFromColumnIndex($c);
            for ($fila = 1; $fila <= min(self::FILAS_DE_TITULOS, $ultimaFila); $fila++) {
                $texto = trim((string) preg_replace('/\s+/u', ' ', (string) self::valor($hoja, $letra . $fila)));
                if ($texto !== '') {
                    $columnas[$letra] = $letra . ' · ' . (mb_strlen($texto) > 40 ? mb_substr($texto, 0, 40) . '…' : $texto);
                    break;
                }
            }
        }

        return $columnas;
    }

    /** La letra de una columna elegida a mano, si existe en el archivo; si no, null. */
    private static function columnaValida(string $letra, int $ultimaColumna): ?string
    {
        $letra = strtoupper(trim($letra));
        if (preg_match('/^[A-Z]{1,2}$/', $letra) !== 1) {
            return null;
        }

        return Coordinate::columnIndexFromString($letra) <= $ultimaColumna ? $letra : null;
    }

    /**
     * El valor de una celda sin crearla si no existe: el resultado guardado si es una fórmula y
     * el texto si tiene formato enriquecido.
     */
    private static function valor(Worksheet $hoja, string $coordenada): mixed
    {
        if (!$hoja->cellExists($coordenada)) {
            return null;
        }
        $celda = $hoja->getCell($coordenada);
        $valor = $celda->isFormula() ? $celda->getOldCalculatedValue() : $celda->getValue();

        return $valor instanceof RichText ? $valor->getPlainText() : $valor;
    }
}
