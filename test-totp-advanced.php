<?php
// Permitir acceso para usuarios autenticados
require_once 'config/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/totp.php';

$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$usuario_id) {
    die('No autenticado');
}

// Conectar a la base de datos
require_once 'config/database.php';

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario['totp_secret']) {
    die('El usuario no tiene 2FA configurado');
}

$secret = $usuario['totp_secret'];

// Mostrar información de debugging
$currentTime = time();
$currentTimeSlice = floor($currentTime / 30);
$currentCode = TOTP::getCode($secret);

echo "<!DOCTYPE html>";
echo "<html>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>TOTP Test Advanced</title>";
echo "<link rel='stylesheet' href='/assets/css/styles.css'>";
echo "<style>";
echo "body { padding: 20px; font-family: Arial, sans-serif; }";
echo ".info { background: #e8f5e9; padding: 15px; border-left: 4px solid #2e7d32; margin: 10px 0; border-radius: 4px; }";
echo ".code { background: #fff3e0; padding: 15px; border-left: 4px solid #f57c00; margin: 10px 0; border-radius: 4px; }";
echo ".code .big { font-size: 2em; font-weight: bold; font-family: monospace; }";
echo ".time { background: #e3f2fd; padding: 15px; border-left: 4px solid #1976d2; margin: 10px 0; border-radius: 4px; }";
echo ".danger { background: #ffebee; padding: 15px; border-left: 4px solid #c62828; margin: 10px 0; border-radius: 4px; }";
echo ".test-codes { background: #f3e5f5; padding: 15px; border-left: 4px solid #7b1fa2; margin: 10px 0; border-radius: 4px; }";
echo "code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>TOTP Test - Validación Avanzada</h1>";

echo "<div class='info'>";
echo "<strong>Usuario:</strong> " . htmlspecialchars($usuario['email']) . "<br>";
echo "</div>";

echo "<div class='time'>";
echo "<strong>Información de Tiempo:</strong><br>";
echo "Hora actual del servidor: <code>" . date('Y-m-d H:i:s') . "</code><br>";
echo "Timestamp Unix: <code>" . $currentTime . "</code><br>";
echo "Time Slice Actual: <code>" . $currentTimeSlice . "</code> (Período de 30 segundos)<br>";
echo "Segundos restantes en período actual: <code>" . (30 - ($currentTime % 30)) . "s</code>";
echo "</div>";

echo "<div class='code'>";
echo "<strong>Código TOTP Actual:</strong><br>";
echo "<span class='big' style='color: #2e7d32;'>" . $currentCode . "</span><br>";
echo "<small>Este código es válido durante los próximos " . (30 - ($currentTime % 30)) . " segundos</small>";
echo "</div>";

echo "<div class='test-codes'>";
echo "<strong>Códigos Válidos en Ventana de ±2 Períodos (120 segundos):</strong><br>";

for ($i = -2; $i <= 2; $i++) {
    $testCode = TOTP::getCode($secret, $currentTimeSlice + $i);
    $timeOffset = $i * 30;
    $statusTime = $currentTime + $timeOffset;
    
    $isActive = ($i === 0) ? '← ACTIVO AHORA' : '';
    echo "<code>" . $testCode . "</code> (Período " . ($i >= 0 ? '+' : '') . ($i*30) . "s) " . $isActive . "<br>";
}

echo "</div>";

echo "<div class='danger'>";
echo "<strong>Probando verificación:</strong><br>";
echo "Ingresa tu código de 6 dígitos:<br>";
echo "<form method='POST'>";
echo "<input type='text' name='test_code' maxlength='6' placeholder='000000' style='padding: 8px; font-size: 1.2em; font-family: monospace; width: 120px;'>";
echo "<button type='submit' style='padding: 8px 15px; cursor: pointer;'>Verificar</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testCode = preg_replace('/[^0-9]/', '', $_POST['test_code'] ?? '');
    
    if (strlen($testCode) !== 6) {
        echo "<p style='color: red;'>El código debe tener exactamente 6 dígitos</p>";
    } else {
        $result = TOTP::verifyCode($secret, $testCode);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✓ Código válido!</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ Código inválido</p>";
            echo "<p>Esperado: <code>" . $currentCode . "</code> pero recibiste: <code>" . $testCode . "</code></p>";
            
            // Mostrar dónde debería estar el código
            $foundIn = null;
            for ($i = -2; $i <= 2; $i++) {
                $testCodeCheck = TOTP::getCode($secret, $currentTimeSlice + $i);
                if ($testCodeCheck === $testCode) {
                    $foundIn = $i;
                    break;
                }
            }
            
            if ($foundIn !== null) {
                $timeOffset = $foundIn * 30;
                echo "<p>El código que ingresaste corresponde a hace " . abs($timeOffset) . " segundos (" . ($foundIn === 0 ? 'ahora mismo' : ($foundIn > 0 ? 'en el futuro' : 'en el pasado')) . ")</p>";
                echo "<p style='color: #f57c00;'><strong>Posible causa:</strong> Tu reloj está desincronizado con el servidor. Ajusta la hora de tu teléfono.</p>";
            } else {
                echo "<p><strong>Posible causa:</strong> El código que usaste no coincide con ninguno en la ventana de validación. Puede ser que:</p>";
                echo "<ul>";
                echo "<li>El código expiró (válido solo 30 segundos)</li>";
                echo "<li>Tu teléfono tiene una hora muy diferente al servidor</li>";
                echo "<li>El código QR se escaneó incorrectamente</li>";
                echo "</ul>";
            }
        }
    }
}

echo "</div>";

// Mostrar información del servidor
echo "<div class='info' style='margin-top: 30px;'>";
echo "<strong>Información del servidor:</strong><br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Timezone: " . date_default_timezone_get() . "<br>";
echo "</div>";

echo "</body>";
echo "</html>";
?>
