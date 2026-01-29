<?php
// Test TOTP with detailed debugging
require_once 'config/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/totp.php';

// Si hay un parámetro secret en la URL, usarlo; de lo contrario, usar el de sesión
$secret = $_GET['secret'] ?? $_SESSION['temp_2fa_secret'] ?? null;

if (!$secret) {
    die('Sin secreto. Genera un código primero.');
}

$currentTime = time();
$currentTimeSlice = floor($currentTime / 30);
$currentCode = TOTP::getCode($secret);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>TOTP Validation Test</title>
<style>
body { 
    font-family: Arial, sans-serif; 
    padding: 20px; 
    background: #f5f5f5;
    max-width: 600px;
    margin: 0 auto;
}
.container { 
    background: white; 
    padding: 20px; 
    border-radius: 8px; 
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.status { 
    padding: 15px; 
    margin: 10px 0; 
    border-radius: 4px;
    border-left: 4px solid;
}
.status.current { 
    background: #e8f5e9;
    border-color: #4caf50;
    color: #2e7d32;
}
.status.success { 
    background: #c8e6c9;
    border-color: #2e7d32;
    color: #1b5e20;
}
.status.error { 
    background: #ffcdd2;
    border-color: #f44336;
    color: #c62828;
}
.code-display { 
    font-size: 2em; 
    font-family: monospace; 
    font-weight: bold; 
    letter-spacing: 5px;
}
.time-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
}
input {
    padding: 10px;
    font-size: 1.1em;
    font-family: monospace;
    letter-spacing: 2px;
    width: 150px;
    text-align: center;
}
button {
    padding: 10px 20px;
    font-size: 1em;
    cursor: pointer;
    background: #2196f3;
    color: white;
    border: none;
    border-radius: 4px;
    margin-left: 10px;
}
button:hover {
    background: #1976d2;
}
.codes-list {
    background: #f3e5f5;
    border-left: 4px solid #9c27b0;
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
}
.codes-list code {
    background: white;
    padding: 3px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-weight: bold;
    display: inline-block;
    margin: 5px 5px 5px 0;
    border: 1px solid #ddd;
}
</style>
</head>
<body>

<div class="container">
<h1>Validador TOTP</h1>

<div class="time-info">
<strong>Hora del servidor:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
<strong>Timestamp:</strong> <?php echo $currentTime; ?><br>
<strong>Time Slice:</strong> <?php echo $currentTimeSlice; ?><br>
<strong>Segundos en período actual:</strong> <?php echo ($currentTime % 30); ?> / 30
</div>

<div class="status current">
<strong>Código TOTP Actual:</strong><br>
<div class="code-display"><?php echo $currentCode; ?></div>
<small>Válido por <?php echo (30 - ($currentTime % 30)); ?> segundos</small>
</div>

<div class="codes-list">
<strong>Todos los códigos válidos (ventana ±2 períodos):</strong><br>
<?php
for ($i = -2; $i <= 2; $i++) {
    $code = TOTP::getCode($secret, $currentTimeSlice + $i);
    $label = ($i === 0) ? " [ACTUAL]" : "";
    echo "<code>" . $code . "</code>";
    if ($label) echo "<strong style='color: green;'>$label</strong><br>";
    else echo "<br>";
}
?>
</div>

<form method="POST">
<strong>Ingresa un código de 6 dígitos:</strong><br><br>
<input type="text" name="test_code" placeholder="000000" maxlength="6" autofocus>
<button type="submit">Validar</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_code'])) {
    $testCode = preg_replace('/[^0-9]/', '', $_POST['test_code']);
    
    if (strlen($testCode) !== 6) {
        echo '<div class="status error">';
        echo '❌ El código debe tener 6 dígitos<br>';
        echo '</div>';
    } else {
        $isValid = TOTP::verifyCode($secret, $testCode);
        
        if ($isValid) {
            echo '<div class="status success">';
            echo '✓ ¡Código válido!<br>';
            echo '</div>';
        } else {
            echo '<div class="status error">';
            echo '✗ Código inválido<br>';
            echo 'Código ingresado: <strong>' . $testCode . '</strong><br>';
            
            // Check where this code would fit
            $foundInSlice = null;
            for ($i = -2; $i <= 2; $i++) {
                if (TOTP::getCode($secret, $currentTimeSlice + $i) === $testCode) {
                    $foundInSlice = $i;
                    break;
                }
            }
            
            if ($foundInSlice !== null) {
                $secondsOffset = $foundInSlice * 30;
                echo 'Este código corresponde a ';
                if ($foundInSlice === 0) {
                    echo 'AHORA MISMO (pero fue rechazado)<br>';
                    echo '<strong>Revisa que ingresaste bien el código</strong>';
                } else if ($foundInSlice > 0) {
                    echo 'dentro de ' . abs($secondsOffset) . ' segundos (futuro)<br>';
                    echo '<strong>Tu teléfono está adelantado</strong>';
                } else {
                    echo 'hace ' . abs($secondsOffset) . ' segundos (pasado)<br>';
                    echo '<strong>Tu teléfono está retrasado o el código expiró</strong>';
                }
                echo '<br><small style="color: #555;">Ajusta la hora de tu dispositivo</small>';
            } else {
                echo '<strong>Este código no existe en la ventana de validación válida</strong><br>';
                echo 'El problema es probablemente que tu teléfono tiene una hora muy diferente al servidor.';
            }
            
            echo '</div>';
        }
    }
}
?>

</div>

</body>
</html>
?>
