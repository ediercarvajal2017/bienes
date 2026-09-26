<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Uploader;
use App\Models\Bien;

final class FotoMasivaService
{
    private const EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png'];

    /** Máximo de archivos dentro de un .zip (evita .zip con millones de entradas). */
    private const MAX_ENTRADAS = 3000;

    /**
     * Recorre un .zip donde cada imagen se llama "{codigo_del_bien}.ext" y la asocia al
     * bien correspondiente de la institución (emparejando por codigo_identificacion). No
     * sobrescribe bienes que ya tienen foto, para que repetir un lote por error nunca borre
     * una foto más reciente — esos casos quedan reportados como "ya_tenian_foto".
     *
     * Protección contra "bombas zip" (un .zip pequeño que al descomprimirse ocupa gigas):
     * cada imagen se revisa por su tamaño declarado ANTES de descomprimirla, y además se
     * leen como máximo TAMANO_MAXIMO_IMAGEN + 1 bytes, por si el encabezado del .zip
     * mintiera sobre el tamaño. Antes cada entrada se descomprimía completa en memoria.
     *
     * @return array{emparejadas: string[], sin_bien: string[], ya_tenian_foto: string[], formato_invalido: string[], demasiado_grandes: string[]}
     */
    public static function procesar(string $rutaZip, int $institucionId): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($rutaZip) !== true) {
            throw new \RuntimeException('El archivo no es un .zip válido o está dañado.');
        }

        if ($zip->numFiles > self::MAX_ENTRADAS) {
            $zip->close();
            throw new \RuntimeException('El .zip tiene demasiados archivos (máximo ' . self::MAX_ENTRADAS . '). Divídalo en varios .zip más pequeños.');
        }

        $resultado = ['emparejadas' => [], 'sin_bien' => [], 'ya_tenian_foto' => [], 'formato_invalido' => [], 'demasiado_grandes' => []];
        $tmpDir = sys_get_temp_dir();
        $maximo = Uploader::TAMANO_MAXIMO_IMAGEN;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombreEntrada = $zip->getNameIndex($i);

            if ($nombreEntrada === false || str_ends_with($nombreEntrada, '/')
                || str_contains($nombreEntrada, '__MACOSX/') || basename($nombreEntrada) === '.DS_Store') {
                continue;
            }

            $nombreArchivo = basename($nombreEntrada);
            $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            $codigo = pathinfo($nombreArchivo, PATHINFO_FILENAME);

            if (!in_array($extension, self::EXTENSIONES_IMAGEN, true)) {
                $resultado['formato_invalido'][] = $nombreArchivo;
                continue;
            }

            $bien = Bien::buscarPorCodigoInstitucion($institucionId, $codigo);
            if (!$bien) {
                $resultado['sin_bien'][] = $nombreArchivo;
                continue;
            }

            if (!empty($bien['foto_path'])) {
                $resultado['ya_tenian_foto'][] = $codigo;
                continue;
            }

            $info = $zip->statIndex($i);
            if ($info === false || $info['size'] > $maximo) {
                $resultado['demasiado_grandes'][] = $nombreArchivo;
                continue;
            }

            $contenido = $zip->getFromIndex($i, $maximo + 1);
            if ($contenido === false) {
                $resultado['formato_invalido'][] = $nombreArchivo;
                continue;
            }
            if (strlen($contenido) > $maximo) {
                $resultado['demasiado_grandes'][] = $nombreArchivo;
                continue;
            }

            $rutaTemporal = tempnam($tmpDir, 'sigebi_foto_');
            file_put_contents($rutaTemporal, $contenido);

            try {
                $path = Uploader::guardarImagenDesdeRuta($rutaTemporal, 'fotos_bienes', $codigo);
                Bien::updateFoto((int) $bien['id'], $path);
                $resultado['emparejadas'][] = $codigo;
            } catch (\RuntimeException $e) {
                $resultado['formato_invalido'][] = $nombreArchivo;
            } finally {
                unlink($rutaTemporal);
            }
        }

        $zip->close();

        return $resultado;
    }
}
