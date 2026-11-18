<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Regla de negocio que impidió una acción, con lo que el usuario debe hacer para lograrla.
 * El mensaje (getMessage) es el porqué; queHacer y accion completan el aviso (Aviso::desdeExcepcion).
 * Extiende DomainException: los controladores que ya atrapan DomainException la siguen
 * atrapando igual.
 *
 * Ej.: throw new ErrorGuiado('El bien 200388980 está en cartera; solo los bienes "Sin cartera" se dan de baja.',
 *          'Reintégralo o cámbiale la categoría a "Sin cartera".', Aviso::accion('Abrir el bien', '/bienes/7/editar'));
 */
final class ErrorGuiado extends \DomainException
{
    /**
     * @param array{texto: string, url: string}|null $accion
     * @param array<string, string> $campos campo del formulario => mensaje corto debajo de él
     */
    public function __construct(
        string $porQue,
        public readonly string $queHacer,
        public readonly ?array $accion = null,
        public readonly array $campos = [],
    ) {
        parent::__construct($porQue);
    }
}
