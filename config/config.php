<?php
// Configuración general de la aplicación PIM
session_start();

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
