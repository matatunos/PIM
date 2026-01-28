<?php
session_start();

echo "<h1>Test de POST</h1>";
echo "<p>Método: " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p>POST data: " . json_encode($_POST) . "</p>";
echo "<p>GET data: " . json_encode($_GET) . "</p>";
echo "<p>Session data: " . json_encode($_SESSION) . "</p>";

if (isset($_POST['verificar_2fa'])) {
    echo "<p style='color: green;'><strong>✓ POST verificar_2fa recibido!</strong></p>";
    echo "<p>Código recibido: " . htmlspecialchars($_POST['code'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>✗ No se recibió POST</p>";
}

echo "<hr>";
echo "<form method='POST' action='test-post.php?paso=test'>";
echo "<input type='text' name='code' value='123456' required>";
echo "<button type='submit' name='verificar_2fa' value='1'>Test POST</button>";
echo "</form>";
