<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Archivo
{
    /**
     * Cada carpeta de /storage/uploads corresponde a un flujo de subida distinto,
     * sin una tabla central que los enumere a todos. Para saber a qué institución
     * pertenece un archivo (y así no dejar que un usuario vea archivos de otra
     * institución adivinando el nombre) se busca la ruta guardada en la tabla
     * dueña de esa carpeta. "facturas" es la única compartida por dos flujos
     * (factura de un bien y factura administrativa), por eso tiene dos consultas.
     *
     * Devuelve null si ningún registro referencia esa ruta (archivo huérfano o
     * inexistente), lo que en ArchivoController se trata igual que "no autorizado".
     */
    public static function institucionPropietaria(string $tipo, string $rutaRelativa): ?int
    {
        $consultas = match ($tipo) {
            'logos' => ['SELECT id AS institucion_id FROM instituciones WHERE logo_path = ?'],
            'fotos_usuarios' => ['SELECT institucion_id FROM usuarios WHERE foto_path = ?'],
            'fotos_bienes' => ['SELECT institucion_id FROM bienes WHERE foto_path = ?'],
            'facturas' => [
                'SELECT institucion_id FROM bienes WHERE factura_pdf_path = ?',
                'SELECT institucion_id FROM facturas_administrativas WHERE archivo_path = ?',
            ],
            'bajas' => ['SELECT b.institucion_id FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id WHERE bb.foto_path = ?'],
            'cartera' => ['SELECT institucion_id FROM cartera_envios WHERE archivo_path = ?'],
            'reintegros' => ['SELECT institucion_id FROM formatos_reintegro WHERE archivo_path = ?'],
            'plaqueteo' => ['SELECT institucion_id FROM formatos_plaqueteo WHERE archivo_path = ?'],
            default => [],
        };

        $pdo = Database::connection();

        foreach ($consultas as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$rutaRelativa]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        return null;
    }
}
