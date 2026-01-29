<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/audit_logger.php';

// Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$mensaje = $error = '';

// Borrar archivo (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrar_archivo') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    
    // Obtener archivo
    $stmt = $pdo->prepare('SELECT id, ruta, nombre_original, usuario_id FROM archivos WHERE id = ?');
    $stmt->execute([$archivo_id]);
    $archivo = $stmt->fetch();
    
    if ($archivo) {
        // Soft delete: marcar como borrado
        $stmt = $pdo->prepare('UPDATE archivos SET borrado_en = NOW() WHERE id = ?');
        $stmt->execute([$archivo_id]);
        
        // Registrar en papelera_logs
        $stmt = $pdo->prepare('INSERT INTO papelera_logs (usuario_id, tipo, item_id, nombre) VALUES (?, ?, ?, ?)');
        $stmt->execute([$archivo['usuario_id'], 'archivos', $archivo_id, $archivo['nombre_original']]);
        
        // Registrar en auditoría
        logAction('eliminar', 'archivo', 'Archivo eliminado: ' . $archivo['nombre_original'], true);
        
        $mensaje = 'Archivo eliminado. Se encuentra en la papelera';
    } else {
        $error = 'Archivo no encontrado';
    }
}

// Obtener listado de archivos
$buscar = $_GET['q'] ?? '';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$sql_where = 'WHERE a.borrado_en IS NULL';
$params = [];
if (!empty($buscar)) {
    $sql_where .= ' AND (a.nombre_original LIKE ? OR u.username LIKE ?)';
    $params = ['%' . $buscar . '%', '%' . $buscar . '%'];
}

