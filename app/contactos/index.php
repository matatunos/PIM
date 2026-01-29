<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// Crear contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $notas = trim($_POST['notas'] ?? '');
    $favorito = isset($_POST['favorito']) ? 1 : 0;
    
    if (!empty($nombre)) {
        $stmt = $pdo->prepare('INSERT INTO contactos (usuario_id, nombre, email, telefono, empresa, cargo, direccion, notas, favorito) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $nombre, $email, $telefono, $empresa, $cargo, $direccion, $notas, $favorito]);
        header('Location: index.php');
        exit;
    }
}

// Editar contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $notas = trim($_POST['notas'] ?? '');
    $favorito = isset($_POST['favorito']) ? 1 : 0;
    
    if (!empty($nombre)) {
        $stmt = $pdo->prepare('UPDATE contactos SET nombre = ?, email = ?, telefono = ?, empresa = ?, cargo = ?, direccion = ?, notas = ?, favorito = ? WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$nombre, $email, $telefono, $empresa, $cargo, $direccion, $notas, $favorito, $id, $usuario_id]);
        header('Location: index.php');
        exit;
    }
}

// Mover contacto a papelera
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('UPDATE contactos SET borrado_en = NOW() WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    
    // Registrar en papelera_logs
    $stmt = $pdo->prepare('SELECT nombre FROM contactos WHERE id = ?');
    $stmt->execute([$id]);
    $contacto = $stmt->fetch();
    $stmt = $pdo->prepare('INSERT INTO papelera_logs (usuario_id, tipo, item_id, nombre) VALUES (?, ?, ?, ?)');
    $stmt->execute([$usuario_id, 'contactos', $id, $contacto['nombre'] ?? 'Sin nombre']);
    
    header('Location: index.php');
    exit;
}

