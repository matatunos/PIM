<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$q = trim($_GET['q'] ?? '');
$tipo_filtro = $_GET['tipo'] ?? 'todos';
$resultados = [];
$total_resultados = 0;

$tipos_disponibles = ['notas', 'tareas', 'eventos', 'contactos', 'archivos', 'links'];

if ($q && strlen($q) >= 2) {
    $busqueda = "%$q%";
    
    if ($tipo_filtro === 'todos' || $tipo_filtro === 'notas') {
        $stmt = $pdo->prepare("SELECT id, 'nota' AS tipo, titulo, contenido AS description, creado_en AS fecha, NULL AS estado FROM notas WHERE usuario_id = ? AND borrado_en IS NULL AND (titulo LIKE ? OR contenido LIKE ?)");
        $stmt->execute([$usuario_id, $busqueda, $busqueda]);
        $resultados = array_merge($resultados, $stmt->fetchAll());
    }
    
    if ($tipo_filtro === 'todos' || $tipo_filtro === 'tareas') {
        $stmt = $pdo->prepare("SELECT id, 'tarea' AS tipo, titulo, descripcion AS description, creado_en AS fecha, IF(completada, 'Completada', 'Pendiente') AS estado FROM tareas WHERE usuario_id = ? AND borrado_en IS NULL AND titulo LIKE ?");
        $stmt->execute([$usuario_id, $busqueda]);
        $resultados = array_merge($resultados, $stmt->fetchAll());
    }
    
    if ($tipo_filtro === 'todos' || $tipo_filtro === 'eventos') {
        $stmt = $pdo->prepare("SELECT id, 'evento' AS tipo, titulo, descripcion AS description, fecha_inicio AS fecha, NULL AS estado FROM eventos WHERE usuario_id = ? AND borrado_en IS NULL AND (titulo LIKE ? OR descripcion LIKE ?)");
        $stmt->execute([$usuario_id, $busqueda, $busqueda]);
        $resultados = array_merge($resultados, $stmt->fetchAll());
    }
    
    if ($tipo_filtro === 'todos' || $tipo_filtro === 'contactos') {
        $stmt = $pdo->prepare("SELECT id, 'contacto' AS tipo, CONCAT(nombre, ' ', COALESCE(apellido, '')) AS titulo, email AS description, creado_en AS fecha, empresa AS estado FROM contactos WHERE usuario_id = ? AND borrado_en IS NULL AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR empresa LIKE ?)");
        $stmt->execute([$usuario_id, $busqueda, $busqueda, $busqueda, $busqueda]);
        $resultados = array_merge($resultados, $stmt->fetchAll());
    }
    
    if ($tipo_filtro === 'todos' || $tipo_filtro === 'archivos') {
        $stmt = $pdo->prepare("SELECT id, 'archivo' AS tipo, nombre_original AS titulo, tipo_mime AS description, creado_en AS fecha, CONCAT(ROUND(tamano/1024), ' KB') AS estado FROM archivos WHERE usuario_id = ? AND nombre_original LIKE ?");
        $stmt->execute([$usuario_id, $busqueda]);
        $resultados = array_merge($resultados, $stmt->fetchAll());
    }
    
    if ($tipo_filtro === 'todos' || $tipo_filtro === 'links') {
        $stmt = $pdo->prepare("SELECT id, 'link' AS tipo, titulo, url AS description, creado_en AS fecha, categoria AS estado FROM links WHERE usuario_id = ? AND (titulo LIKE ? OR url LIKE ? OR descripcion LIKE ?)");
        $stmt->execute([$usuario_id, $busqueda, $busqueda, $busqueda]);
        $resultados = array_merge($resultados, $stmt->fetchAll());
    }
    
    usort($resultados, function($a, $b) use ($q) {
        $a_match = stripos($a['titulo'], $q) !== false ? 1 : 0;
        $b_match = stripos($b['titulo'], $q) !== false ? 1 : 0;
        if ($a_match !== $b_match) return $b_match - $a_match;
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });
    
    $total_resultados = count($resultados);
}

