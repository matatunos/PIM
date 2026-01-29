<?php
/**
 * API para verificar actualizaciones de PIM desde GitHub
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../version.php';

$action = $_GET['action'] ?? 'check';

if ($action === 'check') {
    $cacheFile = '/tmp/pim_update_check.json';
    $cacheTime = 3600; // 1 hora
    
    // Usar caché si existe y es reciente
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        echo file_get_contents($cacheFile);
        exit;
    }
    
    $result = [
        'current_version' => PIM_VERSION,
        'latest_version' => null,
        'update_available' => false,
        'release_url' => null,
        'release_notes' => null,
        'github_url' => PIM_GITHUB_URL,
        'error' => null
    ];
    
    // Primero intentar con tags (más confiable que releases)
    $url = 'https://api.github.com/repos/' . PIM_GITHUB_REPO . '/tags';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'PIM-Update-Checker',
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response) {
        $tags = json_decode($response, true);
        if ($tags && !empty($tags) && is_array($tags)) {
            // Ordenar tags por versión semántica
            usort($tags, function($a, $b) {
                $va = ltrim($a['name'], 'v');
                $vb = ltrim($b['name'], 'v');
                return version_compare($vb, $va);
            });
            
            $latestTag = $tags[0]['name'];
            $latestVersion = ltrim($latestTag, 'v');
            $result['latest_version'] = $latestVersion;
            $result['release_url'] = PIM_GITHUB_URL . '/releases/tag/' . $latestTag;
            
            if (version_compare($latestVersion, PIM_VERSION, '>')) {
                $result['update_available'] = true;
            }
        }
    } else {
        $result['error'] = 'No se pudo conectar a GitHub' . ($curlError ? ': ' . $curlError : '');
    }
    
    // Guardar en caché
    @file_put_contents($cacheFile, json_encode($result));
    
    echo json_encode($result);
    exit;
}

if ($action === 'version') {
    echo json_encode([
        'version' => PIM_VERSION,
        'github_url' => PIM_GITHUB_URL
    ]);
    exit;
}

echo json_encode(['error' => 'Acción no válida']);
