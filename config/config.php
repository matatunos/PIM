<?php
// Configuración general de la aplicación PIM

// Headers de seguridad (antes de cualquier output)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Configurar cookies de sesión seguras
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Regenerar ID de sesión periódicamente para prevenir session fixation
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
} elseif (time() - $_SESSION['_created'] > 1800) { // 30 minutos
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// Versión de la aplicación
define('PIM_VERSION', '2.2.0');

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
        http_response_code(403);
        die('Error de seguridad: token CSRF inválido. <a href="javascript:history.back()">Volver</a>');
    }
}
