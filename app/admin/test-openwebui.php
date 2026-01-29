<?php
/**
 * Endpoint para probar conexión con Open WebUI
 * Ruta: /app/admin/test-openwebui.php
 */

require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

// Solo admins pueden acceder
if ($_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$host = $_GET['host'] ?? '';
$port = $_GET['port'] ?? '';

if (empty($host) || empty($port)) {
    echo json_encode(['success' => false, 'error' => 'Host y puerto requeridos']);
    exit;
}

// Validar host (IP o hostname)
if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
    echo json_encode(['success' => false, 'error' => 'Host inválido']);
    exit;
}

// Validar puerto
$port = intval($port);
if ($port < 1 || $port > 65535) {
    echo json_encode(['success' => false, 'error' => 'Puerto inválido']);
    exit;
}

// Intentar conectar
$test_urls = [
    "http://$host:$port/api/health",
    "http://$host:$port/",
    "http://$host:$port/api/models"
];

$connected = false;
$last_error = '';

foreach ($test_urls as $url) {
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]));
    
    if ($response !== false) {
        $connected = true;
        break;
    } else {
        $last_error = 'Connection timeout';
    }
}

if ($connected) {
    echo json_encode([
        'success' => true,
        'message' => "Conectado a Open WebUI en $host:$port"
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => "No se pudo conectar a $host:$port - $last_error"
    ]);
}
?>