// Contar total
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM archivos a LEFT JOIN usuarios u ON a.usuario_id = u.id ' . $sql_where);
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener archivos
$stmt = $pdo->prepare('SELECT a.id, a.nombre_original, a.tamano, a.creado_en, u.username, u.id as usuario_id FROM archivos a LEFT JOIN usuarios u ON a.usuario_id = u.id ' . $sql_where . ' ORDER BY a.creado_en DESC LIMIT ? OFFSET ?');
$stmt->execute(array_merge($params, [$por_pagina, $offset]));
$archivos = $stmt->fetchAll();
$pdo->exec('CREATE TABLE IF NOT EXISTS config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor VARCHAR(500),
    tipo VARCHAR(20) DEFAULT "text",
    descripcion TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

// Guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_config') {
    $max_size = (int)($_POST['max_size'] ?? 10);
    $extensiones = trim($_POST['extensiones'] ?? '');
    
    if ($max_size > 0 && !empty($extensiones)) {
        // Guardar o actualizar max_size
        $stmt = $pdo->prepare('INSERT INTO config (clave, valor, tipo, descripcion) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        $stmt->execute(['archivo_max_size', $max_size, 'number', 'Tamaño máximo de archivo en MB']);
        
        // Guardar o actualizar extensiones
        $stmt->execute(['archivo_extensiones', $extensiones, 'text', 'Extensiones permitidas separadas por comas']);
        
        $mensaje = 'Configuración guardada correctamente';
    } else {
        $error = 'Por favor, completa todos los campos';
    }
}

// Obtener configuración actual
$configuracion = [];
$stmt = $pdo->prepare('SELECT clave, valor FROM config WHERE clave IN ("archivo_max_size", "archivo_extensiones")');
$stmt->execute();
$cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$max_size_actual = $cfg['archivo_max_size'] ?? '10';
$extensiones_actual = $cfg['archivo_extensiones'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z,mp3,mp4,avi';

// Estadísticas
$stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(tamano) as espacio FROM archivos WHERE borrado_en IS NULL');
$stmt->execute();
$stats = $stmt->fetch();

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Archivos - Admin - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        .config-card {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .config-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: var(--spacing-sm);
        }
        .config-value {
            font-size: 1.5rem;
            color: var(--primary);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-folder-cog"></i> Gestión de Archivos</h1>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div class="config-grid">
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-file"></i> Archivos Totales</div>
                        <div class="config-value"><?= $stats['total'] ?></div>
                    </div>
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-hdd"></i> Espacio Usado</div>
                        <div class="config-value"><?= formatBytes($stats['espacio'] ?? 0) ?></div>
                    </div>
                </div>
                
                <!-- Listado de Archivos -->
                <div class="card" style="margin-bottom: var(--spacing-lg);">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-file-alt"></i> Listado de Archivos</h2>
                    </div>
                    <div class="card-body">
                        <!-- Búsqueda -->
                        <form method="GET" style="margin-bottom: var(--spacing-md);">
                            <div style="display: flex; gap: var(--spacing-md);">
                                <input type="text" name="q" placeholder="Buscar por nombre o propietario..." value="<?= htmlspecialchars($buscar) ?>" style="flex: 1;">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-search"></i>
                                    Buscar
                                </button>
                                <?php if (!empty($buscar)): ?>
                                    <a href="?pagina=1" class="btn btn-sm btn-secondary">Limpiar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (count($archivos) > 0): ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color);">
                                            <th style="padding: var(--spacing-sm); text-align: left;">ID</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Nombre Archivo</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Propietario</th>
                                            <th style="padding: var(--spacing-sm); text-align: right;">Tamaño</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Fecha Carga</th>
                                            <th style="padding: var(--spacing-sm); text-align: center;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($archivos as $archivo): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: var(--spacing-sm);">#<?= $archivo['id'] ?></td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <code style="background-color: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 0.9em;">
                                                        <?= htmlspecialchars($archivo['nombre_original']) ?>
                                                    </code>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?php if ($archivo['usuario_id']): ?>
                                                        <a href="usuarios.php?id=<?= $archivo['usuario_id'] ?>" style="color: var(--primary-color);">
                                                            <?= htmlspecialchars($archivo['username'] ?? 'N/A') ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-secondary);">Eliminado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm); text-align: right;">
                                                    <?= formatBytes($archivo['tamano']) ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?= date('d/m/Y H:i', strtotime($archivo['creado_en'])) ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm); text-align: center;">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmarBorrado(<?= $archivo['id'] ?>, '<?= htmlspecialchars(addslashes($archivo['nombre_original'])) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                        Borrar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginación -->
                            <?php if ($total_paginas > 1): ?>
                                <div style="display: flex; justify-content: center; gap: var(--spacing-sm); margin-top: var(--spacing-md);">
                                    <?php if ($pagina > 1): ?>
                                        <a href="?pagina=1<?= !empty($buscar) ? '&q=' . urlencode($buscar) : '' ?>" class="btn btn-sm btn-outline">Primera</a>
                                        <a href="?pagina=<?= $pagina - 1 ?><?= !empty($buscar) ? '&q=' . urlencode($buscar) : '' ?>" class="btn btn-sm btn-outline">Anterior</a>
                                    <?php endif; ?>
                                    
                                    <span style="display: flex; align-items: center; padding: 0 var(--spacing-sm);">
                                        Página <?= $pagina ?> de <?= $total_paginas ?>
                                    </span>
                                    
                                    <?php if ($pagina < $total_paginas): ?>
                                        <a href="?pagina=<?= $pagina + 1 ?><?= !empty($buscar) ? '&q=' . urlencode($buscar) : '' ?>" class="btn btn-sm btn-outline">Siguiente</a>
                                        <a href="?pagina=<?= $total_paginas ?><?= !empty($buscar) ? '&q=' . urlencode($buscar) : '' ?>" class="btn btn-sm btn-outline">Última</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: var(--spacing-md); display: block;"></i>
                                <p>No hay archivos para mostrar</p>
                                <?php if (!empty($buscar)): ?>
                                    <p style="font-size: 0.9em;">Intenta con otros términos de búsqueda</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Formulario de configuración -->
                <div class="card">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-sliders-h"></i> Configuración de Archivos</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="form">
                            <input type="hidden" name="action" value="guardar_config">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_size">Tamaño Máximo de Archivo (MB) *</label>
                                    <input type="number" id="max_size" name="max_size" value="<?= htmlspecialchars($max_size_actual) ?>" min="1" max="1000" required>
                                    <small>Máximo permitido: 1000 MB</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="extensiones">Extensiones Permitidas *</label>
                                <textarea id="extensiones" name="extensiones" rows="4" required><?= htmlspecialchars($extensiones_actual) ?></textarea>
                                <small>Separadas por comas, sin puntos (ej: jpg,png,pdf,doc)</small>
                            </div>
                            
                            <div style="display: flex; gap: var(--spacing-md);">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Configuración
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Información -->
                <div class="card" style="margin-top: var(--spacing-lg);">
                    <div class="card-header">
                        <h3 style="margin: 0;"><i class="fas fa-info-circle"></i> Información</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Notas sobre la configuración:</strong></p>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>El tamaño máximo se aplica a cada archivo subido</li>
                            <li>Las extensiones se validan sin incluir el punto (solo la extensión)</li>
                            <li>Los usuarios solo verán las extensiones permitidas</li>
                            <li>Los cambios se aplican inmediatamente a nuevas cargas</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario oculto para borrar archivos -->
    <form id="form-borrar" method="POST" style="display: none;">
        <input type="hidden" name="action" value="borrar_archivo">
        <input type="hidden" name="archivo_id" id="archivo_id_input">
    </form>
    
    <script>
        function confirmarBorrado(archivoId, nombreArchivo) {
            if (confirm(`¿Está seguro que desea borrar el archivo "${nombreArchivo}"?\n\nEsta acción no se puede deshacer.`)) {
                document.getElementById('archivo_id_input').value = archivoId;
                document.getElementById('form-borrar').submit();
            }
        }
    </script>
</body>
</html>
