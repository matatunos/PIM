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
$backup_dir = '/backups/pim';

// Crear backup manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_backup') {
    $output = [];
    $return_var = 0;
    
    // Ejecutar script de backup con redirección de errores
    exec('bash /opt/PIM/bin/backup-db.sh ' . escapeshellarg($backup_dir) . ' 30 2>&1', $output, $return_var);
    
    if ($return_var === 0) {
        // El backup se creó exitosamente
        $mensaje = 'Backup creado exitosamente';
        logAction('backup', 'backup', 'Backup de base de datos creado exitosamente', true);
    } else {
        // Mostrar el último error del output
        $error_msg = !empty($output) ? end($output) : 'Error desconocido al crear el backup';
        $error = 'Error al crear el backup: ' . htmlspecialchars($error_msg);
        logAction('backup', 'backup', 'Error al crear backup: ' . $error_msg, false);
    }
}

// Descargar backup
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backup_dir . '/' . $filename;
    
    // Validar que el archivo existe y está en el directorio correcto
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($backup_dir)) === 0) {
        // Registrar en auditoría
        logAction('descargar', 'backup', 'Backup descargado: ' . $filename, true);
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $error = 'Archivo de backup no encontrado';
    }
}

// Eliminar backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!csrf_verify()) {
        die('Error CSRF');
    }
    $filename = basename($_POST['filename'] ?? '');
    $filepath = $backup_dir . '/' . $filename;
    
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($backup_dir)) === 0) {
        if (unlink($filepath)) {
            $mensaje = 'Backup eliminado: ' . $filename;
            logAction('eliminar', 'backup', 'Backup eliminado: ' . $filename, true);
        } else {
            $error = 'Error al eliminar el backup';
            logAction('eliminar', 'backup', 'Error al eliminar backup: ' . $filename, false);
        }
    } else {
        $error = 'Archivo de backup no encontrado';
    }
}

// Restaurar backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restaurar') {
    $filename = basename($_POST['filename'] ?? '');
    $filepath = $backup_dir . '/' . $filename;
    
    // Validar que el archivo existe y está en el directorio correcto
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($backup_dir)) === 0) {
        $output = [];
        $return_var = 0;
        
        // Ejecutar script de restauración
        exec('bash /opt/PIM/bin/restore-db.sh ' . escapeshellarg($filepath) . ' 2>&1', $output, $return_var);
        
        if ($return_var === 0) {
            $mensaje = 'Backup restaurado exitosamente: ' . htmlspecialchars($filename);
            logAction('backup', 'backup', 'Backup restaurado: ' . $filename, true);
        } else {
            $error_msg = !empty($output) ? end($output) : 'Error desconocido';
            $error = 'Error al restaurar el backup: ' . htmlspecialchars($error_msg);
            logAction('backup', 'backup', 'Error al restaurar backup: ' . $error_msg, false);
        }
    } else {
        $error = 'Archivo de backup no encontrado';
    }
}

// Preparar modal de restauración
$restore_available = false;
$restore_filename = '';
if (isset($_POST['action']) && $_POST['action'] === 'prepare_restore') {
    $filename = basename($_POST['filename'] ?? '');
    $filepath = $backup_dir . '/' . $filename;
    
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($backup_dir)) === 0) {
        $restore_available = true;
        $restore_filename = $filename;
    }
}

// Listado de backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '/pim-backup-*.zip');
    if ($files) {
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'date' => filemtime($file),
                'path' => $file
            ];
        }
        // Ordenar por fecha descendente
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
}

// Estadísticas
$total_backups = count($backups);
$total_size = 0;
$oldest_backup = null;
$newest_backup = null;

foreach ($backups as $backup) {
    $total_size += $backup['size'];
    if (!$newest_backup) $newest_backup = $backup;
    $oldest_backup = $backup;
}

