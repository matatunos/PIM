#!/usr/bin/env php
<?php
/**
 * Monitor de rendimiento
 * Registra queries lentas y métricas de performance
 */

require_once __DIR__ . '/../config/database.php';

class PerformanceMonitor {
    private $pdo;
    private $logFile;
    private $threshold = 1000; // ms
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logFile = __DIR__ . '/../logs/slow_queries.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Monitorear queries lentas de MariaDB
     */
    public function checkSlowQueries() {
        echo "🔍 Verificando queries lentas...\n";
        
        try {
            // Verificar si slow_query_log está habilitado
            $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'slow_query_log'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['Value'] !== 'ON') {
                echo "⚠️  Slow query log no está habilitado\n";
                echo "   Ejecuta: SET GLOBAL slow_query_log = 'ON';\n";
                return;
            }
            
            // Obtener queries lentas recientes del processlist
            $stmt = $this->pdo->query("
                SELECT 
                    ID,
                    USER,
                    HOST,
                    DB,
                    COMMAND,
                    TIME,
                    STATE,
                    INFO
                FROM information_schema.PROCESSLIST
                WHERE TIME > " . ($this->threshold / 1000) . "
                  AND COMMAND != 'Sleep'
                  AND INFO IS NOT NULL
                ORDER BY TIME DESC
            ");
            
            $slowQueries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($slowQueries)) {
                echo "✅ No hay queries lentas actualmente\n";
                return;
            }
            
            echo "⚠️  Encontradas " . count($slowQueries) . " queries lentas:\n\n";
            
            foreach ($slowQueries as $query) {
                $message = sprintf(
                    "[%s] User: %s, DB: %s, Time: %ds\nQuery: %s\n---\n",
                    date('Y-m-d H:i:s'),
                    $query['USER'],
                    $query['DB'],
                    $query['TIME'],
                    $query['INFO']
                );
                
                echo $message;
                file_put_contents($this->logFile, $message, FILE_APPEND);
            }
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Estadísticas de tablas
     */
    public function tableStats() {
        echo "\n📊 Estadísticas de tablas:\n";
        
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    TABLE_NAME as tabla,
                    TABLE_ROWS as filas,
                    ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_mb,
                    ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_mb,
                    ROUND(DATA_FREE / 1024 / 1024, 2) as fragmented_mb
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = 'pim_db'
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                LIMIT 10
            ");
            
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            printf("%-20s %10s %10s %10s %15s\n", 
                "Tabla", "Filas", "Data MB", "Index MB", "Fragmentado MB");
            echo str_repeat("-", 70) . "\n";
            
            foreach ($stats as $stat) {
                printf("%-20s %10s %10s %10s %15s\n",
                    $stat['tabla'],
                    number_format($stat['filas']),
                    $stat['data_mb'],
                    $stat['index_mb'],
                    $stat['fragmented_mb']
                );
                
                // Advertir si hay mucha fragmentación
                if ($stat['fragmented_mb'] > 10) {
                    echo "   ⚠️  Considera ejecutar: OPTIMIZE TABLE {$stat['tabla']};\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Verificar índices faltantes
     */
    public function checkMissingIndexes() {
        echo "\n🔍 Verificando índices...\n";
        
        $recommendations = [];
        
        try {
            // Verificar índices en tablas principales
            $tables = ['notas', 'contactos', 'tareas', 'eventos', 'links', 'archivos'];
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("
                    SELECT COUNT(DISTINCT INDEX_NAME) as num_indices
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = 'pim_db'
                      AND TABLE_NAME = '$table'
                ");
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = $result['num_indices'] ?? 0;
                
                if ($count < 3) {
                    $recommendations[] = "Tabla '$table' tiene solo $count índices, considera añadir más";
                } else {
                    echo "✅ $table: $count índices\n";
                }
            }
            
            if (!empty($recommendations)) {
                echo "\n💡 Recomendaciones:\n";
                foreach ($recommendations as $rec) {
                    echo "   • $rec\n";
                }
                echo "\n   Ejecuta: mysql pim_db < db/migration_performance.sql\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Generar reporte completo
     */
    public function generateReport() {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "   REPORTE DE RENDIMIENTO - " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 70) . "\n\n";
        
        $this->checkSlowQueries();
        $this->tableStats();
        $this->checkMissingIndexes();
        
        echo "\n" . str_repeat("=", 70) . "\n";
    }
}

// Ejecutar
if (php_sapi_name() === 'cli') {
    $monitor = new PerformanceMonitor($pdo);
    $monitor->generateReport();
} else {
    die('Este script debe ejecutarse desde CLI');
}
