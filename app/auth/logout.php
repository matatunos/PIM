<?php
require_once '../../config/config.php';

$user_id = $_SESSION['user_id'] ?? null;

// Registrar logout
if ($user_id) {
    $stmt = $pdo->prepare('INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user_id, 'logout', 'Cierre de sesión', 1, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
}

session_start();
session_destroy();
header('Location: /app/auth/login.php');
exit();
