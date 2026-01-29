<?php
// Proxy para Ollama API (evita CORS)
// Permite: POST /api/ollama-proxy.php?endpoint=chat  (body: JSON)
//          GET  /api/ollama-proxy.php?endpoint=tags

header('Content-Type: application/json');

$ollama_host = '192.168.1.19';
$ollama_port = '11434';
$base_url = "http://$ollama_host:$ollama_port/api/";

$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$allowed = ['chat', 'tags'];
if (!in_array($endpoint, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Endpoint no permitido']);
    exit;
}

$url = $base_url . $endpoint;

$options = [
    'http' => [
        'method' => $method,
        'header' => "Content-Type: application/json\r\n",
        'ignore_errors' => true
    ]
];

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    $options['http']['content'] = $body;
}

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar a Ollama']);
    exit;
}

// Pasar código de estado de Ollama
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\d+\.\d+ (\d+)/', $header, $matches)) {
            http_response_code((int)$matches[1]);
            break;
        }
    }
}

echo $response;
