-- =====================================================
-- MIGRACIÓN: Mejoras de Rendimiento
-- Fecha: 2026-01-30
-- Descripción: Añade índices para optimizar consultas
-- =====================================================

USE pim_db;

-- =====================================================
-- ÍNDICES EN NOTAS
-- =====================================================

-- Búsquedas por usuario y fecha (usar creado_en, no fecha_creacion)
ALTER TABLE notas 
ADD INDEX IF NOT EXISTS idx_usuario_fecha (usuario_id, creado_en DESC);

-- Búsquedas por usuario y color (vista filtrada)
ALTER TABLE notas 
ADD INDEX IF NOT EXISTS idx_usuario_color (usuario_id, color);

-- Notas fijadas del usuario
ALTER TABLE notas 
ADD INDEX IF NOT EXISTS idx_usuario_fijada_usuario (usuario_id, fijada);

-- Fulltext para búsqueda en título y contenido
-- ALTER TABLE notas 
-- ADD FULLTEXT INDEX ft_titulo_contenido (titulo, contenido);

-- =====================================================
-- ÍNDICES EN CONTACTOS
-- =====================================================

-- Búsquedas por usuario
ALTER TABLE contactos 
ADD INDEX IF NOT EXISTS idx_usuario_nombre_contacto (usuario_id, nombre);

-- Contactos favoritos (si existe columna favorito)
-- ALTER TABLE contactos 
-- ADD INDEX IF NOT EXISTS idx_usuario_favorito (usuario_id, favorito);

-- Búsqueda por email
ALTER TABLE contactos 
ADD INDEX IF NOT EXISTS idx_email_contacto (email);

-- =====================================================
-- ÍNDICES EN TAREAS
-- =====================================================

-- Tareas por usuario y completada
ALTER TABLE tareas 
ADD INDEX IF NOT EXISTS idx_usuario_completada (usuario_id, completada);

-- Tareas por usuario y prioridad
ALTER TABLE tareas 
ADD INDEX IF NOT EXISTS idx_usuario_prioridad_tarea (usuario_id, prioridad);

-- Tareas por fecha de vencimiento
ALTER TABLE tareas 
ADD INDEX IF NOT EXISTS idx_usuario_fecha_vence (usuario_id, fecha_vencimiento);

-- Tareas por lista
ALTER TABLE tareas 
ADD INDEX IF NOT EXISTS idx_usuario_lista (usuario_id, lista);

-- =====================================================
-- ÍNDICES EN CALENDARIO
-- =====================================================

-- Eventos por usuario y fecha
ALTER TABLE eventos 
ADD INDEX IF NOT EXISTS idx_usuario_fecha_evento (usuario_id, fecha_inicio);

-- Eventos por rango de fechas
ALTER TABLE eventos 
ADD INDEX IF NOT EXISTS idx_fecha_rango (fecha_inicio, fecha_fin);

-- =====================================================
-- ÍNDICES EN LINKS
-- =====================================================

-- Links por usuario y fecha (usar creado_en)
ALTER TABLE links 
ADD INDEX IF NOT EXISTS idx_usuario_fecha_link (usuario_id, creado_en DESC);

-- Links por categoría
ALTER TABLE links 
ADD INDEX IF NOT EXISTS idx_usuario_categoria (usuario_id, categoria);

-- =====================================================
-- ÍNDICES EN ARCHIVOS
-- =====================================================

-- Archivos por usuario y fecha (usar fecha_subida)
ALTER TABLE archivos 
ADD INDEX IF NOT EXISTS idx_usuario_fecha_archivo (usuario_id, fecha_subida DESC);

-- Archivos por tipo_mime
ALTER TABLE archivos 
ADD INDEX IF NOT EXISTS idx_usuario_tipo_mime (usuario_id, tipo_mime);

-- =====================================================
-- ÍNDICES EN ETIQUETAS
-- =====================================================

-- Relación nota-etiqueta optimizada
ALTER TABLE nota_etiqueta 
ADD INDEX IF NOT EXISTS idx_etiqueta_nota_rel (etiqueta_id, nota_id);

-- =====================================================
-- ÍNDICES EN BÚSQUEDAS
-- =====================================================

-- Búsquedas recientes del usuario (si existe tabla)
-- ALTER TABLE busquedas 
-- ADD INDEX IF NOT EXISTS idx_usuario_fecha_busqueda (usuario_id, fecha DESC);

-- =====================================================
-- ÍNDICES EN LOGS Y AUDITORÍA
-- =====================================================

-- Logs por usuario y fecha (si existe tabla logs)
-- ALTER TABLE logs 
-- ADD INDEX IF NOT EXISTS idx_usuario_fecha_log (usuario_id, fecha DESC);

-- Auditoría por usuario (si existe tabla auditoria)
-- ALTER TABLE auditoria 
-- ADD INDEX IF NOT EXISTS idx_usuario_fecha_auditoria (usuario_id, fecha DESC);

-- Auditoría por tabla y acción
-- ALTER TABLE auditoria 
-- ADD INDEX IF NOT EXISTS idx_tabla_accion (tabla, accion);

-- =====================================================
-- ÍNDICES EN WEBHOOKS Y AUTOMATIZACIONES
-- =====================================================

-- Webhooks activos por evento
ALTER TABLE webhooks 
ADD INDEX IF NOT EXISTS idx_activo_evento (activo, evento);

-- Automatizaciones activas por disparador (usar 'disparador' no 'evento_disparador')
ALTER TABLE automatizaciones 
ADD INDEX IF NOT EXISTS idx_activo_disparador (activo, disparador);

-- Logs de webhooks recientes
ALTER TABLE webhook_logs 
ADD INDEX IF NOT EXISTS idx_webhook_fecha (webhook_id, fecha DESC);

-- Logs de automatizaciones recientes
ALTER TABLE automatizacion_logs 
ADD INDEX IF NOT EXISTS idx_automatizacion_fecha (automatizacion_id, fecha DESC);

-- =====================================================
-- OPTIMIZACIÓN DE TABLAS
-- =====================================================

OPTIMIZE TABLE notas;
OPTIMIZE TABLE contactos;
OPTIMIZE TABLE tareas;
OPTIMIZE TABLE eventos;
OPTIMIZE TABLE links;
OPTIMIZE TABLE archivos;
OPTIMIZE TABLE etiquetas;
OPTIMIZE TABLE nota_etiqueta;
OPTIMIZE TABLE busquedas;
OPTIMIZE TABLE logs;
OPTIMIZE TABLE auditoria;
OPTIMIZE TABLE webhooks;
OPTIMIZE TABLE automatizaciones;

-- =====================================================
-- ANÁLISIS DE TABLAS
-- =====================================================

ANALYZE TABLE notas;
ANALYZE TABLE contactos;
ANALYZE TABLE tareas;
ANALYZE TABLE eventos;
ANALYZE TABLE links;
ANALYZE TABLE archivos;

-- =====================================================
-- VERIFICACIÓN
-- =====================================================

SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'pim_db'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT 'Índices de rendimiento creados correctamente' as status;
