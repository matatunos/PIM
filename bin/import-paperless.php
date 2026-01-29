#!/usr/bin/env php
<?php
/**
 * Importar documentos de Paperless-ngx a PIM
 * Extrae el texto OCR de los documentos y los sincroniza con Open WebUI
 */

// Cargar sistema de auditoría
require_once dirname(__DIR__) . '/includes/audit_logger.php';

// Configuración Paperless
$PAPERLESS_URL = 'http://192.168.1.18:8000';
$PAPERLESS_TOKEN = '9580e1d36b354a86fce60c40d6a6854c0e8e95f8';

// Configuración PIM
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
$PIM_USER_ID = 1;

$LOG_FILE = '/var/log/pim-paperless.log';

// Función de log
function plog($msg, $level = 'INFO') {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $msg";
    echo "$line\n";
    @file_put_contents($LOG_FILE, "$line\n", FILE_APPEND | LOCK_EX);
}

plog("=== Importador Paperless-ngx → PIM ===", 'INFO');

// Función para llamar a la API de Paperless
function paperless_api($endpoint) {
    global $PAPERLESS_URL, $PAPERLESS_TOKEN;
    
    $ch = curl_init("$PAPERLESS_URL/api/$endpoint");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $PAPERLESS_TOKEN",
            "Accept: application/json"
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

// Obtener texto OCR de un documento
function get_document_content($doc_id) {
    global $PAPERLESS_URL, $PAPERLESS_TOKEN;
    
    $ch = curl_init("$PAPERLESS_URL/api/documents/$doc_id/");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $PAPERLESS_TOKEN",
            "Accept: application/json"
        ],
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $doc = json_decode($response, true);
    return $doc['content'] ?? '';
}

// Verificar conectividad
echo "📡 Verificando conexión con Paperless...\n";
$test = paperless_api('documents/?page_size=1');
if ($test === null) {
    die("❌ No se puede conectar a Paperless en $PAPERLESS_URL\n");
}
echo "   ✓ Conectado a Paperless\n\n";

// Obtener todos los documentos
echo "📄 Obteniendo lista de documentos...\n";
$page = 1;
$allDocs = [];

do {
    $response = paperless_api("documents/?page=$page&page_size=100");
    if ($response && isset($response['results'])) {
        $allDocs = array_merge($allDocs, $response['results']);
        $hasMore = !empty($response['next']);
        $page++;
    } else {
        $hasMore = false;
    }
} while ($hasMore);

echo "   Encontrados " . count($allDocs) . " documentos\n\n";

if (empty($allDocs)) {
    die("No hay documentos para importar\n");
}

// Obtener correspondents y tags para contexto
$correspondents = [];
$tags = [];

$corrData = paperless_api('correspondents/');
if ($corrData && isset($corrData['results'])) {
    foreach ($corrData['results'] as $c) {
        $correspondents[$c['id']] = $c['name'];
    }
}

$tagData = paperless_api('tags/');
if ($tagData && isset($tagData['results'])) {
    foreach ($tagData['results'] as $t) {
        $tags[$t['id']] = $t['name'];
    }
}

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

// Verificar/crear etiqueta Paperless
$stmt = $pdo->prepare("SELECT id FROM etiquetas WHERE nombre = 'Paperless' AND usuario_id = ?");
$stmt->execute([$PIM_USER_ID]);
$etiqueta = $stmt->fetch();

if (!$etiqueta) {
    $stmt = $pdo->prepare("INSERT INTO etiquetas (usuario_id, nombre, color) VALUES (?, 'Paperless', '#4caf50')");
    $stmt->execute([$PIM_USER_ID]);
    $etiqueta_id = $pdo->lastInsertId();
    echo "🏷️  Creada etiqueta 'Paperless'\n";
} else {
    $etiqueta_id = $etiqueta['id'];
}

