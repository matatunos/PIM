<?php
/**
 * Descarga de extensión de Chrome personalizada para el usuario
 * La extensión viene preconfigurada con:
 * - URL del servidor PIM
 * - Token de autenticación del usuario
 */

require_once '../config/config.php';
require_once '../includes/auth_check.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('No autenticado');
}

$usuario_id = $_SESSION['user_id'];

// Obtener datos del usuario
$stmt = $pdo->prepare('SELECT username, api_token FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario || empty($usuario['api_token'])) {
    // Generar token si no existe
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('UPDATE usuarios SET api_token = ? WHERE id = ?');
    $stmt->execute([$token, $usuario_id]);
    $usuario['api_token'] = $token;
}

// Detectar la URL base del servidor
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname(dirname($_SERVER['REQUEST_URI']));
$base_url = rtrim($protocol . '://' . $host . $path, '/');

// Ruta de la carpeta de la extensión
$extension_path = __DIR__ . '/../chrome-extension';

// Verificar que la carpeta existe
if (!is_dir($extension_path)) {
    http_response_code(404);
    die('Extensión no encontrada');
}

// Nombre del archivo ZIP personalizado
$zip_name = 'pim-extension-' . $usuario['username'] . '.zip';
$zip_path = sys_get_temp_dir() . '/' . uniqid('pim_ext_') . '.zip';

// Crear ZIP
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Agregar archivos de la extensión
    $root_files = array_diff(scandir($extension_path), array('..', '.', 'images'));
    foreach ($root_files as $file) {
        $file_path = $extension_path . '/' . $file;
        if (is_file($file_path)) {
            // Para popup.js, inyectar la configuración preestablecida
            if ($file === 'popup.js') {
                $content = file_get_contents($file_path);
                $config_injection = "// ═══════════════════════════════════════════════════════════
// Configuración preestablecida para: {$usuario['username']}
// Servidor: {$base_url}
// ═══════════════════════════════════════════════════════════
const PIM_PRECONFIG = {
    url: '{$base_url}',
    token: '{$usuario['api_token']}',
    username: '{$usuario['username']}'
};
// ═══════════════════════════════════════════════════════════

";
                $content = $config_injection . $content;
                $zip->addFromString($file, $content);
            } else {
                $zip->addFile($file_path, $file);
            }
        }
    }
    
    // Agregar carpeta images
    $images_path = $extension_path . '/images';
    if (is_dir($images_path)) {
        $images_files = scandir($images_path);
        foreach ($images_files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file_path = $images_path . '/' . $file;
                if (is_file($file_path)) {
                    $zip->addFile($file_path, 'images/' . $file);
                }
            }
        }
    }
    
    $zip->close();
    
    // Enviar archivo
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_name . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Cache-Control: no-cache, must-revalidate');
    
    readfile($zip_path);
    
    // Limpiar archivo temporal
    unlink($zip_path);
    
} else {
    http_response_code(500);
    die('Error al crear el ZIP');
}
