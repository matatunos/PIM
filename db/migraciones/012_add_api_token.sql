-- Migración: Añadir API token para extensiones y aplicaciones externas
-- Fecha: 2026-01-30

-- Añadir campo api_token a usuarios
ALTER TABLE usuarios 
ADD COLUMN api_token VARCHAR(64) NULL COMMENT 'Token API para extensiones y apps externas' AFTER backup_codes,
ADD INDEX idx_api_token (api_token);

-- Generar tokens para usuarios existentes
UPDATE usuarios 
SET api_token = SHA2(CONCAT(id, username, email, RAND(), NOW()), 256) 
WHERE api_token IS NULL;
