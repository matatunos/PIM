<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/totp.php';

$mensaje = $error = '';
$usuario_id = $_SESSION['user_id'];

// Obtener datos del usuario
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

// Generar nuevo secreto
if (isset($_POST['generar_secreto'])) {
    $secret = TOTP::generateSecret();
    $_SESSION['temp_2fa_secret'] = $secret;
    header('Location: 2fa.php?paso=configurar');
    exit;
}

// Verificar y activar 2FA
if (isset($_POST['verificar_2fa'])) {
    $code = preg_replace('/[^0-9]/', '', trim($_POST['code'] ?? ''));
    $secret = $_SESSION['temp_2fa_secret'] ?? '';
    
    if (empty($secret)) {
        $error = 'Sesión expirada. Por favor, comienza el proceso nuevamente.';
    } elseif (empty($code) || strlen($code) !== 6) {
        $error = 'El código debe tener exactamente 6 dígitos.';
    } elseif (!TOTP::verifyCode($secret, $code)) {
        // Debug: Mostrar información de validación
        $currentCode = TOTP::getCode($secret);
        $error = 'Código incorrecto. Código actual esperado: <strong>' . $currentCode . '</strong>. ' .
                 'Si viste un código diferente en tu app, puede ser que tu teléfono tenga una hora incorrecta. ' .
                 'Los códigos cambian cada 30 segundos, así que asegúrate de que estés introduciendo el código que muestra tu app ahora mismo.';
    } else {
        // Generar códigos de respaldo
        $backupCodes = TOTP::generateBackupCodes(10);
        $backupCodesJson = json_encode($backupCodes);
        
        // Activar 2FA
        $stmt = $pdo->prepare('UPDATE usuarios SET totp_secret = ?, totp_enabled = 1, backup_codes = ? WHERE id = ?');
        $stmt->execute([$secret, $backupCodesJson, $usuario_id]);
        
        // Guardar códigos para mostrar
        $_SESSION['backup_codes'] = $backupCodes;
        unset($_SESSION['temp_2fa_secret']);
        
        header('Location: 2fa.php?paso=completado');
        exit;
    }
}

// Desactivar 2FA
if (isset($_POST['desactivar_2fa'])) {
    $password = $_POST['password'] ?? '';
    
    if (password_verify($password, $usuario['password'])) {
        $stmt = $pdo->prepare('UPDATE usuarios SET totp_enabled = 0, totp_secret = NULL, backup_codes = NULL WHERE id = ?');
        $stmt->execute([$usuario_id]);
        
        $mensaje = 'Autenticación de dos factores desactivada exitosamente';
        
        // Recargar datos
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
    } else {
        $error = 'Contraseña incorrecta';
    }
}

// Regenerar códigos de respaldo
if (isset($_POST['regenerar_codigos'])) {
    $password = $_POST['password'] ?? '';
    
    if (password_verify($password, $usuario['password'])) {
        $backupCodes = TOTP::generateBackupCodes(10);
        $backupCodesJson = json_encode($backupCodes);
        
        $stmt = $pdo->prepare('UPDATE usuarios SET backup_codes = ? WHERE id = ?');
        $stmt->execute([$backupCodesJson, $usuario_id]);
        
        $_SESSION['backup_codes'] = $backupCodes;
        header('Location: 2fa.php?paso=codigos');
        exit;
    } else {
        $error = 'Contraseña incorrecta';
    }
}

