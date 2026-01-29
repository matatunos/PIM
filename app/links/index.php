<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// AJAX: Extraer título de URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'obtener_titulo') {
    header('Content-Type: application/json');
    $url = trim($_POST['url'] ?? '');
    
    if (empty($url)) {
        echo json_encode(['error' => 'URL vacía']);
        exit;
    }
    
    // Validar URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'URL inválida']);
        exit;
    }
    
    try {
        // Obtener contenido de la URL con timeout
        $contexto = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $html = @file_get_contents($url, false, $contexto);
        
        if ($html === false) {
            echo json_encode(['error' => 'No se pudo acceder a la URL']);
            exit;
        }
        
        // Extraer título
        $titulo = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $matches)) {
            $titulo = trim(html_entity_decode(strip_tags($matches[1])));
        }
        
        // Si no hay título, intentar obtener el nombre de dominio
        if (empty($titulo)) {
            $titulo = parse_url($url, PHP_URL_HOST) ?? $url;
        }
        
        echo json_encode(['titulo' => $titulo]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al obtener el título']);
    }
    exit;
}

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

// Obtener categorías de la tabla link_categorias
$stmt = $pdo->prepare('SELECT nombre FROM link_categorias WHERE usuario_id = ? ORDER BY orden, nombre');
$stmt->execute([$usuario_id]);
$categorias_db = $stmt->fetchAll(PDO::FETCH_COLUMN);

