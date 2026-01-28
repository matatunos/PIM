<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('No autenticado');
}

// Ruta de la carpeta de la extensión
$extension_path = __DIR__ . '/../chrome-extension';

// Verificar que la carpeta existe
if (!is_dir($extension_path)) {
    http_response_code(404);
    die('Extensión no encontrada');
}

// Nombre del archivo ZIP
$zip_name = 'pim-chrome-extension.zip';
$zip_path = sys_get_temp_dir() . '/' . $zip_name;

// Crear ZIP
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Agregar archivos raíz (manifest.json, etc)
    $root_files = array_diff(scandir($extension_path), array('..', '.', 'images'));
    foreach ($root_files as $file) {
        $file_path = $extension_path . '/' . $file;
        if (is_file($file_path)) {
            $zip->addFile($file_path, $file);
        }
    }
    
    // Agregar carpeta images recursivamente
    $images_path = $extension_path . '/images';
    if (is_dir($images_path)) {
        // Agregar todos los archivos de la carpeta images
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
    
    readfile($zip_path);
    
    // Limpiar archivo temporal
    unlink($zip_path);
    
} else {
    http_response_code(500);
    die('Error al crear el ZIP');
}
?>
