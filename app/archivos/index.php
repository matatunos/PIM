<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/audit_logger.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = $error = '';

// Obtener configuración del admin (o usar valores por defecto)
$config = ['max_size' => 10, 'extensiones' => 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z,mp3,mp4,avi'];
try {
    // Asegurar que la tabla config existe
    $pdo->exec('CREATE TABLE IF NOT EXISTS config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(100) NOT NULL UNIQUE,
        valor VARCHAR(500),
        tipo VARCHAR(20) DEFAULT "text",
        descripcion TEXT,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    
    $stmt = $pdo->prepare('SELECT valor FROM config WHERE clave = ?');
    $stmt->execute(['archivo_max_size']);
    $ms = $stmt->fetchColumn();
    if ($ms) $config['max_size'] = (int)$ms;
    
    $stmt->execute(['archivo_extensiones']);
    $ext = $stmt->fetchColumn();
    if ($ext) $config['extensiones'] = $ext;
} catch (Exception $e) {
    // Si hay error, usar valores por defecto
}
$extensiones_permitidas = array_map('trim', explode(',', $config['extensiones']));
$max_size = $config['max_size'] * 1024 * 1024;

// Función para generar color automático basado en nombre
function generarColorEtiqueta($nombre) {
    $colores = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
    return $colores[crc32($nombre) % count($colores)];
}

// Subir archivo(s)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivos'])) {
    $archivos = $_FILES['archivos'];
    $descripcion = trim($_POST['descripcion'] ?? '');
    $etiquetas = $_POST['etiquetas'] ?? [];
    
    // Si solo hay un archivo (no array de array)
    if (is_string($archivos['error'])) {
        $archivos = [
            'name' => [$archivos['name']],
            'type' => [$archivos['type']],
            'tmp_name' => [$archivos['tmp_name']],
            'error' => [$archivos['error']],
            'size' => [$archivos['size']]
        ];
    }
    
    $contador = 0;
    $duplicados = 0;
    $errores_archivos = [];
    
    for ($i = 0; $i < count($archivos['name']); $i++) {
        if ($archivos['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $nombre_original = basename($archivos['name'][$i]);
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $nombre_unico = uniqid() . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $ruta_destino = UPLOAD_PATH . '/' . $nombre_unico;
        
        // Validar extensión
        if (!in_array($extension, $extensiones_permitidas)) {
            $errores_archivos[] = "$nombre_original: tipo no permitido";
            continue;
        }
        
        // Validar tamaño
        if ($archivos['size'][$i] > $max_size) {
            $errores_archivos[] = "$nombre_original: supera tamaño máximo de " . $config['max_size'] . "MB";
            continue;
        }
        
        // Validar MIME real del contenido (magic bytes)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_real = $finfo->file($archivos['tmp_name'][$i]);
        
        // Mapeo de extensiones permitidas a MIMEs válidos
        $mimes_validos = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt' => ['text/plain'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'mp4' => ['video/mp4'],
            'svg' => ['image/svg+xml'],
        ];
        
        // Verificar si el MIME real coincide con la extensión
        if (isset($mimes_validos[$extension])) {
            if (!in_array($mime_real, $mimes_validos[$extension])) {
                $errores_archivos[] = "$nombre_original: el contenido no coincide con la extensión (posible archivo malicioso)";
                continue;
            }
        }
        
        if (move_uploaded_file($archivos['tmp_name'][$i], $ruta_destino)) {
            $tipo_mime = mime_content_type($ruta_destino);
            $tamano = $archivos['size'][$i];
            $hash_archivo = hash_file('sha256', $ruta_destino);
            
            // Verificar si ya existe un archivo con el mismo hash para este usuario
            $stmt = $pdo->prepare('SELECT id FROM archivos WHERE usuario_id = ? AND hash = ?');
            $stmt->execute([$usuario_id, $hash_archivo]);
            
            if ($stmt->rowCount() > 0) {
                // Archivo duplicado, eliminar el que acaba de subirse
                unlink($ruta_destino);
                $duplicados++;
                continue;
            }
            
            $stmt = $pdo->prepare('INSERT INTO archivos (usuario_id, nombre_original, nombre_archivo, ruta, tipo_mime, tamano, descripcion, hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$usuario_id, $nombre_original, $nombre_unico, $ruta_destino, $tipo_mime, $tamano, $descripcion, $hash_archivo]);
            $archivo_id = $pdo->lastInsertId();
            
            // Agregar etiquetas
            if (!empty($etiquetas)) {
                foreach ($etiquetas as $etiqueta_id) {
                    $stmt = $pdo->prepare('INSERT IGNORE INTO archivo_etiqueta (archivo_id, etiqueta_id) VALUES (?, ?)');
                    $stmt->execute([$archivo_id, $etiqueta_id]);
                }
            }
            
            $contador++;
        }
    }
    
    if ($contador > 0) {
        $mensaje = "Se subieron $contador archivo(s) correctamente";
        if ($duplicados > 0) {
            $mensaje .= " (" . $duplicados . " duplicado(s) rechazado(s))";
        }
        // Registrar en auditoría
        logAction('subir', 'archivo', "Se subieron $contador archivo(s)", true);
    } elseif ($duplicados > 0) {
        $error = "Todos los archivos ya existen (duplicados rechazados)";
        logAction('subir', 'archivo', "Intento de subir archivos duplicados", false);
    } else {
        $error = 'Error al subir los archivos';
        logAction('subir', 'archivo', 'Error durante la subida de archivos', false);
    }
    
    if (!empty($errores_archivos)) {
        foreach ($errores_archivos as $err) {
            error_log("Upload error: $err");
        }
    }
}

// Eliminar archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify()) {
        die('Error CSRF');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM archivos WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuario_id]);
        $archivo = $stmt->fetch();
        
        if ($archivo && file_exists($archivo['ruta'])) {
            unlink($archivo['ruta']);
        }
        
        $stmt = $pdo->prepare('DELETE FROM archivos WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuario_id]);
        header('Location: index.php');
        exit;
    }
}

