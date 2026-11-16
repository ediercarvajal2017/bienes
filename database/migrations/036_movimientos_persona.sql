-- Responsabilidad Individual (bien a cargo de una persona) además de la Grupal (bien en un
-- espacio, del que responden sus responsables). La persona ya se guarda en la columna
-- existente asignaciones.usuario_responsable_id (nula y sin uso desde la migración 005);
-- aquí solo se agrega a los movimientos de dónde y a dónde pasó el bien cuando la
-- responsabilidad era o es individual, para que el historial diga "de Ana a Luis".
--
-- Aditiva e idempotente: dos columnas vacías. Ningún dato existente cambia.
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'persona_origen_id');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE movimientos ADD COLUMN persona_origen_id INT UNSIGNED NULL AFTER espacio_origen_id, ADD CONSTRAINT fk_movimiento_persona_origen FOREIGN KEY (persona_origen_id) REFERENCES usuarios(id)', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'persona_destino_id');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE movimientos ADD COLUMN persona_destino_id INT UNSIGNED NULL AFTER espacio_destino_id, ADD CONSTRAINT fk_movimiento_persona_destino FOREIGN KEY (persona_destino_id) REFERENCES usuarios(id)', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;
