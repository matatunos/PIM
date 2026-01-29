#!/usr/bin/env php
<?php
/**
 * Importar documentos de BookStack a PIM
 * Extrae páginas de BookStack y las crea como notas en PIM
 */

// Configuración BookStack (vía SSH a Proxmox)
$PROXMOX_HOST = '192.168.1.2';
$PROXMOX_PASS = 'fr1t@ng@';
$CT_ID = '124';
$BS_DB_USER = 'bookstack';
$BS_DB_PASS = 'GGyLoCQirPijW';
$BS_DB_NAME = 'bookstack';

// Configuración PIM - cargar .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$PIM_DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$PIM_DB_NAME = $_ENV['DB_NAME'] ?? 'pim_db';
$PIM_DB_USER = $_ENV['DB_USER'] ?? 'pim_user';
$PIM_DB_PASS = $_ENV['DB_PASS'] ?? '';

// Usuario para asignar las notas (admin = 1)
$PIM_USER_ID = 1;

echo "=== Importador BookStack → PIM ===\n\n";

// Función para ejecutar comando en BookStack vía SSH
function bookstack_query($query) {
    global $PROXMOX_HOST, $PROXMOX_PASS, $CT_ID, $BS_DB_USER, $BS_DB_PASS, $BS_DB_NAME;
    
    $query_escaped = addslashes($query);
    $cmd = "sshpass -p '$PROXMOX_PASS' ssh -o StrictHostKeyChecking=no root@$PROXMOX_HOST " .
           "\"pct exec $CT_ID -- mysql -u $BS_DB_USER -p$BS_DB_PASS $BS_DB_NAME -N -e \\\"$query_escaped\\\"\" 2>/dev/null";
    
    $output = shell_exec($cmd);
    return $output;
}

// Obtener páginas de BookStack
echo "📚 Obteniendo páginas de BookStack...\n";

$pages_raw = bookstack_query("SELECT p.id, p.name, p.html, p.text, b.name as book_name, p.updated_at FROM pages p LEFT JOIN books b ON p.book_id = b.id WHERE p.draft = 0 ORDER BY p.updated_at DESC");

if (empty($pages_raw)) {
    die("❌ No se pudieron obtener las páginas de BookStack\n");
}

$lines = explode("\n", trim($pages_raw));
echo "   Encontradas " . count($lines) . " páginas\n\n";

// Conectar a PIM
try {
    $pdo = new PDO(
        "mysql:host=$PIM_DB_HOST;dbname=$PIM_DB_NAME;charset=utf8mb4",
        $PIM_DB_USER,
        $PIM_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ Error conectando a PIM: " . $e->getMessage() . "\n");
}

// Verificar/crear etiqueta BookStack
$stmt = $pdo->prepare("SELECT id FROM etiquetas WHERE nombre = 'BookStack' AND usuario_id = ?");
$stmt->execute([$PIM_USER_ID]);
$etiqueta = $stmt->fetch();

if (!$etiqueta) {
    $stmt = $pdo->prepare("INSERT INTO etiquetas (usuario_id, nombre, color) VALUES (?, 'BookStack', '#3498db')");
    $stmt->execute([$PIM_USER_ID]);
    $etiqueta_id = $pdo->lastInsertId();
    echo "🏷️  Creada etiqueta 'BookStack'\n";
} else {
    $etiqueta_id = $etiqueta['id'];
}

// Procesar cada página
$importadas = 0;
$actualizadas = 0;
$errores = 0;

// Obtener páginas una por una para manejar mejor el contenido largo
$pages_ids = bookstack_query("SELECT id FROM pages WHERE draft = 0");
$ids = array_filter(explode("\n", trim($pages_ids)));

foreach ($ids as $page_id) {
    $page_id = trim($page_id);
    if (empty($page_id) || !is_numeric($page_id)) continue;
    
    // Obtener datos de la página
    $name = trim(bookstack_query("SELECT name FROM pages WHERE id = $page_id"));
    $book = trim(bookstack_query("SELECT b.name FROM pages p LEFT JOIN books b ON p.book_id = b.id WHERE p.id = $page_id"));
    $updated = trim(bookstack_query("SELECT updated_at FROM pages WHERE id = $page_id"));
    
    // Obtener contenido (text es más limpio que html)
    $content = bookstack_query("SELECT text FROM pages WHERE id = $page_id");
    
    if (empty($name)) {
        $name = "Página $page_id";
    }
    
    // Título con contexto del libro
    $titulo = !empty($book) ? "[$book] $name" : $name;
    $titulo = mb_substr($titulo, 0, 200); // Limitar longitud
    
    // Limpiar contenido
    $contenido = trim($content);
    if (empty($contenido)) {
        echo "   ⚠️  Página '$name' está vacía, saltando...\n";
        continue;
    }
    
    // Verificar si ya existe (por título similar)
    $stmt = $pdo->prepare("SELECT id, contenido FROM notas WHERE usuario_id = ? AND titulo LIKE ?");
    $stmt->execute([$PIM_USER_ID, "%$name%"]);
    $existente = $stmt->fetch();
    
    try {
        if ($existente) {
            // Actualizar si el contenido cambió
            if (md5($existente['contenido']) !== md5($contenido)) {
                $stmt = $pdo->prepare("UPDATE notas SET contenido = ?, actualizado_en = NOW() WHERE id = ?");
                $stmt->execute([$contenido, $existente['id']]);
                echo "   🔄 Actualizada: $titulo\n";
                $actualizadas++;
            } else {
                echo "   ⏭️  Sin cambios: $titulo\n";
            }
        } else {
            // Crear nueva nota
            $stmt = $pdo->prepare("INSERT INTO notas (usuario_id, titulo, contenido, creado_en, actualizado_en) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$PIM_USER_ID, $titulo, $contenido]);
            $nota_id = $pdo->lastInsertId();
            
            // Asignar etiqueta BookStack
            $stmt = $pdo->prepare("INSERT IGNORE INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)");
            $stmt->execute([$nota_id, $etiqueta_id]);
            
            echo "   ✅ Importada: $titulo\n";
            $importadas++;
        }
    } catch (PDOException $e) {
        echo "   ❌ Error en '$titulo': " . $e->getMessage() . "\n";
        $errores++;
    }
}

echo "\n=== Resumen ===\n";
echo "✅ Importadas: $importadas\n";
echo "🔄 Actualizadas: $actualizadas\n";
echo "❌ Errores: $errores\n";

// Preguntar si sincronizar con Open WebUI
if ($importadas > 0 || $actualizadas > 0) {
    echo "\n🔄 Sincronizando con Open WebUI...\n";
    passthru('bash ' . __DIR__ . '/sync-openwebui.sh');
}

echo "\n✨ ¡Proceso completado!\n";
echo "Ahora puedes preguntar a Ollama sobre tu documentación de BookStack.\n";
