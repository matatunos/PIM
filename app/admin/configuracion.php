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
            </div>
        </div>
    </div>
</body>
</html>