// Tabla para tracking de documentos importados
$pdo->exec("CREATE TABLE IF NOT EXISTS paperless_sync (
    paperless_id INT PRIMARY KEY,
    nota_id INT,
    checksum VARCHAR(64),
    last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Procesar documentos
$importadas = 0;
$actualizadas = 0;
$sinCambios = 0;
$errores = 0;

foreach ($allDocs as $doc) {
    $docId = $doc['id'];
    $titulo = $doc['title'] ?? "Documento $docId";
    // Convertir fecha ISO 8601 a formato MySQL
    $rawDate = $doc['created'] ?? date('Y-m-d H:i:s');
    try {
        $dateObj = new DateTime($rawDate);
        $created = $dateObj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $created = date('Y-m-d H:i:s');
    }
    $correspondent = isset($doc['correspondent']) ? ($correspondents[$doc['correspondent']] ?? '') : '';
    $docTags = [];
    if (!empty($doc['tags'])) {
        foreach ($doc['tags'] as $tagId) {
            if (isset($tags[$tagId])) {
                $docTags[] = $tags[$tagId];
            }
        }
    }
    
    // Obtener contenido OCR
    $contenido = get_document_content($docId);
    
    if (empty(trim($contenido))) {
        echo "   ⚠️  Doc #$docId '$titulo' - Sin contenido OCR, saltando...\n";
        continue;
    }
    
    // Añadir metadatos al contenido
    $metadatos = "--- Documento Paperless ---\n";
    $metadatos .= "Título: $titulo\n";
    $metadatos .= "Fecha: $created\n";
    if ($correspondent) {
        $metadatos .= "Remitente: $correspondent\n";
    }
    if (!empty($docTags)) {
        $metadatos .= "Etiquetas: " . implode(', ', $docTags) . "\n";
    }
    $metadatos .= "---\n\n";
    
    $contenidoCompleto = $metadatos . $contenido;
    $checksum = md5($contenidoCompleto);
    
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT nota_id, checksum FROM paperless_sync WHERE paperless_id = ?");
    $stmt->execute([$docId]);
    $existing = $stmt->fetch();
    
    // Construir título con contexto
    $tituloNota = "[Paperless] $titulo";
    if ($correspondent) {
        $tituloNota = "[Paperless/$correspondent] $titulo";
    }
    $tituloNota = mb_substr($tituloNota, 0, 200);
    
    try {
        if ($existing) {
            // Ya existe, verificar si cambió
            if ($existing['checksum'] === $checksum) {
                $sinCambios++;
                continue;
            }
            
            // Actualizar
            $stmt = $pdo->prepare("UPDATE notas SET titulo = ?, contenido = ?, actualizado_en = NOW() WHERE id = ?");
            $stmt->execute([$tituloNota, $contenidoCompleto, $existing['nota_id']]);
            
            $stmt = $pdo->prepare("UPDATE paperless_sync SET checksum = ? WHERE paperless_id = ?");
            $stmt->execute([$checksum, $docId]);
            
            plog("Actualizado: $tituloNota");
            $actualizadas++;
        } else {
            // Crear nueva nota
            $stmt = $pdo->prepare("INSERT INTO notas (usuario_id, titulo, contenido, creado_en, actualizado_en) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$PIM_USER_ID, $tituloNota, $contenidoCompleto, $created]);
            $notaId = $pdo->lastInsertId();
            
            // Asignar etiqueta
            $stmt = $pdo->prepare("INSERT IGNORE INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)");
            $stmt->execute([$notaId, $etiqueta_id]);
            
            // Guardar tracking
            $stmt = $pdo->prepare("INSERT INTO paperless_sync (paperless_id, nota_id, checksum) VALUES (?, ?, ?)");
            $stmt->execute([$docId, $notaId, $checksum]);
            
            plog("Importado: $tituloNota");
            $importadas++;
        }
    } catch (PDOException $e) {
        plog("Error en '$tituloNota': " . $e->getMessage(), 'ERROR');
        $errores++;
    }
}

// Resumen
$resumen = "Paperless: $importadas importados, $actualizadas actualizados, $sinCambios sin cambios, $errores errores";
plog("=== Resumen: $resumen ===");

// Registrar en auditoría
$exitoso = ($errores == 0);
logSystemAction($pdo, 'sync', 'Importación Paperless-ngx', $resumen, $exitoso, $PIM_USER_ID);

// Sincronizar con Open WebUI
if ($importadas > 0 || $actualizadas > 0) {
    plog("Sincronizando con Open WebUI...");
    passthru('bash ' . __DIR__ . '/sync-openwebui.sh');
}

plog("¡Proceso completado!");
