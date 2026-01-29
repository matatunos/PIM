<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/audit_logger.php';

// Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

// Parámetros de filtrado
$tipo_evento = $_GET['tipo'] ?? '';
$usuario_id_filtro = $_GET['usuario'] ?? '';
$dia = $_GET['dia'] ?? '';
$pagina = max(1, (int)($_GET['page'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Construir WHERE
$where = [];
$params = [];

if (!empty($tipo_evento)) {
    $where[] = 'tipo_evento = ?';
    $params[] = $tipo_evento;
}

if (!empty($usuario_id_filtro)) {
    $where[] = 'usuario_id = ?';
    $params[] = (int)$usuario_id_filtro;
}

if (!empty($dia)) {
    $where[] = 'DATE(fecha_hora) = ?';
    $params[] = $dia;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Contar total
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM logs_acceso $where_clause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener logs
$stmt = $pdo->prepare("
    SELECT l.*, u.username 
    FROM logs_acceso l 
    LEFT JOIN usuarios u ON l.usuario_id = u.id 
    $where_clause 
    ORDER BY l.fecha_hora DESC 
    LIMIT ? OFFSET ?
");
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener usuarios para el filtro
$stmt = $pdo->prepare('SELECT id, username FROM usuarios ORDER BY username');
$stmt->execute();
$usuarios = $stmt->fetchAll();

// Estadísticas del día actual
$stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(exitoso) as exitosas FROM logs_acceso WHERE DATE(fecha_hora) = CURDATE()');
$stmt->execute();
$stats_hoy = $stmt->fetch();

// Top 5 usuarios más activos hoy
$stmt = $pdo->prepare('
    SELECT usuario_id, u.username, COUNT(*) as acciones 
    FROM logs_acceso l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE DATE(l.fecha_hora) = CURDATE()
    GROUP BY usuario_id 
    ORDER BY acciones DESC 
    LIMIT 5
');
$stmt->execute();
$top_usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - PIM Admin</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/fonts/fontawesome/css/all.min.css">
    <style>
        .log-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .log-exitoso {
            background-color: #d4edda;
            color: #155724;
        }
        .log-fallido {
            background-color: #f8d7da;
            color: #721c24;
        }
        .log-row {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }
        .log-row:hover {
            background-color: var(--bg-secondary);
        }
        .log-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 1.1em;
        }
        .log-content {
            flex: 1;
        }
        .log-user {
            font-weight: bold;
            color: var(--text-primary);
        }
        .log-action {
            font-size: 0.9em;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .log-time {
            text-align: right;
            font-size: 0.85em;
            color: var(--text-secondary);
        }
        .log-ip {
            font-family: monospace;
            font-size: 0.85em;
            color: var(--text-secondary);
        }
        .stats-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            transition: transform var(--transition-base);
        }
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .stats-card h3 {
            margin: 0;
            font-size: 2rem;
        }
        .stats-card p {
            margin: var(--spacing-sm) 0 0 0;
            opacity: 0.9;
        }
        .stats-card.secondary {
            background: linear-gradient(135deg, #a8dfc2, #d4f5e2);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #d5c7f0, #e8e0f5);
        }
        .stats-card.success {
            background: linear-gradient(135deg, #fed9a8, #fff3cd);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-shield-alt"></i> Auditoría y Logs</h1>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Estadísticas -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-lg); margin-bottom: var(--spacing-lg);">
                    <div class="stats-card">
                        <h3><?= $stats_hoy['total'] ?></h3>
                        <p>Acciones hoy</p>
                    </div>
                    <div class="stats-card secondary">
                        <h3><?= $stats_hoy['exitosas'] ?? 0 ?></h3>
                        <p>Acciones exitosas</p>
                    </div>
                    <div class="stats-card info">
                        <h3><?= ($stats_hoy['total'] - ($stats_hoy['exitosas'] ?? 0)) ?></h3>
                        <p>Acciones fallidas</p>
                    </div>
                    <div class="stats-card success">
                        <h3><?= count($top_usuarios) ?></h3>
                        <p>Usuarios activos</p>
                    </div>
                </div>

                <!-- Top Usuarios -->
                <div class="card" style="margin-bottom: var(--spacing-lg);">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-users"></i> Top 5 Usuarios Activos (Hoy)</h2>
                    </div>
                    <div class="card-body">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: var(--spacing-sm); text-align: left;">Usuario</th>
                                    <th style="padding: var(--spacing-sm); text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_usuarios as $tu): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: var(--spacing-sm);"><?= htmlspecialchars($tu['username'] ?? 'Usuario Eliminado') ?></td>
                                        <td style="padding: var(--spacing-sm); text-align: center;">
                                            <span style="background-color: var(--primary-color); color: white; padding: 4px 12px; border-radius: 12px; font-weight: bold;">
                                                <?= $tu['acciones'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card" style="margin-bottom: var(--spacing-lg);">
                    <div class="card-header">
                        <h3 style="margin: 0;"><i class="fas fa-filter"></i> Filtros</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md);">
                            <div>
                                <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: bold;">Tipo de Evento</label>
                                <select name="tipo" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                    <option value="">Todos</option>
                                    <?php foreach (AUDIT_TIPOS as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $tipo_evento === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: bold;">Usuario</label>
                                <select name="usuario" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                    <option value="">Todos</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $usuario_id_filtro == $u['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: bold;">Fecha</label>
                                <input type="date" name="dia" value="<?= htmlspecialchars($dia) ?>" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                            </div>
                            <div style="display: flex; gap: var(--spacing-sm); align-items: flex-end;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                                <a href="auditoria.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs -->
                <div class="card">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-history"></i> Historial de Acciones (<?= $total ?> total)</h2>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (!empty($logs)): ?>
                            <div>
                                <?php foreach ($logs as $log): ?>
                                    <div class="log-row">
                                        <div class="log-icon" style="background-color: <?= getTipoEventoColor($log['tipo_evento']) ?>;">
                                            <i class="<?= getTipoEventoIcon($log['tipo_evento']) ?>"></i>
                                        </div>
                                        <div class="log-content">
                                            <div>
                                                <span class="log-user"><?= htmlspecialchars($log['username'] ?? 'Usuario Eliminado') ?></span>
                                                <span style="margin-left: var(--spacing-sm);">
                                                    <strong><?= getTipoEventoLabel($log['tipo_evento']) ?></strong>
                                                </span>
                                                <span class="log-badge <?= $log['exitoso'] ? 'log-exitoso' : 'log-fallido' ?>">
                                                    <?= $log['exitoso'] ? 'EXITOSO' : 'FALLIDO' ?>
                                                </span>
                                            </div>
                                            <div class="log-action">
                                                <strong><?= htmlspecialchars($log['accion'] ?? 'N/A') ?></strong>
                                                <?php if (!empty($log['descripcion'])): ?>
                                                    — <?= htmlspecialchars($log['descripcion']) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="log-ip">
                                                <i class="fas fa-map-marker-alt"></i> IP: <?= htmlspecialchars($log['ip'] ?? 'DESCONOCIDA') ?>
                                                <?php if (!empty($log['user_agent'])): ?>
                                                    <span title="<?= htmlspecialchars($log['user_agent']) ?>">
                                                        | UA: <?= htmlspecialchars(substr($log['user_agent'], 0, 40)) ?>...
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="log-time">
                                            <?= date('d/m/Y H:i:s', strtotime($log['fecha_hora'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Paginación -->
                            <?php if ($total_paginas > 1): ?>
                                <div style="padding: var(--spacing-lg); text-align: center; border-top: 1px solid var(--border-color);">
                                    <?php if ($pagina > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="btn btn-sm btn-secondary">« Primera</a>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina - 1])) ?>" class="btn btn-sm btn-secondary">‹ Anterior</a>
                                    <?php endif; ?>

                                    <span style="margin: 0 var(--spacing-md);">
                                        Página <strong><?= $pagina ?></strong> de <strong><?= $total_paginas ?></strong>
                                    </span>

                                    <?php if ($pagina < $total_paginas): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina + 1])) ?>" class="btn btn-sm btn-secondary">Siguiente ›</a>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_paginas])) ?>" class="btn btn-sm btn-secondary">Última »</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: var(--spacing-md); display: block;"></i>
                                <p>No hay registros que mostrar</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
