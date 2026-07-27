<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Uploader;
use App\Models\Bien;

final class FotoMasivaService
{
    private const EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png'];

    /**
     * Recorre un .zip donde cada imagen se llama "{codigo_del_bien}.ext" y la asocia al
     * bien correspondiente de la institución (emparejando por codigo_identificacion). No
     * sobrescribe bienes que ya tienen foto, para que repetir un lote por error nunca borre
     * una foto más reciente — esos casos quedan reportados como "ya_tenian_foto".
     *
     * @return array{emparejadas: string[], sin_bien: string[], ya_tenian_foto: string[], formato_invalido: string[]}
     */
    public static function procesar(string $rutaZip, int $institucionId): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($rutaZip) !== true) {
            throw new \RuntimeException('El archivo no es un .zip válido o está dañado.');
        }

        $resultado = ['emparejadas' => [], 'sin_bien' => [], 'ya_tenian_foto' => [], 'formato_invalido' => []];
        $tmpDir = sys_get_temp_dir();

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

            $contenido = $zip->getFromIndex($i);
            if ($contenido === false) {
                $resultado['formato_invalido'][] = $nombreArchivo;
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
