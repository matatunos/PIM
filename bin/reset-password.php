#!/usr/bin/env php
<?php
/**
 * Script CLI para restablecer contraseña del administrador
 * Uso: php reset-password.php [username] [nueva_contraseña]
 */

require_once dirname(__DIR__) . '/config/database.php';

echo "\n╔════════════════════════════════════════╗\n";
echo "║  PIM - Reset Password Tool             ║\n";
echo "╚════════════════════════════════════════╝\n\n";

$username = $argv[1] ?? null;
$new_password = $argv[2] ?? null;

if (!$username) {
    echo "Ingresa el nombre de usuario: ";
    $username = trim(fgets(STDIN));
}

if (!$new_password) {
    echo "Ingresa la nueva contraseña: ";
    $new_password = trim(fgets(STDIN));
}

if (empty($username) || empty($new_password)) {
    echo "❌ Error: Usuario y contraseña son obligatorios\n\n";
    echo "Uso: php reset-password.php [username] [nueva_contraseña]\n\n";
    exit(1);
}

try {
    $stmt = $pdo->prepare('SELECT id, username, email, rol FROM usuarios WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ Error: Usuario '$username' no encontrado\n\n";
        exit(1);
    }
    
    echo "\n📋 Usuario encontrado:\n";
    echo "   - ID: {$user['id']}\n";
    echo "   - Usuario: {$user['username']}\n";
    echo "   - Email: {$user['email']}\n";
    echo "   - Rol: {$user['rol']}\n\n";
    
    echo "¿Deseas restablecer la contraseña? (s/n): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 's' && strtolower($confirm) !== 'y') {
        echo "\n⚠️  Operación cancelada\n\n";
        exit(0);
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE usuarios SET password = ?, totp_enabled = 0, totp_secret = NULL WHERE id = ?');
    $stmt->execute([$hashed_password, $user['id']]);
    
    $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, ip, accion, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user['id'], 'CLI', 'Password reset via CLI', 'reset-password.php']);
    
    echo "\n✅ Contraseña restablecida exitosamente!\n";
    echo "   El 2FA ha sido desactivado.\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
