<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$mensaje = $error = '';

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'user';
    
    if (!empty($username) && !empty($email) && !empty($password)) {
        // Verificar que no exista
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'El usuario o email ya existe';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (username, email, password, nombre_completo, rol) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$username, $email, $password_hash, $nombre_completo, $rol]);
            $mensaje = 'Usuario creado exitosamente';
        }
    }
}

// Editar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $id = (int)$_POST['id'];
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $rol = $_POST['rol'] ?? 'user';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($email)) {
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, rol = ?, password = ? WHERE id = ?');
            $stmt->execute([$username, $email, $nombre_completo, $rol, $password_hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, rol = ? WHERE id = ?');
            $stmt->execute([$username, $email, $nombre_completo, $rol, $id]);
        }
        $mensaje = 'Usuario actualizado exitosamente';
    }
}

// Toggle activo
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id != $_SESSION['user_id']) { // No puede desactivarse a sí mismo
        $stmt = $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id = ?');
        $stmt->execute([$id]);
        $mensaje = 'Estado del usuario actualizado';
    }
}

// Deshabilitar 2FA
if (isset($_GET['disable_2fa']) && is_numeric($_GET['disable_2fa'])) {
    $id = (int)$_GET['disable_2fa'];
    $stmt = $pdo->prepare('UPDATE usuarios SET totp_enabled = 0, totp_secret = NULL, backup_codes = NULL WHERE id = ?');
    $stmt->execute([$id]);
    $mensaje = '2FA deshabilitado para el usuario';
}

// Eliminar usuario
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) { // No puede eliminarse a sí mismo
        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $mensaje = 'Usuario eliminado exitosamente';
    }
}

// Obtener usuarios
$buscar = $_GET['q'] ?? '';
$filtro_rol = $_GET['rol'] ?? '';
$filtro_activo = $_GET['activo'] ?? '';

$sql = 'SELECT u.*, 
        (SELECT COUNT(*) FROM logs_acceso WHERE usuario_id = u.id) as total_accesos,
        (SELECT MAX(fecha_hora) FROM logs_acceso WHERE usuario_id = u.id AND exitoso = 1) as ultimo_acceso
        FROM usuarios u WHERE 1=1';
$params = [];

if (!empty($buscar)) {
    $sql .= ' AND (u.username LIKE ? OR u.email LIKE ? OR u.nombre_completo LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if (!empty($filtro_rol)) {
    $sql .= ' AND u.rol = ?';
    $params[] = $filtro_rol;
}

if ($filtro_activo !== '') {
    $sql .= ' AND u.activo = ?';
    $params[] = (int)$filtro_activo;
}

