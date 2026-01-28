<?php
require_once 'config/config.php';
require_once 'includes/totp.php';

echo "🚀 Generando datos de prueba (10 años de actividad)...\n\n";

set_time_limit(0);

try {
    // 1. Usuarios
    echo "📝 Creando usuarios...\n";
    $usuarios = [
        ['username' => 'admin', 'email' => 'admin@pim.local', 'nombre' => 'Administrador', 'rol' => 'admin'],
        ['username' => 'nacho', 'email' => 'nacho@pim.local', 'nombre' => 'Nacho García', 'rol' => 'user'],
        ['username' => 'maria', 'email' => 'maria@pim.local', 'nombre' => 'María López', 'rol' => 'user'],
        ['username' => 'juan', 'email' => 'juan@pim.local', 'nombre' => 'Juan Martínez', 'rol' => 'user'],
        ['username' => 'pedro', 'email' => 'pedro@pim.local', 'nombre' => 'Pedro Rodríguez', 'rol' => 'user'],
    ];
    
    $user_ids = [];
    foreach ($usuarios as $u) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $stmt->execute([$u['username']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            $secret = TOTP::generateSecret();
            $stmt = $pdo->prepare('INSERT INTO usuarios (username, email, password, nombre_completo, rol, totp_secret, totp_enabled, activo) 
                                 VALUES (?, ?, ?, ?, ?, ?, 1, 1)');
            $stmt->execute([
                $u['username'],
                $u['email'],
                password_hash('password123', PASSWORD_BCRYPT),
                $u['nombre'],
                $u['rol'],
                $secret
            ]);
            $user_ids[$u['username']] = $pdo->lastInsertId();
            echo "  ✓ {$u['username']}\n";
        } else {
            $user_ids[$u['username']] = $existing['id'];
            echo "  ✓ {$u['username']} (ya existe)\n";
        }
    }
    
    // 2. Notas
    echo "\n📋 Creando 500 notas...\n";
    $temas = ['Reunión cliente', 'Ideas proyecto', 'Bugfix', 'Investigación', 'Marketing', 'Feedback', 'Documentación', 'Análisis'];
    $inserted = 0;
    for ($i = 0; $i < 500; $i++) {
        $user_id = array_values($user_ids)[rand(0, count($user_ids)-1)];
        $tema = $temas[rand(0, count($temas)-1)] . ' #' . $i;
        $fecha_days = rand(1, 3650);
        
        $stmt = $pdo->prepare('INSERT INTO notas (usuario_id, titulo, contenido) VALUES (?, ?, ?)');
        if ($stmt->execute([$user_id, $tema, 'Contenido de la nota ' . $i])) {
            $inserted++;
        }
    }
    echo "  ✓ $inserted notas creadas\n";
    
    // 3. Tareas
    echo "\n✅ Creando 300 tareas...\n";
    $prioridades = ['baja', 'media', 'alta', 'urgente'];
    $listas = ['Personal', 'Trabajo', 'Compras', 'Ideas'];
    $inserted = 0;
    for ($i = 0; $i < 300; $i++) {
        $user_id = array_values($user_ids)[rand(0, count($user_ids)-1)];
        $completada = rand(0, 100) > 60 ? 1 : 0;
        
        $stmt = $pdo->prepare('INSERT INTO tareas (usuario_id, titulo, descripcion, completada, prioridad, lista) 
                             VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([
            $user_id,
            'Tarea #' . $i,
            'Descripción de la tarea ' . $i,
            $completada,
            $prioridades[rand(0, 3)],
            $listas[rand(0, 3)]
        ])) {
            $inserted++;
        }
    }
    echo "  ✓ $inserted tareas creadas\n";
    
    // 4. Contactos
    echo "\n👥 Creando 200 contactos...\n";
    $apellidos = ['García', 'López', 'Martínez', 'Rodríguez', 'Fernández', 'González', 'Pérez', 'Sánchez'];
    $nombres = ['Juan', 'María', 'Pedro', 'Ana', 'Luis', 'Carmen', 'José', 'Isabel'];
    $inserted = 0;
    for ($i = 0; $i < 200; $i++) {
        $user_id = array_values($user_ids)[rand(0, count($user_ids)-1)];
        $nombre = $nombres[rand(0, 7)] . ' ' . $apellidos[rand(0, 7)];
        
        $stmt = $pdo->prepare('INSERT INTO contactos (usuario_id, nombre, email, telefono, empresa) 
                             VALUES (?, ?, ?, ?, ?)');
        if ($stmt->execute([
            $user_id,
            $nombre,
            strtolower(str_replace(' ', '.', $nombre)) . '@empresa.es',
            '6' . rand(10000000, 99999999),
            ['Acme', 'TechSol', 'Global', 'Innovation', 'Digital'][rand(0, 4)]
        ])) {
            $inserted++;
        }
    }
    echo "  ✓ $inserted contactos creados\n";
    
    // 5. Etiquetas
    echo "\n🏷️ Creando etiquetas...\n";
    $etiquetas = ['Importante', 'Urgente', 'Revisión', 'Completado', 'Personal', 'Trabajo'];
    foreach ($etiquetas as $nombre) {
        $pdo->prepare('INSERT IGNORE INTO etiquetas (nombre) VALUES (?)')->execute([$nombre]);
    }
    echo "  ✓ " . count($etiquetas) . " etiquetas creadas\n";
    
    // 6. Links
    echo "\n🔗 Creando 100 links...\n";
    $categorias = ['Herramientas', 'Documentación', 'Recursos'];
    $inserted = 0;
    for ($i = 0; $i < 100; $i++) {
        $user_id = array_values($user_ids)[rand(0, count($user_ids)-1)];
        
        $stmt = $pdo->prepare('INSERT INTO links (usuario_id, titulo, url, descripcion, categoria) 
                             VALUES (?, ?, ?, ?, ?)');
        if ($stmt->execute([
            $user_id,
            'Link #' . $i,
            'https://ejemplo-' . $i . '.com',
            'Descripción del link ' . $i,
            $categorias[rand(0, 2)]
        ])) {
            $inserted++;
        }
    }
    echo "  ✓ $inserted links creados\n";
    
    // 7. Logs
    echo "\n📊 Creando 1000 logs de acceso...\n";
    $tipos = ['login', 'logout', 'crear_nota', 'crear_tarea', 'crear_contacto'];
    $inserted = 0;
    for ($i = 0; $i < 1000; $i++) {
        $user_id = array_values($user_ids)[rand(0, count($user_ids)-1)];
        
        $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address) 
                             VALUES (?, ?, ?, ?, ?)');
        if ($stmt->execute([
            $user_id,
            $tipos[rand(0, 4)],
            'Evento de acceso ' . $i,
            rand(0, 100) > 5 ? 1 : 0,
            '192.168.' . rand(1, 255) . '.' . rand(1, 255)
        ])) {
            $inserted++;
        }
    }
    echo "  ✓ $inserted logs creados\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ ¡Base de datos poblada exitosamente!\n";
    echo str_repeat("=", 50) . "\n\n";
    echo "📊 Datos insertados:\n";
    echo "  • Usuarios: 5\n";
    echo "  • Notas: ~500\n";
    echo "  • Tareas: ~300\n";
    echo "  • Contactos: ~200\n";
    echo "  • Etiquetas: 6\n";
    echo "  • Links: ~100\n";
    echo "  • Logs: ~1000\n";
    echo "\n🔐 Contraseña: password123\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
