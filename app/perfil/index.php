<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = $error = '';

// Obtener datos del usuario
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

// Si no se encuentra el usuario, redirigir al login
if (!$usuario) {
    header('Location: /app/auth/login.php');
    exit;
}

// Procesar descarga de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descargar_datos'])) {
    // Crear ZIP temporal
    $zip_dir = '/tmp';
    $zip_file = $zip_dir . '/pim_export_' . time() . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) === true) {
        // 1. Exportar notas
        $stmt = $pdo->prepare('SELECT * FROM notas WHERE usuario_id = ? AND borrado_en IS NULL ORDER BY creado_en DESC');
        $stmt->execute([$usuario_id]);
        $notas = $stmt->fetchAll();
        $json_notas = json_encode($notas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('notas.json', $json_notas);
        
        // 2. Exportar tareas
        $stmt = $pdo->prepare('SELECT * FROM tareas WHERE usuario_id = ? AND borrado_en IS NULL ORDER BY creado_en DESC');
        $stmt->execute([$usuario_id]);
        $tareas = $stmt->fetchAll();
        $json_tareas = json_encode($tareas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('tareas.json', $json_tareas);
        
        // 3. Exportar eventos
        $stmt = $pdo->prepare('SELECT * FROM eventos WHERE usuario_id = ? AND borrado_en IS NULL ORDER BY fecha_inicio DESC');
        $stmt->execute([$usuario_id]);
        $eventos = $stmt->fetchAll();
        $json_eventos = json_encode($eventos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('eventos.json', $json_eventos);
        
        // 4. Exportar contactos
        $stmt = $pdo->prepare('SELECT * FROM contactos WHERE usuario_id = ? AND borrado_en IS NULL ORDER BY creado_en DESC');
        $stmt->execute([$usuario_id]);
        $contactos = $stmt->fetchAll();
        $json_contactos = json_encode($contactos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('contactos.json', $json_contactos);
        
        // 5. Exportar links
        $stmt = $pdo->prepare('SELECT * FROM links WHERE usuario_id = ? ORDER BY creado_en DESC');
        $stmt->execute([$usuario_id]);
        $links = $stmt->fetchAll();
        $json_links = json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('links.json', $json_links);
        
        // 6. Metadata
        $metadata = [
            'export_date' => date('Y-m-d H:i:s'),
            'username' => $usuario['username'],
            'email' => $usuario['email'],
            'statistics' => [
                'notas' => count($notas),
                'tareas' => count($tareas),
                'eventos' => count($eventos),
                'contactos' => count($contactos),
                'links' => count($links)
            ]
        ];
        $json_metadata = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('metadata.json', $json_metadata);
        
        $zip->close();
        
        // Descargar archivo
        if (file_exists($zip_file)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="pim_datos_' . date('Y-m-d') . '.zip"');
            header('Content-Length: ' . filesize($zip_file));
            readfile($zip_file);
            unlink($zip_file);
            exit;
        }
    } else {
        $error = 'No se pudo crear el archivo ZIP';
    }
}

// Procesar importación de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $zip_file = $file['tmp_name'];
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file) === true) {
        $temp_dir = '/tmp/pim_import_' . time();
        mkdir($temp_dir);
        $zip->extractTo($temp_dir);
        $zip->close();
        
        $imported_count = 0;
        
        // Importar notas
        if (file_exists($temp_dir . '/notas.json')) {
            $notas = json_decode(file_get_contents($temp_dir . '/notas.json'), true);
            foreach ($notas as $nota) {
                $stmt = $pdo->prepare('INSERT INTO notas (usuario_id, titulo, contenido, color, creado_en, actualizado_en) VALUES (?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$usuario_id, $nota['titulo'] ?? '', $nota['contenido'] ?? '', $nota['color'] ?? '#a8dadc', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')])) {
                    $imported_count++;
                }
            }
        }
        
        // Importar tareas
        if (file_exists($temp_dir . '/tareas.json')) {
            $tareas = json_decode(file_get_contents($temp_dir . '/tareas.json'), true);
            foreach ($tareas as $tarea) {
                $stmt = $pdo->prepare('INSERT INTO tareas (usuario_id, titulo, descripcion, completada, prioridad, fecha_vencimiento, lista, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$usuario_id, $tarea['titulo'] ?? '', $tarea['descripcion'] ?? '', $tarea['completada'] ?? 0, $tarea['prioridad'] ?? 'media', $tarea['fecha_vencimiento'] ?? null, $tarea['lista'] ?? 'General', date('Y-m-d H:i:s')])) {
                    $imported_count++;
                }
            }
        }
        
        // Importar eventos
        if (file_exists($temp_dir . '/eventos.json')) {
            $eventos = json_decode(file_get_contents($temp_dir . '/eventos.json'), true);
            foreach ($eventos as $evento) {
                $stmt = $pdo->prepare('INSERT INTO eventos (usuario_id, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, color, todo_el_dia, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$usuario_id, $evento['titulo'] ?? '', $evento['descripcion'] ?? '', $evento['fecha_inicio'] ?? date('Y-m-d H:i:s'), $evento['fecha_fin'] ?? null, $evento['ubicacion'] ?? '', $evento['color'] ?? '#a8dadc', $evento['todo_el_dia'] ?? 0, date('Y-m-d H:i:s')])) {
                    $imported_count++;
                }
            }
        }
        
        // Importar contactos
        if (file_exists($temp_dir . '/contactos.json')) {
            $contactos = json_decode(file_get_contents($temp_dir . '/contactos.json'), true);
            foreach ($contactos as $contacto) {
                $stmt = $pdo->prepare('INSERT INTO contactos (usuario_id, nombre, apellido, email, telefono, telefono_alt, direccion, ciudad, pais, empresa, cargo, notas, favorito, avatar_color, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$usuario_id, $contacto['nombre'] ?? '', $contacto['apellido'] ?? '', $contacto['email'] ?? '', $contacto['telefono'] ?? '', $contacto['telefono_alt'] ?? '', $contacto['direccion'] ?? '', $contacto['ciudad'] ?? '', $contacto['pais'] ?? '', $contacto['empresa'] ?? '', $contacto['cargo'] ?? '', $contacto['notas'] ?? '', $contacto['favorito'] ?? 0, $contacto['avatar_color'] ?? '#a8dadc', date('Y-m-d H:i:s')])) {
                    $imported_count++;
                }
            }
        }
        
        // Importar links
        if (file_exists($temp_dir . '/links.json')) {
            $links = json_decode(file_get_contents($temp_dir . '/links.json'), true);
            foreach ($links as $link) {
                $stmt = $pdo->prepare('INSERT INTO links (usuario_id, titulo, url, descripcion, icono, categoria, color, favorito, orden, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$usuario_id, $link['titulo'] ?? '', $link['url'] ?? '', $link['descripcion'] ?? '', $link['icono'] ?? 'link', $link['categoria'] ?? 'General', $link['color'] ?? '#a8dadc', $link['favorito'] ?? 0, $link['orden'] ?? 0, date('Y-m-d H:i:s')])) {
                    $imported_count++;
                }
            }
        }
        
        $mensaje = "Se importaron correctamente $imported_count elementos";
        
        // Limpiar
        array_map('unlink', glob($temp_dir . '/*'));
        rmdir($temp_dir);
    } else {
        $error = 'El archivo ZIP no es válido o está corrupto';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-user"></i> Mi Perfil</h1>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: var(--spacing-lg);">
                    <!-- Sidebar del perfil -->
                    <div>
                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="width: 120px; height: 120px; background: <?= htmlspecialchars($usuario['avatar_color'] ?? '#a8dadc') ?>; border-radius: 50%; margin: 0 auto var(--spacing-lg) auto; display: flex; align-items: center; justify-content: center; color: white; font-size: 3em;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h2 style="margin: var(--spacing-md) 0;"><?= htmlspecialchars($usuario['nombre_completo'] ?? $usuario['username']) ?></h2>
                                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">@<?= htmlspecialchars($usuario['username']) ?></p>
                                
                                <div style="border-top: 1px solid var(--border-color); padding-top: var(--spacing-lg); margin-top: var(--spacing-lg);">
                                    <p style="margin: var(--spacing-sm) 0; color: var(--text-secondary);">
                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($usuario['email']) ?>
                                    </p>
                                    <p style="margin: var(--spacing-sm) 0; color: var(--text-secondary);">
                                        <i class="fas fa-user-tag"></i> <?= ucfirst($usuario['rol']) ?>
                                    </p>
                                    <p style="margin: var(--spacing-sm) 0; color: var(--text-secondary);">
                                        <i class="fas fa-calendar"></i> Desde <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contenido principal -->
                    <div>
                        <!-- Sección de Configuración -->
                        <div class="card" style="margin-bottom: var(--spacing-lg);">
                            <div class="card-header">
                                <h3 style="margin: 0;"><i class="fas fa-cog"></i> Configuración</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                                    <a href="cambiar-contrasena.php" class="card" style="padding: var(--spacing-lg); text-decoration: none; color: inherit; transition: all 0.3s;">
                                        <div style="font-size: 2em; color: var(--primary); margin-bottom: var(--spacing-md);"><i class="fas fa-key"></i></div>
                                        <h4 style="margin: 0 0 var(--spacing-sm) 0;">Contraseña</h4>
                                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">Cambiar tu contraseña</p>
                                    </a>
                                    <a href="2fa.php" class="card" style="padding: var(--spacing-lg); text-decoration: none; color: inherit; transition: all 0.3s;">
                                        <div style="font-size: 2em; color: var(--success); margin-bottom: var(--spacing-md);"><i class="fas fa-shield-alt"></i></div>
                                        <h4 style="margin: 0 0 var(--spacing-sm) 0;">Autenticación 2FA</h4>
                                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">Verificación en dos pasos</p>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sección de Privacidad -->
                        <div class="card" id="export">
                            <div class="card-header">
                                <h3 style="margin: 0;"><i class="fas fa-download"></i> Privacidad y Datos</h3>
                            </div>
                            <div class="card-body">
                                <div style="background: #f0f8ff; padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); border-left: 4px solid var(--info);">
                                    <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Descarga tus datos</h4>
                                    <p>Descarga un archivo ZIP con todos tus datos personales en formato JSON. Incluye:</p>
                                    <ul style="margin: 0; padding-left: 20px;">
                                        <li>Notas</li>
                                        <li>Tareas</li>
                                        <li>Eventos</li>
                                        <li>Contactos</li>
                                        <li>Enlaces</li>
                                        <li>Metadatos de exportación</li>
                                    </ul>
                                </div>
                                
                                <form method="POST" onsubmit="return confirm('¿Descargar todos tus datos? Se generará un archivo ZIP con toda tu información.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="descargar_datos" value="1" class="btn btn-primary">
                                        <i class="fas fa-download"></i> Descargar mis datos
                                    </button>
                                </form>
                                
                                <hr style="margin: var(--spacing-lg) 0; border: none; border-top: 1px solid var(--border-color);">
                                
                                <h4 style="margin: var(--spacing-md) 0; font-size: 1.1em;">Importar datos</h4>
                                <div style="background-color: var(--bg-secondary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-bottom: var(--spacing-md);">
                                    <p>Carga un archivo ZIP anteriormente exportado para restaurar tus datos. Se importarán:</p>
                                    <ul style="margin: 0; padding-left: 20px;">
                                        <li>Notas</li>
                                        <li>Tareas</li>
                                        <li>Eventos</li>
                                        <li>Contactos</li>
                                        <li>Enlaces</li>
                                    </ul>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('¿Importar datos desde el archivo ZIP? Se agregarán nuevos elementos a tu cuenta.');">
                                    <?= csrf_field() ?>
                                    <div style="display: flex; gap: var(--spacing-md); align-items: flex-end;">
                                        <div style="flex: 1;">
                                            <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: bold;">Archivo ZIP</label>
                                            <input type="file" name="import_file" accept=".zip" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                        </div>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-upload"></i> Importar datos
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>