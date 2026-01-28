<?php
session_start();
require_once 'includes/totp.php';

// Obtener el secreto de la sesión si existe
$secret = $_SESSION['temp_2fa_secret'] ?? '';

if (empty($secret)) {
    echo "<p style='color: red;'><strong>No hay secreto en la sesión.</strong></p>";
    echo "<p><a href='app/perfil/2fa.php'>Ir a 2FA</a></p>";
    exit;
}

echo "<h1>Debug TOTP</h1>";
echo "<p><strong>Secreto:</strong> <code>$secret</code></p>";

// Mostrar código actual
$currentCode = TOTP::getCode($secret);
echo "<p><strong>Código actual:</strong> <code style='font-size: 1.5em; color: green;'>$currentCode</code></p>";

// Mostrar códigos válidos en los últimos 3 períodos
echo "<h2>Códigos válidos (últimos 90 segundos):</h2>";
echo "<ul>";
$currentTimeSlice = floor(time() / 30);
for ($i = -1; $i <= 1; $i++) {
    $code = TOTP::getCode($secret, $currentTimeSlice + $i);
    $period = $currentTimeSlice + $i;
    $timestamp = $period * 30;
    $datetime = date('H:i:s', $timestamp);
    $now = time();
    $difference = $now - $timestamp;
    echo "<li>$code (período $period, timestamp $timestamp, hace {$difference}s)</li>";
}
echo "</ul>";

// Test de verificación
echo "<h2>Test de verificación:</h2>";
if (isset($_POST['test_code'])) {
    $testCode = preg_replace('/[^0-9]/', '', $_POST['test_code']);
    $result = TOTP::verifyCode($secret, $testCode);
    
    echo "<p><strong>Código ingresado:</strong> $testCode</p>";
    if ($result) {
        echo "<p style='color: green;'><strong>✓ Código válido!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Código inválido</strong></p>";
    }
}

echo "<form method='POST'>";
echo "<label>Prueba ingresando un código:</label><br>";
echo "<input type='text' name='test_code' maxlength='6' pattern='[0-9]{6}' placeholder='000000' required>";
echo "<button type='submit'>Verificar</button>";
echo "</form>";

echo "<hr>";
echo "<p><a href='app/perfil/2fa.php?paso=configurar'>Volver a 2FA</a></p>";
