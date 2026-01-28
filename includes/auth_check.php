<?php
// La sesión ya se inicia en config.php, no duplicar
if (!isset($_SESSION['user_id'])) {
    header('Location: /app/auth/login.php');
    exit;
}
