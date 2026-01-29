<?php
/**
 * Proxy para Open WebUI API
 * Permite al chat del PIM comunicarse con Open WebUI y usar RAG
 */

require_once '../config/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Configuración de Open WebUI
$stmt = $pdo->prepare('SELECT clave, valor FROM configuracion_ia WHERE clave IN (?, ?)');
$stmt->execute(['openwebui_host', 'openwebui_port']);
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

$OPENWEBUI_HOST = $config['openwebui_host'] ?? '192.168.1.19';
$OPENWEBUI_PORT = $config['openwebui_port'] ?? '8080';
$OPENWEBUI_API_KEY = OPENWEBUI_API_KEY;
$OPENWEBUI_BASE = "http://{$OPENWEBUI_HOST}:{$OPENWEBUI_PORT}";

$endpoint = $_GET['endpoint'] ?? '';

switch ($endpoint) {
    case 'files':
        // Listar archivos disponibles
        $ch = curl_init("{$OPENWEBUI_BASE}/api/v1/files/");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$OPENWEBUI_API_KEY}",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $files = json_decode($response, true);
            // Filtrar solo archivos procesados
            $processedFiles = array_filter($files, fn($f) => ($f['data']['status'] ?? '') === 'completed');
            echo json_encode(array_values($processedFiles));
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error obteniendo archivos']);
        }
        break;
        
    case 'models':
        // Listar modelos disponibles
        $ch = curl_init("{$OPENWEBUI_BASE}/api/models");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$OPENWEBUI_API_KEY}"
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        break;
        
    case 'chat':
        // Chat con RAG usando Open WebUI
        $input = json_decode(file_get_contents('php://input'), true);
        
        $messages = $input['messages'] ?? [];
        $model = $input['model'] ?? 'llama3.2:3b';
        $files = $input['files'] ?? []; // IDs de archivos para RAG
        
        // Construir payload para Open WebUI
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true
        ];
        
        // Si hay archivos, añadir para RAG
        if (!empty($files)) {
            $payload['files'] = $files;
        }
        
        // Streaming response
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        $ch = curl_init("{$OPENWEBUI_BASE}/api/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$OPENWEBUI_API_KEY}",
                "Content-Type: application/json"
            ],
            CURLOPT_WRITEFUNCTION => function($ch, $data) {
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            }
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        break;
        
    case 'chat-simple':
        // Chat sin streaming (para pruebas)
        $input = json_decode(file_get_contents('php://input'), true);
        
        $payload = [
            'model' => $input['model'] ?? 'llama3.2:3b',
            'messages' => $input['messages'] ?? [],
            'stream' => false
        ];
        
        if (!empty($input['files'])) {
            $payload['files'] = $input['files'];
        }
        
        $ch = curl_init("{$OPENWEBUI_BASE}/api/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$OPENWEBUI_API_KEY}",
                "Content-Type: application/json"
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Endpoint no válido']);
}
