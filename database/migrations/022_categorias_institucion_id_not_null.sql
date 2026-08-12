-- Paso 3 de 3: correr SOLO después de database/actualizar_categorias_por_institucion.php
-- (ese script debe dejar la tabla sin ninguna fila con institucion_id NULL antes de
-- aplicar esta migración, o fallará).
ALTER TABLE categorias_bienes DROP FOREIGN KEY fk_categoria_institucion;
ALTER TABLE categorias_bienes MODIFY COLUMN institucion_id INT UNSIGNED NOT NULL;
ALTER TABLE categorias_bienes ADD CONSTRAINT fk_categoria_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id);