// Leer últimas líneas del log
$log_file = '/var/log/pim-backup.log';
$recent_logs = [];
if (file_exists($log_file)) {
    $lines = file($log_file);
    $recent_logs = array_slice($lines, -10);
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Backups - PIM Admin</title>
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
                    <h1 class="page-title"><i class="fas fa-shield-alt"></i> Gestión de Backups</h1>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- Botón de backup manual -->
                <div class="card" style="margin-bottom: var(--spacing-lg); background: linear-gradient(135deg, var(--pastel-blue) 0%, var(--pastel-lavender) 100%); border: none;">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0 0 5px 0; color: var(--text-primary);"><i class="fas fa-database"></i> Crear Backup Manual</h3>
                                <p style="margin: 0; color: var(--text-secondary);">Realiza un backup completo de la BD y archivos ahora</p>
                            </div>
                            <form method="POST" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="crear_backup">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('¿Crear backup ahora? Esto puede tomar algunos minutos.')">
                                    <i class="fas fa-download"></i> Crear Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="config-grid">
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-file-archive"></i> Backups Totales</div>
                        <div class="config-value"><?= $total_backups ?></div>
                    </div>
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-hdd"></i> Espacio Usado</div>
                        <div class="config-value"><?= formatBytes($total_size) ?></div>
                    </div>
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-clock"></i> Más Reciente</div>
                        <div class="config-value"><?= $newest_backup ? date('d/m/Y', $newest_backup['date']) : 'N/A' ?></div>
                    </div>
                    <div class="config-card">
                        <div class="config-label"><i class="fas fa-history"></i> Más Antiguo</div>
                        <div class="config-value"><?= $oldest_backup ? date('d/m/Y', $oldest_backup['date']) : 'N/A' ?></div>
                    </div>
                </div>
                
                <!-- Listado de backups -->
                <div class="card">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-list"></i> Historial de Backups</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($backups) > 0): ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color);">
                                            <th style="padding: var(--spacing-sm); text-align: left;">Archivo</th>
                                            <th style="padding: var(--spacing-sm); text-align: right;">Tamaño</th>
                                            <th style="padding: var(--spacing-sm); text-align: left;">Fecha/Hora</th>
                                            <th style="padding: var(--spacing-sm); text-align: center;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: var(--spacing-sm);">
                                                    <code style="background-color: var(--gray-100); padding: 2px 6px; border-radius: 3px; font-size: 0.9em;">
                                                        <?= htmlspecialchars($backup['filename']) ?>
                                                    </code>
                                                </td>
                                                <td style="padding: var(--spacing-sm); text-align: right;">
                                                    <strong><?= formatBytes($backup['size']) ?></strong>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?= date('d/m/Y H:i:s', $backup['date']) ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm); text-align: center;">
                                                    <a href="?download=<?= urlencode($backup['filename']) ?>" class="btn btn-sm btn-primary" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Restaurar desde este backup? Se sobrescribirá la base de datos actual. Se creará un backup de seguridad automáticamente.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="restaurar">
                                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning" title="Restaurar">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmarBorrado('<?= htmlspecialchars(addslashes($backup['filename'])) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: var(--spacing-md); display: block;"></i>
                                <p>No hay backups disponibles</p>
                                <p style="font-size: 0.9em;">Crea un backup manual o configura backups automáticos</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Configuración de Cron -->
                <div class="card" style="margin-top: var(--spacing-lg);">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-cog"></i> Configurar Backups Automáticos</h2>
                    </div>
                    <div class="card-body">
                        <p><strong>Para activar backups automáticos con cron, ejecuta en terminal:</strong></p>
                        <div style="background: var(--gray-100); padding: var(--spacing-md); border-radius: 5px; margin-bottom: var(--spacing-md); font-family: monospace; overflow-x: auto;">
                            sudo /opt/PIM/bin/cron-setup.sh daily
                        </div>
                        <p><strong>Opciones disponibles:</strong></p>
                        <ul>
                            <li><code>daily</code> - Backup diario a las 2:00 AM</li>
                            <li><code>weekly</code> - Backup semanal (lunes 2:00 AM)</li>
                            <li><code>monthly</code> - Backup mensual (1º mes 2:00 AM)</li>
                        </ul>
                        <p style="font-size: 0.9em; color: var(--text-secondary);">Los backups se guardan en <code>/backups/pim/</code> y se retienen automáticamente 30 días</p>
                    </div>
                </div>
                
                <!-- Logs recientes -->
                <?php if (!empty($recent_logs)): ?>
                <div class="card" style="margin-top: var(--spacing-lg);">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-file-alt"></i> Últimas Líneas del Log</h2>
                    </div>
                    <div class="card-body">
                        <div style="background: var(--gray-800); color: var(--gray-200); padding: var(--spacing-md); border-radius: 5px; font-family: 'Courier New', monospace; font-size: 0.9em; max-height: 300px; overflow-y: auto;">
                            <?php foreach ($recent_logs as $line): ?>
                                <div><?= htmlspecialchars(trim($line)) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Formulario oculto para borrar -->
    <form id="form-borrar" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="eliminar">
        <input type="hidden" name="filename" id="delete_input">
    </form>
    
    <script>
        function confirmarBorrado(filename) {
            if (confirm(`¿Está seguro que desea borrar el backup "${filename}"?\n\nEsta acción no se puede deshacer.`)) {
                document.getElementById('delete_input').value = filename;
                document.getElementById('form-borrar').submit();
            }
        }
    </script>
</body>
</html>
