<?php

/**
 * Sistema de Auditoría y Logging
 * Registra todas las acciones de los usuarios
 */

function logAction($tipo_evento, $accion, $descripcion = '', $exitoso = true) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $usuario_id = $_SESSION['user_id'];
    $ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO logs_acceso (usuario_id, ip, ip_address, user_agent, accion, tipo_evento, descripcion, exitoso)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        return $stmt->execute([
            $usuario_id,
            $ip,
            $ip,
            $user_agent,
            $accion,
            $tipo_evento,
            $descripcion,
            $exitoso ? 1 : 0
        ]);
    } catch (Exception $e) {
        error_log('Error registrando auditoría: ' . $e->getMessage());
        return false;
    }
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    }
}

// Tipos de eventos
const AUDIT_TIPOS = [
    'login' => 'Acceso',
    'logout' => 'Cierre de Sesión',
    'crear' => 'Creación',
    'editar' => 'Edición',
    'eliminar' => 'Eliminación',
    'restaurar' => 'Restauración',
    'descargar' => 'Descarga',
    'subir' => 'Subida',
    'importar' => 'Importación',
    'exportar' => 'Exportación',
    'backup' => 'Respaldo',
    'admin' => 'Admin',
    'sync' => 'Sincronización',
    'cron' => 'Tarea Programada'
];

function getTipoEventoLabel($tipo) {
    return AUDIT_TIPOS[$tipo] ?? ucfirst($tipo);
}

function getTipoEventoColor($tipo) {
    $colores = [
        'login' => '#28a745',      // Verde
        'logout' => '#6c757d',     // Gris
        'crear' => '#17a2b8',      // Cyan
        'editar' => '#ffc107',     // Amarillo
        'eliminar' => '#dc3545',   // Rojo
        'restaurar' => '#20c997',  // Verde claro
        'descargar' => '#007bff',  // Azul
        'subir' => '#007bff',      // Azul
        'importar' => '#6f42c1',   // Púrpura
        'exportar' => '#6f42c1',   // Púrpura
        'backup' => '#e83e8c',     // Rosa
        'admin' => '#fd7e14',      // Naranja
        'sync' => '#00bcd4',       // Cyan claro
        'cron' => '#795548'        // Marrón
    ];
    return $colores[$tipo] ?? '#6c757d';
}

function getTipoEventoIcon($tipo) {
    $iconos = [
        'login' => 'fas fa-sign-in-alt',
        'logout' => 'fas fa-sign-out-alt',
        'crear' => 'fas fa-plus-circle',
        'editar' => 'fas fa-edit',
        'eliminar' => 'fas fa-trash',
        'restaurar' => 'fas fa-undo',
        'descargar' => 'fas fa-download',
        'subir' => 'fas fa-upload',
        'importar' => 'fas fa-file-import',
        'exportar' => 'fas fa-file-export',
        'backup' => 'fas fa-database',
        'admin' => 'fas fa-shield-alt',
        'sync' => 'fas fa-sync-alt',
        'cron' => 'fas fa-clock'
    ];
    return $iconos[$tipo] ?? 'fas fa-info-circle';
}

/**
 * Log para scripts CLI (sin sesión de usuario)
 * Usado por cron jobs y scripts de sincronización
 */
function logSystemAction($pdo, $tipo_evento, $accion, $descripcion = '', $exitoso = true, $usuario_id = 1) {
    try {
        $stmt = $pdo->prepare('
            INSERT INTO logs_acceso (usuario_id, ip, ip_address, user_agent, accion, tipo_evento, descripcion, exitoso)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        return $stmt->execute([
            $usuario_id,
            'SYSTEM',
            'SYSTEM',
            'PIM-CLI/' . php_uname('n'),
            $accion,
            $tipo_evento,
            $descripcion,
            $exitoso ? 1 : 0
        ]);
    } catch (Exception $e) {
        error_log('[AUDIT] Error registrando: ' . $e->getMessage());
        return false;
    }
}

/**
 * Escribe en archivo de log con timestamp
 */
function writeLog($message, $level = 'INFO', $logFile = null) {
    $logFile = $logFile ?? '/var/log/pim-system.log';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message\n";
    
    // Crear directorio si no existe
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
?>
