<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Solo admins pueden ver esto
if ($_SESSION['rol'] !== 'admin') {
    redirect('/index.php');
}

$success = '';
$error = '';

// ==========================================
// Acciones POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    // Bloquear IP
    if (isset($_POST['block_ip'])) {
        $ip = trim($_POST['ip_address'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $duration = intval($_POST['duration'] ?? 0);
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            try {
                $blocked_until = $duration > 0 ? date('Y-m-d H:i:s', time() + ($duration * 3600)) : null;
                $stmt = $pdo->prepare('INSERT INTO ip_blocklist (ip_address, reason, blocked_until) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = ?, blocked_until = ?');
                $stmt->execute([$ip, $reason, $blocked_until, $reason, $blocked_until]);
                security_log('IP_BLOCKED', "Admin bloqueó IP: $ip - Razón: $reason");
                $success = "IP $ip bloqueada correctamente.";
            } catch (Exception $e) {
                $error = "Error al bloquear IP: " . $e->getMessage();
            }
        } else {
            $error = "IP no válida";
        }
    }
    
    // Desbloquear IP
    if (isset($_POST['unblock_ip'])) {
        $ip = trim($_POST['ip_address'] ?? '');
        try {
            $stmt = $pdo->prepare('DELETE FROM ip_blocklist WHERE ip_address = ?');
            $stmt->execute([$ip]);
            security_log('IP_UNBLOCKED', "Admin desbloqueó IP: $ip");
            $success = "IP $ip desbloqueada.";
        } catch (Exception $e) {
            $error = "Error al desbloquear IP";
        }
    }
    
    // Cerrar sesión de usuario
    if (isset($_POST['kill_session'])) {
        $session_id = trim($_POST['session_id'] ?? '');
        try {
            $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE session_id = ?');
            $stmt->execute([$session_id]);
            security_log('SESSION_KILLED', "Admin cerró sesión: $session_id");
            $success = "Sesión terminada.";
        } catch (Exception $e) {
            $error = "Error al cerrar sesión";
        }
    }
    
    // Limpiar logs antiguos
    if (isset($_POST['clean_logs'])) {
        $days = intval($_POST['days'] ?? 30);
        try {
            $stmt = $pdo->prepare('DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            security_log('LOGS_CLEANED', "Admin limpió $deleted logs antiguos (>$days días)");
            $success = "$deleted registros eliminados.";
        } catch (Exception $e) {
            $error = "Error al limpiar logs";
        }
    }
}

// ==========================================
// Obtener datos
// ==========================================

// Estadísticas de seguridad (últimas 24h)
try {
    $stmt = $pdo->query("SELECT event_type, COUNT(*) as count FROM security_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY event_type ORDER BY count DESC");
    $stats_24h = $stmt->fetchAll();
} catch (Exception $e) {
    $stats_24h = [];
}

// IPs más activas con fallos
try {
    $stmt = $pdo->query("SELECT ip_address, COUNT(*) as count FROM security_logs WHERE event_type IN ('LOGIN_FAIL', 'SQLI_ATTEMPT', 'XSS_ATTEMPT', 'RATE_LIMIT') AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY ip_address ORDER BY count DESC LIMIT 10");
    $suspicious_ips = $stmt->fetchAll();
} catch (Exception $e) {
    $suspicious_ips = [];
}

// IPs bloqueadas
try {
    $stmt = $pdo->query("SELECT * FROM ip_blocklist ORDER BY created_at DESC");
    $blocked_ips = $stmt->fetchAll();
} catch (Exception $e) {
    $blocked_ips = [];
}

// Sesiones activas
try {
    $stmt = $pdo->query("SELECT us.*, u.username FROM user_sessions us JOIN usuarios u ON us.user_id = u.id ORDER BY us.last_activity DESC LIMIT 50");
    $active_sessions = $stmt->fetchAll();
} catch (Exception $e) {
    $active_sessions = [];
}

// Últimos eventos de seguridad
try {
    $stmt = $pdo->query("SELECT sl.*, u.username FROM security_logs sl LEFT JOIN usuarios u ON sl.user_id = u.id ORDER BY sl.created_at DESC LIMIT 100");
    $recent_logs = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_logs = [];
}

$lang = $_SESSION['lang'] ?? 'es';
require_once "../../app/idiomas/{$lang}.php";
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Seguridad - PIM</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .event-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .event-LOGIN_FAIL { background: #fee2e2; color: #991b1b; }
        .event-CSRF_FAIL { background: #fef3c7; color: #92400e; }
        .event-SQLI_ATTEMPT { background: #fecaca; color: #7f1d1d; }
        .event-XSS_ATTEMPT { background: #fecaca; color: #7f1d1d; }
        .event-RATE_LIMIT { background: #fed7aa; color: #9a3412; }
        .event-ACCOUNT_LOCKOUT { background: #fecdd3; color: #9f1239; }
        .event-SESSION_HIJACK { background: #fecaca; color: #7f1d1d; }
        .event-IP_BLOCKED { background: #dbeafe; color: #1e40af; }
        .event-default { background: #e5e7eb; color: #374151; }
        .log-table {
            font-size: 0.85rem;
        }
        .log-table td {
            vertical-align: middle;
        }
        .content-area {
            padding: var(--spacing-xl);
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        .col-md-3, .col-md-6 {
            padding: 0 10px;
            box-sizing: border-box;
        }
        .col-md-3 { width: 25%; }
        .col-md-6 { width: 50%; }
        @media (max-width: 992px) {
            .col-md-3, .col-md-6 { width: 50%; }
        }
        @media (max-width: 768px) {
            .col-md-3, .col-md-6 { width: 100%; }
        }
        .mb-4 { margin-bottom: 1.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        .mb-0 { margin-bottom: 0; }
        .me-2 { margin-right: 0.5rem; }
        .gap-2 { gap: 0.5rem; }
        .d-flex { display: flex; }
        .d-inline { display: inline; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }
        .text-muted { color: var(--text-secondary); }
        .text-nowrap { white-space: nowrap; }
        .text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .text-center { text-align: center; }
        .py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        .p-0 { padding: 0; }
        .w-100 { width: 100%; }
        .sticky-top { position: sticky; top: 0; z-index: 10; }
        .bg-white { background: var(--bg-primary); }
        .card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .card-header {
            background: var(--bg-secondary);
            padding: var(--spacing-md) var(--spacing-lg);
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header.bg-warning { background: #fbbf24; color: #1f2937; }
        .card-header.bg-danger { background: #ef4444; color: white; }
        .card-body { padding: var(--spacing-lg); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: var(--spacing-sm) var(--spacing-md); text-align: left; border-bottom: 1px solid var(--border-color); }
        .table-sm th, .table-sm td { padding: var(--spacing-xs) var(--spacing-sm); }
        .table-hover tbody tr:hover { background: var(--bg-secondary); }
        .table-responsive { overflow-x: auto; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge.bg-danger { background: #ef4444; color: white; }
        .badge.bg-primary { background: var(--primary); color: white; }
        .form-control, .form-select { 
            padding: 6px 12px; 
            border: 1px solid var(--border-color); 
            border-radius: var(--radius-md); 
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .form-control-sm, .form-select-sm { padding: 4px 8px; font-size: 0.875rem; }
        .btn-sm { padding: 4px 8px; font-size: 0.875rem; }
        .btn-outline-success { border: 1px solid #22c55e; color: #22c55e; background: transparent; }
        .btn-outline-success:hover { background: #22c55e; color: white; }
        .btn-outline-secondary { border: 1px solid var(--border-color); color: var(--text-secondary); background: transparent; }
        .btn-outline-secondary:hover { background: var(--bg-secondary); }
        .alert { padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .list-unstyled { list-style: none; padding: 0; margin: 0; }
        .list-unstyled li { padding: var(--spacing-xs) 0; }
        code { background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-shield-alt"></i> Panel de Seguridad</h1>
                </div>
                <div class="top-bar-right">
                    <span class="badge bg-primary">v<?= PIM_VERSION ?></span>
                </div>
            </div>
            
            <div class="content-area">
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= h($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= h($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Estadísticas 24h -->
                <div class="row mb-4">
                    <?php
                    $total_events = array_sum(array_column($stats_24h, 'count'));
                    $login_fails = 0;
                    $attacks = 0;
                    foreach ($stats_24h as $stat) {
                        if ($stat['event_type'] === 'LOGIN_FAIL') $login_fails = $stat['count'];
                        if (in_array($stat['event_type'], ['SQLI_ATTEMPT', 'XSS_ATTEMPT'])) $attacks += $stat['count'];
                    }
                    ?>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_events ?></div>
                            <div>Eventos (24h)</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card danger">
                            <div class="stat-number"><?= $login_fails ?></div>
                            <div>Logins Fallidos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="stat-number"><?= $attacks ?></div>
                            <div>Intentos de Ataque</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="stat-number"><?= count($blocked_ips) ?></div>
                            <div>IPs Bloqueadas</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- IPs Sospechosas -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-exclamation-triangle me-2"></i>IPs Sospechosas (24h)
                            </div>
                            <div class="card-body">
                                <?php if (empty($suspicious_ips)): ?>
                                    <p class="text-muted mb-0">No hay actividad sospechosa</p>
                                <?php else: ?>
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>IP</th>
                                                <th>Eventos</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suspicious_ips as $sip): ?>
                                            <tr>
                                                <td><code><?= h($sip['ip_address']) ?></code></td>
                                                <td><span class="badge bg-danger"><?= $sip['count'] ?></span></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="ip_address" value="<?= h($sip['ip_address']) ?>">
                                                        <input type="hidden" name="reason" value="Actividad sospechosa automática">
                                                        <input type="hidden" name="duration" value="24">
                                                        <button type="submit" name="block_ip" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-ban"></i> Bloquear
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- IPs Bloqueadas -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-ban me-2"></i>IPs Bloqueadas
                            </div>
                            <div class="card-body">
                                <!-- Formulario para bloquear IP -->
                                <form method="POST" class="row g-2 mb-3">
                                    <?= csrf_field() ?>
                                    <div class="col-5">
                                        <input type="text" name="ip_address" class="form-control form-control-sm" placeholder="IP a bloquear" required>
                                    </div>
                                    <div class="col-3">
                                        <select name="duration" class="form-select form-select-sm">
                                            <option value="1">1 hora</option>
                                            <option value="24" selected>24 horas</option>
                                            <option value="168">1 semana</option>
                                            <option value="0">Permanente</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <button type="submit" name="block_ip" class="btn btn-sm btn-danger w-100">
                                            <i class="fas fa-plus"></i> Bloquear
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Razón (opcional)">
                                    </div>
                                </form>
                                
                                <?php if (empty($blocked_ips)): ?>
                                    <p class="text-muted mb-0">No hay IPs bloqueadas</p>
                                <?php else: ?>
                                    <div style="max-height: 200px; overflow-y: auto;">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>IP</th>
                                                    <th>Hasta</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($blocked_ips as $bip): ?>
                                                <tr>
                                                    <td><code><?= h($bip['ip_address']) ?></code></td>
                                                    <td><?= $bip['blocked_until'] ? date('d/m H:i', strtotime($bip['blocked_until'])) : 'Permanente' ?></td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="ip_address" value="<?= h($bip['ip_address']) ?>">
                                                            <button type="submit" name="unblock_ip" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Log de Seguridad -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-history me-2"></i>Últimos Eventos de Seguridad</span>
                        <form method="POST" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <select name="days" class="form-select form-select-sm" style="width: auto;">
                                <option value="7">+7 días</option>
                                <option value="30" selected>+30 días</option>
                                <option value="90">+90 días</option>
                            </select>
                            <button type="submit" name="clean_logs" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-broom"></i> Limpiar
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover log-table mb-0">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Usuario</th>
                                        <th>IP</th>
                                        <th>Mensaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_logs)): ?>
                                        <tr><td colspan="5" class="text-muted text-center py-4">No hay eventos registrados</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_logs as $log): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= date('d/m H:i:s', strtotime($log['created_at'])) ?></td>
                                            <td>
                                                <span class="event-badge event-<?= h($log['event_type']) ?> event-default">
                                                    <?= h($log['event_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= h($log['username'] ?? '-') ?></td>
                                            <td><code><?= h($log['ip_address']) ?></code></td>
                                            <td class="text-truncate" style="max-width: 300px;" title="<?= h($log['message']) ?>">
                                                <?= h($log['message']) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Información del sistema -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Estado de Seguridad
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Protecciones Activas:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Content Security Policy (CSP)</li>
                                    <li><i class="fas fa-check text-success me-2"></i>CSRF Protection</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Rate Limiting (Login)</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Session Hijack Detection</li>
                                    <li><i class="fas fa-check text-success me-2"></i>SQL Injection Detection</li>
                                    <li><i class="fas fa-check text-success me-2"></i>XSS Detection</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Secure Headers</li>
                                    <li><i class="fas fa-check text-success me-2"></i>HSTS (si HTTPS)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Configuración:</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Timeout de sesión</td>
                                        <td><code>30 minutos</code></td>
                                    </tr>
                                    <tr>
                                        <td>Intentos de login</td>
                                        <td><code>5 antes de bloqueo</code></td>
                                    </tr>
                                    <tr>
                                        <td>Tiempo de bloqueo</td>
                                        <td><code>15 minutos</code></td>
                                    </tr>
                                    <tr>
                                        <td>Contraseña mínima</td>
                                        <td><code>12 caracteres + símbolos</code></td>
                                    </tr>
                                    <tr>
                                        <td>PHP Version</td>
                                        <td><code><?= phpversion() ?></code></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/ajax-nav.js"></script>
</body>
</html>
