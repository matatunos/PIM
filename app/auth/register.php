<?php
require_once '../../config/config.php';
require_once '../../includes/antibot.php';

if (isset($_SESSION['user_id'])) {
    redirect('/index.php');
}

// Verificar si el registro está habilitado
$stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
$stmt->execute(['registration_enabled']);
$config = $stmt->fetch();
$registration_enabled = $config && $config['valor'] === '1';

$error = '';
$success = '';
$antibot_config = getAntibotConfig($pdo);

if (!$registration_enabled && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $error = 'El registro de nuevos usuarios está deshabilitado por el administrador';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registration_enabled) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Verificar honeypot (campo oculto)
    if (!validateHoneypot()) {
        logAttempt('register_bot', $pdo);
        $error = 'Validación fallida. Intenta de nuevo.';
    }
    // Verificar rate limit
    elseif (isRateLimited('register', $pdo)) {
        $max_attempts = (int)($antibot_config['rate_limit_attempts'] ?? 5);
        $error = "Demasiados intentos de registro. Intenta más tarde. (Máximo $max_attempts intentos por hora)";
    }
    // Validaciones normales
    elseif (empty($username) || empty($email) || empty($password)) {
        logAttempt('register', $pdo);
        $error = 'Por favor completa todos los campos obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logAttempt('register', $pdo);
        $error = 'Email inválido';
    } elseif (strlen($username) < 3) {
        logAttempt('register', $pdo);
        $error = 'El usuario debe tener al menos 3 caracteres';
    } else {
        // Validación de contraseña segura
        $password_check = validate_password($password);
        if (!$password_check['valid']) {
            logAttempt('register', $pdo);
            $error = implode('. ', $password_check['errors']);
        } elseif ($password !== $password_confirm) {
            logAttempt('register', $pdo);
            $error = 'Las contraseñas no coinciden';
        }
    // Verificar reCAPTCHA si está habilitado
    elseif ($antibot_config['recaptcha_enabled'] === '1' && !validateRecaptcha($_POST['g-recaptcha-token'] ?? '', $pdo)) {
        logAttempt('register', $pdo);
        $error = 'Verificación de reCAPTCHA fallida. Intenta de nuevo.';

    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                logAttempt('register', $pdo);
                $error = 'El usuario o email ya están registrados';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (username, email, nombre_completo, password) VALUES (?, ?, ?, ?)');
                $stmt->execute([$username, $email, $nombre_completo, $hashed_password]);
                
                $success = 'Cuenta creada exitosamente. Redirigiendo...';
                
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['nombre_completo'] = $nombre_completo;
                $_SESSION['rol'] = 'user';
                
                header('Refresh: 2; url=/index.php');
            }
        } catch (PDOException $e) {
            logAttempt('register', $pdo);
            $error = 'Error al crear la cuenta. Intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - PIM</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <?php if ($antibot_config && $antibot_config['recaptcha_enabled'] === '1'): ?>
    <script src="https://www.google.com/recaptcha/api.js"></script>
    <?php endif; ?>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="/assets/img/logo-64.png" alt="PIM Logo" style="width: 80px; height: 80px; margin-bottom: var(--spacing-md);">
                <h1><?= $registration_enabled ? 'Crear Cuenta' : 'Registro Deshabilitado' ?></h1>
                <p><?= $registration_enabled ? 'Únete y organiza tu vida' : 'El registro de nuevos usuarios está actualmente deshabilitado' ?></p>
            </div>
            
            
            <?php if (!$registration_enabled): ?>
                <div class="alert alert-error">
                    <i class="fas fa-ban"></i>
                    El registro de nuevos usuarios está deshabilitado por el administrador. 
                    <a href="/app/auth/login.php" style="color: inherit; text-decoration: underline;">Volver a inicio de sesión</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form" id="registerForm" novalidate>
                    <?= csrf_field() ?>
                    <!-- Honeypot field (invisible para humanos, visible para bots) -->
                    <input type="text" name="website" style="display: none; position: absolute; left: -9999px;" tabindex="-1" autocomplete="off">
                    
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Usuario *</label>
                        <input type="text" id="username" name="username" required autofocus placeholder="Elige un usuario" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" required placeholder="tu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre_completo"><i class="fas fa-id-card"></i> Nombre Completo</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Tu nombre completo (opcional)" value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Contraseña *</label>
                        <input type="password" id="password" name="password" required placeholder="Mínimo 6 caracteres">
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm"><i class="fas fa-lock"></i> Confirmar Contraseña *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required placeholder="Repite tu contraseña">
                    </div>
                    
                    <!-- reCAPTCHA v3 (invisible) -->
                    <?php if ($antibot_config && $antibot_config['recaptcha_enabled'] === '1'): ?>
                    <input type="hidden" name="g-recaptcha-token" id="g-recaptcha-token">
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i>
                        Crear Cuenta
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm') || document.querySelector('.auth-form');
            
            if (form) {
                console.log('Formulario encontrado:', form);
                
                form.addEventListener('submit', function(e) {
                    console.log('Submit event triggered');
                    
                    // Si reCAPTCHA está habilitado
                    <?php if ($antibot_config && $antibot_config['recaptcha_enabled'] === '1'): ?>
                    const tokenField = document.getElementById('g-recaptcha-token');
                    const siteKey = '<?= htmlspecialchars($antibot_config['recaptcha_site_key'] ?? '') ?>';
                    
                    if (!tokenField.value && siteKey) {
                        console.log('Ejecutando reCAPTCHA...');
                        e.preventDefault();
                        
                        grecaptcha.execute(siteKey, {action: 'register'})
                            .then(function(token) {
                                console.log('reCAPTCHA token recibido');
                                tokenField.value = token;
                                form.submit();
                            })
                            .catch(function(error) {
                                console.error('Error reCAPTCHA:', error);
                                alert('Error de verificación. Intenta de nuevo.');
                            });
                    } else {
                        console.log('Token ya existe o sin clave reCAPTCHA');
                    }
                    <?php else: ?>
                    console.log('reCAPTCHA deshabilitado, enviando formulario normalmente');
                    <?php endif; ?>
                });
            } else {
                console.error('Formulario no encontrado!');
            }
        });
    </script>
    <?php if ($antibot_config && $antibot_config['recaptcha_enabled'] === '1'): ?>
    <script src="https://www.google.com/recaptcha/api.js"></script>
    <?php endif; ?>
</body>
</html>
