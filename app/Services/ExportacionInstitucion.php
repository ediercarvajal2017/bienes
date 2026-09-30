<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\LibroXlsxStreaming;
use App\Models\Institucion;
use PDO;
use ZipArchive;

/**
 * "Descargar toda la información" de una institución (con sus sedes): un ZIP con un libro de
 * Excel (una hoja por tipo de dato) y, si se pide, las fotos y documentos subidos. Es la
 * entrega de los datos que promete la política de datos cuando una institución deja de usar
 * MIA, y también sirve como copia propia de la institución.
 *
 * - El libro se escribe fila por fila (App\Helpers\LibroXlsxStreaming): una auditoría de
 *   100.000 filas no cabe en la memoria de PhpSpreadsheet. Los códigos quedan como texto
 *   (conservan los ceros a la izquierda, que un CSV abierto en Excel pierde).
 * - Nunca se exportan contraseñas, claves de la verificación en dos pasos ni tokens.
 */
final class ExportacionInstitucion
{
    /** Carpeta de uploads => consultas que devuelven las rutas de la institución (con :ids). */
    private const ARCHIVOS = [
        'logos' => ['SELECT logo_path FROM instituciones WHERE id IN (:ids)'],
        'fotos_usuarios' => ['SELECT foto_path FROM usuarios WHERE institucion_id IN (:ids)'],
        'fotos_bienes' => ['SELECT foto_path FROM bienes WHERE institucion_id IN (:ids)'],
        'facturas' => [
            'SELECT factura_pdf_path FROM bienes WHERE institucion_id IN (:ids)',
            'SELECT archivo_path FROM facturas_administrativas WHERE institucion_id IN (:ids)',
        ],
        'bajas' => ['SELECT bb.foto_path FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id WHERE b.institucion_id IN (:ids)'],
        'cartera' => ['SELECT archivo_path FROM cartera_envios WHERE institucion_id IN (:ids)'],
        'reintegros' => ['SELECT archivo_path FROM formatos_reintegro WHERE institucion_id IN (:ids)'],
        'plaqueteo' => ['SELECT archivo_path FROM formatos_plaqueteo WHERE institucion_id IN (:ids)'],
        'hallazgos' => ['SELECT foto_path FROM hallazgos_verificacion WHERE institucion_id IN (:ids)'],
    ];

    /**
     * Arma el ZIP en un archivo temporal y devuelve su ruta (quien llama lo envía y lo borra).
     *
     * @return array{ruta: string, nombre: string, resumen: array<string, int>, archivos: int, faltantes: int}
     */
    public static function generar(int $institucionId, bool $conArchivos, string $generadoPor, string $carpetaTemporal): array
    {
        $familia = Institucion::familiaDe($institucionId);
        if ($familia === []) {
            throw new \RuntimeException('La institución no existe.');
        }
        $ids = implode(',', array_map(static fn (array $i): int => (int) $i['id'], $familia));
        $principal = $familia[0];

        if (!is_dir($carpetaTemporal) && !mkdir($carpetaTemporal, 0700, true) && !is_dir($carpetaTemporal)) {
            throw new \RuntimeException('No se pudo crear la carpeta temporal.');
        }
        // Restos de exportaciones que se cortaron (el proceso murió antes de borrarlos).
        foreach (glob($carpetaTemporal . '/exportacion-*') ?: [] as $viejo) {
            if (filemtime($viejo) < time() - 86400) {
                is_dir($viejo) ? self::borrarCarpeta($viejo) : @unlink($viejo);
            }
        }
        $trabajo = $carpetaTemporal . '/exportacion-' . bin2hex(random_bytes(8));
        mkdir($trabajo, 0700);

        $rutaZip = $trabajo . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP.');
        }