// Toggle favorito
if (isset($_GET['fav']) && is_numeric($_GET['fav'])) {
    $id = (int)$_GET['fav'];
    $stmt = $pdo->prepare('UPDATE contactos SET favorito = NOT favorito WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    header('Location: index.php');
    exit;
}

// Obtener contactos
$buscar = $_GET['q'] ?? '';
$sql = 'SELECT * FROM contactos WHERE usuario_id = ? AND borrado_en IS NULL';
$params = [$usuario_id];

if (!empty($buscar)) {
    $sql .= ' AND (nombre LIKE ? OR email LIKE ? OR empresa LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= ' ORDER BY favorito DESC, nombre ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contactos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactos - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .contactos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        .contacto-card {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            position: relative;
        }
        .contacto-card:hover {
            box-shadow: var(--shadow-lg);
        }
        .contacto-card.favorito {
            border-top: 3px solid var(--warning);
        }
        .contacto-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        .contacto-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .contacto-info h3 {
            margin: 0 0 var(--spacing-xs) 0;
            font-size: 1.3rem;
            color: var(--text-primary);
        }
        .contacto-empresa {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .contacto-detalles {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        .contacto-detalle {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .contacto-detalle i {
            width: 20px;
            color: var(--primary);
        }
        .phone-link {
            color: var(--primary);
            text-decoration: none;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        .phone-link:hover {
            background: var(--primary);
            color: white;
            text-decoration: none;
        }
        .contacto-actions {
            position: absolute;
            top: var(--spacing-md);
            right: var(--spacing-md);
            display: flex;
            gap: var(--spacing-xs);
        }
        .star-badge {
            position: absolute;
            top: var(--spacing-md);
            left: var(--spacing-md);
            color: var(--warning);
            font-size: 1.2rem;
        }
        .barra-busqueda {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }
        .barra-busqueda input {
            flex: 1;
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
        
        /* Vista Mosaico */
        .contactos-container[data-view="mosaico"] .contactos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        
        /* Vista Lista */
        .contactos-container[data-view="lista"] .contactos-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        .contactos-container[data-view="lista"] .contacto-card {
            display: grid;
            grid-template-columns: 80px 1fr 250px 150px 80px;
            grid-template-rows: auto auto;
            align-items: center;
            gap: var(--spacing-lg);
            padding: var(--spacing-lg);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            position: relative;
            min-height: 80px;
        }
        .contactos-container[data-view="lista"] .contacto-avatar {
            grid-column: 1;
            grid-row: 1 / 3;
            width: 80px;
            height: 80px;
        }
        .contactos-container[data-view="lista"] .contacto-header {
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
            grid-column: 2;
            grid-row: 1 / 3;
        }
        .contactos-container[data-view="lista"] .contacto-info {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }
        .contactos-container[data-view="lista"] .contacto-detalles {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            font-size: 0.85rem;
            grid-column: 3;
            grid-row: 1 / 3;
        }
        .contactos-container[data-view="lista"] .contacto-detalle {
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        .contactos-container[data-view="lista"] .contacto-info h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .contactos-container[data-view="lista"] .contacto-empresa {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .contactos-container[data-view="lista"] .star-badge {
            position: absolute;
            top: var(--spacing-md);
            right: var(--spacing-md);
        }
        .contactos-container[data-view="lista"] .contacto-actions {
            position: static;
            display: flex;
            gap: var(--spacing-xs);
            grid-column: 5;
            grid-row: 1 / 3;
            justify-content: flex-end;
            align-items: center;
        }
        
        /* Vista Compacta */
        .contactos-container[data-view="compacta"] .contactos-grid {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .contactos-container[data-view="compacta"] .contacto-card {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            background: var(--bg-primary);
            border-bottom: 1px solid var(--gray-200);
            border-radius: 0;
            box-shadow: none;
            position: relative;
            min-height: 48px;
        }
        .contactos-container[data-view="compacta"] .contacto-card:first-child {
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .contactos-container[data-view="compacta"] .contacto-card:last-child {
            border-bottom: none;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }
        .contactos-container[data-view="compacta"] .contacto-card:hover {
            background: var(--bg-secondary);
            box-shadow: none;
        }
        .contactos-container[data-view="compacta"] .contacto-header {
            margin: 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: var(--spacing-sm);
            flex: 1;
        }
        .contactos-container[data-view="compacta"] .contacto-avatar {
            width: 40px;
            height: 40px;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .contactos-container[data-view="compacta"] .contacto-info h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .contactos-container[data-view="compacta"] .contacto-empresa {
            display: none;
        }
        .contactos-container[data-view="compacta"] .contacto-detalles {
            display: none;
        }
        .contactos-container[data-view="compacta"] .star-badge {
            position: relative;
            top: auto;
            right: auto;
        }
        .contactos-container[data-view="compacta"] .contacto-actions {
            display: none;
        }
        
        /* Icono de llamada en vista compacta */
        .contacto-call-btn {
            display: none;
        }
        .contactos-container[data-view="compacta"] .contacto-call-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contacto-call-btn a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--success);
            color: white;
            text-decoration: none;
            transition: all var(--transition-fast);
            font-size: 0.9rem;
        }
        .contacto-call-btn a:hover {
            background: #5ed496;
            transform: scale(1.15);
        }
        
        /* Vista Contenido */
        .contactos-container[data-view="contenido"] .contactos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: var(--spacing-lg);
        }
        .contactos-container[data-view="contenido"] .contacto-card {
            display: block;
        }
        
        /* Vista Detalles */
        .contactos-container[data-view="detalles"] .contactos-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }
        .contactos-container[data-view="detalles"] .contacto-card {
            display: block;
            width: 100%;
        }
        .contactos-container[data-view="detalles"] .contacto-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        /* Botones de vista */
        .view-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 1rem;
            transition: all var(--transition-fast);
            border-radius: 0;
        }
        .view-btn:hover {
            color: var(--primary);
            background: var(--bg-secondary);
        }
        .view-btn.active {
            color: var(--primary);
            background: var(--bg-secondary);
        }
        
        @media (max-width: 768px) {
            .contactos-container[data-view="lista"] .contacto-card {
                grid-template-columns: 60px 1fr auto;
            }
            .contactos-container[data-view="contenido"] .contactos-grid {
                grid-template-columns: 1fr;
            }
            .view-toggle {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-address-book"></i> Contactos</h1>
                </div>
                <div class="top-bar-right">
                    <a href="/api/exportar-contactos.php" class="btn btn-secondary" style="margin-right: var(--spacing-md);" download="contactos.vcf" title="Exportar contactos para iOS y Android">
                        <i class="fas fa-file-export"></i>
                        Exportar VCF
                    </a>
                    <a href="importar.php" class="btn btn-secondary" style="margin-right: var(--spacing-md);">
                        <i class="fas fa-file-import"></i>
                        Importar CSV
                    </a>
                    <button onclick="abrirModalNuevo()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Contacto
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Barra de búsqueda y vistas -->
                <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; align-items: center; margin-bottom: var(--spacing-lg);">
                    <form method="GET" style="display: flex; gap: var(--spacing-md); flex: 1; min-width: 250px;">
                        <input type="text" name="q" placeholder="Buscar contactos..." value="<?= htmlspecialchars($buscar) ?>" class="form-control">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($buscar): ?>
                            <a href="index.php" class="btn btn-ghost">Limpiar</a>
                        <?php endif; ?>
                    </form>
                    
                    <div class="view-toggle" style="display: flex; gap: var(--spacing-xs); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0;">
                        <button class="view-btn" onclick="cambiarVista('mosaico')" title="Mosaico" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('lista')" title="Lista" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-list"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('compacta')" title="Compacta" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('contenido')" title="Contenido" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-align-left"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('detalles')" title="Detalles" style="border: none;">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Grid de contactos -->
                <?php if (empty($contactos)): ?>
                    <div class="card">
                        <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                            <i class="fas fa-address-book" style="font-size: 4rem; color: var(--gray-300);"></i>
                            <h3 style="margin-top: var(--spacing-lg); color: var(--text-secondary);">No hay contactos</h3>
                            <p class="text-muted">Añade tu primer contacto para comenzar</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="contactos-container" data-view="mosaico" id="contactosContainer">
                        <div class="contactos-grid">
                        <?php foreach ($contactos as $contacto): ?>
                            <div class="contacto-card <?= $contacto['favorito'] ? 'favorito' : '' ?>">
                                <?php if ($contacto['favorito']): ?>
                                    <i class="fas fa-star star-badge"></i>
                                <?php endif; ?>
                                
                                <div class="contacto-actions">
                                    <button onclick="editarContacto(<?= $contacto['id'] ?>)" class="btn btn-ghost btn-icon btn-sm" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?fav=<?= $contacto['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Favorito">
                                        <i class="fas fa-star"></i>
                                    </a>
                                    <a href="?delete=<?= $contacto['id'] ?>" class="btn btn-danger btn-icon btn-sm" onclick="return confirm('¿Eliminar este contacto?')" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                                
                                <?php if ($contacto['telefono']): ?>
                                    <div class="contacto-call-btn">
                                        <a href="tel:<?= htmlspecialchars($contacto['telefono']) ?>" title="Llamar a <?= htmlspecialchars($contacto['telefono']) ?>">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="contacto-header">
                                    <div class="contacto-avatar">
                                        <?= strtoupper(substr($contacto['nombre'], 0, 2)) ?>
                                    </div>
                                    <div class="contacto-info">
                                        <h3><?= htmlspecialchars($contacto['nombre']) ?></h3>
                                        <?php if ($contacto['cargo'] && $contacto['empresa']): ?>
                                            <div class="contacto-empresa">
                                                <?= htmlspecialchars($contacto['cargo']) ?> en <?= htmlspecialchars($contacto['empresa']) ?>
                                            </div>
                                        <?php elseif ($contacto['empresa']): ?>
                                            <div class="contacto-empresa"><?= htmlspecialchars($contacto['empresa']) ?></div>
                                        <?php elseif ($contacto['cargo']): ?>
                                            <div class="contacto-empresa"><?= htmlspecialchars($contacto['cargo']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="contacto-detalles">
                                    <?php if ($contacto['email']): ?>
                                        <div class="contacto-detalle">
                                            <i class="fas fa-envelope"></i>
                                            <a href="mailto:<?= htmlspecialchars($contacto['email']) ?>" style="color: var(--text-secondary);">
                                                <?= htmlspecialchars($contacto['email']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($contacto['telefono']): ?>
                                        <div class="contacto-detalle">
                                            <i class="fas fa-phone"></i>
                                            <a href="tel:<?= htmlspecialchars($contacto['telefono']) ?>" class="phone-link" title="Llamar a <?= htmlspecialchars($contacto['telefono']) ?>">
                                                <?= htmlspecialchars($contacto['telefono']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($contacto['direccion']): ?>
                                        <div class="contacto-detalle">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?= htmlspecialchars($contacto['direccion']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($contacto['notas']): ?>
                                        <div class="contacto-detalle" style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--gray-200);">
                                            <i class="fas fa-sticky-note"></i>
                                            <span><?= htmlspecialchars($contacto['notas']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Contacto -->
    <div id="modalContacto" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-user-plus"></i>
                <span id="modal-title">Nuevo Contacto</span>
            </h2>
            <form method="POST" class="form" id="formContacto">
                <input type="hidden" name="action" id="form-action" value="crear">
                <input type="hidden" name="id" id="contacto-id">
                
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div class="form-group">
                        <label for="empresa">Empresa</label>
                        <input type="text" id="empresa" name="empresa">
                    </div>
                    
                    <div class="form-group">
                        <label for="cargo">Cargo</label>
                        <input type="text" id="cargo" name="cargo">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion">
                </div>
                
                <div class="form-group">
                    <label for="notas">Notas</label>
                    <textarea id="notas" name="notas" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="favorito" name="favorito">
                        Marcar como favorito
                    </label>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Guardar Contacto
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="cerrarModal()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const contactosData = <?= json_encode($contactos) ?>;
        
        // Vista system
        function cambiarVista(tipo) {
            const container = document.getElementById('contactosContainer');
            if (!container) return;
            
            container.setAttribute('data-view', tipo);
            localStorage.setItem('contactos-view', tipo);
            
            // Actualizar botones
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.view-btn').classList.add('active');
        }
        
        // Cargar vista guardada
        document.addEventListener('DOMContentLoaded', function() {
            // Detectar si es móvil y usar compacta por defecto
            const isMobile = window.innerWidth <= 768;
            const defaultView = isMobile ? 'compacta' : 'mosaico';
            const savedView = localStorage.getItem('contactos-view') || defaultView;
            const container = document.getElementById('contactosContainer');
            if (container) {
                container.setAttribute('data-view', savedView);
                document.querySelectorAll('.view-btn').forEach((btn, idx) => {
                    const views = ['mosaico', 'lista', 'compacta', 'contenido', 'detalles'];
                    if (views[idx] === savedView) {
                        btn.classList.add('active');
                    }
                });
            }
        });
        
        function abrirModalNuevo() {
            document.getElementById('modal-title').textContent = 'Nuevo Contacto';
            document.getElementById('form-action').value = 'crear';
            document.getElementById('formContacto').reset();
            document.getElementById('modalContacto').classList.add('active');
        }
        
        function editarContacto(id) {
            const contacto = contactosData.find(c => c.id == id);
            if (!contacto) return;
            
            document.getElementById('modal-title').textContent = 'Editar Contacto';
            document.getElementById('form-action').value = 'editar';
            document.getElementById('contacto-id').value = contacto.id;
            document.getElementById('nombre').value = contacto.nombre;
            document.getElementById('email').value = contacto.email || '';
            document.getElementById('telefono').value = contacto.telefono || '';
            document.getElementById('empresa').value = contacto.empresa || '';
            document.getElementById('cargo').value = contacto.cargo || '';
            document.getElementById('direccion').value = contacto.direccion || '';
            document.getElementById('notas').value = contacto.notas || '';
            document.getElementById('favorito').checked = contacto.favorito == 1;
            
            document.getElementById('modalContacto').classList.add('active');
        }
        
        function cerrarModal() {
            document.getElementById('modalContacto').classList.remove('active');
        }
        
        document.getElementById('modalContacto').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
    </script>
    <script src="/assets/js/hamburger.js"></script>
</body>
</html>
