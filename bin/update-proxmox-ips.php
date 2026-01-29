#!/usr/bin/env php
<?php
/**
 * Script para actualizar nota con IPs de contenedores Proxmox
 * Se ejecuta por cron cada 30 minutos
 * 
 * Uso: php /opt/PIM/bin/update-proxmox-ips.php
 */

// Configuración
define('PROXMOX_HOST', '192.168.1.2');
define('PROXMOX_USER', 'root');
define('PROXMOX_PASS', 'fr1t@ng@');
define('PIM_USER_ID', 1); // Usuario que tendrá la nota
define('NOTA_TITULO', '🖥️ IPs Proxmox - Inventario');
define('ETIQUETA_NOMBRE', 'Intranet');
define('LOG_FILE', '/var/log/pim-proxmox-ips.log');

// Cargar configuración de BD
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

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'pim_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'pim_user');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

function plog($msg) {
    $line = date('Y-m-d H:i:s') . " - $msg\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
    echo $line;
}

/**
 * Obtiene lista de contenedores desde Proxmox via SSH
 */
function getProxmoxContainers() {
    $cmd = sprintf(
        "sshpass -p '%s' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 %s@%s 'pct list' 2>/dev/null",
        PROXMOX_PASS,
        PROXMOX_USER,
        PROXMOX_HOST
    );
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        plog("ERROR: No se pudo conectar a Proxmox (code: $returnCode)");
        return [];
    }
    
    $containers = [];
    foreach ($output as $line) {
        // Formato: VMID Status Lock Name
        if (preg_match('/^\s*(\d+)\s+(\w+)\s+(?:\S+\s+)?(.+)$/', trim($line), $m)) {
            $vmid = $m[1];
            $status = $m[2];
            $name = trim($m[3]);
            
            if ($status === 'running') {
                $containers[$vmid] = [
                    'vmid' => $vmid,
                    'name' => $name,
                    'status' => $status,
                    'ips' => []
                ];
            }
        }
    }
    
    return $containers;
}

/**
 * Obtiene IP de un contenedor específico
 */
function getContainerIP($vmid) {
    $cmd = sprintf(
        "sshpass -p '%s' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 %s@%s 'pct exec %d -- hostname -I 2>/dev/null || echo \"\"'",
        PROXMOX_PASS,
        PROXMOX_USER,
        PROXMOX_HOST,
        $vmid
    );
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && !empty($output[0])) {
        // Puede devolver múltiples IPs separadas por espacio
        return array_filter(explode(' ', trim($output[0])));
    }
    
    return [];
}

/**
 * Obtiene el hostname real del contenedor
 */
function getContainerHostname($vmid) {
    $cmd = sprintf(
        "sshpass -p '%s' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 %s@%s 'pct exec %d -- hostname 2>/dev/null || echo \"\"'",
        PROXMOX_PASS,
        PROXMOX_USER,
        PROXMOX_HOST,
        $vmid
    );
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && !empty($output[0])) {
        return trim($output[0]);
    }
    
    return '';
}

/**
 * Genera el contenido de la nota en formato Markdown
 */
function generateNoteContent($containers) {
    $content = "# 🖥️ Inventario de IPs - Proxmox\n\n";
    $content .= "**Última actualización:** " . date('d/m/Y H:i:s') . "\n";
    $content .= "**Host Proxmox:** " . PROXMOX_HOST . "\n\n";
    $content .= "---\n\n";
    
    // Ordenar por IP (primer IP de cada contenedor)
    uasort($containers, function($a, $b) {
        $ipA = $a['ips'][0] ?? '999.999.999.999';
        $ipB = $b['ips'][0] ?? '999.999.999.999';
        return ip2long($ipA) <=> ip2long($ipB);
    });
    
    $content .= "| IP | CT ID | Nombre | Hostname |\n";
    $content .= "|:---|:---:|:---|:---|\n";
    
    foreach ($containers as $ct) {
        $ip = $ct['ips'][0] ?? 'N/A';
        $extraIps = count($ct['ips']) > 1 ? ' (+' . (count($ct['ips'])-1) . ')' : '';
        $content .= sprintf(
            "| `%s`%s | %s | %s | %s |\n",
            $ip,
            $extraIps,
            $ct['vmid'],
            $ct['name'],
            $ct['hostname'] ?: '-'
        );
    }
    
    $content .= "\n---\n\n";
    $content .= "**Total contenedores activos:** " . count($containers) . "\n\n";
    
    // Agregar lista de servicios conocidos
    $content .= "### 📋 Servicios\n\n";
    $services = [
        '105' => 'Nginx Proxy Manager',
        '114' => 'MariaDB',
        '118' => 'Paperless-ngx',
        '124' => 'BookStack Wiki',
        '40001' => 'UrBackup',
        '40005' => 'Mimir',
        '40010' => 'Wazuh SIEM',
        '100000' => 'Open WebUI + Ollama',
    ];
    
    foreach ($containers as $ct) {
        if (isset($services[$ct['vmid']])) {
            $ip = $ct['ips'][0] ?? 'N/A';
            $content .= "- **{$services[$ct['vmid']]}**: `$ip` (CT {$ct['vmid']})\n";
        }
    }
    
    $content .= "\n*Nota auto-generada por PIM - Actualizada cada 30 minutos*";
    
    return $content;
}

