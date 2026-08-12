-- Paso 1 de 2 de la migración "categorías por institución". institucion_id queda
-- NULLABLE aquí a propósito: las categorías existentes (compartidas hoy por todo el
-- sistema) todavía no tienen dueño. El script database/actualizar_categorias_por_
-- institucion.php debe correrse DESPUÉS de esta migración y ANTES de la migración
-- 022 (que sí exige NOT NULL) — reasigna cada categoría real a su institución y
-- descarta las que no estén en uso real, dejando la tabla lista para el paso 2.
ALTER TABLE categorias_bienes
    ADD COLUMN institucion_id INT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_categoria_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id);

ALTER TABLE categorias_bienes DROP INDEX nombre;
ALTER TABLE categorias_bienes ADD UNIQUE KEY uq_categoria_institucion_nombre (institucion_id, nombre);
