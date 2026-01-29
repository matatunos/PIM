<?php
// Iniciar sesión PHP si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
/**
 * API de documentos para Open WebUI
 * Endpoint: /api/ai-documents.php
 * 
 * Acciones:
 * - get_documents: Lista documentos/archivos del usuario
 * - get_notes: Lista notas del usuario
 * - search: Búsqueda fulltext en documentos y notas
 * 
 * Autenticación: Sesión PHP requerida
 * Rate limiting: 10 peticiones por minuto
 */


require_once '../config/config.php';

// Permitir acceso sin sesión solo desde localhost con api_key
$ALLOW_LOCAL_API_KEY = true;
$LOCAL_API_KEY = getenv('LOCAL_API_KEY') ?: 'localtest';
$is_local = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');
$api_key = $_GET['api_key'] ?? '';

if ($ALLOW_LOCAL_API_KEY && $is_local && $api_key === $LOCAL_API_KEY) {
    // Usuario para sync: admin (id=1) o el primero
    $stmt = $pdo->query('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch();
    $_SESSION['user_id'] = $row ? $row['id'] : 1;
    $_SESSION['username'] = 'sync-script';
    $_SESSION['rol'] = 'admin';
} else {
    require_once '../includes/auth_check.php';
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Rate limiting
$rate_limit_key = 'api_ai_' . $_SESSION['user_id'];
$_SESSION['rate_limit'] = $_SESSION['rate_limit'] ?? [];

if (!isset($_SESSION['rate_limit'][$rate_limit_key])) {
    $_SESSION['rate_limit'][$rate_limit_key] = [];
}

$now = time();
$_SESSION['rate_limit'][$rate_limit_key] = array_filter(
    $_SESSION['rate_limit'][$rate_limit_key],
    fn($timestamp) => $now - $timestamp < 60
);

if (count($_SESSION['rate_limit'][$rate_limit_key]) >= 10) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded (10 requests/min)',
        'retry_after' => 60
    ]);
    exit;
}

$_SESSION['rate_limit'][$rate_limit_key][] = $now;

$action = $_GET['action'] ?? '';
$usuario_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_documents':
            getDocuments($pdo, $usuario_id);
            break;
        
        case 'get_notes':
            getNotes($pdo, $usuario_id);
            break;
        
        case 'search':
            searchContent($pdo, $usuario_id);
            break;
        
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Allowed: get_documents, get_notes, search'
            ]);
            break;
    }
} catch (Exception $e) {
    logSecurityEvent($pdo, 'AI_API_ERROR', $usuario_id, 'API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

/**
 * Obtiene lista de documentos/archivos del usuario
 */
function getDocuments($pdo, $usuario_id) {
    try {
        if (!$usuario_id) {
            echo json_encode([
                'success' => true,
                'total' => 0,
                'data' => []
            ]);
            return;
        }
        $stmt = $pdo->prepare('
            SELECT 
                id,
                nombre_original as nombre,
                tipo_mime,
                extension,
                tamano,
                descripcion,
                creado_en,
                actualizado_en
            FROM archivos
            WHERE usuario_id = ?
            ORDER BY actualizado_en DESC
            LIMIT 100
        ');
        $stmt->execute([$usuario_id]);
        $documentos = $stmt->fetchAll();
        // Convertir tamaño a formato legible
        foreach ($documentos as &$doc) {
            $doc['tamano_formateado'] = formatBytes($doc['tamano']);
        }
        echo json_encode([
            'success' => true,
            'total' => count($documentos),
            'data' => $documentos
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'PDO Exception: ' . $e->getMessage(),
            'total' => 0,
            'data' => []
        ]);
    }
}

/**
 * Obtiene lista de notas del usuario
 */
function getNotes($pdo, $usuario_id) {
    $stmt = $pdo->prepare('
        SELECT 
            id,
            titulo,
            contenido,
            creado_en,
            actualizado_en,
            fijada,
            archivada
        FROM notas
        WHERE usuario_id = ? AND archivada = 0
        ORDER BY actualizado_en DESC
        LIMIT 100
    ');
    $stmt->execute([$usuario_id]);
    $notas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'total' => count($notas),
        'data' => $notas
    ]);
}

/**
 * Búsqueda fulltext en documentos y notas
 */
function searchContent($pdo, $usuario_id) {
    $q = trim($_GET['q'] ?? '');
    
    if (strlen($q) < 2) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Search query must be at least 2 characters'
        ]);
        return;
    }
    
    // Sanitizar búsqueda para FULLTEXT
    $search_term = '%' . addcslashes($q, '%_') . '%';
    
    // Búsqueda en notas
    $stmt = $pdo->prepare('
        SELECT 
            id,
            titulo,
            contenido,
            creado_en,
            actualizado_en,
            "nota" as tipo
        FROM notas
        WHERE usuario_id = ? AND archivada = 0
        AND (titulo LIKE ? OR contenido LIKE ?)
        ORDER BY actualizado_en DESC
        LIMIT 50
    ');
    $stmt->execute([$usuario_id, $search_term, $search_term]);
    $notas = $stmt->fetchAll();
    
    // Búsqueda en archivos
    $stmt = $pdo->prepare('
        SELECT 
            id,
            nombre_original as titulo,
            descripcion as contenido,
            creado_en,
            actualizado_en,
            "documento" as tipo
        FROM archivos
        WHERE usuario_id = ?
        AND (nombre_original LIKE ? OR descripcion LIKE ?)
        ORDER BY actualizado_en DESC
        LIMIT 50
    ');
    $stmt->execute([$usuario_id, $search_term, $search_term]);
    $documentos = $stmt->fetchAll();
    
    $resultados = array_merge($notas, $documentos);
    
    // Ordenar por fecha más reciente
    usort($resultados, fn($a, $b) => strtotime($b['actualizado_en']) - strtotime($a['actualizado_en']));
    
    echo json_encode([
        'success' => true,
        'query' => $q,
        'total' => count($resultados),
        'data' => array_slice($resultados, 0, 100)
    ]);
}

/**
 * Convierte bytes a formato legible
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Registra eventos de seguridad
 */
function logSecurityEvent($pdo, $event_type, $user_id, $message) {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        $stmt = $pdo->prepare('
            INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, message, uri)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$event_type, $user_id, $ip, $user_agent, $message, $uri]);
    } catch (Exception $e) {
        error_log('Failed to log security event: ' . $e->getMessage());
    }
}
?>
