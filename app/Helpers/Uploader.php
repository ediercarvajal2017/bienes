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

    private const TAMANO_MAXIMO_IMAGEN = 4 * 1024 * 1024;
    private const TAMANO_MAXIMO_PDF = 8 * 1024 * 1024;
    private const TAMANO_MAXIMO_DOCUMENTO = 8 * 1024 * 1024;
    private const TAMANO_MAXIMO_EXCEL = 8 * 1024 * 1024;

    /**
     * Valida y guarda una imagen (JPG/PNG). Devuelve la ruta relativa
     * ("subdir/nombre.ext") a guardar en la BD, o null si no se envió archivo.
     */
    public static function storeImage(array $file, string $subdir): ?string
    {
        $mime = self::validar($file, self::TAMANO_MAXIMO_IMAGEN);
        if ($mime === null) {
            return null;
        }

        if (!isset(self::IMAGENES_PERMITIDAS[$mime])) {
            throw new \RuntimeException('Formato de imagen no permitido. Usa JPG o PNG.');
        }

        return self::mover($file, $subdir, self::IMAGENES_PERMITIDAS[$mime]);
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
            throw new \RuntimeException('El archivo supera el tamaño máximo permitido.');
        }

        return mime_content_type($file['tmp_name']);
    }

    private static function mover(array $file, string $subdir, string $extension): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $targetDir = $config['storage_path'] . '/uploads/' . $subdir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de almacenamiento.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        return $subdir . '/' . $filename;
    }
}
