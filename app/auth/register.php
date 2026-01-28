<?php
require_once '../../config/config.php';

if (isset($_SESSION['user_id'])) {
    redirect('/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($username) < 3) {
        $error = 'El usuario debe tener al menos 3 caracteres';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
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
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h1>Crear Cuenta</h1>
                <p>Únete y organiza tu vida</p>
            </div>
            
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
            
            <form method="POST" class="auth-form">
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
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i>
                    Crear Cuenta
                </button>
            </form>
            
            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>
</body>
</html>
