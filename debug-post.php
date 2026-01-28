<?php
// Debug POST data for 2FA
require_once 'config/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/totp.php';

?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>POST Data Debug</title>
<style>
body { font-family: monospace; padding: 20px; }
.box { background: #f5f5f5; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
.label { font-weight: bold; color: #333; }
.value { color: #666; word-break: break-all; }
</style>
</head>
<body>

<h1>Debug: POST Data</h1>

<div class="box">
<div class="label">POST Data recibido:</div>
<div class="value">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
} else {
    echo 'No hay datos POST. Envía el formulario primero.';
}
?>
</div>
</div>

<div class="box">
<div class="label">GET Parameters:</div>
<div class="value">
<?php
echo '<pre>';
print_r($_GET);
echo '</pre>';
?>
</div>
</div>

<div class="box">
<div class="label">SESSION Data (usuario_id solamente):</div>
<div class="value">
<?php
echo 'usuario_id: ' . ($_SESSION['user_id'] ?? 'NO SET') . '<br>';
echo 'temp_2fa_secret: ' . (isset($_SESSION['temp_2fa_secret']) ? 'SET (' . strlen($_SESSION['temp_2fa_secret']) . ' chars)' : 'NO SET') . '<br>';
?>
</div>
</div>

<div class="box">
<div class="label">Test: TOTP Verification</div>
<div class="value">
<?php
if (isset($_SESSION['temp_2fa_secret']) && isset($_POST['code'])) {
    $code = preg_replace('/[^0-9]/', '', trim($_POST['code']));
    $secret = $_SESSION['temp_2fa_secret'];
    
    echo 'Secret: ' . htmlspecialchars($secret) . '<br>';
    echo 'Code recibido (raw): ' . htmlspecialchars($_POST['code']) . '<br>';
    echo 'Code limpio: ' . htmlspecialchars($code) . '<br>';
    echo 'Current TOTP code: ' . TOTP::getCode($secret) . '<br>';
    
    $result = TOTP::verifyCode($secret, $code);
    echo 'Verification result: ' . ($result ? 'TRUE' : 'FALSE') . '<br>';
    
    // Check all valid codes
    echo '<br>Valid codes in window:<br>';
    $currentTimeSlice = floor(time() / 30);
    for ($i = -2; $i <= 2; $i++) {
        $testCode = TOTP::getCode($secret, $currentTimeSlice + $i);
        echo ($testCode === $code ? '✓ ' : '  ') . $testCode . ' (period ' . ($i >= 0 ? '+' : '') . $i . ')<br>';
    }
}
?>
</div>
</div>

<form method="POST">
<div class="box">
<input type="text" name="code" placeholder="000000" maxlength="6" required>
<button type="submit">Test Code</button>
</div>
</form>

</body>
</html>
?>
