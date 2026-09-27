-- Ciclo de vida de las bajas: pendiente → aprobada | rechazada. Antes solo existía el
-- indicador "aprobada" (0/1) y RECHAZAR BORRABA el reporte (se perdía el historial).
--
-- Aditiva e idempotente (patrón expandir → migrar → contraer): agrega columnas solo si no
-- existen y completa "estado" a partir de "aprobada". La columna "aprobada" SE CONSERVA
-- (el código la mantiene sincronizada) y se podrá quitar en una versión posterior.
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bajas_bienes' AND COLUMN_NAME = 'estado');
SET @sql := IF(@existe = 0, 'ALTER TABLE bajas_bienes ADD COLUMN estado ENUM(''pendiente'',''aprobada'',''rechazada'') NOT NULL DEFAULT ''pendiente'' AFTER aprobada', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bajas_bienes' AND COLUMN_NAME = 'motivo_rechazo');
SET @sql := IF(@existe = 0, 'ALTER TABLE bajas_bienes ADD COLUMN motivo_rechazo VARCHAR(500) NULL AFTER estado', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bajas_bienes' AND COLUMN_NAME = 'resuelta_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE bajas_bienes ADD COLUMN resuelta_por INT UNSIGNED NULL AFTER motivo_rechazo, ADD COLUMN resuelta_en DATETIME NULL AFTER resuelta_por', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
UPDATE bajas_bienes SET estado = 'aprobada' WHERE aprobada = 1 AND estado = 'pendiente';
