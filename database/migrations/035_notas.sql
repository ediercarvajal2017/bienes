-- Notas rápidas (como las notas adhesivas de Windows): cada usuario anota sus pendientes
-- desde el ícono de la barra superior. Son privadas: solo las ve y edita quien las
-- escribe (App\Models\Nota filtra siempre por usuario_id).
--
-- Aditiva e idempotente: solo crea una tabla nueva. Ningún dato existente cambia. Al
-- eliminar un usuario se borran sus notas (ON DELETE CASCADE).
CREATE TABLE IF NOT EXISTS notas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    texto TEXT NOT NULL,
    color ENUM('amarillo', 'verde', 'rosado', 'azul', 'morado') NOT NULL DEFAULT 'amarillo',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notas_usuario (usuario_id, updated_at),
    CONSTRAINT fk_nota_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
