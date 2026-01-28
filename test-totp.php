<?php
require_once 'includes/totp.php';

// Generar un secreto de prueba
$testSecret = TOTP::generateSecret();
echo "<h1>Test TOTP</h1>";
echo "<p>Secreto: <code>$testSecret</code></p>";

// Generar URL QR
$url = TOTP::getQRCodeUrl('test@example.com', $testSecret);
echo "<p><a href='$url' target='_blank'>Ver QR Code</a></p>";

// Mostrar códigos TOTP para los últimos 3 períodos
echo "<h2>Códigos Válidos (últimos 90 segundos):</h2>";
echo "<ul>";
$currentTime = floor(time() / 30);
for ($i = -1; $i <= 1; $i++) {
    $code = TOTP::getCode($testSecret, $currentTime + $i);
    $timestamp = ($currentTime + $i) * 30;
    $datetime = date('Y-m-d H:i:s', $timestamp);
    echo "<li>$code (válido desde: $datetime)</li>";
}
echo "</ul>";

// Test de verificación
echo "<h2>Test de Verificación:</h2>";
if (isset($_POST['test_code'])) {
    $testCode = preg_replace('/[^0-9]/', '', $_POST['test_code']);
    $result = TOTP::verifyCode($testSecret, $testCode);
    if ($result) {
        echo "<p style='color: green;'><strong>✓ Código válido!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Código inválido</strong></p>";
        $currentCode = TOTP::getCode($testSecret);
        echo "<p>Código actual: <code>$currentCode</code></p>";
    }
}

echo "<form method='POST'>";
echo "<label>Ingresa un código para probar:</label><br>";
echo "<input type='text' name='test_code' maxlength='6' pattern='[0-9]{6}' required>";
echo "<button type='submit'>Verificar</button>";
echo "</form>";

echo "<hr>";
echo "<p><a href='app/perfil/2fa.php'>Volver a 2FA</a></p>";
