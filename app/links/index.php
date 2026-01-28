<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// Crear link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $titulo = trim($_POST['titulo'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $icono = trim($_POST['icono'] ?? 'fa-link');
    $categoria = trim($_POST['categoria'] ?? 'General');
    $color = $_POST['color'] ?? '#a8dadc';
    
    if (!empty($titulo) && !empty($url)) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $mensaje = 'URL inválida';
        } else {
            $stmt = $pdo->prepare('INSERT INTO links (usuario_id, titulo, url, descripcion, icono, categoria, color) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$usuario_id, $titulo, $url, $descripcion, $icono, $categoria, $color]);
            $mensaje = 'Link añadido exitosamente';
        }
    }
}

// Actualizar visitas
if (isset($_GET['visit']) && is_numeric($_GET['visit'])) {
    $id = (int)$_GET['visit'];
    $stmt = $pdo->prepare('UPDATE links SET visitas = visitas + 1 WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    
    $stmt = $pdo->prepare('SELECT url FROM links WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    $link = $stmt->fetch();
    if ($link) {
        header('Location: ' . $link['url']);
        exit;
    }
}

// Eliminar link
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM links WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    header('Location: index.php');
    exit;
}

// Toggle favorito
if (isset($_GET['fav']) && is_numeric($_GET['fav'])) {
    $id = (int)$_GET['fav'];
    $stmt = $pdo->prepare('UPDATE links SET favorito = NOT favorito WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    header('Location: index.php');
    exit;
}

// Obtener links
$filtro_categoria = $_GET['categoria'] ?? 'todas';
$sql = 'SELECT * FROM links WHERE usuario_id = ?';
$params = [$usuario_id];

if ($filtro_categoria !== 'todas') {
    $sql .= ' AND categoria = ?';
    $params[] = $filtro_categoria;
}

$sql .= ' ORDER BY favorito DESC, orden ASC, titulo ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$links = $stmt->fetchAll();

// Obtener categorías
$stmt = $pdo->prepare('SELECT DISTINCT categoria FROM links WHERE usuario_id = ? ORDER BY categoria');
$stmt->execute([$usuario_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Agrupar links por categoría
$links_por_categoria = [];
foreach ($links as $link) {
    $cat = $link['categoria'];
    if (!isset($links_por_categoria[$cat])) {
        $links_por_categoria[$cat] = [];
    }
    $links_por_categoria[$cat][] = $link;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Links - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .category-section {
            margin-bottom: var(--spacing-2xl);
        }
        .category-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--gray-200);
        }
        .category-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-lg);
        }
        .link-card {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            position: relative;
            border-left: 4px solid var(--primary);
            cursor: pointer;
        }
        .link-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .link-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            margin-bottom: var(--spacing-md);
        }
        .link-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }
        .link-description {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
            line-height: 1.5;
        }
        .link-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .link-actions {
            position: absolute;
            top: var(--spacing-md);
            right: var(--spacing-md);
            display: flex;
            gap: var(--spacing-sm);
            opacity: 0;
            transition: opacity var(--transition-base);
        }
        .link-card:hover .link-actions {
            opacity: 1;
        }
        .fav-icon {
            color: var(--warning);
            position: absolute;
            top: var(--spacing-sm);
            right: var(--spacing-sm);
            font-size: 1.2rem;
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
        .color-picker {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            cursor: pointer;
            border: 3px solid transparent;
            transition: all var(--transition-fast);
        }
        .color-option:hover {
            transform: scale(1.1);
        }
        .color-option.selected {
            border-color: var(--text-primary);
        }
        .icon-picker {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: var(--spacing-sm);
            max-height: 200px;
            overflow-y: auto;
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }
        .icon-option {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1.5rem;
            transition: all var(--transition-fast);
        }
        .icon-option:hover {
            background: var(--primary);
            color: white;
        }
        .icon-option.selected {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-link"></i> Links</h1>
                </div>
                <div class="top-bar-right">
                    <select onchange="location.href='?categoria='+this.value" style="padding: 0.5rem 1rem; border: 2px solid var(--gray-200); border-radius: var(--radius-md);">
                        <option value="todas">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $filtro_categoria === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="document.getElementById('modalNuevo').classList.add('active')" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Link
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if (empty($links)): ?>
                    <div class="card">
                        <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                            <i class="fas fa-link" style="font-size: 4rem; color: var(--gray-300);"></i>
                            <h3 style="margin-top: var(--spacing-lg); color: var(--text-secondary);">No hay links guardados</h3>
                            <p class="text-muted">Añade tus sitios web favoritos para acceder rápidamente</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($links_por_categoria as $categoria => $links_cat): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <i class="fas fa-folder" style="color: var(--primary); font-size: 1.5rem;"></i>
                                <h2 class="category-title"><?= htmlspecialchars($categoria) ?></h2>
                                <span class="text-muted">(<?= count($links_cat) ?>)</span>
                            </div>
                            
                            <div class="links-grid">
                                <?php foreach ($links_cat as $link): ?>
                                    <div class="link-card" style="border-left-color: <?= htmlspecialchars($link['color']) ?>;" onclick="window.open('?visit=<?= $link['id'] ?>', '_blank')">
                                        <?php if ($link['favorito']): ?>
                                            <i class="fas fa-star fav-icon"></i>
                                        <?php endif; ?>
                                        
                                        <div class="link-actions" onclick="event.stopPropagation();">
                                            <a href="?fav=<?= $link['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Favorito">
                                                <i class="fas fa-star"></i>
                                            </a>
                                            <a href="?delete=<?= $link['id'] ?>" class="btn btn-danger btn-icon btn-sm" onclick="return confirm('¿Eliminar este link?')" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                        
                                        <div class="link-icon" style="background: linear-gradient(135deg, <?= htmlspecialchars($link['color']) ?>, <?= htmlspecialchars($link['color']) ?>dd);">
                                            <i class="fas <?= htmlspecialchars($link['icono']) ?>"></i>
                                        </div>
                                        
                                        <div class="link-title"><?= htmlspecialchars($link['titulo']) ?></div>
                                        
                                        <?php if ($link['descripcion']): ?>
                                            <div class="link-description"><?= htmlspecialchars($link['descripcion']) ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="link-meta">
                                            <span><i class="fas fa-eye"></i> <?= $link['visitas'] ?> visitas</span>
                                            <span style="font-size: 0.75rem; opacity: 0.7;"><?= parse_url($link['url'], PHP_URL_HOST) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Nuevo Link -->
    <div id="modalNuevo" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);"><i class="fas fa-link"></i> Nuevo Link</h2>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="icono" id="icono_seleccionado" value="fa-link">
                <input type="hidden" name="color" id="color_seleccionado" value="#a8dadc">
                
                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required autofocus placeholder="Mi Sitio Web">
                </div>
                
                <div class="form-group">
                    <label for="url">URL *</label>
                    <input type="url" id="url" name="url" required placeholder="https://ejemplo.com">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="2" placeholder="Breve descripción del sitio"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="categoria">Categoría</label>
                    <input type="text" id="categoria" name="categoria" value="General" list="categorias-existentes" placeholder="General">
                    <datalist id="categorias-existentes">
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <div class="color-picker">
                        <?php $colores = ['#a8dadc', '#ffc6d3', '#d4c5f9', '#c7f0db', '#ffe5d9', '#ffb5a7', '#cce2ff', '#fff9e6']; ?>
                        <?php foreach ($colores as $color): ?>
                            <div class="color-option <?= $color === '#a8dadc' ? 'selected' : '' ?>" 
                                 style="background: <?= $color ?>;" 
                                 onclick="selectColor('<?= $color ?>')"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Icono</label>
                    <div class="icon-picker">
                        <?php $iconos = ['fa-link', 'fa-globe', 'fa-home', 'fa-envelope', 'fa-shopping-cart', 'fa-github', 'fa-twitter', 'fa-facebook', 'fa-youtube', 'fa-instagram', 'fa-linkedin', 'fa-server', 'fa-database', 'fa-code', 'fa-book', 'fa-music', 'fa-video', 'fa-image', 'fa-file', 'fa-cloud']; ?>
                        <?php foreach ($iconos as $i => $icono): ?>
                            <div class="icon-option <?= $i === 0 ? 'selected' : '' ?>" onclick="selectIcon('<?= $icono ?>')">
                                <i class="fas <?= $icono ?>"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Añadir Link
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalNuevo').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('modalNuevo').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
        
        function selectColor(color) {
            document.getElementById('color_seleccionado').value = color;
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }
        
        function selectIcon(icon) {
            document.getElementById('icono_seleccionado').value = icon;
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            event.target.closest('.icon-option').classList.add('selected');
        }
    </script>
</body>
</html>
