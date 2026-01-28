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

// Eliminar contacto
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM contactos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
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
$sql = 'SELECT * FROM contactos WHERE usuario_id = ?';
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
                    <button onclick="abrirModalNuevo()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Contacto
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Barra de búsqueda -->
                <div class="barra-busqueda">
                    <form method="GET" style="display: flex; gap: var(--spacing-md); flex: 1;">
                        <input type="text" name="q" placeholder="Buscar contactos..." value="<?= htmlspecialchars($buscar) ?>" class="form-control">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        <?php if ($buscar): ?>
                            <a href="index.php" class="btn btn-ghost">Limpiar</a>
                        <?php endif; ?>
                    </form>
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
                                            <a href="tel:<?= htmlspecialchars($contacto['telefono']) ?>" style="color: var(--text-secondary);">
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
</body>
</html>
