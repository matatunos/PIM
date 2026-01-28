<?php
require_once '../../config/config.php';
require_once '../../includes/totp.php';

// Asegurar que la sesión está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    redirect('/index.php');
}

$error = '';
$require_2fa = false;

// Verificación de código 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    $code = trim($_POST['totp_code'] ?? '');
    $user_id = $_SESSION['temp_user_id'] ?? null;
    $is_backup_code = isset($_POST['use_backup']);
    
    // Debug
    error_log("DEBUG LOGIN 2FA: temp_user_id = " . var_export($user_id, true) . ", code = " . var_export($code, true));
    
    if (!$user_id) {
        $error = 'Sesión expirada. Por favor, inicia sesión nuevamente.';
        unset($_SESSION['temp_user_id']);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $valid = false;
        
        if ($is_backup_code) {
            // Verificar código de respaldo
            $backupCodes = json_decode($user['backup_codes'] ?? '[]', true);
            if (in_array($code, $backupCodes)) {
                // Remover el código usado
                $backupCodes = array_diff($backupCodes, [$code]);
                $stmt = $pdo->prepare('UPDATE usuarios SET backup_codes = ? WHERE id = ?');
                $stmt->execute([json_encode(array_values($backupCodes)), $user_id]);
                $valid = true;
            }
        } else {
            // Verificar código TOTP
            if (TOTP::verifyCode($user['totp_secret'], $code)) {
                $valid = true;
            }
        }
        
        if ($valid) {
            // 2FA exitoso - completar login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre_completo'] = $user['nombre_completo'];
            $_SESSION['rol'] = $user['rol'];
            unset($_SESSION['temp_user_id']);
            
            // Log exitoso
            $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], 'login_2fa', 'Inicio de sesión exitoso con 2FA', 1, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
            
            // Actualizar último acceso
            $stmt = $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);
            
            redirect('/index.php');
        } else {
            $error = 'Código incorrecto. Por favor, inténtalo de nuevo.';
            
            // Log fallido
            $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user_id, 'login_2fa_failed', 'Intento fallido de verificación 2FA', 0, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
            
            $require_2fa = true;
        }
    }
}

// Login inicial (usuario y contraseña)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_2fa'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor completa todos los campos';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE (username = ? OR email = ?) AND activo = 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Verificar si tiene 2FA habilitado
                if ($user['totp_enabled']) {
                    // Requerir código 2FA
                    $_SESSION['temp_user_id'] = $user['id'];
                    session_write_close(); // Forzar guardar la sesión
                    $require_2fa = true;
                    
                    // Log de primer paso exitoso
                    $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$user['id'], 'login_password', 'Contraseña correcta, esperando 2FA', 1, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } else {
                    // Login directo sin 2FA
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nombre_completo'] = $user['nombre_completo'];
                    $_SESSION['rol'] = $user['rol'];
                    
                    // Log exitoso
                    $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$user['id'], 'login', 'Inicio de sesión exitoso', 1, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    
                    // Actualizar último acceso
                    $stmt = $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?');
                    $stmt->execute([$user['id']]);
                    
                    redirect('/index.php');
                }
            } else {
                $error = 'Usuario o contraseña incorrectos';
                
                // Log fallido
                if ($user) {
                    $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$user['id'], 'login_failed', 'Contraseña incorrecta', 0, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                }
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema. Intenta de nuevo.';
        }
    }
}

// Verificar si ya está en proceso de 2FA
if (isset($_SESSION['temp_user_id']) && !$require_2fa) {
    $require_2fa = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $require_2fa ? 'Verificación 2FA' : 'Iniciar Sesión' ?> - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-<?= $require_2fa ? 'shield-alt' : 'clipboard-list' ?>"></i>
                </div>
                <h1><?= $require_2fa ? 'Verificación de Dos Factores' : 'Bienvenido a PIM' ?></h1>
                <p><?= $require_2fa ? 'Ingresa el código de tu app de autenticación' : 'Tu gestor personal de información' ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($require_2fa): ?>
                <!-- Formulario 2FA -->
                <form method="POST" class="auth-form">
                    <input type="hidden" name="verify_2fa" value="1">
                    
                    <div class="form-group">
                        <label for="totp_code">
                            <i class="fas fa-key"></i>
                            Código de Autenticación
                        </label>
                        <input 
                            type="text" 
                            id="totp_code" 
                            name="totp_code" 
                            maxlength="8" 
                            pattern="[0-9]{6,8}"
                            required 
                            autofocus
                            placeholder="000000"
                            style="font-size: 1.5rem; text-align: center; letter-spacing: 0.5em;"
                        >
                        <small style="color: var(--text-muted); display: block; margin-top: var(--spacing-xs);">
                            Ingresa el código de 6 dígitos de tu app
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-check"></i>
                        Verificar Código
                    </button>
                    
                    <div style="text-align: center; margin-top: var(--spacing-md);">
                        <button type="button" onclick="document.getElementById('backupForm').style.display='block'; this.style.display='none'" class="btn btn-ghost btn-sm">
                            ¿No tienes acceso? Usa un código de respaldo
                        </button>
                    </div>
                </form>
                
                <!-- Formulario de código de respaldo (oculto inicialmente) -->
                <form method="POST" class="auth-form" id="backupForm" style="display: none; margin-top: var(--spacing-lg);">
                    <input type="hidden" name="verify_2fa" value="1">
                    <input type="hidden" name="use_backup" value="1">
                    
                    <div class="form-group">
                        <label for="backup_code">
                            <i class="fas fa-life-ring"></i>
                            Código de Respaldo
                        </label>
                        <input 
                            type="text" 
                            id="backup_code" 
                            name="totp_code" 
                            maxlength="8" 
                            pattern="[0-9]{8}"
                            placeholder="00000000"
                            style="font-size: 1.3rem; text-align: center; letter-spacing: 0.3em;"
                        >
                        <small style="color: var(--text-muted); display: block; margin-top: var(--spacing-xs);">
                            Ingresa uno de tus códigos de respaldo de 8 dígitos
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-warning btn-block">
                        <i class="fas fa-unlock"></i>
                        Usar Código de Respaldo
                    </button>
                </form>
                
                <div class="auth-footer">
                    <a href="?cancel=1" onclick="<?php unset($_SESSION['temp_user_id']); ?>" style="color: var(--text-muted);">
                        <i class="fas fa-arrow-left"></i> Volver al login
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Formulario de login normal -->
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i>
                            Usuario o Email
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required 
                            autofocus
                            placeholder="Ingresa tu usuario o email"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            placeholder="Ingresa tu contraseña"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar Sesión
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                </div>
                
                <div class="demo-info">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Demo: usuario <strong>admin</strong> / contraseña <strong>password</strong>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
