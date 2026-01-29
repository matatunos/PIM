<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';
$error = '';

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $contrasena_nueva = $_POST['contrasena_nueva'] ?? '';
    $contrasena_confirmar = $_POST['contrasena_confirmar'] ?? '';
    
    // Validar que los campos no estén vacíos
    if (empty($contrasena_actual) || empty($contrasena_nueva) || empty($contrasena_confirmar)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (strlen($contrasena_nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres';
    } elseif ($contrasena_nueva !== $contrasena_confirmar) {
        $error = 'Las contraseñas nuevas no coinciden';
    } else {
        // Obtener contraseña actual del usuario
        $stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        // Verificar contraseña actual
        if ($usuario && password_verify($contrasena_actual, $usuario['password'])) {
            // Actualizar contraseña
            $hash_nueva = password_hash($contrasena_nueva, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
            
            if ($stmt->execute([$hash_nueva, $usuario_id])) {
                $mensaje = 'Contraseña actualizada exitosamente';
                // Limpiar campos
                $_POST = [];
            } else {
                $error = 'Error al actualizar la contraseña';
            }
        } else {
            $error = 'La contraseña actual es incorrecta';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .password-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .form-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168, 218, 220, 0.1);
        }

        .password-strength {
            margin-top: var(--spacing-sm);
            font-size: 0.85rem;
        }

        .strength-meter {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 4px;
        }

        .strength-meter-fill {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak {
            background: var(--error);
            width: 33%;
        }

        .strength-medium {
            background: var(--warning);
            width: 66%;
        }

        .strength-strong {
            background: var(--success);
            width: 100%;
        }

        .password-requirements {
            background: var(--bg-secondary);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-top: var(--spacing-lg);
            font-size: 0.9rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
        }

        .requirement i {
            width: 16px;
            text-align: center;
        }

        .requirement.met {
            color: var(--success);
        }

        .requirement.met i {
            color: var(--success);
        }

        .buttons {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
        }

        .btn {
            flex: 1;
            padding: var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }

        .btn-ghost {
            background: var(--gray-200);
            color: var(--text-primary);
        }

        .btn-ghost:hover {
            background: var(--gray-300);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-key"></i> Cambiar Contraseña</h1>
                </div>
            </div>
            
            <div class="content-area">
                <div class="card password-container">
                    <?php if ($mensaje): ?>
                        <div class="alert alert-success" style="margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($mensaje) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" style="margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="passwordForm" style="padding: var(--spacing-lg);">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="contrasena_actual">Contraseña Actual *</label>
                            <input type="password" id="contrasena_actual" name="contrasena_actual" required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label for="contrasena_nueva">Nueva Contraseña *</label>
                            <input type="password" id="contrasena_nueva" name="contrasena_nueva" required oninput="actualizarRequirimientos()">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strengthMeter"></div>
                            </div>
                            <div class="password-strength">
                                Fortaleza: <span id="strengthText">Muy débil</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="contrasena_confirmar">Confirmar Contraseña *</label>
                            <input type="password" id="contrasena_confirmar" name="contrasena_confirmar" required oninput="verificarCoincidencia()">
                            <div id="coincidenceMsg"></div>
                        </div>
                        
                        <div class="password-requirements">
                            <div style="font-weight: 600; margin-bottom: var(--spacing-md);">Requisitos de contraseña:</div>
                            
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle"></i>
                                <span>Al menos 8 caracteres</span>
                            </div>
                            
                            <div class="requirement" id="req-uppercase">
                                <i class="fas fa-circle"></i>
                                <span>Una letra mayúscula (A-Z)</span>
                            </div>
                            
                            <div class="requirement" id="req-lowercase">
                                <i class="fas fa-circle"></i>
                                <span>Una letra minúscula (a-z)</span>
                            </div>
                            
                            <div class="requirement" id="req-number">
                                <i class="fas fa-circle"></i>
                                <span>Un número (0-9)</span>
                            </div>
                            
                            <div class="requirement" id="req-special">
                                <i class="fas fa-circle"></i>
                                <span>Un carácter especial (!@#$%^&*)</span>
                            </div>
                        </div>
                        
                        <div class="buttons">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="fas fa-save"></i> Cambiar Contraseña
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="window.history.back()">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function actualizarRequirimientos() {
            const pass = document.getElementById('contrasena_nueva').value;
            const submitBtn = document.getElementById('submitBtn');
            
            const requirements = {
                'req-length': pass.length >= 8,
                'req-uppercase': /[A-Z]/.test(pass),
                'req-lowercase': /[a-z]/.test(pass),
                'req-number': /[0-9]/.test(pass),
                'req-special': /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pass)
            };
            
            // Actualizar requisitos
            Object.keys(requirements).forEach(id => {
                const el = document.getElementById(id);
                if (requirements[id]) {
                    el.classList.add('met');
                } else {
                    el.classList.remove('met');
                }
            });
            
            // Calcular fortaleza
            const metCount = Object.values(requirements).filter(v => v).length;
            const meter = document.getElementById('strengthMeter');
            const text = document.getElementById('strengthText');
            
            let strength = 'Muy débil';
            meter.className = 'strength-meter-fill';
            
            if (metCount >= 3) {
                strength = 'Media';
                meter.classList.add('strength-medium');
            }
            if (metCount >= 5) {
                strength = 'Fuerte';
                meter.classList.add('strength-strong');
            }
            
            text.textContent = strength;
            
            // Habilitar botón si cumple requisitos y coinciden
            verificarCoincidencia();
        }
        
        function verificarCoincidencia() {
            const pass = document.getElementById('contrasena_nueva').value;
            const confirm = document.getElementById('contrasena_confirmar').value;
            const msg = document.getElementById('coincidenceMsg');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirm === '') {
                msg.innerHTML = '';
            } else if (pass === confirm) {
                msg.innerHTML = '<span style="color: var(--success); font-size: 0.9rem;"><i class="fas fa-check"></i> Las contraseñas coinciden</span>';
            } else {
                msg.innerHTML = '<span style="color: var(--error); font-size: 0.9rem;"><i class="fas fa-times"></i> Las contraseñas no coinciden</span>';
            }
            
            // Habilitar botón
            const requirements = {
                length: pass.length >= 8,
                uppercase: /[A-Z]/.test(pass),
                lowercase: /[a-z]/.test(pass),
                number: /[0-9]/.test(pass),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pass)
            };
            
            const allMet = Object.values(requirements).every(v => v);
            const coincide = pass === confirm && pass !== '';
            
            submitBtn.disabled = !(allMet && coincide);
        }
    </script>
</body>
</html>
