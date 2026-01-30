-- Migración: Sistema de Webhooks y Automatizaciones
-- Fecha: 2026-01-30

-- Tabla de webhooks
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    evento VARCHAR(50) NOT NULL COMMENT 'nota_creada, contacto_creado, tarea_completada, etc',
    metodo ENUM('GET', 'POST', 'PUT', 'DELETE') DEFAULT 'POST',
    headers TEXT NULL COMMENT 'JSON con headers personalizados',
    activo BOOLEAN DEFAULT 1,
    secret VARCHAR(255) NULL COMMENT 'Secret para firmar requests',
    ultima_ejecucion TIMESTAMP NULL,
    total_ejecuciones INT DEFAULT 0,
    total_errores INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_evento (usuario_id, evento),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de automatizaciones
CREATE TABLE IF NOT EXISTS automatizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    disparador VARCHAR(50) NOT NULL COMMENT 'nota_creada, contacto_creado, cron, etc',
    condiciones TEXT NULL COMMENT 'JSON con condiciones (campo, operador, valor)',
    acciones TEXT NOT NULL COMMENT 'JSON con array de acciones a ejecutar',
    cron_expression VARCHAR(100) NULL COMMENT 'Expresión cron si disparador es programado',
    activo BOOLEAN DEFAULT 1,
    ultima_ejecucion TIMESTAMP NULL,
    total_ejecuciones INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_disparador (usuario_id, disparador),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de webhooks
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    evento VARCHAR(50) NOT NULL,
    request_url VARCHAR(500) NOT NULL,
    request_method VARCHAR(10) NOT NULL,
    request_headers TEXT NULL,
    request_body TEXT NULL,
    response_code INT NULL,
    response_body TEXT NULL,
    error TEXT NULL,
    duracion_ms INT NULL COMMENT 'Duración en milisegundos',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_fecha (webhook_id, fecha),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de automatizaciones
CREATE TABLE IF NOT EXISTS automatizacion_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    automatizacion_id INT NOT NULL,
    disparador VARCHAR(50) NOT NULL,
    entidad_id INT NULL COMMENT 'ID de la nota/contacto/tarea que disparó',
    condiciones_cumplidas BOOLEAN DEFAULT 0,
    acciones_ejecutadas TEXT NULL COMMENT 'JSON con resultado de cada acción',
    exito BOOLEAN DEFAULT 1,
    error TEXT NULL,
    duracion_ms INT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (automatizacion_id) REFERENCES automatizaciones(id) ON DELETE CASCADE,
    INDEX idx_automatizacion_fecha (automatizacion_id, fecha),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de eventos disponibles (para referencia)
CREATE TABLE IF NOT EXISTS eventos_disponibles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    categoria VARCHAR(50) NOT NULL COMMENT 'notas, contactos, tareas, calendario, sistema',
    payload_ejemplo TEXT NULL COMMENT 'JSON de ejemplo del payload',
    activo BOOLEAN DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar eventos disponibles
INSERT INTO eventos_disponibles (codigo, nombre, descripcion, categoria, payload_ejemplo) VALUES
('nota_creada', 'Nota creada', 'Se dispara cuando se crea una nueva nota', 'notas', '{"id": 123, "titulo": "Mi nota", "color": "amarillo", "etiquetas": ["tag1"]}'),
('nota_modificada', 'Nota modificada', 'Se dispara cuando se modifica una nota', 'notas', '{"id": 123, "titulo": "Mi nota actualizada"}'),
('nota_eliminada', 'Nota eliminada', 'Se dispara cuando se mueve una nota a papelera', 'notas', '{"id": 123, "titulo": "Nota eliminada"}'),
('contacto_creado', 'Contacto creado', 'Se dispara cuando se crea un contacto', 'contactos', '{"id": 456, "nombre": "Juan Pérez", "email": "juan@example.com"}'),
('contacto_modificado', 'Contacto modificado', 'Se dispara cuando se modifica un contacto', 'contactos', '{"id": 456, "nombre": "Juan Pérez García"}'),
('contacto_eliminado', 'Contacto eliminado', 'Se dispara cuando se elimina un contacto', 'contactos', '{"id": 456, "nombre": "Juan Pérez"}'),
('tarea_creada', 'Tarea creada', 'Se dispara cuando se crea una tarea', 'tareas', '{"id": 789, "titulo": "Nueva tarea", "prioridad": "alta"}'),
('tarea_completada', 'Tarea completada', 'Se dispara cuando se completa una tarea', 'tareas', '{"id": 789, "titulo": "Tarea completada"}'),
('tarea_modificada', 'Tarea modificada', 'Se dispara cuando se modifica una tarea', 'tareas', '{"id": 789, "estado": "en_progreso"}'),
('evento_creado', 'Evento creado', 'Se dispara cuando se crea un evento de calendario', 'calendario', '{"id": 111, "titulo": "Reunión", "fecha": "2026-02-01"}'),
('evento_modificado', 'Evento modificado', 'Se dispara cuando se modifica un evento', 'calendario', '{"id": 111, "titulo": "Reunión actualizada"}'),
('link_creado', 'Link guardado', 'Se dispara cuando se guarda un link', 'links', '{"id": 222, "url": "https://example.com", "titulo": "Ejemplo"}'),
('archivo_subido', 'Archivo subido', 'Se dispara cuando se sube un archivo', 'archivos', '{"id": 333, "nombre": "documento.pdf", "tamano": 1024000}'),
('usuario_login', 'Usuario inició sesión', 'Se dispara cuando un usuario inicia sesión', 'sistema', '{"usuario_id": 1, "username": "admin", "ip": "192.168.1.1"}'),
('cron_diario', 'Programado - Diario', 'Disparador programado que se ejecuta diariamente', 'sistema', '{"fecha": "2026-01-30", "hora": "00:00:00"}');
