<?php
require_once 'config/config.php';

// Mostrar información de sesión
echo '<pre>';
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo '</pre>';

// POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>POST Data:\n';
    print_r($_POST);
    echo '</pre>';
}
?>
