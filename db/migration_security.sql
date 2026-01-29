-- Migración de seguridad para PIM v2.5.0
-- Ejecutar: mysql -u root -p pim_db < migration_security.sql

-- Tabla de logs de seguridad
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

-- Tabla para sesiones activas del usuario (permite cerrar sesiones remotamente)
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

-- Agregar campo para intentos de login fallidos en usuarios
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0 AFTER backup_codes,
ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL AFTER login_attempts;

-- Agregar campo para última contraseña cambiada
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP NULL AFTER locked_until;
