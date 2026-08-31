-- Búsqueda de bienes por foto: guarda la "huella visual" (vector de características,
-- ~1000 números) que el navegador calcula a partir de la foto del bien usando un modelo
-- de reconocimiento de imágenes (MobileNet vía TensorFlow.js) que corre en el propio
-- celular del usuario -- el servidor nunca procesa la imagen, solo compara estos números.
-- NULL significa "sin indexar todavía" (bien sin foto, o foto pendiente de procesar).
ALTER TABLE bienes
    ADD COLUMN foto_vector MEDIUMTEXT NULL AFTER foto_path;
