<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$mensaje = $error = '';

// Crear tabla config si no existe
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
$stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(tamano) as espacio FROM archivos');
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
</body>
</html>
