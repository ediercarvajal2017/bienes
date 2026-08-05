<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Traduce el antes/después crudo de la auditoría (columnas de BD tal cual las guarda
 * cada controlador) a algo legible para alguien que no conoce el esquema: etiquetas en
 * español, IDs de catálogo resueltos a su nombre (vía $mapas, resuelto una sola vez por
 * página en AuditoriaController, no por fila) y nunca expone datos sensibles.
 */
final class AuditoriaFormato
{
    private const ETIQUETAS = [
        'documento' => 'Documento',
        'nombres' => 'Nombres',
        'apellidos' => 'Apellidos',
        'email' => 'Correo',
        'cargo_id' => 'Cargo',
        'rol_id' => 'Rol',
        'institucion_id' => 'Institución',
        'institucion_padre_id' => 'Institución principal',
        'codigo_dane' => 'Código DANE',
        'direccion' => 'Dirección',
        'tipo_sede' => 'Tipo de sede',
        'email_institucional' => 'Correo institucional',
        'nombre' => 'Nombre',
        'codigo' => 'Código / número',
        'responsables' => 'Responsables',
        'codigo_identificacion' => 'Código de identificación',
        'descripcion' => 'Descripción',
        'marca' => 'Marca',
        'categoria_id' => 'Categoría',
        'fecha_ingreso' => 'Fecha de ingreso',
        'valor' => 'Valor',
        'tiene_factura' => 'Tiene factura',
        'estado' => 'Estado',
        'fecha_factura' => 'Fecha de la factura',
        'fecha_reintegro' => 'Fecha de reintegro',
        'fecha_plaqueteo' => 'Fecha de plaqueteo',
        'funcionario_asistio' => 'Funcionario que asistió',
        'fecha_envio' => 'Fecha de envío',
        'correo_remitente' => 'Correo remitente',
        'nombre_funcionario' => 'Funcionario',
        'archivo_path' => 'Archivo adjunto',
        'registrado_por' => 'Registrado por',
        'created_by' => 'Creado por',
    ];

    /** Nunca se muestran, aunque vengan en el snapshot: internos o sin valor informativo para el lector. */
    private const OCULTOS = ['password_hash', 'id', 'qr_token', 'foto_path', 'factura_pdf_path', 'logo_path'];

    private const ESTADOS_BIEN = [
        'activo' => 'Activo',
        'reintegrado' => 'Reintegrado',
        'en_reparacion' => 'En reparación',
        'dado_de_baja' => 'Dado de baja',
    ];

    private const TIPOS_SEDE = ['principal' => 'Principal', 'seccion' => 'Sección'];

    public static function etiqueta(string $campo): string
    {
        return self::ETIQUETAS[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    }

    /**
     * Para "editar" (ambos lados no nulos) solo devuelve los campos que de verdad
     * cambiaron. Para "crear"/"eliminar" (un lado es null porque no aplica) devuelve
     * todos los campos del lado que sí existe.
     */
    public static function resumen(?array $antes, ?array $despues, array $mapas): array
    {
        $campos = array_unique(array_merge(array_keys($antes ?? []), array_keys($despues ?? [])));
        $filas = [];

        foreach ($campos as $campo) {
            if (in_array($campo, self::OCULTOS, true)) {
                continue;
            }

            $valorAntes = $antes[$campo] ?? null;
            $valorDespues = $despues[$campo] ?? null;

            if ($antes !== null && $despues !== null && $valorAntes === $valorDespues) {
                continue;
            }

            $filas[] = [
                'etiqueta' => self::etiqueta($campo),
                'antes' => $antes !== null ? self::valorLegible($campo, $valorAntes, $mapas) : null,
                'despues' => $despues !== null ? self::valorLegible($campo, $valorDespues, $mapas) : null,
            ];
        }

        return $filas;
    }

    private static function valorLegible(string $campo, mixed $valor, array $mapas): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if ($campo === 'responsables' && is_array($valor)) {
            $nombres = array_map(
                static fn ($id) => $mapas['responsables'][(int) $id] ?? "#{$id}",
                $valor
            );

            return $nombres !== [] ? implode(', ', $nombres) : '—';
        }

        if (isset($mapas[$campo]) && is_scalar($valor)) {
            return $mapas[$campo][(int) $valor] ?? "#{$valor}";
        }

        if ($campo === 'estado') {
            return self::ESTADOS_BIEN[$valor] ?? (string) $valor;
        }

        if ($campo === 'tipo_sede') {
            return self::TIPOS_SEDE[$valor] ?? (string) $valor;
        }

        if ($campo === 'tiene_factura') {
            return ((int) $valor) === 1 ? 'Sí' : 'No';
        }

        if ($campo === 'valor' && is_numeric($valor)) {
            return '$' . number_format((float) $valor, 0, ',', '.');
        }

        return (string) $valor;
    }
}
