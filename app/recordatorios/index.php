<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
$stmt = $pdo->prepare('SELECT * FROM tareas WHERE usuario_id = ? AND fecha_vencimiento >= CURDATE() AND completada = 0 ORDER BY fecha_vencimiento');
$stmt->execute([$_SESSION['user_id']]);
$recordatorios = $stmt->fetchAll();

// Redirigir a index.php si no es AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if (!$isAjax) {
    header('Location: /index.php?mod=recordatorios');
    exit;
}
if ($isAjax) {
?>
<div class="container mt-4">
    <h2><i class="fas fa-bell"></i> Recordatorios</h2>
    <ul class="list-group">
        <?php foreach ($recordatorios as $r): ?>
            <li class="list-group-item">
                <strong><?= htmlspecialchars($r['titulo']) ?></strong> - <?= htmlspecialchars($r['descripcion']) ?>
                <span class="badge bg-warning text-dark float-end"><?= htmlspecialchars($r['fecha_vencimiento']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recordatorios</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
<?php include_once '../../includes/navbar.php'; ?>
<div class="container mt-4">
    <h2><i class="fas fa-bell"></i> Recordatorios</h2>
    <ul class="list-group">
        <?php foreach ($recordatorios as $r): ?>
            <li class="list-group-item">
                <strong><?= htmlspecialchars($r['titulo']) ?></strong> - <?= htmlspecialchars($r['descripcion']) ?>
                <span class="badge bg-warning text-dark float-end"><?= htmlspecialchars($r['fecha_vencimiento']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
<div id="js-log" style="position:fixed;bottom:10px;right:10px;z-index:9999;background:#fff3cd;color:#856404;padding:8px 16px;border-radius:1rem;box-shadow:0 2px 8px #fc5c7d33;font-size:1rem;display:none;">AJAX NAV activo</div>
<script src="../../assets/js/ajax-nav.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var jsLog = document.getElementById('js-log');
        if (jsLog) jsLog.style.display = 'block';
    });
</script>
</html>