// Descargar archivo
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $id = (int)$_GET['download'];
    $stmt = $pdo->prepare('SELECT * FROM archivos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    $archivo = $stmt->fetch();
    
    if ($archivo && file_exists($archivo['ruta'])) {
        $stmt = $pdo->prepare('UPDATE archivos SET descargas = descargas + 1 WHERE id = ?');
        $stmt->execute([$id]);
        
        // Registrar en auditoría
        logAction('descargar', 'archivo', 'Archivo descargado: ' . $archivo['nombre_original'], true);
        
        header('Content-Type: ' . $archivo['tipo_mime']);
        header('Content-Disposition: attachment; filename="' . $archivo['nombre_original'] . '"');
        header('Content-Length: ' . filesize($archivo['ruta']));
        readfile($archivo['ruta']);
        exit;
    }
}

// Editar etiquetas de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_etiquetas') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $etiquetas = $_POST['etiquetas'] ?? [];
    $nueva_etiqueta = trim($_POST['nueva_etiqueta'] ?? '');
    
    $stmt = $pdo->prepare('SELECT id FROM archivos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$archivo_id, $usuario_id]);
    
    if ($stmt->rowCount()) {
        // Limpiar etiquetas existentes
        $stmt = $pdo->prepare('DELETE FROM archivo_etiqueta WHERE archivo_id = ?');
        $stmt->execute([$archivo_id]);
        
        // Si hay nueva etiqueta, crearla
        if (!empty($nueva_etiqueta)) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO etiquetas (usuario_id, nombre, color) VALUES (?, ?, ?)');
            $stmt->execute([$usuario_id, $nueva_etiqueta, generarColorEtiqueta($nueva_etiqueta)]);
            
            $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE usuario_id = ? AND nombre = ?');
            $stmt->execute([$usuario_id, $nueva_etiqueta]);
            $etiqueta = $stmt->fetch();
            if ($etiqueta) {
                $etiquetas[] = $etiqueta['id'];
            }
        }
        
        // Agregar etiquetas seleccionadas
        if (!empty($etiquetas)) {
            foreach ($etiquetas as $etiqueta_id) {
                $stmt = $pdo->prepare('INSERT IGNORE INTO archivo_etiqueta (archivo_id, etiqueta_id) VALUES (?, ?)');
                $stmt->execute([$archivo_id, (int)$etiqueta_id]);
            }
        }
        
        $mensaje = 'Etiquetas actualizadas correctamente';
    }
}

