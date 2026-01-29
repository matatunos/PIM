<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Verificar que es admin
if ($_SESSION['rol'] !== 'admin') {
    redirect('/index.php');
}

$mensaje = '';
$error = '';

// Guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_enabled = isset($_POST['registration_enabled']) ? '1' : '0';
    $antibot_enabled = isset($_POST['antibot_enabled']) ? '1' : '0';
    $recaptcha_enabled = isset($_POST['recaptcha_enabled']) ? '1' : '0';
    $recaptcha_site_key = trim($_POST['recaptcha_site_key'] ?? '');
    $recaptcha_secret_key = trim($_POST['recaptcha_secret_key'] ?? '');
    $rate_limit_attempts = intval($_POST['rate_limit_attempts'] ?? 5);
    
    try {
        // Validar valores
        if ($rate_limit_attempts < 1) $rate_limit_attempts = 5;
        if ($rate_limit_attempts > 100) $rate_limit_attempts = 100;
        
        // Guardar configuraciones
        $configs = [
            'registration_enabled' => $registration_enabled,
            'antibot_enabled' => $antibot_enabled,
            'recaptcha_enabled' => $recaptcha_enabled,
            'recaptcha_site_key' => $recaptcha_site_key,
            'recaptcha_secret_key' => $recaptcha_secret_key,
            'rate_limit_attempts' => (string)$rate_limit_attempts
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $pdo->prepare('INSERT INTO config_sitio (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?');
            $stmt->execute([$key, $value, $value]);
        }
        
        $mensaje = 'Configuración guardada correctamente';
    } catch (Exception $e) {
        $error = 'Error al guardar la configuración: ' . $e->getMessage();
    }
}

// Obtener configuraciones actuales
$configs_keys = ['registration_enabled', 'antibot_enabled', 'recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key', 'rate_limit_attempts'];
$configs = [];
foreach ($configs_keys as $key) {
    $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    $configs[$key] = $result ? $result['valor'] : '';
}

$registration_enabled = $configs['registration_enabled'] === '1';
$antibot_enabled = $configs['antibot_enabled'] === '1';
$recaptcha_enabled = $configs['recaptcha_enabled'] === '1';
$recaptcha_site_key = $configs['recaptcha_site_key'];
$recaptcha_secret_key = $configs['recaptcha_secret_key'];
$rate_limit_attempts = intval($configs['rate_limit_attempts']) ?: 5;