// También obtener categorías de links existentes (por compatibilidad)
$stmt = $pdo->prepare('SELECT DISTINCT categoria FROM links WHERE usuario_id = ? AND categoria NOT IN (SELECT nombre FROM link_categorias WHERE usuario_id = ?) ORDER BY categoria');
$stmt->execute([$usuario_id, $usuario_id]);
$categorias_links = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Combinar ambas listas
$categorias = array_unique(array_merge($categorias_db, $categorias_links));

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
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
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
        .drop-zone {
            border: 3px dashed var(--primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-2xl);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-base);
            background: rgba(168, 218, 220, 0.05);
            margin-bottom: var(--spacing-2xl);
        }
        .drop-zone:hover {
            background: rgba(168, 218, 220, 0.1);
            border-color: var(--primary-dark);
        }
        .drop-zone.dragover {
            background: rgba(168, 218, 220, 0.2);
            border-color: var(--primary-dark);
            transform: scale(1.01);
        }
        .drop-zone i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: var(--spacing-md);
            display: block;
        }
        .drop-zone-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }
        .drop-zone-hint {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        /* Vista Mosaico */
        .links-container[data-view="mosaico"] .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-lg);
        }
        
        /* Vista Lista */
        .links-container[data-view="lista"] .links-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        .links-container[data-view="lista"] .link-card {
            display: grid;
            grid-template-columns: 56px 1fr auto auto;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-left: 4px solid;
            border-radius: var(--radius-md);
        }
        .links-container[data-view="lista"] .link-icon {
            margin: 0;
        }
        .links-container[data-view="lista"] .link-description {
            display: none;
            margin: 0;
        }
        .links-container[data-view="lista"] .link-meta {
            display: flex;
            gap: var(--spacing-md);
            font-size: 0.8rem;
        }
        
        /* Vista Contenido */
        .links-container[data-view="contenido"] .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        .links-container[data-view="contenido"] .link-card {
            display: block;
        }
        
        /* Vista Detalles */
        .links-container[data-view="detalles"] .links-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }
        .links-container[data-view="detalles"] .link-card {
            display: block;
            width: 100%;
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
            .links-container[data-view="lista"] .link-card {
                grid-template-columns: 56px 1fr auto;
            }
            .links-container[data-view="contenido"] .links-grid {
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
                    <button onclick="document.getElementById('modalCategorias').classList.add('active')" class="btn btn-ghost" title="Gestionar categorías">
                        <i class="fas fa-folder-plus"></i>
                    </button>
                    <button onclick="document.getElementById('modalNuevo').classList.add('active')" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Link
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Búsqueda y vistas -->
                <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; align-items: center; margin-bottom: var(--spacing-lg);">
                    <form method="GET" style="display: flex; gap: var(--spacing-md); flex: 1; min-width: 250px;">
                        <input type="text" name="q" placeholder="Buscar links..." value="<?= htmlspecialchars($buscar) ?>" class="form-control">
                        <select name="categoria" class="form-control">
                            <option value="todas">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $filtro_categoria === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($buscar || $filtro_categoria !== 'todas'): ?>
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
                        <button class="view-btn" onclick="cambiarVista('contenido')" title="Contenido" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-align-left"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('detalles')" title="Detalles" style="border: none;">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                    
                    <button onclick="document.getElementById('modalNuevo').classList.add('active')" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Link
                    </button>
                </div>
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <!-- Drop Zone -->
                <div id="dropZone" class="drop-zone">
                    <i class="fas fa-link"></i>
                    <div class="drop-zone-text">Arrastra una URL aquí</div>
                    <div class="drop-zone-hint">Arrastra desde la barra de direcciones o un enlace web</div>
                </div>
                
                <?php if (empty($links)): ?>
                    <div class="card">
                        <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                            <i class="fas fa-link" style="font-size: 4rem; color: var(--gray-300);"></i>
                            <h3 style="margin-top: var(--spacing-lg); color: var(--text-secondary);">No hay links guardados</h3>
                            <p class="text-muted">Añade tus sitios web favoritos para acceder rápidamente</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="links-container" data-view="mosaico" id="linksContainer">
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
                                            <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick="editarLink(<?= htmlspecialchars(json_encode($link)) ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
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
                    </div>
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
    
    <!-- Modal Editar Link -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);"><i class="fas fa-edit"></i> Editar Link</h2>
            <div class="form">
                <input type="hidden" id="editar_icono_seleccionado" value="fa-link">
                <input type="hidden" id="editar_color_seleccionado" value="#a8dadc">
                
                <div class="form-group">
                    <label for="editar_titulo">Título *</label>
                    <input type="text" id="editar_titulo" required autofocus placeholder="Mi Sitio Web">
                </div>
                
                <div class="form-group">
                    <label for="editar_url">URL *</label>
                    <input type="url" id="editar_url" required placeholder="https://ejemplo.com">
                </div>
                
                <div class="form-group">
                    <label for="editar_descripcion">Descripción</label>
                    <textarea id="editar_descripcion" rows="2" placeholder="Breve descripción del sitio"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editar_categoria">Categoría</label>
                    <select id="editar_categoria" style="padding: 0.5rem; border: 2px solid var(--gray-200); border-radius: var(--radius-md); width: 100%;">
                        <option value="General">General</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <div class="color-picker">
                        <?php $colores = ['#a8dadc', '#ffc6d3', '#d4c5f9', '#c7f0db', '#ffe5d9', '#ffb5a7', '#cce2ff', '#fff9e6']; ?>
                        <?php foreach ($colores as $color): ?>
                            <div class="color-option" 
                                 data-color="<?= $color ?>"
                                 style="background: <?= $color ?>;" 
                                 onclick="document.getElementById('editar_color_seleccionado').value='<?= $color ?>'; document.querySelectorAll('#modalEditar .color-option').forEach(el => el.classList.remove('selected')); this.classList.add('selected')"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Icono</label>
                    <div class="icon-picker">
                        <?php $iconos = ['fa-link', 'fa-globe', 'fa-home', 'fa-envelope', 'fa-shopping-cart', 'fa-github', 'fa-twitter', 'fa-facebook', 'fa-youtube', 'fa-instagram', 'fa-linkedin', 'fa-server', 'fa-database', 'fa-code', 'fa-book', 'fa-music', 'fa-video', 'fa-image', 'fa-file', 'fa-cloud']; ?>
                        <?php foreach ($iconos as $i => $icono): ?>
                            <div class="icon-option" onclick="document.getElementById('editar_icono_seleccionado').value='<?= $icono ?>'; document.querySelectorAll('#modalEditar .icon-option').forEach(el => el.classList.remove('selected')); this.classList.add('selected')">
                                <i class="fas <?= $icono ?>"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="guardarEdicion()">
                        <i class="fas fa-check"></i>
                        Guardar Cambios
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalEditar').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Gestionar Categorías -->
    <div id="modalCategorias" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h2 style="margin-bottom: var(--spacing-lg);"><i class="fas fa-folder"></i> Gestionar Categorías</h2>
            
            <div class="form-group">
                <label for="nueva_categoria">Nueva Categoría</label>
                <div style="display: flex; gap: var(--spacing-md);">
                    <input type="text" id="nueva_categoria" placeholder="Ej: Trabajo, Compras, etc." style="flex: 1;">
                    <button onclick="crearCategoria()" class="btn btn-primary" style="white-space: nowrap;">
                        <i class="fas fa-plus"></i> Crear
                    </button>
                </div>
            </div>
            
            <div style="margin-top: var(--spacing-xl); border-top: 1px solid var(--gray-200); padding-top: var(--spacing-lg);">
                <h3 style="margin-bottom: var(--spacing-md); color: var(--text-secondary);">Categorías Existentes</h3>
                <div id="listaCategorias" style="display: flex; flex-direction: column; gap: var(--spacing-sm); max-height: 300px; overflow-y: auto;">
                    <?php foreach ($categorias as $cat): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--spacing-md); background: var(--bg-secondary); border-radius: var(--radius-md);">
                            <span><i class="fas fa-folder" style="color: var(--primary); margin-right: var(--spacing-sm);"></i><?= htmlspecialchars($cat) ?></span>
                            <button onclick="eliminarCategoria('<?= htmlspecialchars(addslashes($cat)) ?>')" class="btn btn-danger btn-icon btn-sm" title="Eliminar categoría">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                <button type="button" class="btn btn-ghost" style="flex: 1;" onclick="document.getElementById('modalCategorias').classList.remove('active')">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Vista system
        function cambiarVista(tipo) {
            const container = document.getElementById('linksContainer');
            if (!container) return;
            
            container.setAttribute('data-view', tipo);
            localStorage.setItem('links-view', tipo);
            
            // Actualizar botones
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.view-btn').classList.add('active');
        }
        
        // Cargar vista guardada
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('links-view') || 'mosaico';
            const container = document.getElementById('linksContainer');
            if (container) {
                container.setAttribute('data-view', savedView);
                document.querySelectorAll('.view-btn').forEach((btn, idx) => {
                    const views = ['mosaico', 'lista', 'contenido', 'detalles'];
                    if (views[idx] === savedView) {
                        btn.classList.add('active');
                    }
                });
            }
        });
        
        // Array de categorías disponibles
        const categoriasDisponibles = [
            <?php foreach ($categorias as $cat): ?>
                '<?= htmlspecialchars(addslashes($cat)) ?>',
            <?php endforeach; ?>
        ];
        console.log('Categorías disponibles:', categoriasDisponibles);
        
        // Drop Zone
        const dropZone = document.getElementById('dropZone');
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
            
            const url = e.dataTransfer.getData('text/plain') || e.dataTransfer.getData('text/uri-list');
            
            if (url && isValidUrl(url)) {
                obtenerTituloYAbrir(url);
            } else {
                alert('Por favor, arrastra una URL válida');
            }
        });
        
        dropZone.addEventListener('click', () => {
            const url = prompt('Ingresa la URL:');
            if (url && isValidUrl(url)) {
                obtenerTituloYAbrir(url);
            }
        });
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        function obtenerTituloYAbrir(url) {
            // Mostrar loading
            dropZone.innerHTML = '<i class="fas fa-spinner fa-spin"></i><div class="drop-zone-text">Obteniendo información...</div>';
            dropZone.style.pointerEvents = 'none';
            
            const formData = new FormData();
            formData.append('action', 'obtener_titulo');
            formData.append('url', url);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Restaurar drop zone
                dropZone.innerHTML = '<i class="fas fa-link"></i><div class="drop-zone-text">Arrastra una URL aquí</div><div class="drop-zone-hint">Arrastra desde la barra de direcciones o un enlace web</div>';
                dropZone.style.pointerEvents = 'auto';
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Llenar formulario y abrir modal
                document.getElementById('titulo').value = data.titulo || '';
                document.getElementById('url').value = url;
                document.getElementById('descripcion').value = '';
                document.getElementById('categoria').value = 'General';
                document.getElementById('icono_seleccionado').value = 'fa-link';
                document.getElementById('color_seleccionado').value = '#a8dadc';
                
                // Reset color e icon selection
                document.querySelectorAll('.color-option').forEach((el, i) => {
                    if (i === 0) el.classList.add('selected');
                    else el.classList.remove('selected');
                });
                document.querySelectorAll('.icon-option').forEach((el, i) => {
                    if (i === 0) el.classList.add('selected');
                    else el.classList.remove('selected');
                });
                
                // Abrir modal
                document.getElementById('modalNuevo').classList.add('active');
                document.getElementById('descripcion').focus();
            })
            .catch(error => {
                // Restaurar drop zone
                dropZone.innerHTML = '<i class="fas fa-link"></i><div class="drop-zone-text">Arrastra una URL aquí</div><div class="drop-zone-hint">Arrastra desde la barra de direcciones o un enlace web</div>';
                dropZone.style.pointerEvents = 'auto';
                
                console.error('Error:', error);
                alert('Error al obtener el título de la página');
            });
        }
        
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
        
        // Editar link
        let linkEnEdicion = null;
        
        function editarLink(link) {
            linkEnEdicion = link;
            const modal = document.getElementById('modalEditar');
            
            // Llenar formulario
            document.getElementById('editar_titulo').value = link.titulo;
            document.getElementById('editar_url').value = link.url;
            document.getElementById('editar_descripcion').value = link.descripcion || '';
            document.getElementById('editar_icono_seleccionado').value = link.icono;
            document.getElementById('editar_color_seleccionado').value = link.color;
            
            // Cargar categorías con AJAX
            fetch('/api/links.php?action=get_categories')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('editar_categoria');
                    if (select && data.categorias) {
                        select.innerHTML = '';
                        data.categorias.forEach(cat => {
                            const option = document.createElement('option');
                            option.value = cat;
                            option.textContent = cat;
                            select.appendChild(option);
                        });
                        // Seleccionar la categoría actual
                        select.value = link.categoria;
                    }
                })
                .catch(error => console.error('Error cargando categorías:', error));
            
            // Reset selections
            document.querySelectorAll('#modalEditar .color-option').forEach(el => {
                el.classList.toggle('selected', el.style.background === link.color || el.getAttribute('data-color') === link.color);
            });
            document.querySelectorAll('#modalEditar .icon-option').forEach((el, i) => {
                const iconClass = el.querySelector('i').className.split(' ').pop();
                el.classList.toggle('selected', iconClass === link.icono.replace('fa-', ''));
            });
            
            modal.classList.add('active');
        }
        
        function guardarEdicion() {
            const titulo = document.getElementById('editar_titulo').value.trim();
            const url = document.getElementById('editar_url').value.trim();
            const descripcion = document.getElementById('editar_descripcion').value.trim();
            const categoria = document.getElementById('editar_categoria').value.trim();
            const icono = document.getElementById('editar_icono_seleccionado').value;
            const color = document.getElementById('editar_color_seleccionado').value;
            
            if (!titulo) {
                alert('El título es requerido');
                return;
            }
            
            if (!url) {
                alert('La URL es requerida');
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            
            fetch('/api/links.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: linkEnEdicion.id,
                    titulo,
                    url,
                    descripcion,
                    categoria,
                    icono,
                    color
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Link actualizado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar los cambios');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Guardar Cambios';
            });
        }
        
        document.getElementById('modalEditar').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
        
        // Funciones de Categorías
        function crearCategoria() {
            const nombre = document.getElementById('nueva_categoria').value.trim();
            
            if (!nombre) {
                alert('Por favor ingresa un nombre para la categoría');
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('/api/links.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'crear_categoria',
                    nombre: nombre
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('nueva_categoria').value = '';
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al crear la categoría');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus"></i> Crear';
            });
        }
        
        function eliminarCategoria(nombre) {
            if (!confirm('¿Eliminar la categoría "' + nombre + '"? Los links en esta categoría se moverán a "General"')) {
                return;
            }
            
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('/api/links.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'eliminar_categoria',
                    nombre: nombre
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar la categoría');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash"></i>';
            });
        }
        
        document.getElementById('modalCategorias').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
        
        // Enter en input de categoría
        document.getElementById('nueva_categoria')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') crearCategoria();
        });
    </script>
</body>
</html>
