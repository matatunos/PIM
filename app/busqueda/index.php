<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
$q = $_GET['q'] ?? '';
$resultados = [];
if ($q) {
    $stmt = $pdo->prepare("SELECT 'nota' AS tipo, titulo, contenido AS texto FROM notas WHERE usuario_id = ? AND (titulo LIKE ? OR contenido LIKE ?)");
    $stmt->execute([$_SESSION['user_id'], "%$q%", "%$q%"]);
    $resultados = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT 'contacto' AS tipo, nombre AS titulo, notas AS texto FROM contactos WHERE usuario_id = ? AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR notas LIKE ?)");
    $stmt->execute([$_SESSION['user_id'], "%$q%", "%$q%", "%$q%", "%$q%"]);
    $resultados = array_merge($resultados, $stmt->fetchAll());
}

// Redirigir a index.php si no es AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if (!$isAjax) {
    header('Location: /index.php?mod=busqueda');
    exit;
}
if ($isAjax) {
?>
<div class="container mt-4">
    <h2><i class="fas fa-search"></i> Búsqueda</h2>
    <form method="get" class="mb-3">
        <input type="text" name="q" class="form-control" placeholder="Buscar en notas y contactos..." value="<?= htmlspecialchars($q) ?>">
    </form>
    <?php if ($q): ?>
        <h5>Resultados para "<?= htmlspecialchars($q) ?>":</h5>
        <ul class="list-group">
            <?php foreach ($resultados as $r): ?>
                <li class="list-group-item">
                    <span class="badge bg-secondary"><?= $r['tipo'] ?></span>
                    <strong><?= htmlspecialchars($r['titulo']) ?></strong><br>
                    <span><?= htmlspecialchars($r['texto']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Búsqueda</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
<?php include_once '../../includes/navbar.php'; ?>
<div class="container mt-4">
    <h2><i class="fas fa-search"></i> Búsqueda</h2>
    <form method="get" class="mb-3">
        <input type="text" name="q" class="form-control" placeholder="Buscar en notas y contactos..." value="<?= htmlspecialchars($q) ?>">
    </form>
    <?php if ($q): ?>
        <h5>Resultados para "<?= htmlspecialchars($q) ?>":</h5>
        <ul class="list-group">
            <?php foreach ($resultados as $r): ?>
                <li class="list-group-item">
                    <span class="badge bg-secondary"><?= $r['tipo'] ?></span>
                    <strong><?= htmlspecialchars($r['titulo']) ?></strong><br>
                    <span><?= htmlspecialchars($r['texto']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($resultados)): ?>
                <li class="list-group-item">Sin resultados.</li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
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