function getRedirectUrl($tipo, $id) {
    $urls = [
        'nota' => "/app/notas/index.php?edit=$id",
        'tarea' => "/app/tareas/index.php?edit=$id",
        'evento' => "/app/calendario/index.php?edit=$id",
        'contacto' => "/app/contactos/index.php?edit=$id",
        'archivo' => "/app/archivos/index.php?id=$id",
        'link' => "/app/links/index.php?edit=$id"
    ];
    return $urls[$tipo] ?? '/app/busqueda/index.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-search"></i> Búsqueda Global</h1>
                </div>
            </div>
            <div class="content-area">
                <form method="get" style="margin-bottom: var(--spacing-lg);">
                    <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                        <input type="text" name="q" placeholder="Buscar en todas tus notas, tareas, eventos..." value="<?= htmlspecialchars($q) ?>" 
                               style="flex: 1; min-width: 200px; padding: var(--spacing-sm); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <?php if (!empty($q)): ?>
                            <a href="index.php" class="btn btn-secondary">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php if (!empty($q) && strlen($q) >= 2): ?>
                <div style="margin-bottom: var(--spacing-lg); display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                    <a href="?q=<?= urlencode($q) ?>&tipo=todos" class="btn btn-sm <?= $tipo_filtro === 'todos' ? 'btn-primary' : 'btn-outline' ?>">
                        Todos (<?= $total_resultados ?>)
                    </a>
                    <?php foreach ($tipos_disponibles as $tipo): ?>
                        <?php $count = count(array_filter($resultados, fn($r) => $r['tipo'] === $tipo)); ?>
                        <?php if ($count > 0): ?>
                        <a href="?q=<?= urlencode($q) ?>&tipo=<?= $tipo ?>" class="btn btn-sm <?= $tipo_filtro === $tipo ? 'btn-primary' : 'btn-outline' ?>">
                            <?= ucfirst($tipo) ?> (<?= $count ?>)
                        </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($q && strlen($q) < 2): ?>
                    <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                        <i class="fas fa-info-circle" style="font-size: 2em; margin-bottom: var(--spacing-md); display: block;"></i>
                        <p>Escribe al menos 2 caracteres para buscar</p>
                    </div>
                <?php elseif ($q && strlen($q) >= 2 && $total_resultados === 0): ?>
                    <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                        <i class="fas fa-search" style="font-size: 2em; margin-bottom: var(--spacing-md); display: block;"></i>
                        <p>No se encontraron resultados para "<?= htmlspecialchars($q) ?>"</p>
                    </div>
                <?php elseif ($total_resultados > 0): ?>
                    <div style="display: grid; gap: var(--spacing-md);">
                        <?php foreach ($resultados as $r): 
                            $colors = ['nota' => '#fff9e6', 'tarea' => '#e8f4f8', 'evento' => '#f0e6ff', 'contacto' => '#ffe6f0', 'archivo' => '#f0f0f0', 'link' => '#e6f7ff'];
                            $icons = ['nota' => 'fa-sticky-note', 'tarea' => 'fa-tasks', 'evento' => 'fa-calendar', 'contacto' => 'fa-address-card', 'archivo' => 'fa-file', 'link' => 'fa-link'];
                            $labels = ['nota' => 'Nota', 'tarea' => 'Tarea', 'evento' => 'Evento', 'contacto' => 'Contacto', 'archivo' => 'Archivo', 'link' => 'Link'];
                        ?>
                            <div style="background: <?= $colors[$r['tipo']] ?>; padding: var(--spacing-md); border-radius: var(--radius-md); border-left: 4px solid var(--primary-color); display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
                                        <i class="fas <?= $icons[$r['tipo']] ?>" style="color: var(--primary-color);"></i>
                                        <span style="font-size: 0.85em; font-weight: 600; color: var(--primary-color);">
                                            <?= $labels[$r['tipo']] ?>
                                        </span>
                                        <?php if (!empty($r['estado'])): ?>
                                            <span style="font-size: 0.8em; background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 3px;">
                                                <?= htmlspecialchars(substr($r['estado'], 0, 20)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 style="margin: 0 0 var(--spacing-xs) 0; font-size: 1.1em;">
                                        <?= htmlspecialchars($r['titulo']) ?>
                                    </h3>
                                    <?php if (!empty($r['description'])): ?>
                                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                                            <?= htmlspecialchars(substr($r['description'], 0, 100)) ?>
                                            <?php if (strlen($r['description']) > 100): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.85em; color: var(--text-secondary);">
                                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($r['fecha'])) ?>
                                    </p>
                                </div>
                                <div style="margin-left: var(--spacing-md);">
                                    <a href="<?= getRedirectUrl($r['tipo'], $r['id']) ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