// Obtener configuraciones de Open WebUI
$ia_configs = [];
$ia_config_keys = ['openwebui_host', 'openwebui_port', 'sync_interval_minutes', 'sync_enabled', 'openwebui_api_key'];
try {
    $stmt = $pdo->prepare('SELECT clave, valor FROM configuracion_ia WHERE clave IN (?, ?, ?, ?, ?)');
    $stmt->execute($ia_config_keys);
    while ($row = $stmt->fetch()) {
        $ia_configs[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {
    // Tabla no existe aún, ignorar
}

// Manejar actualización de configuración de Open WebUI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_openwebui'])) {
    try {
        $openwebui_host = trim($_POST['openwebui_host'] ?? '');
        $openwebui_port = intval($_POST['openwebui_port'] ?? 3000);
        $openwebui_api_key = trim($_POST['openwebui_api_key'] ?? '');
        $sync_interval = intval($_POST['sync_interval_minutes'] ?? 5);
        $sync_enabled = isset($_POST['sync_enabled']) ? '1' : '0';
        
        // Validar valores
        if (empty($openwebui_host)) {
            $error = 'El host de Open WebUI no puede estar vacío';
        } elseif ($openwebui_port < 1 || $openwebui_port > 65535) {
            $error = 'El puerto debe estar entre 1 y 65535';
        } elseif ($sync_interval < 1 || $sync_interval > 1440) {
            $error = 'El intervalo de sincronización debe estar entre 1 y 1440 minutos';
        } elseif (empty($openwebui_api_key)) {
            $error = 'La API Key de Open WebUI no puede estar vacía';
        } else {
            // Guardar configuraciones
            $ia_updates = [
                'openwebui_host' => $openwebui_host,
                'openwebui_port' => $openwebui_port,
                'openwebui_api_key' => $openwebui_api_key,
                'sync_interval_minutes' => $sync_interval,
                'sync_enabled' => $sync_enabled
            ];
            
            foreach ($ia_updates as $clave => $valor) {
                $stmt = $pdo->prepare('
                    INSERT INTO configuracion_ia (clave, valor) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE valor = ?
                ');
                $stmt->execute([$clave, $valor, $valor]);
            }
            
            $mensaje = 'Configuración de Open WebUI guardada correctamente';
        }
    } catch (Exception $e) {
        $error = 'Error al guardar configuración de Open WebUI: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - PIM Admin</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-cogs"></i> Configuración del Sistema</h1>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Registro de Usuarios</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                                    <input type="checkbox" 
                                           id="registration_enabled" 
                                           name="registration_enabled" 
                                           <?= $registration_enabled ? 'checked' : '' ?>
                                           style="width: 20px; height: 20px; cursor: pointer;">
                                    <label for="registration_enabled" style="cursor: pointer; margin: 0;">
                                        <strong>Permitir registro automático de nuevos usuarios</strong>
                                    </label>
                                </div>
                                <p style="color: var(--text-secondary); margin-top: var(--spacing-md); font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i>
                                    Cuando está deshabilitado, solo los administradores pueden crear nuevas cuentas.
                                </p>
                            </div>
                            
                            <!-- Anti-Bot Settings -->
                            <hr style="margin: var(--spacing-lg) 0; border: none; border-top: 1px solid var(--border-color);">
                            
                            <div class="form-group">
                                <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                                    <input type="checkbox" 
                                           id="antibot_enabled" 
                                           name="antibot_enabled" 
                                           <?= $antibot_enabled ? 'checked' : '' ?>
                                           style="width: 20px; height: 20px; cursor: pointer;">
                                    <label for="antibot_enabled" style="cursor: pointer; margin: 0;">
                                        <strong>Habilitar Protección Anti-Bot</strong>
                                    </label>
                                </div>
                                <p style="color: var(--text-secondary); margin-top: var(--spacing-md); font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i>
                                    Incluye: Honeypot (campo invisible), Rate Limiting (límite de intentos) y validaciones de comportamiento.
                                </p>
                            </div>
                            
                            <div class="form-group" style="margin-top: var(--spacing-md);">
                                <label for="rate_limit_attempts">
                                    <i class="fas fa-hourglass-half"></i>
                                    Intentos máximos por hora
                                </label>
                                <input type="number" 
                                       id="rate_limit_attempts" 
                                       name="rate_limit_attempts"
                                       min="1"
                                       max="100"
                                       value="<?= $rate_limit_attempts ?>"
                                       class="form-input"
                                       style="max-width: 150px;">
                                <small style="color: var(--text-secondary);">Si una IP intenta registrarse más veces, será bloqueada temporalmente</small>
                            </div>
                            
                            <!-- reCAPTCHA Settings -->
                            <hr style="margin: var(--spacing-lg) 0; border: none; border-top: 1px solid var(--border-color);">
                            
                            <div class="form-group">
                                <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                                    <input type="checkbox" 
                                           id="recaptcha_enabled" 
                                           name="recaptcha_enabled" 
                                           <?= $recaptcha_enabled ? 'checked' : '' ?>
                                           style="width: 20px; height: 20px; cursor: pointer;">
                                    <label for="recaptcha_enabled" style="cursor: pointer; margin: 0;">
                                        <strong>Habilitar Google reCAPTCHA v3</strong>
                                    </label>
                                </div>
                                <p style="color: var(--text-secondary); margin-top: var(--spacing-md); font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i>
                                    Verificación invisible. Requiere claves de Google. <a href="https://www.google.com/recaptcha/admin" target="_blank" style="color: var(--primary);">Obtener claves aquí</a>
                                </p>
                            </div>
                            
                            <div class="form-group" style="margin-top: var(--spacing-md);">
                                <label for="recaptcha_site_key">
                                    <i class="fas fa-key"></i>
                                    Clave de Sitio (Site Key)
                                </label>
                                <input type="text" 
                                       id="recaptcha_site_key" 
                                       name="recaptcha_site_key"
                                       value="<?= htmlspecialchars($recaptcha_site_key) ?>"
                                       placeholder="6Le..."
                                       class="form-input">
                            </div>
                            
                            <div class="form-group" style="margin-top: var(--spacing-md);">
                                <label for="recaptcha_secret_key">
                                    <i class="fas fa-key"></i>
                                    Clave Secreta (Secret Key)
                                </label>
                                <input type="password" 
                                       id="recaptcha_secret_key" 
                                       name="recaptcha_secret_key"
                                       value="<?= htmlspecialchars($recaptcha_secret_key) ?>"
                                       placeholder="6Le..."
                                       class="form-input">
                                <small style="color: var(--text-secondary);">No se muestra en pantalla por seguridad</small>
                            </div>
                            
                            <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Cambios
                                </button>
                                <a href="/app/admin/usuarios.php" class="btn btn-ghost">
                                    <i class="fas fa-arrow-left"></i>
                                    Volver
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Open WebUI Integration Section -->
                <div class="card" id="openwebui-section" style="margin-top: var(--spacing-xl);">
                    <div class="card-header">
                        <h2><i class="fas fa-brain"></i> Integración Open WebUI + IA</h2>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 5px;">
                            Configura la conexión a tu instancia de Open WebUI con Ollama
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="save_openwebui" value="1">
                            
                            <!-- Host Configuration -->
                            <div class="form-group">
                                <label for="openwebui_host">
                                    <i class="fas fa-server"></i>
                                    Host de Open WebUI
                                </label>
                                <input type="text" 
                                       id="openwebui_host" 
                                       name="openwebui_host"
                                       value="<?= htmlspecialchars($ia_configs['openwebui_host'] ?? '192.168.1.19') ?>"
                                       placeholder="192.168.1.19 o openwebui.local"
                                       class="form-input">
                                <small style="color: var(--text-secondary);">IP o hostname de tu servidor Open WebUI en la red local</small>
                            </div>
                            
                            <!-- Port Configuration -->
                            <div class="form-group">
                                <label for="openwebui_port">
                                    <i class="fas fa-plug"></i>
                                    Puerto
                                </label>
                                <input type="number" 
                                       id="openwebui_port" 
                                       name="openwebui_port"
                                       value="<?= htmlspecialchars($ia_configs['openwebui_port'] ?? '3000') ?>"
                                       min="1"
                                       max="65535"
                                       class="form-input"
                                       style="max-width: 150px;">
                                <small style="color: var(--text-secondary);">Por defecto: 3000, 8000 u 8080</small>
                            </div>
                            
                            <!-- API Key -->
                            <div class="form-group">
                                <label for="openwebui_api_key">
                                    <i class="fas fa-key"></i>
                                    API Key de Open WebUI
                                </label>
                                <textarea id="openwebui_api_key" 
                                          name="openwebui_api_key"
                                          class="form-input"
                                          rows="4"
                                          placeholder="Pega aquí la API Key de Open WebUI (Settings > API Keys > Create New)"
                                          style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($ia_configs['openwebui_api_key'] ?? '') ?></textarea>
                                <small style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle"></i>
                                    La API Key se obtiene en: Open WebUI → Settings → API Keys → Create New
                                    <br>
                                    <strong>⚠️ Mantén esto seguro.</strong> Esta clave permite acceder a Open WebUI.
                                </small>
                            </div>
                            
                            <!-- Sync Interval -->
                            <div class="form-group">
                                <label for="sync_interval_minutes">
                                    <i class="fas fa-clock"></i>
                                    Intervalo de Sincronización (minutos)
                                </label>
                                <input type="number" 
                                       id="sync_interval_minutes" 
                                       name="sync_interval_minutes"
                                       value="<?= htmlspecialchars($ia_configs['sync_interval_minutes'] ?? '5') ?>"
                                       min="1"
                                       max="1440"
                                       class="form-input"
                                       style="max-width: 150px;">
                                <small style="color: var(--text-secondary);">Cada cuántos minutos sincronizar documentos y notas (1-1440)</small>
                            </div>
                            
                            <!-- Enable Sync -->
                            <div class="form-group">
                                <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                                    <input type="checkbox" 
                                           id="sync_enabled" 
                                           name="sync_enabled" 
                                           <?= ($ia_configs['sync_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                                           style="width: 20px; height: 20px; cursor: pointer;">
                                    <label for="sync_enabled" style="cursor: pointer; margin: 0;">
                                        <strong>Habilitar Sincronización Automática</strong>
                                    </label>
                                </div>
                                <p style="color: var(--text-secondary); margin-top: var(--spacing-md); font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i>
                                    Se ejecutará cada X minutos vía cron para sincronizar documentos y notas con Open WebUI.
                                </p>
                            </div>
                            
                            <!-- Connection Test -->
                            <div style="margin-top: var(--spacing-lg); padding: var(--spacing-md); background: #f5f5f5; border-radius: 8px; border: 1px solid var(--border-color);">
                                <h3 style="margin-top: 0; font-size: 1rem;">🔗 Prueba de Conexión</h3>
                                <p style="margin: 0 0 var(--spacing-md) 0; color: var(--text-secondary); font-size: 0.9rem;">
                                    Haz clic en el botón para verificar que Open WebUI es accesible
                                </p>
                                <button type="button" 
                                        id="test-connection-btn" 
                                        class="btn btn-ghost"
                                        onclick="testOpenWebUIConnection()">
                                    <i class="fas fa-plug"></i>
                                    Probar Conexión
                                </button>
                                <div id="connection-result" style="margin-top: var(--spacing-md); display: none; padding: var(--spacing-md); border-radius: 5px; font-size: 0.9rem;"></div>
                            </div>
                            
                            <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Configuración IA
                                </button>
                                <a href="/app/ai-assistant.php" class="btn btn-ghost" target="_blank">
                                    <i class="fas fa-external-link-alt"></i>
                                    Abrir Chat IA
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function testOpenWebUIConnection() {
        const btn = document.getElementById('test-connection-btn');
        const resultDiv = document.getElementById('connection-result');
        
        const host = document.getElementById('openwebui_host').value.trim();
        const port = document.getElementById('openwebui_port').value.trim();
        
        if (!host || !port) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-error';
            resultDiv.innerHTML = '<i class="fas fa-times-circle"></i> Host y puerto son requeridos';
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probando...';
        resultDiv.style.display = 'block';
        resultDiv.className = '';
        resultDiv.innerHTML = '<i class="fas fa-hourglass-half"></i> Conectando...';
        
        // Llamar endpoint de prueba
        fetch('/app/admin/test-openwebui.php?host=' + encodeURIComponent(host) + '&port=' + encodeURIComponent(port))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'alert alert-success';
                    resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> ✓ Conexión exitosa con Open WebUI en ' + host + ':' + port;
                } else {
                    resultDiv.className = 'alert alert-error';
                    resultDiv.innerHTML = '<i class="fas fa-times-circle"></i> ✗ No se puede conectar: ' + (data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                resultDiv.className = 'alert alert-error';
                resultDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error de red: ' + error.message;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plug"></i> Probar Conexión';
            });
    }
    </script>
