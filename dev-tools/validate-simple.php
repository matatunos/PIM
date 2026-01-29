<?php
// Simple TOTP validator with explicit timezone
date_default_timezone_set('Europe/Madrid');

session_start();
require_once 'includes/auth_check.php';
require_once 'includes/totp.php';

$secret = $_GET['secret'] ?? $_SESSION['temp_2fa_secret'] ?? null;

if (!$secret) {
    die('Sin secreto');
}

$currentTime = time();
$currentTimeSlice = floor($currentTime / 30);
$currentCode = TOTP::getCode($secret);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>TOTP Simple Test</title>
<style>
body { font-family: Arial; padding: 20px; max-width: 600px; margin: 0 auto; }
.status { padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid; }
.current { background: #e8f5e9; border-color: #4caf50; }
.code { font-size: 2em; font-family: monospace; font-weight: bold; letter-spacing: 3px; }
input { padding: 10px; font-size: 1.1em; font-family: monospace; width: 150px; text-align: center; }
button { padding: 10px 20px; margin-left: 10px; cursor: pointer; background: #2196f3; color: white; border: none; border-radius: 4px; }
.success { background: #c8e6c9; border-color: #2e7d32; color: #1b5e20; }
.error { background: #ffcdd2; border-color: #f44336; color: #c62828; }
</style>
</head>
<body>

<h1>TOTP Simple Test</h1>

<div class="status current">
<strong>Hora servidor:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
<strong>Timestamp Unix:</strong> <?php echo $currentTime; ?><br>
<strong>Código actual:</strong><br>
<div class="code" style="color: #2e7d32;"><?php echo $currentCode; ?></div>
</div>

<div style="margin: 20px 0;">
<form method="POST">
<input type="text" name="code" placeholder="000000" maxlength="6" autofocus>
<button type="submit">Validar</button>
</form>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = preg_replace('/[^0-9]/', '', $_POST['code']);
    
    if (strlen($code) !== 6) {
        echo '<div class="status error">❌ 6 dígitos</div>';
    } elseif (TOTP::verifyCode($secret, $code)) {
        echo '<div class="status success">✓ ¡Válido!</div>';
    } else {
        echo '<div class="status error">✗ Inválido - Esperado: ' . $currentCode . '</div>';
    }
}
?>

</body>
</html>
?>