        $resumen = [];
        $libro = new LibroXlsxStreaming($trabajo);
        $pdo = Database::connection();
        // Sin búfer: las filas llegan de a una y la memoria no crece con el tamaño de la tabla.
        $pdo->setAttribute(self::atributoBufer(), false);
        try {
            foreach (self::consultas() as $clave => $sql) {
                $titulo = self::TITULOS[$clave];
                $stmt = $pdo->query(str_replace(':ids', $ids, $sql));
                if ($stmt === false) {
                    throw new \RuntimeException('No se pudo leer la información.');
                }
                $resumen[$titulo] = $libro->agregarHoja($titulo, $stmt, self::COLUMNAS_NUMERICAS);
            }
        } finally {
            $pdo->setAttribute(self::atributoBufer(), true);
        }
        $rutaLibro = $trabajo . '/informacion.xlsx';
        $libro->guardar($rutaLibro);
        $zip->addFile($rutaLibro, 'informacion.xlsx');

        $incluidos = 0;
        $faltantes = 0;
        if ($conArchivos) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $uploads = $config['storage_path'] . '/uploads';
            foreach (self::rutasDeArchivos($ids) as $ruta) {
                $absoluta = $uploads . '/' . $ruta;
                if (!is_file($absoluta)) {
                    $faltantes++;
                    continue;
                }
                $zip->addFile($absoluta, 'archivos/' . $ruta);
                // Fotos y PDF ya vienen comprimidos: guardarlos tal cual ahorra tiempo de CPU.
                $zip->setCompressionName('archivos/' . $ruta, ZipArchive::CM_STORE);
                $incluidos++;
            }
        }

        $zip->addFromString('LEEME.txt', self::leeme($familia, $resumen, $conArchivos, $incluidos, $faltantes, $generadoPor));
        $zip->close();
        self::borrarCarpeta($trabajo);

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(self::sinTildes((string) $principal['nombre']))), '-');

        return [
            'ruta' => $rutaZip,
            'nombre' => 'MIA_' . ($slug !== '' ? $slug : 'institucion') . '_' . date('Y-m-d') . '.zip',
            'resumen' => $resumen,
            'archivos' => $incluidos,
            'faltantes' => $faltantes,
        ];
    }

    /** Nombre de la hoja de Excel de cada consulta (máximo 31 caracteres). */
    private const TITULOS = [
        'instituciones' => 'Instituciones',
        'bienes' => 'Bienes',
        'asignaciones' => 'Asignaciones',
        'movimientos' => 'Movimientos',
        'bajas' => 'Bajas',
        'solicitudes_reintegro' => 'Solicitudes de reintegro',
        'lotes_reintegro' => 'Lotes de reintegro',
        'espacios' => 'Espacios',
        'categorias' => 'Categorías',
        'usuarios' => 'Usuarios',
        'verificaciones' => 'Verificaciones',
        'verificaciones_resultados' => 'Resultados de verificación',
        'hallazgos' => 'Hallazgos',
        'documentos' => 'Documentos',
        'auditoria' => 'Auditoría',
    ];

    /** Columnas que se guardan como número en Excel (el resto, como texto). */
    private const COLUMNAS_NUMERICAS = ['Id', 'Valor', 'Bienes', 'Verificación', 'Id de la entidad', 'Lote de reintegro'];

    /**
     * Una hoja por tipo de dato, con encabezados en español y nombres en vez de identificadores.
     * :ids se reemplaza por los id (enteros) de la institución y sus sedes.
     *
     * @return array<string, string>
     */
    private static function consultas(): array
    {
        $persona = static fn (string $a): string => "TRIM(CONCAT_WS(' ', {$a}.nombres, {$a}.apellidos))";

        return [
            'instituciones' => "SELECT i.id AS `Id`, i.codigo_dane AS `Código DANE`, i.nombre AS `Nombre`, i.direccion AS `Dirección`,
                    i.tipo_sede AS `Tipo de sede`, p.nombre AS `Sede principal`, i.email_institucional AS `Correo`,
                    IF(i.activo = 1, 'Sí', 'No') AS `Activa`, i.created_at AS `Creada`
                FROM instituciones i LEFT JOIN instituciones p ON p.id = i.institucion_padre_id
                WHERE i.id IN (:ids) ORDER BY i.id",
            'bienes' => "SELECT b.id AS `Id`, i.nombre AS `Sede`, b.codigo_identificacion AS `Código`, b.descripcion AS `Descripción`,
                    b.lote AS `Lote`, b.marca AS `Marca`, c.nombre AS `Categoría`, b.fecha_ingreso AS `Fecha de ingreso`,
                    b.valor AS `Valor`, b.estado AS `Estado`,
                    (SELECT GROUP_CONCAT(DISTINCT e.nombre SEPARATOR ' | ') FROM asignaciones a JOIN espacios e ON e.id = a.espacio_id
                        WHERE a.bien_id = b.id AND a.activa = 1) AS `Ubicación actual`,
                    (SELECT IF(a.usuario_responsable_id IS NULL, 'Grupal', 'Individual') FROM asignaciones a
                        WHERE a.bien_id = b.id AND a.activa = 1 ORDER BY a.id DESC LIMIT 1) AS `Tipo de responsabilidad`,
                    (SELECT IF(a.usuario_responsable_id IS NOT NULL,
                               (SELECT {$persona('u')} FROM usuarios u WHERE u.id = a.usuario_responsable_id),
                               (SELECT GROUP_CONCAT({$persona('u')} SEPARATOR ', ') FROM espacio_responsables er
                                  JOIN usuarios u ON u.id = er.usuario_id WHERE er.espacio_id = a.espacio_id))
                        FROM asignaciones a WHERE a.bien_id = b.id AND a.activa = 1 ORDER BY a.id DESC LIMIT 1) AS `Responsable actual`,
                    IF(b.tiene_factura = 1, 'Sí', 'No') AS `Tiene factura`, b.foto_path AS `Foto`, b.factura_pdf_path AS `Factura`,
                    b.qr_impreso_en AS `QR impreso en`, b.qr_confirmado_en AS `QR pegado en`,
                    {$persona('cr')} AS `Registrado por`, b.created_at AS `Registrado en`, b.updated_at AS `Actualizado en`
                FROM bienes b
                JOIN instituciones i ON i.id = b.institucion_id
                LEFT JOIN categorias_bienes c ON c.id = b.categoria_id
                LEFT JOIN usuarios cr ON cr.id = b.created_by
                WHERE b.institucion_id IN (:ids) ORDER BY b.codigo_identificacion",
            'asignaciones' => "SELECT a.id AS `Id`, b.codigo_identificacion AS `Código del bien`, b.descripcion AS `Bien`,
                    IF(a.usuario_responsable_id IS NULL, 'Grupal', 'Individual') AS `Tipo de responsabilidad`,
                    {$persona('u')} AS `Responsable individual`, u.documento AS `Documento del responsable`, e.nombre AS `Espacio`,
                    a.fecha_asignacion AS `Fecha de asignación`, IF(a.activa = 1, 'Sí', 'No') AS `Vigente`,
                    a.observaciones AS `Observaciones`, {$persona('ap')} AS `Asignado por`, a.created_at AS `Registrada en`
                FROM asignaciones a
                JOIN bienes b ON b.id = a.bien_id
                LEFT JOIN usuarios u ON u.id = a.usuario_responsable_id
                LEFT JOIN espacios e ON e.id = a.espacio_id
                LEFT JOIN usuarios ap ON ap.id = a.asignado_por
                WHERE b.institucion_id IN (:ids) ORDER BY a.id",
            'movimientos' => "SELECT m.id AS `Id`, b.codigo_identificacion AS `Código del bien`, b.descripcion AS `Bien`, m.tipo AS `Tipo`,
                    m.fecha AS `Fecha`, {$persona('r')} AS `Registrado por`, {$persona('ua')} AS `Responsable anterior`,
                    eo.nombre AS `Espacio de origen`, {$persona('po')} AS `Persona de origen`,
                    ed.nombre AS `Espacio de destino`, {$persona('pd')} AS `Persona de destino`, m.destino_texto AS `Destino`,
                    m.lote_reintegro_id AS `Lote de reintegro`, m.observaciones AS `Observaciones`, m.created_at AS `Registrado en`
                FROM movimientos m
                JOIN bienes b ON b.id = m.bien_id
                LEFT JOIN usuarios r ON r.id = m.responsable_id
                LEFT JOIN usuarios ua ON ua.id = m.usuario_anterior_id
                LEFT JOIN espacios eo ON eo.id = m.espacio_origen_id
                LEFT JOIN espacios ed ON ed.id = m.espacio_destino_id
                LEFT JOIN usuarios po ON po.id = m.persona_origen_id
                LEFT JOIN usuarios pd ON pd.id = m.persona_destino_id
                WHERE b.institucion_id IN (:ids) ORDER BY m.id",
            'bajas' => "SELECT bb.id AS `Id`, b.codigo_identificacion AS `Código del bien`, b.descripcion AS `Bien`,
                    bb.estado_reportado AS `Estado reportado`, bb.ubicacion AS `Ubicación`, {$persona('r')} AS `Reportada por`,
                    bb.fecha_reporte AS `Fecha del reporte`, bb.descripcion AS `Descripción`, bb.foto_path AS `Foto`,
                    bb.estado AS `Estado`, bb.motivo_rechazo AS `Motivo del rechazo`, {$persona('rs')} AS `Resuelta por`,
                    bb.resuelta_en AS `Resuelta en`
                FROM bajas_bienes bb
                JOIN bienes b ON b.id = bb.bien_id
                LEFT JOIN usuarios r ON r.id = bb.responsable_id
                LEFT JOIN usuarios rs ON rs.id = bb.resuelta_por
                WHERE b.institucion_id IN (:ids) ORDER BY bb.id",
            'solicitudes_reintegro' => "SELECT s.id AS `Id`, b.codigo_identificacion AS `Código del bien`, b.descripcion AS `Bien`,
                    {$persona('so')} AS `Solicitada por`, s.motivo AS `Motivo`, s.estado AS `Estado`, s.respuesta AS `Respuesta`,
                    {$persona('re')} AS `Resuelta por`, s.resuelta_en AS `Resuelta en`, s.created_at AS `Solicitada en`
                FROM solicitudes_reintegro s
                JOIN bienes b ON b.id = s.bien_id
                LEFT JOIN usuarios so ON so.id = s.solicitado_por
                LEFT JOIN usuarios re ON re.id = s.resuelta_por
                WHERE s.institucion_id IN (:ids) ORDER BY s.id",
            'lotes_reintegro' => "SELECT l.id AS `Id`, i.nombre AS `Sede`, l.fecha AS `Fecha`, l.destino_texto AS `Destino`,
                    l.observaciones AS `Observaciones`, {$persona('r')} AS `Registrado por`, l.created_at AS `Registrado en`,
                    (SELECT COUNT(*) FROM movimientos m WHERE m.lote_reintegro_id = l.id) AS `Bienes`
                FROM lotes_reintegro l
                JOIN instituciones i ON i.id = l.institucion_id
                LEFT JOIN usuarios r ON r.id = l.registrado_por
                WHERE l.institucion_id IN (:ids) ORDER BY l.id",
            'espacios' => "SELECT e.id AS `Id`, i.nombre AS `Sede`, e.codigo AS `Código`, e.nombre AS `Nombre`,
                    IF(e.activo = 1, 'Sí', 'No') AS `Activo`, e.eliminado_en AS `En la papelera desde`,
                    (SELECT GROUP_CONCAT({$persona('u')} SEPARATOR ' | ') FROM espacio_responsables er JOIN usuarios u ON u.id = er.usuario_id
                        WHERE er.espacio_id = e.id) AS `Responsables`,
                    e.created_at AS `Creado en`
                FROM espacios e JOIN instituciones i ON i.id = e.institucion_id
                WHERE e.institucion_id IN (:ids) ORDER BY i.nombre, e.nombre",
            'categorias' => "SELECT c.id AS `Id`, i.nombre AS `Sede`, c.nombre AS `Nombre`, IF(c.activo = 1, 'Sí', 'No') AS `Activa`,
                    c.eliminado_en AS `En la papelera desde`
                FROM categorias_bienes c JOIN instituciones i ON i.id = c.institucion_id
                WHERE c.institucion_id IN (:ids) ORDER BY c.nombre",
            'usuarios' => "SELECT u.id AS `Id`, i.nombre AS `Sede`, u.documento AS `Documento`, u.nombres AS `Nombres`,
                    u.apellidos AS `Apellidos`, ca.nombre AS `Cargo`, u.email AS `Correo`, r.nombre AS `Rol`,
                    IF(u.activo = 1, 'Sí', 'No') AS `Activo`, u.eliminado_en AS `En la papelera desde`,
                    u.ultimo_login AS `Último ingreso`, IF(u.totp_activado_en IS NULL, 'No', 'Sí') AS `Verificación en dos pasos`,
                    u.politica_aceptada_en AS `Aceptó la política de datos el`, u.politica_version AS `Versión de la política`,
                    u.created_at AS `Creado en`
                FROM usuarios u
                JOIN instituciones i ON i.id = u.institucion_id
                JOIN roles r ON r.id = u.rol_id
                LEFT JOIN cargos ca ON ca.id = u.cargo_id
                WHERE u.institucion_id IN (:ids) ORDER BY u.apellidos, u.nombres",
            'verificaciones' => "SELECT j.id AS `Id`, i.nombre AS `Sede`, j.nombre AS `Nombre`, j.fecha_inicio AS `Inicio`,
                    j.fecha_cierre AS `Cierre`, j.estado AS `Estado`, j.observaciones_cierre AS `Observaciones del cierre`,
                    {$persona('u')} AS `Creada por`
                FROM jornadas_verificacion j
                JOIN instituciones i ON i.id = j.institucion_id
                LEFT JOIN usuarios u ON u.id = j.creada_por
                WHERE j.institucion_id IN (:ids) ORDER BY j.id",
            'verificaciones_resultados' => "SELECT v.id AS `Id`, v.jornada_id AS `Verificación`, b.codigo_identificacion AS `Código del bien`,
                    b.descripcion AS `Bien`, v.resultado AS `Resultado`, v.motivo AS `Motivo`, v.observaciones AS `Observaciones`,
                    {$persona('u')} AS `Verificado por`, IF(v.revisada = 1, 'Sí', 'No') AS `Revisada`, v.created_at AS `Registrado en`
                FROM verificaciones_bienes v
                JOIN jornadas_verificacion j ON j.id = v.jornada_id
                JOIN bienes b ON b.id = v.bien_id
                LEFT JOIN usuarios u ON u.id = v.usuario_id
                WHERE j.institucion_id IN (:ids) ORDER BY v.id",
            'hallazgos' => "SELECT h.id AS `Id`, h.jornada_id AS `Verificación`, e.nombre AS `Espacio`, h.descripcion AS `Descripción`,
                    h.foto_path AS `Foto`, {$persona('r')} AS `Reportado por`, h.estado AS `Estado`,
                    b.codigo_identificacion AS `Bien relacionado`, h.observaciones_resolucion AS `Resolución`,
                    {$persona('rs')} AS `Resuelto por`, h.resuelto_en AS `Resuelto en`, h.created_at AS `Registrado en`
                FROM hallazgos_verificacion h
                LEFT JOIN espacios e ON e.id = h.espacio_id
                LEFT JOIN bienes b ON b.id = h.bien_id
                LEFT JOIN usuarios r ON r.id = h.reportado_por
                LEFT JOIN usuarios rs ON rs.id = h.resuelto_por
                WHERE h.institucion_id IN (:ids) ORDER BY h.id",
            'documentos' => "SELECT 'Factura' AS `Tipo`, f.fecha_factura AS `Fecha`, f.descripcion AS `Descripción`, f.archivo_path AS `Archivo`,
                        f.fecha_registro AS `Registrado en`, f.eliminado_en AS `En la papelera desde`
                    FROM facturas_administrativas f WHERE f.institucion_id IN (:ids)
                UNION ALL
                SELECT 'Cartera recibida', c.fecha_envio,
                        CONCAT_WS(' · ', CONCAT('Solicitada por ', c.nombre_funcionario), c.correo_solicitante, CONCAT('Llegó desde ', c.correo_remitente)),
                        c.archivo_path,
                        c.fecha_registro, c.eliminado_en
                    FROM cartera_envios c WHERE c.institucion_id IN (:ids)
                UNION ALL
                SELECT 'Formato de reintegro', r.fecha_reintegro, r.descripcion, r.archivo_path, r.fecha_registro, r.eliminado_en
                    FROM formatos_reintegro r WHERE r.institucion_id IN (:ids)
                UNION ALL
                SELECT 'Formato de plaqueteo', p.fecha_plaqueteo, CONCAT_WS(' · ', p.descripcion, p.funcionario_asistio), p.archivo_path,
                        p.fecha_registro, p.eliminado_en
                    FROM formatos_plaqueteo p WHERE p.institucion_id IN (:ids)
                ORDER BY 1, 2",
            'auditoria' => "SELECT a.id AS `Id`, a.created_at AS `Fecha`, {$persona('u')} AS `Usuario`, a.accion AS `Acción`,
                    a.entidad AS `Entidad`, a.entidad_id AS `Id de la entidad`, a.datos_antes AS `Datos antes`,
                    a.datos_despues AS `Datos después`, a.ip AS `IP`
                FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.institucion_id IN (:ids) ORDER BY a.id",
        ];
    }

    /**
     * Rutas relativas (carpeta/archivo) de todo lo que la institución subió, sin repetir.
     * Solo se aceptan rutas con el formato que genera Uploader (nada de "../").
     *
     * @return list<string>
     */
    private static function rutasDeArchivos(string $ids): array
    {
        $rutas = [];
        $pdo = Database::connection();
        foreach (self::ARCHIVOS as $carpeta => $consultas) {
            foreach ($consultas as $sql) {
                foreach ($pdo->query(str_replace(':ids', $ids, $sql))->fetchAll(PDO::FETCH_COLUMN) as $ruta) {
                    $ruta = (string) $ruta;
                    if (preg_match('#^' . $carpeta . '/[A-Za-z0-9_-]+\.[a-z0-9]+$#', $ruta)) {
                        $rutas[$ruta] = true;
                    }
                }
            }
        }

        return array_keys($rutas);
    }

    /**
     * @param array<int, array<string, mixed>> $familia
     * @param array<string, int> $resumen
     */
    private static function leeme(array $familia, array $resumen, bool $conArchivos, int $incluidos, int $faltantes, string $generadoPor): string
    {
        $l = [];
        $l[] = 'MIA - Manejo de Inventario de Activos';
        $l[] = 'Información completa de: ' . $familia[0]['nombre'];
        if (count($familia) > 1) {
            $l[] = 'Incluye sus sedes: ' . implode(', ', array_map(static fn (array $i): string => (string) $i['nombre'], array_slice($familia, 1)));
        }
        $l[] = 'Generada el ' . date('d/m/Y \a \l\a\s H:i') . ' por ' . $generadoPor . '.';
        $l[] = '';
        $l[] = 'ARCHIVO informacion.xlsx';
        $l[] = 'Un libro de Excel con una hoja por tipo de información (se abre con Excel o LibreOffice):';
        foreach ($resumen as $hoja => $filas) {
            $l[] = sprintf('  - %s: %d %s', $hoja, $filas, $filas === 1 ? 'fila' : 'filas');
        }
        $l[] = '';
        if ($conArchivos) {
            $l[] = 'CARPETA archivos/';
            $l[] = "Fotos y documentos subidos a MIA ({$incluidos}), en la misma ruta que indican las columnas";
            $l[] = '"Foto", "Factura" o "Archivo" del libro de Excel.';
            if ($faltantes > 0) {
                $l[] = "Hay {$faltantes} archivo(s) registrados que ya no estaban en el servidor y no se incluyeron.";
            }
        } else {
            $l[] = 'Esta descarga NO incluye las fotos ni los documentos. Para tenerlos, descarga la opción';
            $l[] = '"con fotos y documentos" desde Reportes.';
        }
        $l[] = '';
        $l[] = 'No se incluyen contraseñas ni claves de seguridad de los usuarios.';
        $l[] = 'Contiene datos personales: guárdala en un lugar seguro (Ley 1581 de 2012).';

        return implode("\r\n", $l) . "\r\n";
    }

    /** PHP 8.4+ tiene la constante en Pdo\Mysql; la de PDO queda obsoleta desde PHP 8.5. */
    private static function atributoBufer(): int
    {
        return \defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            ? (int) \constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
    }

    private static function borrarCarpeta(string $carpeta): void
    {
        foreach (glob($carpeta . '/*') ?: [] as $archivo) {
            @unlink($archivo);
        }
        @rmdir($carpeta);
    }

    private static function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u']);
    }
}
