<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test';
$_SESSION['rol'] = 'admin';

require_once 'includes/auth_check.php';
require_once 'includes/cache.php';
require_once 'config/database.php';

echo "AUTH CHECK: OK<br>";
echo "CACHE CLASS: " . (class_exists('Cache') ? 'OK' : 'FAIL') . "<br>";
echo "PDO: " . (isset($pdo) ? 'OK' : 'FAIL') . "<br>";

try {
    $cacheStats = Cache::stats();
    echo "CACHE STATS: OK (files: " . $cacheStats['total_files'] . ")<br>";
} catch (Exception $e) {
    echo "CACHE STATS ERROR: " . $e->getMessage() . "<br>";
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $count = $stmt->fetch()['total'];
    echo "DB QUERY: OK (users: $count)<br>";
} catch (Exception $e) {
    echo "DB ERROR: " . $e->getMessage() . "<br>";
}

echo "<br><a href='/app/admin/performance.php'>→ Ir a Performance</a>";
?>
