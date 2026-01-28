<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// No establecer header JSON para descarga
if ($action !== 'descargar') {
    header('Content-Type: application/json');
}

// Obtener archivos del usuario
if ($action === 'listar') {
    $tipo = $_GET['tipo'] ?? 'todos'; // 'todos', 'imagenes', 'documentos'
    
    $sql = 'SELECT id, nombre_original, nombre_archivo, tipo_mime, tamano FROM archivos WHERE usuario_id = ?';
    $params = [$usuario_id];
    
    if ($tipo === 'imagenes') {
        $sql .= ' AND tipo_mime LIKE "image/%"';
    } elseif ($tipo === 'documentos') {
        $sql .= ' AND tipo_mime NOT LIKE "image/%" AND tipo_mime NOT LIKE "video/%" AND tipo_mime NOT LIKE "audio/%"';
    }
    
    $sql .= ' ORDER BY nombre_original ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'archivos' => $archivos]);
}

// Obtener archivos vinculados a una tarea
elseif ($action === 'tareas') {
    $tarea_id = (int)($_GET['tarea_id'] ?? 0);
    
    // Verificar que la tarea pertenece al usuario
    $stmt = $pdo->prepare('SELECT id FROM tareas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$tarea_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Tarea no encontrada']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT a.* FROM archivos a INNER JOIN archivo_tarea at ON a.id = at.archivo_id WHERE at.tarea_id = ?');
    $stmt->execute([$tarea_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'archivos' => $archivos]);
}

// Obtener archivos vinculados a una nota
elseif ($action === 'notas') {
    $nota_id = (int)($_GET['nota_id'] ?? 0);
    
    // Verificar que la nota pertenece al usuario
    $stmt = $pdo->prepare('SELECT id FROM notas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$nota_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Nota no encontrada']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT a.* FROM archivos a INNER JOIN archivo_nota an ON a.id = an.archivo_id WHERE an.nota_id = ?');
    $stmt->execute([$nota_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'archivos' => $archivos]);
}

// Obtener archivos vinculados a un evento
elseif ($action === 'eventos') {
    $evento_id = (int)($_GET['evento_id'] ?? 0);
    
    // Verificar que el evento pertenece al usuario
    $stmt = $pdo->prepare('SELECT id FROM eventos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$evento_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Evento no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT a.* FROM archivos a INNER JOIN archivo_evento ae ON a.id = ae.archivo_id WHERE ae.evento_id = ?');
    $stmt->execute([$evento_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'archivos' => $archivos]);
}