// Obtener archivos
$buscar = $_GET['q'] ?? '';
$filtro_etiqueta = $_GET['etiqueta'] ?? '';

$sql = 'SELECT DISTINCT a.* FROM archivos a';
$params = [];

if (!empty($filtro_etiqueta)) {
    $sql .= ' INNER JOIN archivo_etiqueta ae ON a.id = ae.archivo_id
              INNER JOIN etiquetas e ON ae.etiqueta_id = e.id';
}

$sql .= ' WHERE a.usuario_id = ?';
$params[] = $usuario_id;

if (!empty($buscar)) {
    $sql .= ' AND (a.nombre_original LIKE ? OR a.descripcion LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if (!empty($filtro_etiqueta)) {
    $sql .= ' AND e.id = ?';
    $params[] = (int)$filtro_etiqueta;
}

$sql .= ' ORDER BY a.fecha_subida DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$archivos = $stmt->fetchAll();

// Obtener todas las etiquetas del usuario
$stmt = $pdo->prepare('SELECT id, nombre, color FROM etiquetas WHERE usuario_id = ? ORDER BY nombre');
$stmt->execute([$usuario_id]);
$todas_etiquetas = $stmt->fetchAll();

// Obtener etiquetas de cada archivo
$archivos_con_etiquetas = [];
foreach ($archivos as $archivo) {
    $stmt = $pdo->prepare('SELECT e.* FROM etiquetas e INNER JOIN archivo_etiqueta ae ON e.id = ae.etiqueta_id WHERE ae.archivo_id = ?');
    $stmt->execute([$archivo['id']]);
    $archivo['etiquetas'] = $stmt->fetchAll();
    $archivos_con_etiquetas[] = $archivo;
}
$archivos = $archivos_con_etiquetas;

