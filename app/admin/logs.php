<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

// Filtros
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_exitoso = $_GET['exitoso'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

$sql = 'SELECT l.*, u.username 
        FROM logs_acceso l 
        LEFT JOIN usuarios u ON l.usuario_id = u.id 
        WHERE 1=1';
$params = [];

if (!empty($filtro_usuario)) {
    $sql .= ' AND u.username LIKE ?';
    $params[] = "%$filtro_usuario%";
}

if (!empty($filtro_tipo)) {
    $sql .= ' AND l.tipo_evento = ?';
    $params[] = $filtro_tipo;
}

if ($filtro_exitoso !== '') {
    $sql .= ' AND l.exitoso = ?';
    $params[] = (int)$filtro_exitoso;
}

if (!empty($filtro_fecha)) {
    $sql .= ' AND DATE(l.fecha_hora) = ?';
    $params[] = $filtro_fecha;
}

$sql .= ' ORDER BY l.fecha_hora DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Estadísticas del día
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_hoy,
    SUM(CASE WHEN exitoso = 1 THEN 1 ELSE 0 END) as exitosos_hoy,
    SUM(CASE WHEN exitoso = 0 THEN 1 ELSE 0 END) as fallidos_hoy
    FROM logs_acceso 
    WHERE DATE(fecha_hora) = CURDATE()");
$stats = $stmt->fetch();

// Tipos de eventos únicos
$stmt = $pdo->query('SELECT DISTINCT tipo_evento FROM logs_acceso ORDER BY tipo_evento');
$tipos_eventos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Acceso - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .stats-row {
            display: flex;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        .stat-card {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
            flex: 1;
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: var(--spacing-sm);
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .logs-table {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th {
            background: var(--bg-secondary);
            padding: var(--spacing-sm) var(--spacing-md);
            text-align: left;
            font-weight: 700;
            white-space: nowrap;
        }
        td {
            padding: var(--spacing-sm) var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }
        tr:hover {
            background: var(--bg-secondary);
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success {
            background: var(--success-light);
            color: var(--success);
        }
        .badge-danger {
            background: var(--danger-light);
            color: var(--danger);
        }
        .filtros {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
        }
        .filtros > * {
            flex: 1;
            min-width: 150px;
        }
        .ip-address {
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .user-agent {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-history"></i> Logs de Acceso</h1>
                </div>
                <div class="top-bar-right">
                    <a href="usuarios.php" class="btn btn-ghost">
                        <i class="fas fa-arrow-left"></i>
                        Volver a Usuarios
                    </a>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Estadísticas del día -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-list"></i></div>
                        <div class="stat-value"><?= $stats['total_hoy'] ?></div>
                        <div class="stat-label">Eventos hoy</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?= $stats['exitosos_hoy'] ?></div>
                        <div class="stat-label">Exitosos hoy</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--danger);"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-value"><?= $stats['fallidos_hoy'] ?></div>
                        <div class="stat-label">Fallidos hoy</div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filtros">
                    <form method="GET" style="display: contents;">
                        <input type="text" name="usuario" placeholder="Usuario..." value="<?= htmlspecialchars($filtro_usuario) ?>" class="form-control">
                        
                        <select name="tipo" class="form-control" onchange="this.form.submit()">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_eventos as $tipo): ?>
                                <option value="<?= htmlspecialchars($tipo) ?>" <?= $filtro_tipo === $tipo ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="exitoso" class="form-control" onchange="this.form.submit()">
                            <option value="">Todos los resultados</option>
                            <option value="1" <?= $filtro_exitoso === '1' ? 'selected' : '' ?>>Exitosos</option>
                            <option value="0" <?= $filtro_exitoso === '0' ? 'selected' : '' ?>>Fallidos</option>
                        </select>
                        
                        <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" class="form-control" onchange="this.form.submit()">
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        
                        <?php if ($filtro_usuario || $filtro_tipo || $filtro_exitoso !== '' || $filtro_fecha): ?>
                            <a href="logs.php" class="btn btn-ghost">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Tabla de logs -->
                <div class="logs-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Tipo Evento</th>
                                <th>Descripción</th>
                                <th>Resultado</th>
                                <th>IP</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-muted);">
                                        No se encontraron registros
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;">
                                            <?= date('d/m/Y H:i:s', strtotime($log['fecha_hora'])) ?>
                                        </td>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <strong><?= htmlspecialchars($log['username']) ?></strong>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code style="background: var(--bg-secondary); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-size: 0.8rem;">
                                                <?= htmlspecialchars($log['tipo_evento']) ?>
                                            </code>
                                        </td>
                                        <td><?= htmlspecialchars($log['descripcion']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $log['exitoso'] ? 'success' : 'danger' ?>">
                                                <?= $log['exitoso'] ? 'Exitoso' : 'Fallido' ?>
                                            </span>
                                        </td>
                                        <td class="ip-address"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                        <td>
                                            <div class="user-agent" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                                <?= htmlspecialchars($log['user_agent'] ?? '-') ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: var(--spacing-lg); color: var(--text-muted); text-align: center; font-size: 0.9rem;">
                    Mostrando los últimos 500 registros
                </div>
            </div>
        </div>
    </div>
</body>
</html>
