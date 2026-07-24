CREATE TABLE espacio_responsables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    espacio_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_espacio_responsable (espacio_id, usuario_id),
    CONSTRAINT fk_espacio_responsable_espacio FOREIGN KEY (espacio_id) REFERENCES espacios(id) ON DELETE CASCADE,
    CONSTRAINT fk_espacio_responsable_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Migra el único responsable que pudiera tener cada espacio antes de retirar la columna.
INSERT INTO espacio_responsables (espacio_id, usuario_id)
SELECT id, responsable_usuario_id FROM espacios WHERE responsable_usuario_id IS NOT NULL;

ALTER TABLE espacios DROP FOREIGN KEY fk_espacio_responsable;
ALTER TABLE espacios DROP COLUMN responsable_usuario_id;

-- El responsable de un bien pasa a ser el espacio; ya no se elige una persona al asignar.
ALTER TABLE asignaciones MODIFY COLUMN usuario_responsable_id INT UNSIGNED NULL;

-- Registra de qué espacio salió un bien al reintegrarse (reemplaza a usuario_anterior_id).
ALTER TABLE movimientos ADD COLUMN espacio_origen_id INT UNSIGNED NULL AFTER espacio_destino_id;
ALTER TABLE movimientos ADD CONSTRAINT fk_movimiento_espacio_origen FOREIGN KEY (espacio_origen_id) REFERENCES espacios(id);
