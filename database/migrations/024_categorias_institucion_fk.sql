-- Termina lo que 022_categorias_institucion_id_not_null.sql dejó a medias en produccion:
-- sus dos primeros pasos (quitar la NOT NULL vieja, convertir la columna) si se aplicaron,
-- pero el tercero (esta linea) fallo porque habia categorias huerfanas con
-- institucion_id = 0 -- ver database/corregir_categorias_huerfanas.php, que debe correrse
-- ANTES de esta migracion (o esta migracion tambien va a fallar).
ALTER TABLE categorias_bienes ADD CONSTRAINT fk_categoria_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id);
