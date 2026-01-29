<?php
// Configuración general de la aplicación PIM

// ==========================================
// SEGURIDAD AVANZADA - Headers HTTP
// ==========================================

// Content Security Policy estricta
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com; ";
$csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; ";
$csp .= "font-src 'self' https://fonts.gstatic.com; ";
$csp .= "img-src 'self' data: https:; ";
$csp .= "connect-src 'self'; ";
$csp .= "frame-ancestors 'none'; ";
$csp .= "form-action 'self'; ";
$csp .= "base-uri 'self';";
header("Content-Security-Policy: $csp");

// Headers de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

// HSTS - Forzar HTTPS (solo activar si tienes SSL)
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
if ($isSecure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// ==========================================
// SEGURIDAD AVANZADA - Sesiones
// ==========================================

// Configurar cookies de sesión ultra-seguras
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Configuración adicional de sesión
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

session_start();

// ==========================================
// SEGURIDAD AVANZADA - Validación de sesión
// ==========================================

// Regenerar ID de sesión periódicamente
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
    $_SESSION['_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
} elseif (time() - $_SESSION['_created'] > 1800) { // 30 minutos
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// Validar que la sesión pertenece al mismo cliente (IP + User-Agent)
// Esto previene session hijacking
if (isset($_SESSION['user_id'])) {
    $session_ip = $_SESSION['_ip'] ?? '';
    $session_ua = $_SESSION['_ua'] ?? '';
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Si cambia la IP O el User-Agent, invalidar sesión (posible hijack)
    if ($session_ip !== $current_ip || $session_ua !== $current_ua) {
        // Loguear el intento sospechoso
        error_log("SECURITY: Posible session hijack detectado. User: {$_SESSION['user_id']}, IP original: $session_ip, IP actual: $current_ip");
        
        // Destruir sesión
        session_unset();
        session_destroy();
        session_start();
        
        // Redirigir al login
        if (!strpos($_SERVER['PHP_SELF'], '/auth/')) {
            header('Location: /app/auth/login.php?security=1');
            exit;
        }
    }
}

// Timeout de inactividad (30 minutos)
define('SESSION_TIMEOUT', 1800);
if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > SESSION_TIMEOUT)) {
    if (isset($_SESSION['user_id'])) {
        error_log("SECURITY: Sesión expirada por inactividad. User: {$_SESSION['user_id']}");
    }
    session_unset();
    session_destroy();
    session_start();
    
    if (!strpos($_SERVER['PHP_SELF'], '/auth/')) {
        header('Location: /app/auth/login.php?expired=1');
        exit;
    }
}
$_SESSION['_last_activity'] = time();

// Versión de la aplicación
define('PIM_VERSION', '2.5.0');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

// Rutas
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB

// URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . dirname($_SERVER['SCRIPT_NAME']);
define('BASE_URL', rtrim($base_url, '/'));

// Idioma por defecto
$lang = $_SESSION['lang'] ?? 'es';

// Incluir base de datos
require_once __DIR__ . '/database.php';

// Funciones auxiliares
function redirect($url) {
    header("Location: $url");
    exit();
}

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// ==========================================
// Protección CSRF
// ==========================================

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Obtener el token CSRF actual
 */
function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Generar campo hidden con token CSRF
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verificar token CSRF
 * @param string|null $token Token a verificar (si es null, usa $_POST['csrf_token'])
 * @return bool
 */
function csrf_verify($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Verificar CSRF y abortar si es inválido
 */
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        security_log('CSRF_FAIL', 'Token CSRF inválido en ' . $_SERVER['REQUEST_URI']);
        http_response_code(403);
        die('Error de seguridad: token CSRF inválido. <a href="javascript:history.back()">Volver</a>');
    }
}

// ==========================================
// SEGURIDAD AVANZADA - Logging de seguridad
// ==========================================

/**
 * Registrar evento de seguridad
 */
function security_log($event_type, $message, $user_id = null) {
    global $pdo;
    
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500);
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    // Log a archivo
    $log_message = date('Y-m-d H:i:s') . " | $event_type | IP: $ip | User: " . ($user_id ?? 'N/A') . " | $message | URI: $uri";
    error_log($log_message, 3, dirname(__DIR__) . '/logs/security.log');
    
    // Log a base de datos si está disponible
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, message, uri, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$event_type, $user_id, $ip, $user_agent, $message, $uri]);
        } catch (Exception $e) {
            // Si falla, al menos queda en el archivo
        }
    }
}

