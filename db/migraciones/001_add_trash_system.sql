-- Migración: Agregar soporte para papelera (borrado blando)
-- Fecha: 2026-01-29

-- Agregar columna borrado_en a tabla notas
ALTER TABLE notas ADD COLUMN borrado_en TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha cuando fue movido a papelera' AFTER actualizado_en;
ALTER TABLE notas ADD INDEX idx_borrado (borrado_en);

-- Agregar columna borrado_en a tabla tareas
ALTER TABLE tareas ADD COLUMN borrado_en TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha cuando fue movido a papelera' AFTER actualizado_en;
ALTER TABLE tareas ADD INDEX idx_borrado (borrado_en);

-- Agregar columna borrado_en a tabla eventos
ALTER TABLE eventos ADD COLUMN borrado_en TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha cuando fue movido a papelera' AFTER actualizado_en;
ALTER TABLE eventos ADD INDEX idx_borrado (borrado_en);

-- Agregar columna borrado_en a tabla contactos
ALTER TABLE contactos ADD COLUMN borrado_en TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha cuando fue movido a papelera' AFTER actualizado_en;
ALTER TABLE contactos ADD INDEX idx_borrado (borrado_en);

-- Crear tabla para auditoría de borrados
CREATE TABLE IF NOT EXISTS papelera_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL COMMENT 'notas, tareas, eventos, contactos',
    item_id INT NOT NULL COMMENT 'ID del item borrado',
    nombre VARCHAR(255) NULL COMMENT 'Nombre/título del item',
    borrado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    restaurado_en TIMESTAMP NULL,
    permanentemente_eliminado_en TIMESTAMP NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_tipo (tipo),
    INDEX idx_borrado (borrado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
