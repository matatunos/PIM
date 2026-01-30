<?php
/**
 * Sistema de caché simple basado en archivos
 * Mejora el rendimiento almacenando resultados de consultas frecuentes
 */

class Cache {
    private static $cacheDir = __DIR__ . '/../cache/';
    private static $enabled = true;
    private static $defaultTTL = 3600; // 1 hora por defecto
    
    /**
     * Inicializar directorio de caché
     */
    public static function init() {
        if (!file_exists(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        // Crear subdirectorios
        $dirs = ['queries', 'views', 'data', 'search'];
        foreach ($dirs as $dir) {
            $path = self::$cacheDir . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
        
        // Crear .htaccess para proteger cache
        $htaccess = self::$cacheDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all");
        }
    }
    
    /**
     * Obtener valor del caché
     * @param string $key Clave única
     * @param string $namespace Namespace (queries, views, data, search)
     * @return mixed|null Valor cacheado o null si no existe/expiró
     */
    public static function get($key, $namespace = 'data') {
        if (!self::$enabled) return null;
        
        $file = self::getFilePath($key, $namespace);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Verificar expiración
        if ($data['expires'] > 0 && time() > $data['expires']) {
            self::delete($key, $namespace);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Guardar en caché
     * @param string $key Clave única
     * @param mixed $value Valor a cachear
     * @param int $ttl Tiempo de vida en segundos (0 = sin expiración)
     * @param string $namespace Namespace
     * @return bool
     */
    public static function set($key, $value, $ttl = null, $namespace = 'data') {
        if (!self::$enabled) return false;
        
        self::init();
        
        $ttl = $ttl ?? self::$defaultTTL;
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        $data = [
            'key' => $key,
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        $file = self::getFilePath($key, $namespace);
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    /**
     * Eliminar del caché
     * @param string $key Clave
     * @param string $namespace Namespace
     * @return bool
     */
    public static function delete($key, $namespace = 'data') {
        $file = self::getFilePath($key, $namespace);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * Limpiar todo el caché o un namespace específico
     * @param string|null $namespace Si es null, limpia todo
     * @return int Número de archivos eliminados
     */
    public static function clear($namespace = null) {
        $count = 0;
        
        if ($namespace) {
            $dir = self::$cacheDir . $namespace;
            if (is_dir($dir)) {
                $files = glob($dir . '/*.cache');
                foreach ($files as $file) {
                    if (unlink($file)) $count++;
                }
            }
        } else {
            // Limpiar todo
            $files = glob(self::$cacheDir . '*/*.cache');
            foreach ($files as $file) {
                if (unlink($file)) $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Limpiar caché expirado
     * @return int Número de archivos eliminados
     */
    public static function clearExpired() {
        $count = 0;
        $files = glob(self::$cacheDir . '*/*.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] > 0 && time() > $data['expires']) {
                if (unlink($file)) $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Cachear resultado de consulta SQL
     * @param PDO $pdo Conexión PDO
     * @param string $query Query SQL
     * @param array $params Parámetros
     * @param int $ttl TTL en segundos
     * @return array Resultado
     */
    public static function query($pdo, $query, $params = [], $ttl = 300) {
        $key = md5($query . serialize($params));
        
        // Intentar obtener del caché
        $cached = self::get($key, 'queries');
        if ($cached !== null) {
            return $cached;
        }
        
        // Ejecutar query
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Guardar en caché
        self::set($key, $result, $ttl, 'queries');
        
        return $result;
    }
    
    /**
     * Invalidar caché relacionado con una tabla
     * @param string $table Nombre de tabla
     * @return int Archivos eliminados
     */
    public static function invalidateTable($table) {
        // Limpiar queries que contengan el nombre de la tabla
        $count = 0;
        $files = glob(self::$cacheDir . 'queries/*.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            // Simple heurística: si la key contiene el nombre de tabla
            if (stripos($data['key'], $table) !== false) {
                if (unlink($file)) $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Obtener estadísticas del caché
     * @return array
     */
    public static function stats() {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'expired' => 0,
            'by_namespace' => []
        ];
        
        $namespaces = ['queries', 'views', 'data', 'search'];
        
        foreach ($namespaces as $ns) {
            $dir = self::$cacheDir . $ns;
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*.cache');
            $nsStats = [
                'count' => count($files),
                'size' => 0,
                'expired' => 0
            ];
            
            foreach ($files as $file) {
                $size = filesize($file);
                $nsStats['size'] += $size;
                $stats['total_size'] += $size;
                $stats['total_files']++;
                
                $data = unserialize(file_get_contents($file));
                if ($data['expires'] > 0 && time() > $data['expires']) {
                    $nsStats['expired']++;
                    $stats['expired']++;
                }
            }
            
            $stats['by_namespace'][$ns] = $nsStats;
        }
        
        return $stats;
    }
    
    /**
     * Habilitar/deshabilitar caché
     * @param bool $enabled
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }
    
    /**
     * Obtener ruta de archivo de caché
     * @param string $key
     * @param string $namespace
     * @return string
     */
    private static function getFilePath($key, $namespace) {
        $hash = md5($key);
        return self::$cacheDir . $namespace . '/' . $hash . '.cache';
    }
    
    /**
     * Wrapper para remember pattern (get or execute and cache)
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @param string $namespace
     * @return mixed
     */
    public static function remember($key, $callback, $ttl = null, $namespace = 'data') {
        $value = self::get($key, $namespace);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl, $namespace);
        
        return $value;
    }
}

// Inicializar caché al cargar
Cache::init();
