<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$mensaje = $error = '';

// Restaurar item
if (isset($_POST['action']) && $_POST['action'] === 'restaurar') {
    $tipo = $_POST['tipo'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    if (in_array($tipo, ['notas', 'tareas', 'eventos', 'contactos', 'archivos']) && $item_id > 0) {
        $table = $tipo;
        $stmt = $pdo->prepare("UPDATE $table SET borrado_en = NULL WHERE id = ?");
        if ($stmt->execute([$item_id])) {
            // Registrar restauración
            $stmt = $pdo->prepare('UPDATE papelera_logs SET restaurado_en = NOW() WHERE tipo = ? AND item_id = ? AND restaurado_en IS NULL');
            $stmt->execute([$tipo, $item_id]);
            $mensaje = "Item restaurado correctamente desde la papelera";
        } else {
            $error = "Error al restaurar el item";
        }
    }
}

// Borrar permanentemente
if (isset($_POST['action']) && $_POST['action'] === 'borrar_permanente') {
    $tipo = $_POST['tipo'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    if (in_array($tipo, ['notas', 'tareas', 'eventos', 'contactos', 'archivos']) && $item_id > 0) {
        $table = $tipo;
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND borrado_en IS NOT NULL");
        if ($stmt->execute([$item_id])) {
            // Registrar borrado permanente
            $stmt = $pdo->prepare('UPDATE papelera_logs SET permanentemente_eliminado_en = NOW() WHERE tipo = ? AND item_id = ? AND permanentemente_eliminado_en IS NULL');
            $stmt->execute([$tipo, $item_id]);
            $mensaje = "Item eliminado permanentemente de la papelera";
        } else {
            $error = "Error al eliminar el item";
        }
    }
}

// Vaciar papelera (borrar todos los items más antiguos de 30 días)
if (isset($_POST['action']) && $_POST['action'] === 'vaciar_papelera') {
    $fecha_limite = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    try {
        // Obtener todos los items a borrar
        $stmt = $pdo->prepare('SELECT DISTINCT tipo, item_id FROM papelera_logs WHERE borrado_en < ? AND permanentemente_eliminado_en IS NULL');
        $stmt->execute([$fecha_limite]);
        $items = $stmt->fetchAll();
        
        $count = 0;
        foreach ($items as $item) {
            $tipo = $item['tipo'];
            $item_id = $item['item_id'];
            $table = $tipo;
            
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND borrado_en IS NOT NULL");
            if ($stmt->execute([$item_id])) {
                $count++;
                $stmt = $pdo->prepare('UPDATE papelera_logs SET permanentemente_eliminado_en = NOW() WHERE tipo = ? AND item_id = ? AND permanentemente_eliminado_en IS NULL');
                $stmt->execute([$tipo, $item_id]);
            }
        }
        
        $mensaje = "Papelera vaciada: $count items eliminados permanentemente";
    } catch (Exception $e) {
        $error = "Error al vaciar la papelera: " . $e->getMessage();
    }
}

// Obtener items en papelera
$sql = 'SELECT pl.*, u.username 
        FROM papelera_logs pl 
        LEFT JOIN usuarios u ON pl.usuario_id = u.id 
        WHERE pl.restaurado_en IS NULL AND pl.permanentemente_eliminado_en IS NULL 
        ORDER BY pl.borrado_en DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute();
$items_papelera = $stmt->fetchAll();

// Agrupar por tipo
$papelera_por_tipo = [];
foreach ($items_papelera as $item) {
    if (!isset($papelera_por_tipo[$item['tipo']])) {
        $papelera_por_tipo[$item['tipo']] = [];
    }
    $papelera_por_tipo[$item['tipo']][] = $item;
}

$total_items = count($items_papelera);
$items_antiguos = 0;
$fecha_limite = date('Y-m-d H:i:s', strtotime('-30 days'));
foreach ($items_papelera as $item) {
    if ($item['borrado_en'] < $fecha_limite) {
        $items_antiguos++;
    }
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getTipoIcon($tipo) {
    $icons = [
        'notas' => 'fas fa-sticky-note',
        'tareas' => 'fas fa-tasks',
        'eventos' => 'fas fa-calendar',
        'contactos' => 'fas fa-address-book',
        'archivos' => 'fas fa-file'
    ];
    return $icons[$tipo] ?? 'fas fa-trash';
}

function getTipoLabel($tipo) {
    $labels = [
        'notas' => 'Notas',
        'tareas' => 'Tareas',
        'eventos' => 'Eventos',
        'contactos' => 'Contactos',
        'archivos' => 'Archivos'
    ];
    return $labels[$tipo] ?? ucfirst($tipo);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papelera - PIM Admin</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/fonts/fontawesome/css/all.min.css">
</head>

<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-trash"></i> Papelera</h1>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div class="config-grid">
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-trash-alt"></i> Items en Papelera</div>
                        <div class="config-value"><?= $total_items ?></div>
                    </div>
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-clock"></i> Items Antiguos (>30 días)</div>
                        <div class="config-value"><?= $items_antiguos ?></div>
                    </div>
                </div>
                
                <?php if ($total_items > 0): ?>
                    <!-- Botón vaciar papelera -->
                    <div style="margin-bottom: var(--spacing-lg);">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="vaciar_papelera">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Eliminar permanentemente todos los items antiguos (>30 días)?\n\nEsta acción no se puede deshacer.')">
                                <i class="fas fa-times-circle"></i> Vaciar Papelera (>30 días)
                            </button>
                        </form>
                    </div>
                    
                    <!-- Items agrupados por tipo -->
                    <?php foreach ($papelera_por_tipo as $tipo => $items): ?>
                    <div class="card" style="margin-bottom: var(--spacing-lg);">
                        <div class="card-header">
                            <h2 style="margin: 0;"><i class="<?= getTipoIcon($tipo) ?>"></i> <?= getTipoLabel($tipo) ?> (<?= count($items) ?>)</h2>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color);">
                                            <th style="padding: var(--spacing-sm); text-align: left;">Nombre</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Propietario</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Borrado en</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Días en Papelera</th>
                                            <th style="padding: var(--spacing-sm); text-align: center;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): 
                                            $dias = floor((strtotime(date('Y-m-d H:i:s')) - strtotime($item['borrado_en'])) / 86400);
                                            $es_antiguo = $dias >= 30;
                                        ?>
                                            <tr style="border-bottom: 1px solid var(--border-color); <?= $es_antiguo ? 'background-color: #fff3cd;' : '' ?>">
                                                <td style="padding: var(--spacing-sm);">
                                                    <strong><?= htmlspecialchars($item['nombre'] ?? 'Sin nombre') ?></strong>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?= htmlspecialchars($item['username'] ?? 'Usuario eliminado') ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?= date('d/m/Y H:i', strtotime($item['borrado_en'])) ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <span style="<?= $es_antiguo ? 'color: #d9534f; font-weight: bold;' : '' ?>">
                                                        <?= $dias ?> días
                                                        <?php if ($es_antiguo): ?>
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td style="padding: var(--spacing-sm); text-align: center;">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="restaurar">
                                                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
                                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Restaurar">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="borrar_permanente">
                                                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
                                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar permanentemente este item?\n\n<?= htmlspecialchars(addslashes($item['nombre'])) ?>\n\nEsta acción no se puede deshacer.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: var(--spacing-md); display: block;"></i>
                                <p>La papelera está vacía</p>
                                <p style="font-size: 0.9em;">Los elementos eliminados aparecerán aquí</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