// Vincular archivo a tarea
elseif ($action === 'vincular_tarea' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $tarea_id = (int)($_POST['tarea_id'] ?? 0);
    
    // Verificar permisos
    $stmt = $pdo->prepare('SELECT id FROM archivos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$archivo_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT id FROM tareas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$tarea_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Tarea no encontrada']);
        exit;
    }
    
    $stmt = $pdo->prepare('INSERT IGNORE INTO archivo_tarea (archivo_id, tarea_id) VALUES (?, ?)');
    $stmt->execute([$archivo_id, $tarea_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Archivo vinculado']);
}

// Vincular archivo a nota
elseif ($action === 'vincular_nota' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $nota_id = (int)($_POST['nota_id'] ?? 0);
    
    // Verificar permisos
    $stmt = $pdo->prepare('SELECT id FROM archivos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$archivo_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT id FROM notas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$nota_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Nota no encontrada']);
        exit;
    }
    
    $stmt = $pdo->prepare('INSERT IGNORE INTO archivo_nota (archivo_id, nota_id) VALUES (?, ?)');
    $stmt->execute([$archivo_id, $nota_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Archivo vinculado']);
}

// Vincular archivo a evento
elseif ($action === 'vincular_evento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $evento_id = (int)($_POST['evento_id'] ?? 0);
    
    // Verificar permisos
    $stmt = $pdo->prepare('SELECT id FROM archivos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$archivo_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT id FROM eventos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$evento_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Evento no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('INSERT IGNORE INTO archivo_evento (archivo_id, evento_id) VALUES (?, ?)');
    $stmt->execute([$archivo_id, $evento_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Archivo vinculado']);
}

// Desvincular archivo de tarea
elseif ($action === 'desvincular_tarea' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $tarea_id = (int)($_POST['tarea_id'] ?? 0);
    
    // Verificar que el usuario es propietario
    $stmt = $pdo->prepare('SELECT at.id FROM archivo_tarea at 
                          INNER JOIN archivos a ON at.archivo_id = a.id
                          INNER JOIN tareas t ON at.tarea_id = t.id
                          WHERE at.archivo_id = ? AND at.tarea_id = ? 
                          AND a.usuario_id = ? AND t.usuario_id = ?');
    $stmt->execute([$archivo_id, $tarea_id, $usuario_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Vínculo no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('DELETE FROM archivo_tarea WHERE archivo_id = ? AND tarea_id = ?');
    $stmt->execute([$archivo_id, $tarea_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Vínculo removido']);
}

// Desvincular archivo de nota
elseif ($action === 'desvincular_nota' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $nota_id = (int)($_POST['nota_id'] ?? 0);
    
    $stmt = $pdo->prepare('SELECT an.id FROM archivo_nota an 
                          INNER JOIN archivos a ON an.archivo_id = a.id
                          INNER JOIN notas n ON an.nota_id = n.id
                          WHERE an.archivo_id = ? AND an.nota_id = ? 
                          AND a.usuario_id = ? AND n.usuario_id = ?');
    $stmt->execute([$archivo_id, $nota_id, $usuario_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Vínculo no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('DELETE FROM archivo_nota WHERE archivo_id = ? AND nota_id = ?');
    $stmt->execute([$archivo_id, $nota_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Vínculo removido']);
}

// Desvincular archivo de evento
elseif ($action === 'desvincular_evento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    $evento_id = (int)($_POST['evento_id'] ?? 0);
    
    $stmt = $pdo->prepare('SELECT ae.id FROM archivo_evento ae 
                          INNER JOIN archivos a ON ae.archivo_id = a.id
                          INNER JOIN eventos e ON ae.evento_id = e.id
                          WHERE ae.archivo_id = ? AND ae.evento_id = ? 
                          AND a.usuario_id = ? AND e.usuario_id = ?');
    $stmt->execute([$archivo_id, $evento_id, $usuario_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Vínculo no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare('DELETE FROM archivo_evento WHERE archivo_id = ? AND evento_id = ?');
    $stmt->execute([$archivo_id, $evento_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Vínculo removido']);
}

elseif ($action === 'descargar') {
    $archivo_id = (int)($_GET['archivo_id'] ?? 0);
    
    // Verificar que el archivo pertenece al usuario
    $stmt = $pdo->prepare('SELECT id, nombre_original, nombre_archivo, ruta, tipo_mime FROM archivos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$archivo_id, $usuario_id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$archivo) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        exit;
    }
    
    // La ruta se guarda como ruta absoluta, usarla directamente
    $ruta_archivo = $archivo['ruta'];
    
    // Validar que el archivo existe
    if (!file_exists($ruta_archivo)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        error_log("Archivo no encontrado: $ruta_archivo");
        echo json_encode(['success' => false, 'error' => 'El archivo no existe en el servidor']);
        exit;
    }
    
    // Incrementar contador de descargas
    $stmt = $pdo->prepare('UPDATE archivos SET descargas = descargas + 1 WHERE id = ?');
    $stmt->execute([$archivo_id]);
    
    // Configurar headers para descarga
    $tipo_mime = $archivo['tipo_mime'] ?: 'application/octet-stream';
    header('Content-Type: ' . $tipo_mime);
    header('Content-Disposition: attachment; filename="' . basename($archivo['nombre_original']) . '"');
    header('Content-Length: ' . filesize($ruta_archivo));
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    // Enviar archivo
    readfile($ruta_archivo);
    exit;
}

else {
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}