$sql .= ' ORDER BY u.fecha_registro DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Estadísticas
$stmt = $pdo->query('SELECT 
    COUNT(*) as total,
    SUM(activo) as activos,
    SUM(totp_enabled) as con_2fa,
    SUM(CASE WHEN rol = "admin" THEN 1 ELSE 0 END) as admins
    FROM usuarios');
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        .stat-card {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .usuarios-table {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: var(--bg-secondary);
            padding: var(--spacing-md);
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--gray-200);
        }
        td {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }
        tr:hover {
            background: var(--bg-secondary);
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-admin {
            background: var(--danger-light);
            color: var(--danger);
        }
        .badge-user {
            background: var(--primary-light);
            color: var(--primary);
        }
        .badge-activo {
            background: var(--success-light);
            color: var(--success);
        }
        .badge-inactivo {
            background: var(--gray-200);
            color: var(--text-muted);
        }
        .badge-2fa {
            background: var(--warning-light);
            color: var(--warning);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .filtros {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
        }
        .filtros > * {
            flex: 1;
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-users-cog"></i> Gestión de Usuarios</h1>
                </div>
                <div class="top-bar-right">
                    <a href="logs.php" class="btn btn-ghost">
                        <i class="fas fa-history"></i>
                        Ver Logs
                    </a>
                    <button onclick="abrirModalNuevo()" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Nuevo Usuario
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-users"></i></div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Usuarios totales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-user-check"></i></div>
                        <div class="stat-value"><?= $stats['activos'] ?></div>
                        <div class="stat-label">Usuarios activos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-shield-alt"></i></div>
                        <div class="stat-value"><?= $stats['con_2fa'] ?></div>
                        <div class="stat-label">Con 2FA habilitado</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--danger);"><i class="fas fa-user-shield"></i></div>
                        <div class="stat-value"><?= $stats['admins'] ?></div>
                        <div class="stat-label">Administradores</div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filtros">
                    <form method="GET" style="display: contents;">
                        <input type="text" name="q" placeholder="Buscar usuarios..." value="<?= htmlspecialchars($buscar) ?>" class="form-control">
                        
                        <select name="rol" class="form-control" onchange="this.form.submit()">
                            <option value="">Todos los roles</option>
                            <option value="admin" <?= $filtro_rol === 'admin' ? 'selected' : '' ?>>Administradores</option>
                            <option value="user" <?= $filtro_rol === 'user' ? 'selected' : '' ?>>Usuarios</option>
                        </select>
                        
                        <select name="activo" class="form-control" onchange="this.form.submit()">
                            <option value="">Todos los estados</option>
                            <option value="1" <?= $filtro_activo === '1' ? 'selected' : '' ?>>Activos</option>
                            <option value="0" <?= $filtro_activo === '0' ? 'selected' : '' ?>>Inactivos</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        
                        <?php if ($buscar || $filtro_rol || $filtro_activo !== ''): ?>
                            <a href="usuarios.php" class="btn btn-ghost">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Tabla de usuarios -->
                <div class="usuarios-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>2FA</th>
                                <th>Último acceso</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($usuario['username']) ?></strong>
                                        <?php if ($usuario['nombre_completo']): ?>
                                            <br><small style="color: var(--text-muted);"><?= htmlspecialchars($usuario['nombre_completo']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $usuario['rol'] ?>">
                                            <?= $usuario['rol'] === 'admin' ? 'Admin' : 'Usuario' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $usuario['activo'] ? 'activo' : 'inactivo' ?>">
                                            <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($usuario['totp_enabled']): ?>
                                            <span class="badge badge-2fa"><i class="fas fa-lock"></i> Habilitado</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">No configurado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['ultimo_acceso']): ?>
                                            <?= date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></td>
                                    <td>
                                        <div style="display: flex; gap: var(--spacing-xs);">
                                            <button onclick="editarUsuario(<?= htmlspecialchars(json_encode($usuario)) ?>)" class="btn btn-ghost btn-icon btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                <a href="?toggle=<?= $usuario['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="<?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $usuario['activo'] ? 'ban' : 'check' ?>"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($usuario['totp_enabled']): ?>
                                                <a href="?disable_2fa=<?= $usuario['id'] ?>" class="btn btn-warning btn-icon btn-sm" onclick="return confirm('¿Deshabilitar 2FA para este usuario?')" title="Deshabilitar 2FA">
                                                    <i class="fas fa-unlock"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?= $usuario['id'] ?>" class="btn btn-danger btn-icon btn-sm" onclick="return confirm('¿Eliminar este usuario?')" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Nuevo Usuario -->
    <div id="modalUsuario" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-user-plus"></i>
                <span id="modal-title">Nuevo Usuario</span>
            </h2>
            <form method="POST" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="form-action" value="crear">
                <input type="hidden" name="id" id="usuario-id">
                
                <div class="form-group">
                    <label for="username">Usuario *</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="nombre_completo">Nombre completo</label>
                    <input type="text" id="nombre_completo" name="nombre_completo">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña <span id="password-required">*</span></label>
                    <input type="password" id="password" name="password">
                    <small class="text-muted">Deja vacío para no cambiar la contraseña (solo en edición)</small>
                </div>
                
                <div class="form-group">
                    <label for="rol">Rol *</label>
                    <select id="rol" name="rol" required class="form-control">
                        <option value="user">Usuario</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Guardar Usuario
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="cerrarModal()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function abrirModalNuevo() {
            document.getElementById('modal-title').textContent = 'Nuevo Usuario';
            document.getElementById('form-action').value = 'crear';
            document.getElementById('password').required = true;
            document.getElementById('password-required').style.display = 'inline';
            document.querySelector('form').reset();
            document.getElementById('modalUsuario').classList.add('active');
        }
        
        function editarUsuario(usuario) {
            document.getElementById('modal-title').textContent = 'Editar Usuario';
            document.getElementById('form-action').value = 'editar';
            document.getElementById('usuario-id').value = usuario.id;
            document.getElementById('username').value = usuario.username;
            document.getElementById('email').value = usuario.email;
            document.getElementById('nombre_completo').value = usuario.nombre_completo || '';
            document.getElementById('rol').value = usuario.rol;
            document.getElementById('password').required = false;
            document.getElementById('password-required').style.display = 'none';
            document.getElementById('modalUsuario').classList.add('active');
        }
        
        function cerrarModal() {
            document.getElementById('modalUsuario').classList.remove('active');
        }
        
        document.getElementById('modalUsuario').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
    </script>
</body>
</html>
