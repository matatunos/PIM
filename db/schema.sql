-- Esquema completo y corregido para PIM (Personal Information Manager)

DROP DATABASE IF EXISTS pim_db;
CREATE DATABASE pim_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pim_db;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    nombre_completo VARCHAR(150),
    avatar VARCHAR(255),
    tema VARCHAR(20) DEFAULT 'light',
    rol ENUM('admin','user') DEFAULT 'user',
    totp_secret VARCHAR(32) NULL COMMENT 'Secreto TOTP para 2FA',
    totp_enabled BOOLEAN DEFAULT 0 COMMENT 'Si el 2FA está habilitado',
    backup_codes TEXT NULL COMMENT 'Códigos de respaldo en JSON',
    activo BOOLEAN DEFAULT 1 COMMENT 'Usuario activo o desactivado',
    ultimo_acceso TIMESTAMP NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acceso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    ip VARCHAR(45),
    ip_address VARCHAR(45) NULL COMMENT 'IP del cliente',
    user_agent TEXT NULL COMMENT 'User agent del navegador',
    accion VARCHAR(100),
    tipo_evento VARCHAR(50) NULL COMMENT 'Tipo de evento (login, logout, etc)',
    descripcion TEXT NULL COMMENT 'Descripción detallada del evento',
    exitoso BOOLEAN DEFAULT 1,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_exitoso (exitoso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100),
    email VARCHAR(100),
    telefono VARCHAR(30),
    telefono_alt VARCHAR(30),
    direccion TEXT,
    ciudad VARCHAR(100),
    pais VARCHAR(100),
    empresa VARCHAR(100),
    cargo VARCHAR(100),
    notas TEXT,
    favorito BOOLEAN DEFAULT 0,
    avatar_color VARCHAR(7) DEFAULT '#a8dadc',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_nombre (nombre, apellido),
    INDEX idx_favorito (favorito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#a8dadc',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_etiqueta (usuario_id, nombre),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255),
    contenido TEXT NOT NULL,
    color VARCHAR(7) DEFAULT '#fff9e6',
    fijada BOOLEAN DEFAULT 0,
    archivada BOOLEAN DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fijada (fijada),
    INDEX idx_archivada (archivada),
    FULLTEXT idx_busqueda (titulo, contenido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nota_etiqueta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nota_id INT NOT NULL,
    etiqueta_id INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_id) REFERENCES notas(id) ON DELETE CASCADE,
    FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_nota_etiqueta (nota_id, etiqueta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME,
    hora_inicio TIME NULL COMMENT 'Hora de inicio del evento',
    hora_fin TIME NULL COMMENT 'Hora de fin del evento',
    todo_el_dia BOOLEAN DEFAULT 0 COMMENT 'Evento de todo el día',
    ubicacion VARCHAR(255) NULL COMMENT 'Lugar del evento',
    color VARCHAR(7) DEFAULT '#a8dadc' COMMENT 'Color del evento en el calendario',
    recordatorio_minutos INT DEFAULT 15,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha_inicio (fecha_inicio),
    INDEX idx_fecha_fin (fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    completada BOOLEAN DEFAULT 0,
    prioridad ENUM('baja','media','alta','urgente') DEFAULT 'media',
    fecha_vencimiento DATE,
    lista VARCHAR(100) DEFAULT 'General',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_completada (completada),
    INDEX idx_prioridad (prioridad),
    INDEX idx_lista (lista)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100),
    extension VARCHAR(10),
    tamano INT COMMENT 'Tamaño en bytes',
    categoria VARCHAR(50),
    descripcion TEXT,
    descargas INT DEFAULT 0 COMMENT 'Contador de descargas',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de subida del archivo',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_tipo (tipo_mime),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE archivo_etiqueta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    archivo_id INT NOT NULL,
    etiqueta_id INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
    FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_archivo_etiqueta (archivo_id, etiqueta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'link',
    categoria VARCHAR(100) DEFAULT 'General',
    color VARCHAR(7) DEFAULT '#a8dadc',
    favorito BOOLEAN DEFAULT 0,
    orden INT DEFAULT 0,
    visitas INT DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_categoria (categoria),
    INDEX idx_favorito (favorito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE link_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'fa-folder',
    color VARCHAR(7) DEFAULT '#a8dadc',
    orden INT DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_categoria (usuario_id, nombre),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('evento','tarea','recordatorio') DEFAULT 'evento',
    referencia_id INT NOT NULL COMMENT 'ID del evento o tarea',
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    visto BOOLEAN DEFAULT 0,
    leido_en TIMESTAMP NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_visto (visto),
    INDEX idx_fecha_envio (fecha_envio),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario administrador por defecto (password: password)
INSERT INTO usuarios (username, email, password, nombre_completo, rol) 
VALUES ('admin', 'admin@pim.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin');

-- Usuario demo (password: demo)
INSERT INTO usuarios (username, email, password, nombre_completo, rol) 
VALUES ('demo', 'demo@pim.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Usuario Demo', 'user');

-- ==========================================
-- SEGURIDAD AVANZADA - Tabla de logs de seguridad
-- ==========================================
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL COMMENT 'LOGIN_FAIL, CSRF_FAIL, SQLI_ATTEMPT, XSS_ATTEMPT, RATE_LIMIT, etc',
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500),
    message TEXT,
    uri VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_ip (ip_address),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para bloqueo de IPs
CREATE TABLE IF NOT EXISTS ip_blocklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    blocked_until TIMESTAMP NULL COMMENT 'NULL = permanente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para sesiones activas del usuario
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ==========================================
-- OPEN WEBUI INTEGRATION - Configuración
-- ==========================================
CREATE TABLE IF NOT EXISTS configuracion_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo VARCHAR(50) DEFAULT 'string' COMMENT 'string, int, bool, json',
    descripcion TEXT,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserciones de configuración predeterminada
INSERT INTO configuracion_ia (clave, valor, tipo, descripcion) VALUES
('openwebui_host', '192.168.1.19', 'string', 'Host/IP de Open WebUI (ej: 192.168.1.19)'),
('openwebui_port', '3000', 'int', 'Puerto de Open WebUI (ej: 3000, 8000, 11434)'),
('sync_interval_minutes', '5', 'int', 'Intervalo de sincronización en minutos'),
('sync_enabled', '0', 'bool', 'Si la sincronización automática está habilitada (0=deshabilitado, 1=habilitado)'),
('sync_documents', '1', 'bool', 'Sincronizar documentos/archivos (0=no, 1=sí)'),
('sync_notes', '1', 'bool', 'Sincronizar notas (0=no, 1=sí)')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- ==========================================
-- OPEN WEBUI INTEGRATION - Sesiones de Chat
-- ==========================================
CREATE TABLE IF NOT EXISTS chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255),
    resumen TEXT COMMENT 'Resumen breve de la conversación',
    modelo VARCHAR(100) COMMENT 'Modelo de IA usado (ej: llama2, mistral)',
    tokens_utilizados INT DEFAULT 0 COMMENT 'Tokens consumidos en la sesión',
    activo BOOLEAN DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_activo (activo),
    INDEX idx_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- OPEN WEBUI INTEGRATION - Historial de Sincronización
-- ==========================================
CREATE TABLE IF NOT EXISTS sync_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('documento','nota') DEFAULT 'documento',
    origen_id INT COMMENT 'ID del documento/nota en PIM',
    status ENUM('success','failed','pending') DEFAULT 'pending',
    mensaje TEXT,
    documentos_procesados INT DEFAULT 0,
    errores_count INT DEFAULT 0,
    duracion_segundos FLOAT DEFAULT 0,
    sincronizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_status (status),
    INDEX idx_sincronizado (sincronizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;