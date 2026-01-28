<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Obtener estadísticas del usuario
$usuario_id = $_SESSION['user_id'];

// Obtener datos del usuario para verificar 2FA
$stmt = $pdo->prepare('SELECT totp_enabled FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Tareas pendientes
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM tareas WHERE usuario_id = ? AND completada = 0');
$stmt->execute([$usuario_id]);
$tareas_pendientes = $stmt->fetch()['total'];

// Notas totales
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM notas WHERE usuario_id = ? AND archivada = 0');
$stmt->execute([$usuario_id]);
$notas_totales = $stmt->fetch()['total'];

// Eventos próximos (próximos 7 días)
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM eventos WHERE usuario_id = ? AND fecha_inicio >= NOW() AND fecha_inicio <= DATE_ADD(NOW(), INTERVAL 7 DAY)');
$stmt->execute([$usuario_id]);
$eventos_proximos = $stmt->fetch()['total'];

// Contactos
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM contactos WHERE usuario_id = ?');
$stmt->execute([$usuario_id]);
$contactos_totales = $stmt->fetch()['total'];

// Tareas urgentes
$stmt = $pdo->prepare('SELECT * FROM tareas WHERE usuario_id = ? AND completada = 0 AND prioridad = "urgente" ORDER BY IFNULL(fecha_vencimiento, "9999-12-31") ASC LIMIT 5');
$stmt->execute([$usuario_id]);
$tareas_urgentes = $stmt->fetchAll();

// Eventos de hoy
$stmt = $pdo->prepare('SELECT * FROM eventos WHERE usuario_id = ? AND DATE(fecha_inicio) = CURDATE() ORDER BY fecha_inicio ASC');
$stmt->execute([$usuario_id]);
$eventos_hoy = $stmt->fetchAll();

// Notas recientes
$stmt = $pdo->prepare('SELECT * FROM notas WHERE usuario_id = ? AND archivada = 0 ORDER BY actualizado_en DESC LIMIT 6');
$stmt->execute([$usuario_id]);
$notas_recientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PIM</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            transition: transform var(--transition-base);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, var(--secondary), #ff9eb3);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, var(--info), #bfaae5);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, var(--success), #a8dfc2);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: var(--spacing-sm);
        }
        
        .stat-label {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .quick-add {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
            margin-bottom: var(--spacing-2xl);
        }
        
        .note-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }
        
        .note-card {
            background: var(--pastel-yellow);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            cursor: pointer;
        }
        
        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .note-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }
        
        .note-content {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            max-height: 80px;
            overflow: hidden;
        }
        
        .task-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-sm);
        }
        
        .task-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-400);
            border-radius: 6px;
            cursor: pointer;
        }
        
        .task-content {
            flex: 1;
        }
        
        .task-title {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .task-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .priority-urgente {
            background: var(--danger);
            color: white;
        }
        
        .priority-alta {
            background: var(--warning);
            color: var(--gray-900);
        }
        
        .event-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-sm);
            border-left: 4px solid var(--primary);
        }
        
        .event-time {
            font-weight: 700;
            color: var(--primary);
            min-width: 60px;
        }
        
        .event-details {
            flex: 1;
        }
        
        .event-title {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .event-location {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title">Dashboard</h1>
                </div>
                <div class="top-bar-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Buscar...">
                    </div>
                    <a href="/app/auth/logout.php" class="btn btn-ghost btn-sm">
                        <i class="fas fa-sign-out-alt"></i>
                        Salir
                    </a>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Alerta 2FA si no está habilitado -->
                <?php if (!$usuario['totp_enabled']): ?>
                <div style="background: var(--warning-light); border-left: 4px solid var(--warning); padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-xl); display: flex; align-items: center; gap: var(--spacing-md);">
                    <i class="fas fa-shield-alt" style="font-size: 2rem; color: var(--warning);"></i>
                    <div style="flex: 1;">
                        <strong style="color: var(--text-primary);">Protege tu cuenta con autenticación de dos factores</strong>
                        <p style="margin: var(--spacing-xs) 0 0; color: var(--text-secondary);">
                            Añade una capa adicional de seguridad usando tu teléfono
                        </p>
                    </div>
                    <a href="/app/perfil/2fa.php" class="btn btn-warning">
                        <i class="fas fa-lock"></i>
                        Habilitar 2FA
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $tareas_pendientes ?></div>
                        <div class="stat-label">Tareas Pendientes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-sticky-note"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $notas_totales ?></div>
                        <div class="stat-label">Notas Activas</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $eventos_proximos ?></div>
                        <div class="stat-label">Eventos Próximos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-address-book"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $contactos_totales ?></div>
                        <div class="stat-label">Contactos</div>
                    </div>
                </div>
                
                <!-- Acciones Rápidas -->
                <div class="quick-add">
                    <a href="/app/tareas/index.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nueva Tarea
                    </a>
                    <a href="/app/notas/index.php?action=new" class="btn btn-secondary">
                        <i class="fas fa-plus"></i>
                        Nueva Nota
                    </a>
                    <a href="/app/calendario/index.php?action=new" class="btn btn-success">
                        <i class="fas fa-plus"></i>
                        Nuevo Evento
                    </a>
                    <a href="/app/contactos/index.php?action=new" class="btn btn-ghost">
                        <i class="fas fa-plus"></i>
                        Nuevo Contacto
                    </a>
                </div>
                
                <div class="grid grid-cols-2">
                    <!-- Tareas Urgentes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-fire" style="color: var(--danger);"></i>
                                Tareas Urgentes
                            </h3>
                            <a href="/app/tareas/index.php" class="btn btn-sm btn-ghost">Ver Todas</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tareas_urgentes)): ?>
                                <p class="text-muted text-center" style="padding: var(--spacing-xl);">
                                    <i class="fas fa-check-circle" style="font-size: 3rem; opacity: 0.3;"></i><br>
                                    No tienes tareas urgentes
                                </p>
                            <?php else: ?>
                                <?php foreach ($tareas_urgentes as $tarea): ?>
                                    <div class="task-item">
                                        <div class="task-checkbox"></div>
                                        <div class="task-content">
                                            <div class="task-title"><?= htmlspecialchars($tarea['titulo']) ?></div>
                                            <div class="task-meta">
                                                <span class="priority-badge priority-urgente">Urgente</span>
                                                <?php if ($tarea['fecha_vencimiento']): ?>
                                                    <i class="fas fa-calendar"></i> 
                                                    <?= date('d/m/Y', strtotime($tarea['fecha_vencimiento'])) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Eventos de Hoy -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-day" style="color: var(--primary);"></i>
                                Eventos de Hoy
                            </h3>
                            <a href="/app/calendario/index.php" class="btn btn-sm btn-ghost">Ver Calendario</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($eventos_hoy)): ?>
                                <p class="text-muted text-center" style="padding: var(--spacing-xl);">
                                    <i class="fas fa-calendar-check" style="font-size: 3rem; opacity: 0.3;"></i><br>
                                    No tienes eventos para hoy
                                </p>
                            <?php else: ?>
                                <?php foreach ($eventos_hoy as $evento): ?>
                                    <div class="event-item" style="border-left-color: <?= htmlspecialchars($evento['color']) ?>;">
                                        <div class="event-time">
                                            <?= date('H:i', strtotime($evento['fecha_inicio'])) ?>
                                        </div>
                                        <div class="event-details">
                                            <div class="event-title"><?= htmlspecialchars($evento['titulo']) ?></div>
                                            <?php if ($evento['ubicacion']): ?>
                                                <div class="event-location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?= htmlspecialchars($evento['ubicacion']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Notas Recientes -->
                <div class="card" style="margin-top: var(--spacing-xl);">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-sticky-note" style="color: var(--secondary);"></i>
                            Notas Recientes
                        </h3>
                        <a href="/app/notas/index.php" class="btn btn-sm btn-ghost">Ver Todas</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notas_recientes)): ?>
                            <p class="text-muted text-center" style="padding: var(--spacing-xl);">
                                <i class="fas fa-sticky-note" style="font-size: 3rem; opacity: 0.3;"></i><br>
                                No tienes notas aún
                            </p>
                        <?php else: ?>
                            <div class="note-grid">
                                <?php foreach ($notas_recientes as $nota): ?>
                                    <div class="note-card" style="background: <?= htmlspecialchars($nota['color']) ?>;">
                                        <div class="note-title"><?= htmlspecialchars($nota['titulo']) ?></div>
                                        <div class="note-content"><?= htmlspecialchars(substr($nota['contenido'], 0, 150)) ?>...</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
