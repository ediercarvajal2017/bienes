<?php

declare(strict_types=1);

namespace App\Helpers;

use PDOStatement;
use ZipArchive;

/**
 * Libro de Excel (.xlsx) escrito fila por fila en disco, para exportaciones grandes.
 *
 * PhpSpreadsheet guarda todo el libro en memoria (≈1 KB por celda): una auditoría de 100.000
 * filas no cabe en un hosting compartido. Aquí cada hoja se escribe directo a un archivo
 * temporal y al final se empaqueta el .xlsx (que es un ZIP de XML).
 *
 * Todas las celdas son TEXTO (así los códigos y documentos conservan los ceros a la
 * izquierda y nada se interpreta como fórmula ni como fecha), salvo las columnas que se
 * indiquen como numéricas. Encabezado en negrita, fijo al desplazarse y con filtros.
 */
final class LibroXlsxStreaming
{
    /** @var list<array{titulo: string, archivo: string}> */
    private array $hojas = [];

    public function __construct(private readonly string $carpetaTrabajo)
    {
    }

    /**
     * Escribe una hoja con los resultados de $stmt (encabezados = nombres de columna).
     *
     * @param list<string> $numericas columnas cuyos valores numéricos se guardan como número
     * @return int filas escritas (sin contar el encabezado)
     */
    public function agregarHoja(string $titulo, PDOStatement $stmt, array $numericas = []): int
    {
        $n = count($this->hojas) + 1;
        $ruta = $this->carpetaTrabajo . "/hoja{$n}.xml";
        $archivo = fopen($ruta, 'wb');
        if ($archivo === false) {
            throw new \RuntimeException('No se pudo escribir el archivo temporal.');
        }

        $encabezados = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $encabezados[] = (string) ($stmt->getColumnMeta($i)['name'] ?? '');
        }
        $esNumerica = array_map(static fn (string $e): bool => in_array($e, $numericas, true), $encabezados);
        $ultimaColumna = self::columna(max(1, count($encabezados)));

        fwrite($archivo, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>');
        foreach ($encabezados as $i => $encabezado) {
            $col = $i + 1;
            fwrite($archivo, sprintf('<col min="%d" max="%d" width="%d" customWidth="1"/>', $col, $col, self::ancho($encabezado)));
        }
        fwrite($archivo, '</cols><sheetData>');

        fwrite($archivo, '<row r="1">');
        foreach ($encabezados as $i => $encabezado) {
            fwrite($archivo, self::celdaTexto(self::columna($i + 1) . '1', $encabezado, 1));
        }
        fwrite($archivo, '</row>');

        $filas = 0;
        while (($fila = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
            $r = $filas + 2;
            $xml = '<row r="' . $r . '">';
            foreach ($fila as $i => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                $ref = self::columna($i + 1) . $r;
                $texto = (string) $valor;
                $xml .= ($esNumerica[$i] ?? false) && is_numeric($texto)
                    ? '<c r="' . $ref . '"><v>' . $texto . '</v></c>'
                    : self::celdaTexto($ref, $texto);
            }
            fwrite($archivo, $xml . '</row>');
            $filas++;
        }
        $stmt->closeCursor();

        fwrite($archivo, '</sheetData><autoFilter ref="A1:' . $ultimaColumna . '1"/></worksheet>');
        fclose($archivo);

        $this->hojas[] = ['titulo' => self::tituloValido($titulo), 'archivo' => $ruta];

        return $filas;
    }

    /** Empaqueta el libro en $rutaXlsx y borra los archivos temporales de las hojas. */
    public function guardar(string $rutaXlsx): void
    {
        $zip = new ZipArchive();
        if ($zip->open($rutaXlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el libro de Excel.');
        }

        $tiposHojas = '';
        $relaciones = '';
        $hojas = '';
        foreach ($this->hojas as $i => $hoja) {
            $n = $i + 1;
            $tiposHojas .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $relaciones .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $hojas .= '<sheet name="' . self::xml($hoja['titulo']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $zip->addFile($hoja['archivo'], 'xl/worksheets/sheet' . $n . '.xml');
        }
        $idEstilos = count($this->hojas) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $tiposHojas . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $hojas . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relaciones
            . '<Relationship Id="rId' . $idEstilos . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        // Estilo 0: normal. Estilo 1: negrita con fondo gris claro (encabezados).
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F0EC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>');

        $zip->close();
        foreach ($this->hojas as $hoja) {
            @unlink($hoja['archivo']);
        }
    }

    private static function celdaTexto(string $ref, string $texto, int $estilo = 0): string
    {
        $limpio = self::xml($texto);
        $espacios = $limpio !== trim($limpio) ? ' xml:space="preserve"' : '';

        return '<c r="' . $ref . '" t="inlineStr"' . ($estilo > 0 ? ' s="' . $estilo . '"' : '') . '><is><t' . $espacios . '>' . $limpio . '</t></is></c>';
    }

    /** Escapa para XML y quita los caracteres de control que XML no admite. */
    private static function xml(string $texto): string
    {
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);
        // Una celda de Excel admite hasta 32.767 caracteres.
        if (mb_strlen($texto) > 32000) {
            $texto = mb_substr($texto, 0, 32000) . '…';
        }

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** 1 → A, 27 → AA. */
    private static function columna(int $numero): string
    {
        $letras = '';
        while ($numero > 0) {
            $resto = ($numero - 1) % 26;
            $letras = chr(65 + $resto) . $letras;
            $numero = intdiv($numero - $resto - 1, 26);
        }

        return $letras;
    }

    private static function ancho(string $encabezado): int
    {
        if (preg_match('/Descripción|Observaciones|Datos|Motivo|Respuesta|Resolución|Bien$|Responsables?|Nombre$/u', $encabezado)) {
            return 40;
        }

        return max(10, min(28, mb_strlen($encabezado) + 4));
    }

    /** Excel: máximo 31 caracteres y sin : \ / ? * [ ]. */
    private static function tituloValido(string $titulo): string
    {
        return mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $titulo), 0, 31);
    }
}
