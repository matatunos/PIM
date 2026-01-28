<?php
// Debug TOTP generation
date_default_timezone_set('Europe/Madrid');
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/totp.php';

?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>TOTP Debug Completo</title>
<style>
body { font-family: Arial; padding: 20px; max-width: 800px; margin: 0 auto; }
.box { background: #f5f5f5; padding: 15px; margin: 15px 0; border: 1px solid #ddd; border-radius: 4px; }
.label { font-weight: bold; color: #333; margin-bottom: 8px; }
.code { font-family: monospace; background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 4px; word-break: break-all; }
.section { margin: 20px 0; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px; }
</style>
</head>
<body>

<h1>Debug TOTP Completo</h1>

<div class="box">
<div class="label">Secreto en Sesión:</div>
<?php if (isset($_SESSION['temp_2fa_secret'])): ?>
    <div class="code"><?php echo htmlspecialchars($_SESSION['temp_2fa_secret']); ?></div>
    <p><strong>Longitud:</strong> <?php echo strlen($_SESSION['temp_2fa_secret']); ?> caracteres</p>
<?php else: ?>
    <div style="color: red;"><strong>NO HAY SECRETO EN SESIÓN</strong></div>
    <p>Primero abre /app/perfil/2fa.php y haz click en "Habilitar 2FA"</p>
<?php endif; ?>
</div>

<?php if (isset($_SESSION['temp_2fa_secret'])): ?>

<div class="section">
<h3>Código TOTP Actual:</h3>
<?php 
$secret = $_SESSION['temp_2fa_secret'];
$currentTime = time();
$currentCode = TOTP::getCode($secret);
?>
<div style="font-size: 2em; font-family: monospace; font-weight: bold; color: #2e7d32; letter-spacing: 3px;">
<?php echo $currentCode; ?>
</div>
<p>Hora del servidor: <?php echo date('H:i:s'); ?> (<?php echo date('Y-m-d H:i:s'); ?>)</p>
</div>

<div class="box">
<div class="label">Todos los Códigos Válidos (Ventana ±2 períodos):</div>
<?php
$currentTimeSlice = floor($currentTime / 30);
for ($i = -2; $i <= 2; $i++) {
    $code = TOTP::getCode($secret, $currentTimeSlice + $i);
    $label = ($i === 0) ? " ← ACTUAL" : "";
    echo "<div style='margin: 5px 0;'><code style='background: white; padding: 5px; border: 1px solid #ccc;'>" . $code . "</code> (período " . ($i >= 0 ? '+' : '') . ($i*30) . "s)" . $label . "</div>";
}
?>
</div>

<div class="box">
<div class="label">Probar Código Manualmente:</div>
<form method="POST">
<input type="text" name="manual_code" placeholder="000000" maxlength="6" style="padding: 8px; font-size: 1.1em; font-family: monospace; width: 120px;">
<button type="submit" style="padding: 8px 15px; cursor: pointer; margin-left: 10px;">Validar</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_code'])) {
    $testCode = preg_replace('/[^0-9]/', '', $_POST['manual_code']);
    
    if (strlen($testCode) !== 6) {
        echo '<div style="color: red; margin-top: 10px;">❌ Debe tener 6 dígitos</div>';
    } else {
        $result = TOTP::verifyCode($secret, $testCode);
        if ($result) {
            echo '<div style="color: green; margin-top: 10px;">✓ ¡Código válido!</div>';
        } else {
            echo '<div style="color: red; margin-top: 10px;">✗ Código inválido: ' . htmlspecialchars($testCode) . '</div>';
        }
    }
}
?>
</div>

<div class="box">
<div class="label">URL QR Actual:</div>
<?php echo '<a href="' . htmlspecialchars(TOTP::getQRCodeUrl('user', $secret)) . '" target="_blank">Ver QR en Google Charts</a>'; ?>
</div>

<div class="box">
<div class="label">OTPAuth URL (copia esto si necesitas ingresar manualmente):</div>
<div class="code"><?php echo htmlspecialchars(TOTP::getOTPAuthUrl('user', $secret)); ?></div>
</div>

<?php else: ?>
<p style="color: red; font-size: 1.2em;">⚠️ Necesitas generar un secreto primero. Abre /app/perfil/2fa.php y haz click en "Habilitar 2FA"</p>
<?php endif; ?>

</body>
</html>
?>