/**
 * Obtiene o crea la etiqueta "Intranet"
 */
function getOrCreateEtiqueta($pdo, $userId, $nombre) {
    // Buscar existente
    $stmt = $pdo->prepare("SELECT id FROM etiquetas WHERE usuario_id = ? AND nombre = ?");
    $stmt->execute([$userId, $nombre]);
    $etiqueta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($etiqueta) {
        return $etiqueta['id'];
    }
    
    // Crear nueva con color azul (network/intranet)
    $stmt = $pdo->prepare("INSERT INTO etiquetas (usuario_id, nombre, color) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $nombre, '#0077b6']);
    
    return $pdo->lastInsertId();
}

/**
 * Actualiza o crea la nota
 */
function updateOrCreateNota($pdo, $userId, $titulo, $contenido, $etiquetaId) {
    // Buscar nota existente por título
    $stmt = $pdo->prepare("SELECT id FROM notas WHERE usuario_id = ? AND titulo = ?");
    $stmt->execute([$userId, $titulo]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($nota) {
        // Actualizar existente
        $stmt = $pdo->prepare("UPDATE notas SET contenido = ?, fijada = 1, actualizado_en = NOW() WHERE id = ?");
        $stmt->execute([$contenido, $nota['id']]);
        plog("Nota actualizada (ID: {$nota['id']})");
        return $nota['id'];
    }
    
    // Crear nueva nota fijada con color azul claro
    $stmt = $pdo->prepare("INSERT INTO notas (usuario_id, titulo, contenido, color, fijada) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$userId, $titulo, $contenido, '#e3f2fd']);
    $notaId = $pdo->lastInsertId();
    
    // Asociar etiqueta
    $stmt = $pdo->prepare("INSERT IGNORE INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)");
    $stmt->execute([$notaId, $etiquetaId]);
    
    plog("Nota creada (ID: $notaId)");
    return $notaId;
}

// ============= MAIN =============

plog("=== Iniciando actualización de IPs Proxmox ===");

try {
    // Conectar a BD
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener lista de contenedores
    plog("Obteniendo lista de contenedores...");
    $containers = getProxmoxContainers();
    
    if (empty($containers)) {
        plog("ERROR: No se encontraron contenedores o no se pudo conectar");
        exit(1);
    }
    
    plog("Encontrados " . count($containers) . " contenedores activos");
    
    // Obtener IPs y hostnames de cada contenedor
    foreach ($containers as $vmid => &$ct) {
        $ct['ips'] = getContainerIP($vmid);
        $ct['hostname'] = getContainerHostname($vmid);
        plog("  CT $vmid ({$ct['name']}): " . implode(', ', $ct['ips']) . " - hostname: {$ct['hostname']}");
    }
    unset($ct);
    
    // Generar contenido de la nota
    $contenido = generateNoteContent($containers);
    
    // Obtener/crear etiqueta
    $etiquetaNombre = ETIQUETA_NOMBRE;
    $etiquetaId = getOrCreateEtiqueta($pdo, PIM_USER_ID, $etiquetaNombre);
    plog("Etiqueta '$etiquetaNombre' ID: $etiquetaId");
    
    // Actualizar/crear nota
    $notaId = updateOrCreateNota($pdo, PIM_USER_ID, NOTA_TITULO, $contenido, $etiquetaId);
    
    // Asegurar que la etiqueta esté asociada (por si ya existía la nota)
    $stmt = $pdo->prepare("INSERT IGNORE INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)");
    $stmt->execute([$notaId, $etiquetaId]);
    
    // Registrar en auditoría directamente
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_acceso (usuario_id, accion, tipo_evento, descripcion, ip_address, fecha_hora) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            PIM_USER_ID,
            'proxmox_ip_sync',
            'cron',
            json_encode(['containers' => count($containers), 'nota_id' => $notaId]),
            '127.0.0.1'
        ]);
    } catch (Exception $e) {
        plog("Warning: No se pudo registrar auditoría: " . $e->getMessage());
    }
    
    plog("=== Actualización completada ===\n");
    
} catch (Exception $e) {
    plog("ERROR: " . $e->getMessage());
    exit(1);
}
