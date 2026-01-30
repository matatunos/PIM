<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/cache.php';
require_once '../../config/database.php';

// Solo admin
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'clear_cache') {
        $namespace = $_POST['namespace'] ?? null;
        $count = Cache::clear($namespace);
        $mensaje = "✅ Limpiados $count archivos de caché";
    }
    
    if ($accion === 'clear_expired') {
        $count = Cache::clearExpired();
        $mensaje = "✅ Limpiados $count archivos expirados";
    }
}

// Obtener estadísticas de caché
$cacheStats = Cache::stats();

// Obtener queries lentas (últimas 24h)
$slowQueries = [];
if (file_exists('../../logs/slow_queries.log')) {
    $lines = file('../../logs/slow_queries.log');
    $slowQueries = array_slice(array_reverse($lines), 0, 20);
}

// Stats de BD
try {
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME as tabla,
            TABLE_ROWS as filas,
            ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_mb,
            ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_mb,
            ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_mb
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'pim_db'
        ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
        LIMIT 10
    ");
    $dbStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbStats = [];
}

// Índices por tabla
try {
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME as tabla,
            COUNT(DISTINCT INDEX_NAME) as num_indices
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = 'pim_db'
        GROUP BY TABLE_NAME
        ORDER BY num_indices DESC
    ");
    $indexStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $indexStats = [];
}

// OPcache stats
$opcacheStats = null;
if (function_exists('opcache_get_status')) {
    $opcacheStats = opcache_get_status();
}

require_once '../../includes/lang.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance - PIM</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">⚡ Performance y Optimización</h1>
                </div>
                
                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-success"><?= $mensaje ?></div>
                <?php endif; ?>
                
                <!-- Estadísticas de Caché -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h5>💾 Sistema de Caché</h5>
                                <div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="accion" value="clear_expired">
                                        <button type="submit" class="btn btn-sm btn-warning">Limpiar Expirados</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="accion" value="clear_cache">
                                        <button type="submit" class="btn btn-sm btn-danger">Limpiar Todo</button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-md-3">
                                        <h3><?= $cacheStats['total_files'] ?></h3>
                                        <small class="text-muted">Archivos totales</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h3><?= round($cacheStats['total_size'] / 1024 / 1024, 2) ?> MB</h3>
                                        <small class="text-muted">Tamaño total</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h3><?= $cacheStats['expired'] ?></h3>
                                        <small class="text-muted">Expirados</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h3><?= $cacheStats['total_files'] - $cacheStats['expired'] ?></h3>
                                        <small class="text-muted">Válidos</small>
                                    </div>
                                </div>
                                
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Namespace</th>
                                            <th>Archivos</th>
                                            <th>Tamaño</th>
                                            <th>Expirados</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cacheStats['by_namespace'] as $ns => $stats): ?>
                                        <tr>
                                            <td><code><?= $ns ?></code></td>
                                            <td><?= $stats['count'] ?></td>
                                            <td><?= round($stats['size'] / 1024, 2) ?> KB</td>
                                            <td><?= $stats['expired'] ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="accion" value="clear_cache">
                                                    <input type="hidden" name="namespace" value="<?= $ns ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger">Limpiar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats de Base de Datos -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>🗄️ Tablas Más Grandes</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tabla</th>
                                            <th>Filas</th>
                                            <th>Data</th>
                                            <th>Índices</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dbStats as $stat): ?>
                                        <tr>
                                            <td><code><?= $stat['tabla'] ?></code></td>
                                            <td><?= number_format($stat['filas']) ?></td>
                                            <td><?= $stat['data_mb'] ?> MB</td>
                                            <td><?= $stat['index_mb'] ?> MB</td>
                                            <td><strong><?= $stat['total_mb'] ?> MB</strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>📊 Índices por Tabla</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tabla</th>
                                            <th>Número de Índices</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($indexStats as $stat): ?>
                                        <tr>
                                            <td><code><?= $stat['tabla'] ?></code></td>
                                            <td>
                                                <span class="badge bg-<?= $stat['num_indices'] >= 5 ? 'success' : 'warning' ?>">
                                                    <?= $stat['num_indices'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- OPcache Stats -->
                <?php if ($opcacheStats): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>⚙️ OPcache Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h3><?= $opcacheStats['opcache_enabled'] ? '✅ Activo' : '❌ Inactivo' ?></h3>
                                        <small class="text-muted">Estado</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h3><?= round($opcacheStats['memory_usage']['used_memory'] / 1024 / 1024, 2) ?> MB</h3>
                                        <small class="text-muted">Memoria usada</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h3><?= $opcacheStats['opcache_statistics']['num_cached_scripts'] ?></h3>
                                        <small class="text-muted">Scripts cacheados</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h3><?= round($opcacheStats['opcache_statistics']['opcache_hit_rate'], 2) ?>%</h3>
                                        <small class="text-muted">Hit rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Queries Lentas -->
                <?php if (!empty($slowQueries)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>🐌 Queries Lentas (últimas 20)</h5>
                            </div>
                            <div class="card-body">
                                <pre style="max-height: 300px; overflow-y: auto;"><?php foreach ($slowQueries as $query) echo htmlspecialchars($query); ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recomendaciones -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>💡 Recomendaciones</h5>
                            </div>
                            <div class="card-body">
                                <ul>
                                    <?php if (!$opcacheStats || !$opcacheStats['opcache_enabled']): ?>
                                    <li class="text-danger">⚠️ <strong>Activa OPcache</strong> en php.ini para mejor rendimiento</li>
                                    <?php endif; ?>
                                    
                                    <?php if ($cacheStats['expired'] > 10): ?>
                                    <li class="text-warning">⚠️ Hay <?= $cacheStats['expired'] ?> archivos de caché expirados, considera limpiarlos</li>
                                    <?php endif; ?>
                                    
                                    <?php if ($cacheStats['total_size'] > 100 * 1024 * 1024): ?>
                                    <li class="text-warning">⚠️ El caché ocupa más de 100MB, considera limpiarlo periódicamente</li>
                                    <?php endif; ?>
                                    
                                    <li class="text-success">✅ Ejecuta <code>php bin/optimize-assets.php</code> para minificar CSS/JS</li>
                                    <li class="text-success">✅ Usa <code>Cache::query()</code> para cachear consultas frecuentes</li>
                                    <li class="text-success">✅ Los índices están optimizados en la migración migration_performance.sql</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