// ==========================================
// SEGURIDAD AVANZADA - Sanitización de inputs
// ==========================================

/**
 * Sanitizar string para prevenir XSS
 */
function sanitize_string($input) {
    if (is_array($input)) {
        return array_map('sanitize_string', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizar para salida HTML
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validar y sanitizar email
 */
function sanitize_email($email) {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

/**
 * Validar y sanitizar entero
 */
function sanitize_int($input) {
    return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : false;
}

/**
 * Sanitizar nombre de archivo
 */
function sanitize_filename($filename) {
    // Remover caracteres peligrosos
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    // Remover doble punto para prevenir directory traversal
    $filename = str_replace('..', '', $filename);
    // Limitar longitud
    return substr($filename, 0, 200);
}

/**
 * Validar URL
 */
function sanitize_url($url) {
    $url = filter_var(trim($url), FILTER_SANITIZE_URL);
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        // Solo permitir http/https
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }
    }
    return false;
}

// ==========================================
// SEGURIDAD AVANZADA - Rate Limiting Global
// ==========================================

/**
 * Rate limiting por IP para cualquier acción
 */
function rate_limit($action, $max_attempts = 30, $window_seconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window_seconds];
    }
    
    // Resetear si pasó la ventana
    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window_seconds];
    }
    
    $_SESSION[$key]['count']++;
    
    if ($_SESSION[$key]['count'] > $max_attempts) {
        security_log('RATE_LIMIT', "Rate limit excedido para acción: $action");
        return false;
    }
    
    return true;
}

// ==========================================
// SEGURIDAD AVANZADA - Validación de contraseñas
// ==========================================

/**
 * Verificar si una contraseña cumple los requisitos de seguridad
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'La contraseña debe tener al menos 12 caracteres';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una mayúscula';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una minúscula';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos un número';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos un carácter especial';
    }
    
    // Verificar contraseñas comunes
    $common_passwords = ['password', '123456', 'qwerty', 'admin', 'letmein', 'welcome', 'monkey', 'dragon'];
    if (in_array(strtolower($password), $common_passwords)) {
        $errors[] = 'Esta contraseña es demasiado común';
    }
    
    return ['valid' => empty($errors), 'errors' => $errors];
}

// ==========================================
// SEGURIDAD AVANZADA - Detección de ataques
// ==========================================

/**
 * Detectar posible SQL injection en inputs
 */
function detect_sqli($input) {
    if (!is_string($input)) return false;
    
    $patterns = [
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
        '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
        '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
        '/((\%27)|(\'))union/i',
        '/exec(\s|\+)+(s|x)p\w+/i',
        '/union(.*)select/i',
        '/select(.*)from/i',
        '/insert(.*)into/i',
        '/drop(.*)table/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            security_log('SQLI_ATTEMPT', "Posible SQLi detectado: " . substr($input, 0, 100));
            return true;
        }
    }
    return false;
}

/**
 * Detectar posible XSS en inputs
 */
function detect_xss($input) {
    if (!is_string($input)) return false;
    
    $patterns = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<\s*img[^>]+onerror/i',
        '/<\s*svg[^>]+onload/i',
        '/data:\s*text\/html/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            security_log('XSS_ATTEMPT', "Posible XSS detectado: " . substr($input, 0, 100));
            return true;
        }
    }
    return false;
}

/**
 * Escanear todos los inputs por ataques
 */
function scan_inputs() {
    $suspicious = false;
    
    foreach ($_GET as $key => $value) {
        if (detect_sqli($value) || detect_xss($value)) {
            $suspicious = true;
        }
    }
    
    foreach ($_POST as $key => $value) {
        if (is_string($value) && (detect_sqli($value) || detect_xss($value))) {
            $suspicious = true;
        }
    }
    
    if ($suspicious) {
        // Opcionalmente bloquear la request
        // http_response_code(403);
        // die('Request blocked');
    }
    
    return !$suspicious;
}

// Ejecutar escaneo automático
scan_inputs();