// Estadísticas
$stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(tamano) as espacio FROM archivos WHERE usuario_id = ?');
$stmt->execute([$usuario_id]);
$stats = $stmt->fetch();

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function getFileIcon($tipo_mime) {
    if (strpos($tipo_mime, 'image/') === 0) return 'fa-image';
    if (strpos($tipo_mime, 'video/') === 0) return 'fa-video';
    if (strpos($tipo_mime, 'audio/') === 0) return 'fa-music';
    if (strpos($tipo_mime, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($tipo_mime, 'word') !== false) return 'fa-file-word';
    if (strpos($tipo_mime, 'excel') !== false || strpos($tipo_mime, 'spreadsheet') !== false) return 'fa-file-excel';
    if (strpos($tipo_mime, 'powerpoint') !== false || strpos($tipo_mime, 'presentation') !== false) return 'fa-file-powerpoint';
    if (strpos($tipo_mime, 'zip') !== false || strpos($tipo_mime, 'compressed') !== false) return 'fa-file-archive';
    if (strpos($tipo_mime, 'text/') === 0) return 'fa-file-alt';
    return 'fa-file';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archivos - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        .stat-card {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: var(--spacing-md);
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .archivos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-lg);
        }
        .archivo-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all var(--transition-base);
            position: relative;
        }
        .archivo-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .archivo-preview {
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
            position: relative;
        }
        .archivo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .archivo-icon {
            font-size: 4rem;
            color: var(--primary);
        }
        .archivo-info {
            padding: var(--spacing-md);
        }
        .archivo-nombre {
            font-weight: 700;
            margin-bottom: var(--spacing-xs);
            word-break: break-word;
        }
        .archivo-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: var(--spacing-sm);
        }
        .archivo-actions {
            display: flex;
            gap: var(--spacing-xs);
            margin-top: var(--spacing-md);
        }
        .upload-zone {
            border: 3px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--spacing-2xl);
            text-align: center;
            background: var(--bg-secondary);
            transition: all var(--transition-base);
            cursor: pointer;
        }
        .upload-zone:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .upload-zone.dragover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
        }
        
        /* Vista Mosaico */
        .archivos-container[data-view="mosaico"] .archivos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-lg);
        }
        
        /* Vista Lista */
        .archivos-container[data-view="lista"] .archivos-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        .archivos-container[data-view="lista"] .archivo-card {
            display: grid;
            grid-template-columns: 100px 1fr auto auto auto;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }
        .archivos-container[data-view="lista"] .archivo-preview {
            height: 100px;
            width: 100px;
            margin: 0;
        }
        .archivos-container[data-view="lista"] .archivo-info {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }
        .archivos-container[data-view="lista"] .archivo-etiquetas {
            display: flex;
            gap: var(--spacing-xs);
            flex-wrap: wrap;
        }
        
        /* Vista Contenido */
        .archivos-container[data-view="contenido"] .archivos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        .archivos-container[data-view="contenido"] .archivo-preview {
            height: 250px;
        }
        
        /* Vista Detalles */
        .archivos-container[data-view="detalles"] .archivos-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }
        .archivos-container[data-view="detalles"] .archivo-card {
            display: block;
            padding: var(--spacing-lg);
        }
        .archivos-container[data-view="detalles"] .archivo-preview {
            height: 300px;
            margin-bottom: var(--spacing-lg);
        }
        
        /* Botones de vista */
        .view-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 1rem;
            transition: all var(--transition-fast);
            border-radius: 0;
        }
        .view-btn:hover {
            color: var(--primary);
            background: var(--bg-secondary);
        }
        .view-btn.active {
            color: var(--primary);
            background: var(--bg-secondary);
        }
        
        @media (max-width: 768px) {
            .archivos-container[data-view="lista"] .archivo-card {
                grid-template-columns: 60px 1fr auto;
            }
            .view-toggle {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-folder"></i> Archivos</h1>
                </div>
                <div class="top-bar-right">
                    <button onclick="document.getElementById('modalSubir').classList.add('active')" class="btn btn-primary">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Subir Archivo
                    </button>
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
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-file"></i></div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Archivos totales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-hdd"></i></div>
                        <div class="stat-value"><?= formatBytes($stats['espacio'] ?? 0) ?></div>
                        <div class="stat-label">Espacio usado</div>
                    </div>
                </div>
                
                <!-- Búsqueda y Vistas -->
                <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; align-items: center; margin-bottom: var(--spacing-lg);">
                    <form method="GET" style="display: flex; gap: var(--spacing-md); flex: 1; min-width: 250px;">
                        <input type="text" name="q" placeholder="Buscar archivos..." value="<?= htmlspecialchars($buscar) ?>" class="form-control" style="flex: 1; min-width: 200px;">
                        
                        <select name="etiqueta" onchange="this.form.submit()" style="padding: 0.5rem 1rem; border: 2px solid var(--gray-200); border-radius: var(--radius-md);">
                            <option value="">Todas las etiquetas</option>
                            <?php foreach ($todas_etiquetas as $et): ?>
                                <option value="<?= $et['id'] ?>" <?= $filtro_etiqueta == $et['id'] ? 'selected' : '' ?>>
                                    <span style="display: inline-block; width: 10px; height: 10px; background: <?= htmlspecialchars($et['color']) ?>; border-radius: 50%; margin-right: 5px;"></span>
                                    <?= htmlspecialchars($et['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($buscar || $filtro_etiqueta): ?>
                            <a href="index.php" class="btn btn-ghost">Limpiar</a>
                        <?php endif; ?>
                    </form>
                    
                    <div class="view-toggle" style="display: flex; gap: var(--spacing-xs); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0;">
                        <button class="view-btn" onclick="cambiarVista('mosaico')" title="Mosaico" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('lista')" title="Lista" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-list"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('contenido')" title="Contenido" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-align-left"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('detalles')" title="Detalles" style="border: none;">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Grid de archivos -->
                <?php if (empty($archivos)): ?>
                    <div class="card">
                        <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                            <i class="fas fa-folder-open" style="font-size: 4rem; color: var(--gray-300);"></i>
                            <h3 style="margin-top: var(--spacing-lg); color: var(--text-secondary);">No hay archivos</h3>
                            <p class="text-muted">Sube tu primer archivo para comenzar</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="archivos-container" data-view="mosaico" id="archivosContainer">
                        <div class="archivos-grid">
                        <?php foreach ($archivos as $archivo): ?>
                            <div class="archivo-card">
                                <div class="archivo-preview">
                                    <?php if (strpos($archivo['tipo_mime'], 'image/') === 0): ?>
                                        <img src="/assets/uploads/<?= htmlspecialchars($archivo['nombre_archivo']) ?>" alt="<?= htmlspecialchars($archivo['nombre_original']) ?>">
                                    <?php else: ?>
                                        <div class="archivo-icon">
                                            <i class="fas <?= getFileIcon($archivo['tipo_mime']) ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="archivo-info">
                                    <div class="archivo-nombre" title="<?= htmlspecialchars($archivo['nombre_original']) ?>">
                                        <?= htmlspecialchars(strlen($archivo['nombre_original']) > 30 ? substr($archivo['nombre_original'], 0, 27) . '...' : $archivo['nombre_original']) ?>
                                    </div>
                                    
                                    <?php if ($archivo['descripcion']): ?>
                                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: var(--spacing-xs);">
                                            <?= htmlspecialchars($archivo['descripcion']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="archivo-meta">
                                        <span><?= formatBytes($archivo['tamano']) ?></span>
                                        <span><?= date('d/m/Y', strtotime($archivo['fecha_subida'])) ?></span>
                                    </div>
                                    
                                    <?php if (!empty($archivo['etiquetas'])): ?>
                                        <div style="margin-top: var(--spacing-sm); display: flex; gap: var(--spacing-xs); flex-wrap: wrap;">
                                            <?php foreach ($archivo['etiquetas'] as $et): ?>
                                                <span style="display: inline-block; background: <?= htmlspecialchars($et['color']) ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">
                                                    <?= htmlspecialchars($et['nombre']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="archivo-actions">
                                        <button type="button" class="btn btn-secondary btn-icon btn-sm" onclick="editarEtiquetas(<?= $archivo['id'] ?>, <?= htmlspecialchars(json_encode(array_column($archivo['etiquetas'], 'id'))) ?>)" title="Editar etiquetas">
                                            <i class="fas fa-tags"></i>
                                        </button>
                                        <a href="?download=<?= $archivo['id'] ?>" class="btn btn-primary btn-icon btn-sm" title="Descargar">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este archivo?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $archivo['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-icon btn-sm" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <?php if ($archivo['descargas'] > 0): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: var(--spacing-xs);">
                                            <i class="fas fa-download"></i> <?= $archivo['descargas'] ?> descargas
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Subir Archivo(s) -->
    <div id="modalSubir" class="modal">
        <div class="modal-content" style="display: flex; flex-direction: column; max-height: 90vh;">
            <h2 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-cloud-upload-alt"></i>
                Subir Archivo(s)
            </h2>
            <form method="POST" enctype="multipart/form-data" class="form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                <?= csrf_field() ?>
                <div style="flex: 1; overflow-y: auto; padding-right: var(--spacing-md);">
                    <div class="upload-zone" onclick="document.getElementById('archivos').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary); margin-bottom: var(--spacing-md);"></i>
                        <p style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Haz clic o arrastra archivos aquí</p>
                        <p style="margin: var(--spacing-sm) 0 0 0; font-size: 0.85rem; color: var(--text-muted);">Máximo <?= $config['max_size'] ?>MB | Extensiones: <?= htmlspecialchars($config['extensiones']) ?></p>
                    </div>
                    
                    <input type="file" id="archivos" name="archivos[]" multiple required style="display: none;" onchange="mostrarArchivosSeleccionados(this)">
                    
                    <div id="archivos-seleccionados" style="display: none; margin-top: var(--spacing-md); padding: var(--spacing-md); background: var(--bg-secondary); border-radius: var(--radius-md);">
                        <strong>Archivos seleccionados:</strong> 
                        <div id="lista-archivos" style="margin-top: var(--spacing-sm);"></div>
                    </div>
                    
                    <div class="form-group" style="margin-top: var(--spacing-lg);">
                        <label for="descripcion">Descripción (opcional)</label>
                        <textarea id="descripcion" name="descripcion" rows="3" placeholder="Añade una descripción..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="etiquetas">Etiquetas (opcional)</label>
                        <div style="display: flex; gap: var(--spacing-sm); margin-bottom: var(--spacing-md); flex-wrap: wrap;">
                            <?php foreach ($todas_etiquetas as $et): ?>
                                <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer;">
                                    <input type="checkbox" name="etiquetas[]" value="<?= $et['id'] ?>">
                                    <span style="display: inline-block; width: 12px; height: 12px; background: <?= htmlspecialchars($et['color']) ?>; border-radius: 3px;"></span>
                                    <span><?= htmlspecialchars($et['nombre']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl); border-top: 1px solid var(--gray-200); padding-top: var(--spacing-md); flex-shrink: 0;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Subir Archivo(s)
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalSubir').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Editar Etiquetas -->
    <div id="modalEditarEtiquetas" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-tags"></i>
                Editar Etiquetas
            </h2>
            <form method="POST" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="editar_etiquetas">
                <input type="hidden" id="editar-archivo-id" name="archivo_id">
                
                <div class="form-group">
                    <label>Etiquetas existentes</label>
                    <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap; margin-bottom: var(--spacing-lg);">
                        <?php foreach ($todas_etiquetas as $et): ?>
                            <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); background: var(--bg-secondary); border-radius: var(--radius-md);">
                                <input type="checkbox" name="etiquetas[]" value="<?= $et['id'] ?>" class="etiqueta-checkbox">
                                <span style="display: inline-block; width: 12px; height: 12px; background: <?= htmlspecialchars($et['color']) ?>; border-radius: 3px;"></span>
                                <span><?= htmlspecialchars($et['nombre']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nueva-etiqueta">Crear nueva etiqueta (opcional)</label>
                    <input type="text" id="nueva-etiqueta" name="nueva_etiqueta" placeholder="Nombre de la nueva etiqueta...">
                    <small style="color: var(--text-secondary);">Se asignará un color automático</small>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Guardar Etiquetas
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalEditarEtiquetas').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Vista system
        function cambiarVista(tipo) {
            const container = document.getElementById('archivosContainer');
            if (!container) return;
            
            container.setAttribute('data-view', tipo);
            localStorage.setItem('archivos-view', tipo);
            
            // Actualizar botones
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.view-btn').classList.add('active');
        }
        
        // Cargar vista guardada
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('archivos-view') || 'mosaico';
            const container = document.getElementById('archivosContainer');
            if (container) {
                container.setAttribute('data-view', savedView);
                document.querySelectorAll('.view-btn').forEach((btn, idx) => {
                    const views = ['mosaico', 'lista', 'contenido', 'detalles'];
                    if (views[idx] === savedView) {
                        btn.classList.add('active');
                    }
                });
            }
        });
        
        function mostrarArchivosSeleccionados(input) {
            if (input.files && input.files.length > 0) {
                let lista = '';
                for (let i = 0; i < input.files.length; i++) {
                    lista += `<div style="padding: var(--spacing-xs); color: var(--text-secondary); font-size: 0.9rem;">
                        <i class="fas fa-file"></i> ${input.files[i].name} (${(input.files[i].size / 1024 / 1024).toFixed(2)}MB)
                    </div>`;
                }
                document.getElementById('lista-archivos').innerHTML = lista;
                document.getElementById('archivos-seleccionados').style.display = 'block';
            }
        }
        
        function editarEtiquetas(archivoId, etiquetasIds) {
            document.getElementById('editar-archivo-id').value = archivoId;
            
            // Desmarcar todas las etiquetas
            document.querySelectorAll('.etiqueta-checkbox').forEach(cb => cb.checked = false);
            
            // Marcar etiquetas del archivo
            etiquetasIds.forEach(id => {
                document.querySelector(`.etiqueta-checkbox[value="${id}"]`)?.click();
            });
            
            document.getElementById('modalEditarEtiquetas').classList.add('active');
        }
        
        // Cerrar modales al hacer clic fuera
        document.getElementById('modalSubir').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
        
        document.getElementById('modalEditarEtiquetas').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
        
        // Drag and drop
        const uploadZone = document.querySelector('.upload-zone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => {
                uploadZone.classList.add('dragover');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => {
                uploadZone.classList.remove('dragover');
            }, false);
        });
        
        uploadZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('archivos').files = files;
            mostrarArchivosSeleccionados(document.getElementById('archivos'));
        }, false);
    </script>
</body>
</html>
