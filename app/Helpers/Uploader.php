<?php

declare(strict_types=1);

namespace App\Helpers;

final class Uploader
{
    private const IMAGENES_PERMITIDAS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const DOCUMENTOS_PERMITIDOS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Un .xlsx es, por dentro, un contenedor ZIP: según el servidor, mime_content_type()
     * puede reportarlo como el tipo "oficial" de Excel o simplemente como application/zip.
     * Por eso se exige también que la extensión del archivo sea .xlsx/.xls, para no aceptar
     * cualquier .zip disfrazado.
     */
    private const EXCEL_PERMITIDOS = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xls',
        'application/zip' => 'xlsx',
    ];

    private const TAMANO_MAXIMO_IMAGEN = 2 * 1024 * 1024;
    private const TAMANO_MAXIMO_PDF = 8 * 1024 * 1024;
    private const TAMANO_MAXIMO_DOCUMENTO = 8 * 1024 * 1024;
    private const TAMANO_MAXIMO_EXCEL = 8 * 1024 * 1024;

    /**
     * Valida y guarda una imagen (JPG/PNG). Devuelve la ruta relativa
     * ("subdir/nombre.ext") a guardar en la BD, o null si no se envió archivo.
     *
     * Si se indica $nombreBase (p.ej. el código del bien), el archivo se guarda como
     * "{nombreBase}_{consecutivo}.ext" en vez del nombre aleatorio por defecto —
     * el consecutivo siempre sube y nunca se repite dentro de esa carpeta.
     */
    public static function storeImage(array $file, string $subdir, ?string $nombreBase = null): ?string
    {
        $mime = self::validar($file, self::TAMANO_MAXIMO_IMAGEN);
        if ($mime === null) {
            return null;
        }

        if (!isset(self::IMAGENES_PERMITIDAS[$mime])) {
            throw new \RuntimeException('Formato de imagen no permitido. Usa JPG o PNG.');
        }

        $extension = self::IMAGENES_PERMITIDAS[$mime];

        return $nombreBase !== null
            ? self::moverConNombre($file, $subdir, $extension, $nombreBase)
            : self::mover($file, $subdir, $extension);
    }

    /**
     * Valida y guarda un PDF (facturas, formatos de reintegro). Misma convención de retorno.
     */
    public static function storePdf(array $file, string $subdir): ?string
    {
        $mime = self::validar($file, self::TAMANO_MAXIMO_PDF);
        if ($mime === null) {
            return null;
        }

        if ($mime !== 'application/pdf') {
            throw new \RuntimeException('El archivo debe ser un PDF.');
        }

        return self::mover($file, $subdir, 'pdf');
    }

    /**
     * Valida y guarda un documento de evidencia (PDF, o foto/escaneo en JPG/PNG),
     * para casos donde el soporte puede llegar de cualquiera de esas formas.
     */
    public static function storeDocumento(array $file, string $subdir): ?string
    {
        $mime = self::validar($file, self::TAMANO_MAXIMO_DOCUMENTO);
        if ($mime === null) {
            return null;
        }

        if (!isset(self::DOCUMENTOS_PERMITIDOS[$mime])) {
            throw new \RuntimeException('Formato no permitido. Usa PDF, JPG o PNG.');
        }

        return self::mover($file, $subdir, self::DOCUMENTOS_PERMITIDOS[$mime]);
    }

    /**
     * Valida y guarda un Excel (.xlsx o .xls) — usado por la cartera de bienes,
     * que siempre se maneja en ese formato.
     */
    public static function storeExcel(array $file, string $subdir): ?string
    {
        $mime = self::validar($file, self::TAMANO_MAXIMO_EXCEL);
        if ($mime === null) {
            return null;
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!isset(self::EXCEL_PERMITIDOS[$mime]) || !in_array($extension, ['xlsx', 'xls'], true)) {
            throw new \RuntimeException('El archivo debe ser un Excel (.xlsx o .xls).');
        }

        return self::mover($file, $subdir, $extension);
    }

    private static function validar(array $file, int $tamanoMaximo): ?string
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Ocurrió un error al subir el archivo.');
        }

        if ($file['size'] > $tamanoMaximo) {
            $maximoMb = (int) round($tamanoMaximo / 1024 / 1024);
            throw new \RuntimeException("El archivo supera el tamaño máximo permitido ({$maximoMb}MB).");
        }

        // mime_content_type() devuelve false (no null) si no logra determinar el tipo --
        // sin este ajuste, ese false se colaba como si fuera un mime válido, porque el
        // llamador solo compara contra null.
        return mime_content_type($file['tmp_name']) ?: null;
    }

    private static function mover(array $file, string $subdir, string $extension): string
    {
        $targetDir = self::prepararCarpeta($subdir);
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        return $subdir . '/' . $filename;
    }

    /**
     * Igual que mover(), pero con nombre legible "{codigo}_{consecutivo}.ext" en vez de
     * uno aleatorio. El consecutivo arranca después de los archivos ya existentes para
     * ese mismo código y sigue subiendo hasta encontrar un nombre libre, así nunca se
     * repite ni sobrescribe una foto anterior del mismo bien.
     */
    private static function moverConNombre(array $file, string $subdir, string $extension, string $nombreBase): string
    {
        $targetDir = self::prepararCarpeta($subdir);
        $filename = self::siguienteNombreDisponible($targetDir, self::nombreSeguro($nombreBase), $extension);

        if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        return $subdir . '/' . $filename;
    }

    /**
     * Igual que storeImage() con nombreBase, pero para una imagen que ya está en disco
     * (no viene de un $_FILES de una petición HTTP) — usado por la carga masiva de fotos
     * vía .zip, donde cada imagen se extrae primero a un archivo temporal.
     */
    public static function guardarImagenDesdeRuta(string $rutaOrigen, string $subdir, string $nombreBase): string
    {
        if (!is_file($rutaOrigen)) {
            throw new \RuntimeException('El archivo no existe.');
        }

        if ((int) filesize($rutaOrigen) > self::TAMANO_MAXIMO_IMAGEN) {
            $maximoMb = (int) round(self::TAMANO_MAXIMO_IMAGEN / 1024 / 1024);
            throw new \RuntimeException("El archivo supera el tamaño máximo permitido ({$maximoMb}MB).");
        }

        $mime = mime_content_type($rutaOrigen);
        if (!isset(self::IMAGENES_PERMITIDAS[$mime])) {
            throw new \RuntimeException('Formato de imagen no permitido. Usa JPG o PNG.');
        }

        $targetDir = self::prepararCarpeta($subdir);
        $filename = self::siguienteNombreDisponible($targetDir, self::nombreSeguro($nombreBase), self::IMAGENES_PERMITIDAS[$mime]);

        if (!copy($rutaOrigen, $targetDir . '/' . $filename)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        return $subdir . '/' . $filename;
    }

    private static function prepararCarpeta(string $subdir): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $targetDir = $config['storage_path'] . '/uploads/' . $subdir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de almacenamiento.');
        }

        return $targetDir;
    }

    /**
     * "{base}_{consecutivo}.ext": el consecutivo arranca después de los archivos ya
     * existentes con ese mismo prefijo y sigue subiendo hasta encontrar un nombre libre,
     * así nunca se repite ni sobrescribe un archivo anterior del mismo bien.
     */
    private static function siguienteNombreDisponible(string $targetDir, string $base, string $extension): string
    {
        $consecutivo = count(glob($targetDir . '/' . $base . '_*') ?: []) + 1;
        $filename = $base . '_' . $consecutivo . '.' . $extension;

        while (file_exists($targetDir . '/' . $filename)) {
            $consecutivo++;
            $filename = $base . '_' . $consecutivo . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Convierte un texto libre (código del bien) en un nombre de archivo seguro:
     * solo letras/números/guiones, sin espacios ni caracteres que puedan romper
     * la ruta o el sistema de archivos.
     */
    private static function nombreSeguro(string $texto): string
    {
        $limpio = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $texto), '-');

        return $limpio !== '' ? $limpio : 'bien';
    }
}