$paso = $_GET['paso'] ?? '';
// Limpiar sesión temporal solo si no estamos en configuración y no hay datos de respaldo a mostrar
if ($paso !== 'configurar' && $paso !== 'completado' && $paso !== 'codigos') {
    unset($_SESSION['backup_codes'], $_SESSION['temp_2fa_secret']);
}
?>
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autenticación de Dos Factores - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/2fa-fix.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        /* Estilos específicos 2FA - Todos delegados a 2fa-fix.css */
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-shield-alt"></i> Autenticación de Dos Factores</h1>
                </div>
                <div class="top-bar-right">
                    <a href="/index.php" class="btn btn-ghost">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="setup-container">
                    <?php if ($paso === 'configurar' && isset($_SESSION['temp_2fa_secret'])): ?>
                        <!-- PASO 2: Escanear QR y verificar -->
                        <div class="setup-card">
                            <h2><i class="fas fa-qrcode"></i> Configurar Autenticador</h2>
                            
                            <div class="steps">
                                <div class="step">
                                    <strong>Descarga una app de autenticación</strong>
                                    <p>Google Authenticator, Microsoft Authenticator, o cualquier app compatible con TOTP</p>
                                </div>
                                
                                <div class="step">
                                    <strong>Escanea el código QR</strong>
                                    <div class="qr-code">
                                        <img src="<?= TOTP::getQRCodeUrl($usuario['username'], $_SESSION['temp_2fa_secret']) ?>" 
                                             alt="QR Code" 
                                             onerror="this.style.display='none'; document.getElementById('qr-error').style.display='block';">
                                        <div id="qr-error" class="qr-error-msg hidden">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            No se pudo cargar el código QR. Usa el código manual a continuación.
                                        </div>
                                    </div>
                                    <p class="text-center">O introduce manualmente el código:</p>
                                    <div class="secret-code" id="secret-display">
                                        <?= htmlspecialchars($_SESSION['temp_2fa_secret']) ?>
                                    </div>
                                    <div style="text-align: center; margin-top: 10px;">
                                        <button type="button" onclick="copiarSecretoDinamico()" class="btn btn-sm" style="padding: 6px 12px; font-size: 0.9em;">
                                            <i class="fas fa-copy"></i> Copiar código
                                        </button>
                                    </div>
                                    <script>
                                        function copiarSecretoDinamico() {
                                            const secretElement = document.getElementById('secret-display');
                                            const secretText = secretElement.innerText.trim();
                                            
                                            navigator.clipboard.writeText(secretText).then(() => {
                                                alert('Código copiado al portapapeles');
                                            }).catch(() => {
                                                // Fallback para navegadores antiguos
                                                const tempInput = document.createElement('input');
                                                tempInput.value = secretText;
                                                document.body.appendChild(tempInput);
                                                tempInput.select();
                                                document.execCommand('copy');
                                                document.body.removeChild(tempInput);
                                                alert('Código copiado al portapapeles');
                                            });
                                        }
                                    </script>
                                </div>
                                
                                <div class="step">
                                    <strong>Verifica con un código</strong>
                                    <p style="font-size: 0.85rem; color: #666; margin: 10px 0;">
                                        Los códigos cambian cada 30 segundos. Introduce el código de 6 dígitos de tu app.
                                    </p>
                                    <form method="POST" action="2fa.php?paso=configurar">
                                        <?= csrf_field() ?>
                                        <div class="form-group">
                                            <label for="code">Código de 6 dígitos</label>
                                            <input type="text" 
                                                   id="code" 
                                                   name="code" 
                                                   class="totp-input"
                                                   maxlength="6" 
                                                   pattern="[0-9]{6}" 
                                                   required 
                                                   autofocus 
                                                   inputmode="numeric" 
                                                   placeholder="000000">
                                        </div>
                                        
                                        <div class="btn-group">
                                            <button type="submit" name="verificar_2fa" value="1" class="btn btn-primary">
                                                <i class="fas fa-check"></i>
                                                Verificar y Activar
                                            </button>
                                            <a href="2fa.php" class="btn btn-ghost">Cancelar</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($paso === 'completado' && isset($_SESSION['backup_codes'])): ?>
                        <!-- PASO 3: Mostrar códigos de respaldo -->
                        <div class="setup-card">
                            <h2 class="success-title"><i class="fas fa-check-circle"></i> 2FA Activado Exitosamente</h2>
                            
                            <div class="warning-box">
                                <p class="warning-text">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Guarda estos códigos de respaldo en un lugar seguro
                                </p>
                                <p class="warning-subtext">
                                    Podrás usarlos para acceder a tu cuenta si pierdes acceso a tu app de autenticación. Cada código solo puede usarse una vez.
                                </p>
                            </div>
                            
                            <div class="backup-codes">
                                <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                                    <div class="backup-code"><?= htmlspecialchars($code) ?></div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center-margin">
                                <button onclick="window.print()" class="btn btn-ghost">
                                    <i class="fas fa-print"></i>
                                    Imprimir Códigos
                                </button>
                                <a href="2fa.php" class="btn btn-primary">
                                    <i class="fas fa-check"></i>
                                    He Guardado los Códigos
                                </a>
                            </div>
                        </div>
                        
                    <?php elseif ($paso === 'codigos' && isset($_SESSION['backup_codes'])): ?>
                        <!-- Mostrar códigos regenerados -->
                        <div class="setup-card">
                            <h2><i class="fas fa-key"></i> Nuevos Códigos de Respaldo</h2>
                            
                            <div class="warning-box">
                                <p class="warning-text">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Los códigos anteriores ya no son válidos
                                </p>
                            </div>
                            
                            <div class="backup-codes">
                                <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                                    <div class="backup-code"><?= htmlspecialchars($code) ?></div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center-margin">
                                <button onclick="window.print()" class="btn btn-ghost">
                                    <i class="fas fa-print"></i>
                                    Imprimir Códigos
                                </button>
                                <a href="2fa.php" class="btn btn-primary">Continuar</a>
                            </div>
                        </div>
                        
                    <?php else: ?>
        <?php 
        // Solo borrar sesión si realmente no estamos en configuración
        if ($paso !== 'configurar') {
            unset($_SESSION['backup_codes'], $_SESSION['temp_2fa_secret']);
        }
        ?>
                        <!-- PASO 1: Estado actual -->
                        <div class="setup-card">
                            <div class="status-header">
                                <h2>Estado de 2FA</h2>
                                <span class="status-badge status-<?= $usuario['totp_enabled'] ? 'enabled' : 'disabled' ?>">
                                    <i class="fas fa-<?= $usuario['totp_enabled'] ? 'lock' : 'unlock' ?>"></i>
                                    <?= $usuario['totp_enabled'] ? 'Habilitado' : 'Deshabilitado' ?>
                                </span>
                            </div>
                            
                            <?php if (!$usuario['totp_enabled']): ?>
                                <p>La autenticación de dos factores añade una capa adicional de seguridad a tu cuenta. Necesitarás tu contraseña y un código de tu teléfono para iniciar sesión.</p>
                                
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="generar_secreto" class="btn btn-primary btn-lg">
                                        <i class="fas fa-shield-alt"></i>
                                        Habilitar 2FA
                                    </button>
                                </form>
                            <?php else: ?>
                                <p>Tu cuenta está protegida con autenticación de dos factores.</p>
                                
                                <div class="btn-group">
                                    <button onclick="document.getElementById('modalRegenerar').classList.add('active')" class="btn btn-ghost">
                                        <i class="fas fa-sync"></i>
                                        Regenerar Códigos de Respaldo
                                    </button>
                                    
                                    <button onclick="document.getElementById('modalDesactivar').classList.add('active')" class="btn btn-danger">
                                        <i class="fas fa-times"></i>
                                        Desactivar 2FA
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($usuario['totp_enabled']): ?>
                            <!-- Información adicional -->
                            <div class="setup-card">
                                <h3><i class="fas fa-info-circle"></i> Información</h3>
                                <ul class="info-list">
                                    <li>Los códigos se regeneran cada 30 segundos</li>
                                    <li>Usa códigos de respaldo si pierdes acceso a tu app</li>
                                    <li>Cada código de respaldo solo funciona una vez</li>
                                    <li>Regenera códigos si crees que fueron comprometidos</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Desactivar 2FA -->
    <div id="modalDesactivar" class="modal">
        <div class="modal-content modal-content-2fa">
            <h2 class="modal-title">
                <i class="fas fa-exclamation-triangle danger-icon"></i>
                Desactivar 2FA
            </h2>
            <p>¿Estás seguro de que quieres desactivar la autenticación de dos factores? Tu cuenta será menos segura.</p>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="password_desactivar">Confirma tu contraseña</label>
                    <input type="password" id="password_desactivar" name="password" required>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="desactivar_2fa" class="btn btn-danger">
                        Desactivar 2FA
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalDesactivar').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Regenerar Códigos -->
    <div id="modalRegenerar" class="modal">
        <div class="modal-content modal-content-2fa">
            <h2 class="modal-title">
                <i class="fas fa-sync"></i>
                Regenerar Códigos de Respaldo
            </h2>
            <p>Los códigos anteriores dejarán de funcionar. Asegúrate de guardar los nuevos códigos.</p>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="password_regenerar">Confirma tu contraseña</label>
                    <input type="password" id="password_regenerar" name="password" required>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="regenerar_codigos" class="btn btn-primary">
                        Regenerar Códigos
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalRegenerar').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
</body>
</html>
