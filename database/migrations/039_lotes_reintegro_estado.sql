-- Lotes de reintegro con estado y trazabilidad de las unificaciones.
--
--   pendiente   -> el lote todavía no se ha entregado a la Alcaldía (estado inicial).
--   reintegrado -> el usuario confirmó que ya lo entregó (fecha y nota de la entrega);
--                  queda bloqueado: no se unifica ni se mezcla con reintegros nuevos.
--   unificado   -> sus bienes pasaron a lotes nuevos (uno por categoría) al unificarlo;
--                  lotes_reintegro_unificaciones dice a cuáles.
--
-- Aditiva e idempotente: solo agrega columnas, un índice y una tabla. Los lotes que ya
-- existen quedan 'pendiente' y ningún dato cambia; el usuario marca después, desde la
-- pantalla, los que ya entregó.

-- Un solo ALTER (atómico): o se agregan todas las columnas o ninguna.
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes_reintegro' AND COLUMN_NAME = 'estado');
SET @sql := IF(@col_existe = 0,
    'ALTER TABLE lotes_reintegro
        ADD COLUMN estado ENUM(''pendiente'',''unificado'',''reintegrado'') NOT NULL DEFAULT ''pendiente'' AFTER observaciones,
        ADD COLUMN reintegrado_fecha DATE NULL AFTER estado,
        ADD COLUMN reintegrado_nota VARCHAR(500) NULL AFTER reintegrado_fecha,
        ADD COLUMN reintegrado_por INT UNSIGNED NULL AFTER reintegrado_nota,
        ADD COLUMN reintegrado_registrado_en DATETIME NULL AFTER reintegrado_por,
        ADD COLUMN unificado_por INT UNSIGNED NULL AFTER reintegrado_registrado_en,
        ADD COLUMN unificado_en DATETIME NULL AFTER unificado_por,
        ADD CONSTRAINT fk_lote_reintegrado_por FOREIGN KEY (reintegrado_por) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_lote_unificado_por FOREIGN KEY (unificado_por) REFERENCES usuarios(id)',
    'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- La pantalla de lotes filtra por institución y estado, y ordena por fecha.
SET @idx_existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes_reintegro' AND INDEX_NAME = 'idx_lotes_reintegro_estado');
SET @sql := IF(@idx_existe = 0,
    'CREATE INDEX idx_lotes_reintegro_estado ON lotes_reintegro (institucion_id, estado, fecha)',
    'DO 0');
PREPARE stmt_idx FROM @sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- De qué lotes salió cada lote nuevo al unificar (un lote mixto puede repartirse en
-- varios lotes, uno por categoría, y un lote nuevo puede reunir varios de origen).
CREATE TABLE IF NOT EXISTS lotes_reintegro_unificaciones (
    lote_origen_id INT UNSIGNED NOT NULL,
    lote_destino_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lote_origen_id, lote_destino_id),
    KEY idx_lotes_unificaciones_destino (lote_destino_id),
    CONSTRAINT fk_lote_unificacion_origen FOREIGN KEY (lote_origen_id) REFERENCES lotes_reintegro(id) ON DELETE CASCADE,
    CONSTRAINT fk_lote_unificacion_destino FOREIGN KEY (lote_destino_id) REFERENCES lotes_reintegro(id) ON DELETE CASCADE
) ENGINE=InnoDB;
