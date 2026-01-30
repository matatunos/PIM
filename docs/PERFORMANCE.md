# 🚀 PIM - Optimización de Rendimiento

Guía completa para optimizar el rendimiento de PIM.

---

## 📋 Índice

- [Sistema de Caché](#sistema-de-caché)
- [Optimización de Base de Datos](#optimización-de-base-de-datos)
- [Minificación de Assets](#minificación-de-assets)
- [PHP OPcache](#php-opcache)
- [Monitoreo](#monitoreo)
- [Best Practices](#best-practices)

---

## 💾 Sistema de Caché

PIM incluye un sistema de caché basado en archivos para mejorar el rendimiento.

### Uso básico

```php
require_once 'includes/cache.php';

// Guardar en caché
Cache::set('mi_clave', $datos, 3600); // 1 hora

// Obtener del caché
$datos = Cache::get('mi_clave');

// Eliminar
Cache::delete('mi_clave');

// Limpiar todo
Cache::clear();
```

### Pattern "Remember"

```php
// Ejecuta el callback solo si no está en caché
$usuarios = Cache::remember('usuarios_activos', function() use ($pdo) {
    $stmt = $pdo->query("SELECT * FROM usuarios WHERE activo = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, 600); // 10 minutos
```

### Cachear Queries SQL

```php
// En lugar de:
$stmt = $pdo->prepare("SELECT * FROM notas WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Usa:
$notas = Cache::query(
    $pdo,
    "SELECT * FROM notas WHERE usuario_id = ?",
    [$usuario_id],
    300 // TTL 5 minutos
);
```

### Namespaces

```php
// Organiza el caché por tipo
Cache::set('nota_123', $nota, 300, 'data');
Cache::set('search_results', $results, 60, 'search');
Cache::set('rendered_view', $html, 600, 'views');
Cache::set('user_query', $data, 300, 'queries');

// Limpiar un namespace específico
Cache::clear('queries'); // Solo queries
Cache::clear('search');  // Solo búsquedas
```

### Invalidar caché al modificar datos

```php
// Al crear/actualizar/eliminar nota
Cache::invalidateTable('notas');

// Invalidar clave específica
Cache::delete('nota_' . $nota_id);
```

### Panel de administración

Ve a **Admin → Performance** para:
- Ver estadísticas de caché
- Limpiar caché por namespace
- Ver archivos expirados
- Monitorear tamaño total

---

## 🗄️ Optimización de Base de Datos

### Ejecutar migración de índices

```bash
mysql -u root pim_db < db/migration_performance.sql
```

Esto añade:
- **40+ índices** en tablas principales
- Índices compuestos (usuario_id + fecha, usuario_id + estado, etc.)
- Índices FULLTEXT para búsqueda en texto
- Optimización y análisis de tablas

### Índices añadidos

#### Notas
- `idx_usuario_fecha` - Búsquedas por usuario y fecha
- `idx_usuario_color` - Filtrar por color
- `idx_usuario_fijada` - Notas fijadas
- `ft_titulo_contenido` - Búsqueda fulltext

#### Contactos
- `idx_usuario_nombre` - Listar contactos
- `idx_usuario_favorito` - Contactos favoritos
- `idx_email` - Búsqueda por email
- `ft_contacto_search` - Búsqueda fulltext

#### Tareas
- `idx_usuario_estado` - Filtrar por estado
- `idx_usuario_prioridad` - Ordenar por prioridad
- `idx_usuario_fecha_vence` - Próximas a vencer
- `idx_completada` - Tareas completadas

#### Y más para: Calendario, Links, Archivos, Webhooks, Logs...

### Verificar índices

```sql
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'pim_db'
ORDER BY TABLE_NAME, INDEX_NAME;
```

### Queries lentas

Habilitar slow query log en MariaDB:

```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Queries > 1 segundo
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-queries.log';
```

Monitorear:

```bash
php bin/monitor-performance.php
```

### Mantenimiento periódico

```sql
-- Optimizar tablas (desfragmentar)
OPTIMIZE TABLE notas, contactos, tareas, eventos;

-- Analizar tablas (actualizar estadísticas)
ANALYZE TABLE notas, contactos, tareas, eventos;

-- Ver fragmentación
SELECT 
    TABLE_NAME,
    ROUND(DATA_FREE / 1024 / 1024, 2) as fragmented_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'pim_db'
  AND DATA_FREE > 0;
```

---

## 📦 Minificación de Assets

### Minificar CSS y JS

```bash
php bin/optimize-assets.php
```

Esto genera:
- `assets/dist/app.min.css` - Todos los CSS combinados y minificados
- `assets/dist/app.min.js` - Todos los JS combinados y minificados
- `assets/dist/manifest.json` - Hashes para cache busting

### Resultados típicos

```
Original CSS:  890 KB
Minificado:    245 KB  (-72%)

Original JS:   320 KB
Minificado:    180 KB  (-44%)
```

### Usar assets minificados

En producción, reemplaza en tus plantillas:

```html
<!-- Desarrollo -->
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="stylesheet" href="assets/css/2fa-fix.css">

<!-- Producción -->
<link rel="stylesheet" href="assets/dist/app.min.css?v=abc12345">
```

```html
<!-- Desarrollo -->
<script src="assets/js/ajax-nav.js"></script>
<script src="assets/js/hamburger.js"></script>
<script src="assets/js/notifications.js"></script>

<!-- Producción -->
<script src="assets/dist/app.min.js?v=def67890"></script>
```

### Automatizar en deploy

Añade al script de deploy:

```bash
#!/bin/bash
echo "Optimizing assets..."
php bin/optimize-assets.php

echo "Enabling production mode..."
# Cambiar a assets minificados
# Deshabilitar debug logs
# etc.
```

---

## ⚙️ PHP OPcache

OPcache almacena bytecode precompilado de PHP en memoria.

### Configurar en php.ini

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
opcache.save_comments=1
opcache.fast_shutdown=1
```

### Verificar configuración

```bash
php -i | grep opcache
```

O en el navegador:

```php
<?php
phpinfo();
// Busca sección "Zend OPcache"
```

### Limpiar OPcache después de deploy

```php
<?php
opcache_reset();
echo "OPcache limpiado";
```

O desde CLI:

```bash
php -r "opcache_reset();"
```

### Monitorear OPcache

Ve a **Admin → Performance** para ver:
- Hit rate (debe ser > 95%)
- Memoria usada
- Scripts cacheados
- Estadísticas de misses/hits

---

## 📊 Monitoreo

### Script de monitoreo

```bash
# Ver reporte completo
php bin/monitor-performance.php

# Ejecutar cada hora con cron
0 * * * * cd /opt/PIM && php bin/monitor-performance.php >> logs/performance.log
```

### Qué monitorea

1. **Queries lentas** - Queries > 1 segundo
2. **Tablas grandes** - Top 10 por tamaño
3. **Fragmentación** - Tablas que necesitan OPTIMIZE
4. **Índices faltantes** - Recomendaciones

### Logs generados

- `logs/slow_queries.log` - Queries lentas detectadas
- `logs/performance.log` - Reportes de monitoreo
- `logs/cache.log` - Operaciones de caché (si se habilita)

### Dashboard web

Ve a **Admin → Performance** para ver en tiempo real:
- Estadísticas de caché
- Tablas más grandes
- Índices por tabla
- OPcache status
- Queries lentas recientes

---

## 🎯 Best Practices

### 1. Cachear datos estáticos o poco cambiantes

```php
// ✅ BIEN: Cachear lista de etiquetas
$etiquetas = Cache::remember('etiquetas_usuario_' . $usuario_id, function() use ($pdo, $usuario_id) {
    $stmt = $pdo->prepare("SELECT * FROM etiquetas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, 600);

// ❌ MAL: No cachear datos que cambian constantemente
// (notificaciones en tiempo real, contadores live, etc.)
```

### 2. Usar índices correctamente

```sql
-- ✅ BIEN: Índice compuesto en el orden correcto
CREATE INDEX idx_usuario_fecha ON notas(usuario_id, fecha_creacion);
SELECT * FROM notas WHERE usuario_id = 1 ORDER BY fecha_creacion DESC;

-- ❌ MAL: Índice no usado porque falta usuario_id
SELECT * FROM notas ORDER BY fecha_creacion DESC;
```

### 3. Limitar resultados

```php
// ✅ BIEN: Paginación con LIMIT
$stmt = $pdo->prepare("
    SELECT * FROM notas 
    WHERE usuario_id = ? 
    ORDER BY fecha_creacion DESC 
    LIMIT ?, ?
");
$stmt->execute([$usuario_id, $offset, $limit]);

// ❌ MAL: Cargar todo y filtrar en PHP
$stmt = $pdo->prepare("SELECT * FROM notas WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$todas = $stmt->fetchAll();
$pagina = array_slice($todas, $offset, $limit);
```

### 4. Invalidar caché apropiadamente

```php
// Al crear nota
$stmt->execute([$titulo, $contenido]);
$nota_id = $pdo->lastInsertId();

// Invalidar cachés relacionados
Cache::delete('notas_recientes_' . $usuario_id);
Cache::delete('stats_usuario_' . $usuario_id);
Cache::invalidateTable('notas');
```

### 5. Lazy loading de imágenes

```html
<!-- ✅ BIEN: Lazy loading -->
<img src="placeholder.jpg" data-src="imagen-real.jpg" loading="lazy">

<!-- ❌ MAL: Cargar todas las imágenes al inicio -->
<img src="imagen-pesada-1.jpg">
<img src="imagen-pesada-2.jpg">
<!-- ... 50 imágenes más ... -->
```

### 6. Comprimir respuestas

En Apache (`.htaccess`):

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

En Nginx:

```nginx
gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
gzip_comp_level 6;
```

### 7. Cache headers para assets estáticos

```apache
<FilesMatch "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2)$">
    Header set Cache-Control "max-age=31536000, public"
</FilesMatch>
```

---

## 📈 Benchmarks

Resultados típicos antes/después de optimizaciones:

| Métrica | Antes | Después | Mejora |
|---------|-------|---------|--------|
| Tiempo de carga home | 850ms | 120ms | **-86%** |
| Queries por request | 18 | 4 | **-78%** |
| Tamaño página | 1.2MB | 320KB | **-73%** |
| Time to First Byte | 450ms | 80ms | **-82%** |
| Memoria PHP | 45MB | 12MB | **-73%** |

### Herramientas para medir

```bash
# Tiempo de respuesta
curl -w "@curl-format.txt" -o /dev/null -s "https://pim.local"

# Apache Bench
ab -n 1000 -c 10 https://pim.local/

# Lighthouse (Chrome DevTools)
# Performance tab → Run audit

# PHP profiling con Xdebug
php -d xdebug.profiler_enable=1 index.php
```

---

## 🔧 Troubleshooting

### Caché no funciona

```bash
# Verificar permisos
ls -la cache/
chmod -R 755 cache/

# Verificar logs
tail -f logs/php_errors.log
```

### Queries siguen lentas

```sql
-- Ver query plan
EXPLAIN SELECT * FROM notas WHERE usuario_id = 1;

-- Debe usar índice, no Full Table Scan
-- Si dice "Using filesort" o "Using temporary", necesitas mejor índice
```

### OPcache no activo

```bash
# Verificar módulo cargado
php -m | grep opcache

# Si no aparece, instalar:
sudo apt install php-opcache
sudo systemctl restart apache2
```

### Mucho espacio en caché

```bash
# Limpiar desde CLI
php -r "require 'includes/cache.php'; Cache::clear();"

# O limpiar expirados
php -r "require 'includes/cache.php'; Cache::clearExpired();"
```

---

📋 **PIM** - Personal Information Manager  
Documentación Performance - 30 de enero de 2026
