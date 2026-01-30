<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/audit_logger.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// Crear nota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $color = $_POST['color'] ?? '#a8dadc';
    
    if (!empty($contenido)) {
        $stmt = $pdo->prepare('INSERT INTO notas (usuario_id, titulo, contenido, color) VALUES (?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $titulo, $contenido, $color]);
        $nota_id = $pdo->lastInsertId();
        
        // Registrar en auditoría
        logAction('crear', 'nota', 'Nota creada: ' . substr($titulo ?: $contenido, 0, 50), true);
        
        // Agregar etiquetas
        if (!empty($_POST['etiquetas'])) {
            $etiquetas = array_filter(array_map('trim', explode(',', $_POST['etiquetas'])));
            foreach ($etiquetas as $etiqueta) {
                // Buscar si existe la etiqueta para este usuario
                $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre = ? AND usuario_id = ?');
                $stmt->execute([$etiqueta, $usuario_id]);
                $etiqueta_id = $stmt->fetchColumn();
                
                // Si no existe, crearla
                if (!$etiqueta_id) {
                    $stmt = $pdo->prepare('INSERT INTO etiquetas (usuario_id, nombre, color) VALUES (?, ?, ?)');
                    $stmt->execute([$usuario_id, $etiqueta, '#2196f3']);
                    $etiqueta_id = $pdo->lastInsertId();
                }
                
                if ($etiqueta_id) {
                    $stmt = $pdo->prepare('INSERT INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)');
                    $stmt->execute([$nota_id, $etiqueta_id]);
                }
            }
        }
        
        header('Location: index.php');
        exit;
    }
}

// Editar nota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $id = (int)$_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $color = $_POST['color'] ?? '#a8dadc';
    
    if (!empty($contenido)) {
        $stmt = $pdo->prepare('UPDATE notas SET titulo = ?, contenido = ?, color = ?, actualizado_en = NOW() WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$titulo, $contenido, $color, $id, $usuario_id]);
        
        // Actualizar etiquetas
        $stmt = $pdo->prepare('DELETE FROM nota_etiqueta WHERE nota_id = ?');
        $stmt->execute([$id]);
        
        if (!empty($_POST['etiquetas'])) {
            $etiquetas = array_filter(array_map('trim', explode(',', $_POST['etiquetas'])));
            foreach ($etiquetas as $etiqueta) {
                // Buscar si existe la etiqueta para este usuario
                $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre = ? AND usuario_id = ?');
                $stmt->execute([$etiqueta, $usuario_id]);
                $etiqueta_id = $stmt->fetchColumn();
                
                // Si no existe, crearla
                if (!$etiqueta_id) {
                    $stmt = $pdo->prepare('INSERT INTO etiquetas (usuario_id, nombre, color) VALUES (?, ?, ?)');
                    $stmt->execute([$usuario_id, $etiqueta, '#2196f3']);
                    $etiqueta_id = $pdo->lastInsertId();
                }
                
                if ($etiqueta_id) {
                    $stmt = $pdo->prepare('INSERT INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)');
                    $stmt->execute([$id, $etiqueta_id]);
                }
            }
        }
        
        header('Location: index.php');
        exit;
    }
}

// Mover nota a papelera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify()) {
        die('Error CSRF');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE notas SET borrado_en = NOW() WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuario_id]);
        
        // Registrar en papelera_logs
        $stmt = $pdo->prepare('SELECT titulo FROM notas WHERE id = ?');
        $stmt->execute([$id]);
        $nota = $stmt->fetch();
        $stmt = $pdo->prepare('INSERT INTO papelera_logs (usuario_id, tipo, item_id, nombre) VALUES (?, ?, ?, ?)');
        $stmt->execute([$usuario_id, 'notas', $id, $nota['titulo'] ?? 'Sin título']);
        
        header('Location: index.php');
        exit;
    }
}

// Archivar/desarchivar
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $id = (int)$_GET['archive'];
    $stmt = $pdo->prepare('UPDATE notas SET archivada = NOT archivada WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    header('Location: index.php');
    exit;
}

// Fijar/desfijar
if (isset($_GET['pin']) && is_numeric($_GET['pin'])) {
    $id = (int)$_GET['pin'];
    $stmt = $pdo->prepare('UPDATE notas SET fijada = NOT fijada WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    header('Location: index.php');
    exit;
}

// Obtener filtros
$buscar = $_GET['q'] ?? '';
$filtro_etiqueta = $_GET['etiqueta'] ?? '';
$mostrar_archivadas = isset($_GET['archivadas']);

// Obtener notas
$sql = 'SELECT n.*, GROUP_CONCAT(e.nombre SEPARATOR ", ") as etiquetas 
        FROM notas n 
        LEFT JOIN nota_etiqueta ne ON n.id = ne.nota_id 
        LEFT JOIN etiquetas e ON ne.etiqueta_id = e.id 
        WHERE n.usuario_id = ? AND n.borrado_en IS NULL';
$params = [$usuario_id];

if (!$mostrar_archivadas) {
    $sql .= ' AND n.archivada = 0';
}

if (!empty($buscar)) {
    $sql .= ' AND (n.titulo LIKE ? OR n.contenido LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= ' GROUP BY n.id ORDER BY n.fijada DESC, n.actualizado_en DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notas = $stmt->fetchAll();

// Filtrar por etiqueta
if (!empty($filtro_etiqueta)) {
    $notas = array_filter($notas, function($nota) use ($filtro_etiqueta) {
        return $nota['etiquetas'] && strpos($nota['etiquetas'], $filtro_etiqueta) !== false;
    });
}

// Obtener todas las etiquetas del usuario
$stmt = $pdo->prepare('SELECT id, nombre, color FROM etiquetas WHERE usuario_id = ? ORDER BY nombre');
$stmt->execute([$usuario_id]);
$todas_etiquetas_full = $stmt->fetchAll(PDO::FETCH_ASSOC);
$todas_etiquetas = array_column($todas_etiquetas_full, 'nombre');
$etiquetas_json = json_encode($todas_etiquetas_full);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <script src="/assets/js/marked.min.js"></script>
    <style>
        /* Estilos para selector de etiquetas */
        .etiquetas-selector {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-sm);
            background: var(--bg-primary);
            min-height: 44px;
            cursor: text;
        }
        .etiquetas-selector:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        .etiquetas-selected {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-xs);
            margin-bottom: var(--spacing-xs);
        }
        .etiqueta-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
        }
        .etiqueta-tag .remove-tag {
            cursor: pointer;
            opacity: 0.7;
            font-size: 0.9rem;
            line-height: 1;
        }
        .etiqueta-tag .remove-tag:hover {
            opacity: 1;
        }
        .etiquetas-input-wrapper {
            position: relative;
        }
        .etiquetas-input {
            border: none;
            outline: none;
            width: 100%;
            padding: 4px;
            font-size: 0.95rem;
            background: transparent;
        }
        .etiquetas-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .etiquetas-dropdown.show {
            display: block;
        }
        .etiqueta-option {
            padding: var(--spacing-sm) var(--spacing-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: background 0.15s;
        }
        .etiqueta-option:hover, .etiqueta-option.highlighted {
            background: var(--gray-100);
        }
        .etiqueta-option.selected {
            background: var(--primary-light);
        }
        .etiqueta-option .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .etiqueta-option.create-new {
            border-top: 1px solid var(--border-color);
            color: var(--primary);
            font-weight: 500;
        }
        .etiqueta-option.create-new i {
            color: var(--primary);
        }
        .etiqueta-option.no-results {
            color: var(--text-muted);
            font-style: italic;
            cursor: default;
        }
        
        .notas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-lg);
        }
        .nota-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: all var(--transition-base);
            border-left: 4px solid;
            position: relative;
            cursor: pointer;
        }
        .nota-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15), 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        .nota-card.fijada {
            border-top: 2px solid var(--warning);
        }
        .nota-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        .nota-titulo {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }
        .nota-contenido {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: var(--spacing-md);
            max-height: 200px;
            overflow: hidden;
            position: relative;
        }
        .nota-contenido.markdown-body::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(transparent, var(--bg-primary));
            pointer-events: none;
        }
        /* Quitar gradiente en vista detalles */
        .notas-container[data-view="detalles"] .nota-contenido.markdown-body::after {
            display: none;
        }
        /* Estilos Markdown */
        .nota-contenido.markdown-body h1, .nota-contenido.markdown-body h2, .nota-contenido.markdown-body h3 {
            margin: 0.5em 0 0.3em;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.3em;
        }
        .nota-contenido.markdown-body h1 { font-size: 1.3em; }
        .nota-contenido.markdown-body h2 { font-size: 1.15em; }
        .nota-contenido.markdown-body h3 { font-size: 1.05em; border-bottom: none; }
        .nota-contenido.markdown-body p { margin: 0.5em 0; }
        .nota-contenido.markdown-body ul, .nota-contenido.markdown-body ol { 
            margin: 0.5em 0; 
            padding-left: 1.5em; 
        }
        .nota-contenido.markdown-body li { margin: 0.2em 0; }
        .nota-contenido.markdown-body code {
            background: var(--gray-100);
            padding: 0.15em 0.4em;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.9em;
            color: var(--danger);
        }
        .nota-contenido.markdown-body pre {
            background: var(--gray-100);
            padding: 0.8em;
            border-radius: var(--radius-md);
            overflow-x: auto;
            margin: 0.5em 0;
        }
        .nota-contenido.markdown-body pre code {
            background: none;
            padding: 0;
            color: var(--text-primary);
        }
        .nota-contenido.markdown-body table {
            border-collapse: collapse;
            width: 100%;
            margin: 0.5em 0;
            font-size: 0.9em;
        }
        .nota-contenido.markdown-body th, .nota-contenido.markdown-body td {
            border: 1px solid var(--border-color);
            padding: 0.4em 0.6em;
            text-align: left;
        }
        .nota-contenido.markdown-body th {
            background: var(--gray-100);
            font-weight: 600;
        }
        .nota-contenido.markdown-body blockquote {
            border-left: 3px solid var(--primary);
            margin: 0.5em 0;
            padding: 0.3em 1em;
            background: var(--gray-50);
            color: var(--text-secondary);
        }
        .nota-contenido.markdown-body hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 1em 0;
        }
        .nota-contenido.markdown-body a {
            color: var(--primary);
            text-decoration: none;
        }
        .nota-contenido.markdown-body a:hover {
            text-decoration: underline;
        }
        .nota-contenido.markdown-body strong { color: var(--text-primary); }
        
        /* Estilos para preview en modal */
        #contenido-preview.markdown-body h1, #contenido-preview.markdown-body h2, #contenido-preview.markdown-body h3 {
            margin: 0.5em 0 0.3em;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.3em;
        }
        #contenido-preview.markdown-body h1 { font-size: 1.4em; }
        #contenido-preview.markdown-body h2 { font-size: 1.2em; }
        #contenido-preview.markdown-body h3 { font-size: 1.1em; border-bottom: none; }
        #contenido-preview.markdown-body p { margin: 0.5em 0; }
        #contenido-preview.markdown-body ul, #contenido-preview.markdown-body ol { margin: 0.5em 0; padding-left: 1.5em; }
        #contenido-preview.markdown-body li { margin: 0.3em 0; }
        #contenido-preview.markdown-body code {
            background: var(--gray-200);
            padding: 0.15em 0.4em;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.9em;
            color: var(--danger);
        }
        #contenido-preview.markdown-body pre {
            background: var(--gray-200);
            padding: 0.8em;
            border-radius: var(--radius-md);
            overflow-x: auto;
        }
        #contenido-preview.markdown-body pre code { background: none; padding: 0; color: var(--text-primary); }
        #contenido-preview.markdown-body table { border-collapse: collapse; width: 100%; margin: 0.5em 0; }
        #contenido-preview.markdown-body th, #contenido-preview.markdown-body td { border: 1px solid var(--border-color); padding: 0.4em 0.6em; }
        #contenido-preview.markdown-body th { background: var(--gray-200); font-weight: 600; }
        #contenido-preview.markdown-body blockquote { border-left: 3px solid var(--primary); margin: 0.5em 0; padding: 0.3em 1em; background: var(--gray-100); }
        #contenido-preview.markdown-body hr { border: none; border-top: 1px solid var(--border-color); margin: 1em 0; }
        
        /* Estilos para modal de lectura */
        #ver-nota-contenido.markdown-body h1, #ver-nota-contenido.markdown-body h2, #ver-nota-contenido.markdown-body h3 {
            margin: 0.5em 0 0.3em;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.3em;
        }
        #ver-nota-contenido.markdown-body h1 { font-size: 1.5em; }
        #ver-nota-contenido.markdown-body h2 { font-size: 1.3em; }
        #ver-nota-contenido.markdown-body h3 { font-size: 1.15em; border-bottom: none; }
        #ver-nota-contenido.markdown-body p { margin: 0.6em 0; line-height: 1.6; }
        #ver-nota-contenido.markdown-body ul, #ver-nota-contenido.markdown-body ol { margin: 0.5em 0; padding-left: 1.5em; }
        #ver-nota-contenido.markdown-body li { margin: 0.3em 0; }
        #ver-nota-contenido.markdown-body code {
            background: var(--gray-200);
            padding: 0.2em 0.5em;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.9em;
            color: var(--danger);
        }
        #ver-nota-contenido.markdown-body pre {
            background: var(--gray-100);
            padding: 1em;
            border-radius: var(--radius-md);
            overflow-x: auto;
            margin: 0.8em 0;
        }
        #ver-nota-contenido.markdown-body pre code { background: none; padding: 0; color: var(--text-primary); }
        #ver-nota-contenido.markdown-body table { border-collapse: collapse; width: 100%; margin: 0.8em 0; }
        #ver-nota-contenido.markdown-body th, #ver-nota-contenido.markdown-body td { border: 1px solid var(--border-color); padding: 0.5em 0.8em; }
        #ver-nota-contenido.markdown-body th { background: var(--gray-100); font-weight: 600; }
        #ver-nota-contenido.markdown-body blockquote { border-left: 4px solid var(--primary); margin: 0.8em 0; padding: 0.5em 1em; background: var(--gray-50); font-style: italic; }
        #ver-nota-contenido.markdown-body hr { border: none; border-top: 2px solid var(--border-color); margin: 1.5em 0; }
        #ver-nota-contenido.markdown-body a { color: var(--primary); text-decoration: none; }
        #ver-nota-contenido.markdown-body a:hover { text-decoration: underline; }
        #ver-nota-contenido.markdown-body strong { color: var(--text-primary); }
        #ver-nota-contenido.markdown-body img { max-width: 100%; border-radius: var(--radius-md); }
        
        .nota-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--gray-200);
        }
        .nota-etiquetas {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-xs);
            margin-bottom: var(--spacing-md);
        }
        .etiqueta-badge {
            background: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        .nota-actions {
            position: absolute;
            top: var(--spacing-sm);
            right: var(--spacing-sm);
            display: flex;
            gap: var(--spacing-xs);
            opacity: 0;
            transition: opacity var(--transition-base);
            background: var(--bg-primary);
            padding: var(--spacing-xs);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .nota-card:hover .nota-actions {
            opacity: 1;
        }
        .pin-icon {
            position: absolute;
            top: var(--spacing-sm);
            left: var(--spacing-sm);
            color: var(--warning);
            font-size: 1rem;
        }
        .barra-busqueda {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }
        .barra-busqueda input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        .etiquetas-filtro {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
            margin-bottom: var(--spacing-lg);
        }
        .etiqueta-filtro {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            border: 2px solid var(--gray-200);
            background: var(--bg-primary);
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            font-size: 0.95rem;
        }
        .etiqueta-filtro:hover {
            background: var(--gray-100);
            border-color: var(--primary);
            color: var(--primary);
        }
        .etiqueta-filtro.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Vista Mosaico */
        .notas-container[data-view="mosaico"] .notas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            grid-auto-rows: max-content;
        }
        .notas-container[data-view="mosaico"] .nota-card {
            display: flex;
            flex-direction: column;
            min-height: 200px;
            max-height: 400px;
        }
        .notas-container[data-view="mosaico"] .nota-contenido {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
        }
        
        /* Vista Lista */
        .notas-container[data-view="lista"] .notas-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        .notas-container[data-view="lista"] .nota-card {
            display: grid;
            grid-template-columns: 40px 100px 1fr 100px;
            grid-template-rows: auto auto;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            background: var(--bg-secondary);
            border-left: 3px solid;
            border-radius: var(--radius-md);
            min-height: 55px;
            position: relative;
        }
        .notas-container[data-view="lista"] .nota-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            border-radius: var(--radius-md) 0 0 var(--radius-md);
        }
        .notas-container[data-view="lista"] .nota-titulo {
            font-weight: 600;
            font-size: 0.95rem;
            grid-column: 3;
            grid-row: 1;
        }
        .notas-container[data-view="lista"] .nota-contenido {
            display: none;
        }
        .notas-container[data-view="lista"] .nota-etiquetas {
            display: flex;
            gap: var(--spacing-xs);
            flex-wrap: wrap;
            grid-column: 2 / 4;
            grid-row: 2;
            align-self: start;
        }
        .notas-container[data-view="lista"] .nota-etiquetas .etiqueta {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
        }
        .notas-container[data-view="lista"] .nota-footer {
            font-size: 0.8rem;
            color: var(--text-secondary);
            white-space: nowrap;
            grid-column: 2;
            grid-row: 1;
            text-align: left;
        }
        .notas-container[data-view="lista"] .nota-actions {
            opacity: 1;
            display: flex;
            gap: var(--spacing-xs);
            grid-column: 4;
            grid-row: 1 / 3;
            justify-content: flex-end;
            align-items: center;
        }
        .notas-container[data-view="lista"] .pin-icon {
            position: static;
            color: var(--text-secondary);
            grid-column: 1;
            grid-row: 1 / 3;
        }
        
        /* Vista Contenido */
        .notas-container[data-view="contenido"] .notas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        .notas-container[data-view="contenido"] .nota-card {
            min-height: auto;
            max-height: 500px;
            padding: var(--spacing-lg);
        }
        .notas-container[data-view="contenido"] .nota-titulo {
            font-size: 1.1rem;
            margin-bottom: var(--spacing-sm);
        }
        .notas-container[data-view="contenido"] .nota-contenido {
            display: -webkit-box;
            -webkit-line-clamp: 8;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: var(--spacing-md);
        }
        
        /* Vista Detalles */
        .notas-container[data-view="detalles"] .notas-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }
        .notas-container[data-view="detalles"] .nota-card {
            min-height: auto;
            max-height: none;
            grid-template-columns: none;
            padding: var(--spacing-lg);
            display: block;
        }
        .notas-container[data-view="detalles"] .nota-titulo {
            font-size: 1.2rem;
            margin-bottom: var(--spacing-md);
            font-weight: 700;
        }
        .notas-container[data-view="detalles"] .nota-contenido {
            display: block;
            overflow: visible;
            -webkit-line-clamp: unset;
            -webkit-box-orient: unset;
            margin-bottom: var(--spacing-md);
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .notas-container[data-view="detalles"] .nota-etiquetas {
            margin-bottom: var(--spacing-md);
        }
        .notas-container[data-view="detalles"] .nota-footer {
            font-size: 0.85rem;
            color: var(--text-secondary);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border-color);
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .notas-container[data-view="lista"] .nota-card {
                grid-template-columns: 1fr auto;
            }
            .notas-container[data-view="contenido"] .notas-grid {
                grid-template-columns: 1fr;
            }
            .view-toggle {
                width: 100%;
            }
            .barra-busqueda form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <script>
        const notasData = <?= json_encode($notas, JSON_UNESCAPED_UNICODE) ?>;
        const todasEtiquetas = <?= $etiquetas_json ?>;
        let etiquetasSeleccionadas = [];
        let highlightedIndex = -1;
        
        // === SELECTOR DE ETIQUETAS ===
        function initEtiquetasSelector() {
            const input = document.getElementById('etiquetas-input');
            const dropdown = document.getElementById('etiquetas-dropdown');
            const selectedContainer = document.getElementById('etiquetas-selected');
            const hiddenInput = document.getElementById('etiquetas');
            
            if (!input) return;
            
            // Click en el contenedor enfoca el input
            document.getElementById('etiquetas-selector').addEventListener('click', () => input.focus());
            
            // Mostrar dropdown al enfocar
            input.addEventListener('focus', () => {
                renderDropdown(input.value);
                dropdown.classList.add('show');
            });
            
            // Filtrar al escribir
            input.addEventListener('input', () => {
                highlightedIndex = -1;
                renderDropdown(input.value);
                dropdown.classList.add('show');
            });
            
            // Navegación con teclado
            input.addEventListener('keydown', (e) => {
                const options = dropdown.querySelectorAll('.etiqueta-option:not(.no-results)');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    highlightedIndex = Math.min(highlightedIndex + 1, options.length - 1);
                    updateHighlight(options);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    highlightedIndex = Math.max(highlightedIndex - 1, 0);
                    updateHighlight(options);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (highlightedIndex >= 0 && options[highlightedIndex]) {
                        options[highlightedIndex].click();
                    } else if (input.value.trim()) {
                        // Crear nueva etiqueta si hay texto
                        agregarEtiqueta(input.value.trim());
                        input.value = '';
                        renderDropdown('');
                    }
                } else if (e.key === 'Backspace' && !input.value && etiquetasSeleccionadas.length > 0) {
                    // Eliminar última etiqueta con Backspace
                    const ultima = etiquetasSeleccionadas[etiquetasSeleccionadas.length - 1];
                    eliminarEtiqueta(ultima);
                } else if (e.key === 'Escape') {
                    dropdown.classList.remove('show');
                    input.blur();
                }
            });
            
            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.etiquetas-selector')) {
                    dropdown.classList.remove('show');
                }
            });
        }
        
        function renderDropdown(filtro) {
            const dropdown = document.getElementById('etiquetas-dropdown');
            const filtroLower = filtro.toLowerCase().trim();
            
            let html = '';
            let hayResultados = false;
            let existeExacta = false;
            
            todasEtiquetas.forEach((etiq, idx) => {
                const nombre = etiq.nombre;
                const color = etiq.color || '#2196f3';
                const yaSeleccionada = etiquetasSeleccionadas.includes(nombre);
                
                if (nombre.toLowerCase() === filtroLower) {
                    existeExacta = true;
                }
                
                if (!filtroLower || nombre.toLowerCase().includes(filtroLower)) {
                    hayResultados = true;
                    html += `<div class="etiqueta-option ${yaSeleccionada ? 'selected' : ''}" onclick="toggleEtiqueta('${nombre.replace(/'/g, "\\'")}')">
                        <span class="color-dot" style="background: ${color}"></span>
                        <span>${nombre}</span>
                        ${yaSeleccionada ? '<i class="fas fa-check" style="margin-left: auto; color: var(--success);"></i>' : ''}
                    </div>`;
                }
            });
            
            // Opción para crear nueva etiqueta si no existe exacta
            if (filtroLower && !existeExacta) {
                html += `<div class="etiqueta-option create-new" onclick="agregarEtiqueta('${filtroLower.replace(/'/g, "\\'")}'); document.getElementById('etiquetas-input').value = ''; renderDropdown('');">
                    <i class="fas fa-plus"></i>
                    <span>Crear "${filtro.trim()}"</span>
                </div>`;
            }
            
            if (!hayResultados && !filtroLower) {
                html = '<div class="etiqueta-option no-results">No hay etiquetas. Escribe para crear una.</div>';
            }
            
            dropdown.innerHTML = html;
            highlightedIndex = -1;
        }
        
        function updateHighlight(options) {
            options.forEach((opt, idx) => {
                opt.classList.toggle('highlighted', idx === highlightedIndex);
            });
            if (options[highlightedIndex]) {
                options[highlightedIndex].scrollIntoView({ block: 'nearest' });
            }
        }
        
        function toggleEtiqueta(nombre) {
            if (etiquetasSeleccionadas.includes(nombre)) {
                eliminarEtiqueta(nombre);
            } else {
                agregarEtiqueta(nombre);
            }
            document.getElementById('etiquetas-input').value = '';
            renderDropdown('');
        }
        
        function agregarEtiqueta(nombre) {
            if (!nombre || etiquetasSeleccionadas.includes(nombre)) return;
            
            etiquetasSeleccionadas.push(nombre);
            actualizarEtiquetasUI();
            
            // Si es nueva, añadirla al array de todas las etiquetas
            if (!todasEtiquetas.find(e => e.nombre.toLowerCase() === nombre.toLowerCase())) {
                todasEtiquetas.push({ nombre: nombre, color: '#2196f3' });
            }
        }
        
        function eliminarEtiqueta(nombre) {
            etiquetasSeleccionadas = etiquetasSeleccionadas.filter(e => e !== nombre);
            actualizarEtiquetasUI();
        }
        
        function actualizarEtiquetasUI() {
            const container = document.getElementById('etiquetas-selected');
            const hiddenInput = document.getElementById('etiquetas');
            
            container.innerHTML = etiquetasSeleccionadas.map(nombre => {
                const etiq = todasEtiquetas.find(e => e.nombre === nombre);
                const color = etiq?.color || '#2196f3';
                return `<span class="etiqueta-tag" style="background: ${color}">
                    ${nombre}
                    <span class="remove-tag" onclick="event.stopPropagation(); eliminarEtiqueta('${nombre.replace(/'/g, "\\'")}')">&times;</span>
                </span>`;
            }).join('');
            
            hiddenInput.value = etiquetasSeleccionadas.join(', ');
        }
        
        function setEtiquetasFromString(str) {
            etiquetasSeleccionadas = str ? str.split(', ').map(e => e.trim()).filter(e => e) : [];
            actualizarEtiquetasUI();
        }
        
        function limpiarEtiquetas() {
            etiquetasSeleccionadas = [];
            actualizarEtiquetasUI();
        }
        
        // === FIN SELECTOR DE ETIQUETAS ===
        
        // Vista system
        function cambiarVista(tipo) {
            const container = document.getElementById('notasContainer');
            if (!container) return;
            
            container.setAttribute('data-view', tipo);
            localStorage.setItem('notas-view', tipo);
            
            // Actualizar botones
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.view-btn').classList.add('active');
        }
        
        // Cargar vista guardada
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('notas-view') || 'mosaico';
            const container = document.getElementById('notasContainer');
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
        
        function abrirModalNueva() {
            document.getElementById('modal-title').textContent = 'Nueva Nota';
            document.getElementById('form-action').value = 'crear';
            document.getElementById('formNota').reset();
            document.getElementById('color_seleccionado').value = '#a8dadc';
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            document.querySelector('.color-option').classList.add('selected');
            // Limpiar etiquetas seleccionadas
            limpiarEtiquetas();
            document.getElementById('etiquetas-input').value = '';
            document.getElementById('modalNota').classList.add('active');
        }
        
        function editarNota(id) {
            const nota = notasData.find(n => n.id == id);
            if (!nota) return;
            
            // Guardar ID de nota actual para poder editar después
            window.currentNotaId = id;
            
            // Mostrar modal de lectura
            document.getElementById('ver-nota-titulo').textContent = nota.titulo || 'Sin título';
            document.getElementById('ver-nota-contenido').innerHTML = marked.parse(nota.contenido);
            document.getElementById('ver-nota-contenido').style.borderLeftColor = nota.color;
            document.getElementById('ver-nota-fecha').textContent = 'Actualizado: ' + (nota.actualizado_en || nota.creado_en);
            
            // Etiquetas
            const etiquetasContainer = document.getElementById('ver-nota-etiquetas');
            if (nota.etiquetas) {
                etiquetasContainer.innerHTML = nota.etiquetas.split(', ').map(e => 
                    '<span class="etiqueta-badge">' + e + '</span>'
                ).join(' ');
                etiquetasContainer.style.display = 'flex';
                etiquetasContainer.style.gap = 'var(--spacing-xs)';
                etiquetasContainer.style.flexWrap = 'wrap';
            } else {
                etiquetasContainer.innerHTML = '';
                etiquetasContainer.style.display = 'none';
            }
            
            // Links de acciones
            document.getElementById('ver-nota-pin').href = '?pin=' + id;
            document.getElementById('ver-nota-pin').innerHTML = nota.fijada ? '<i class="fas fa-thumbtack"></i> Desfija' : '<i class="fas fa-thumbtack"></i> Fijar';
            document.getElementById('ver-nota-archive').href = '?archive=' + id;
            document.getElementById('ver-nota-archive').innerHTML = nota.archivada ? '<i class="fas fa-box-open"></i> Restaurar' : '<i class="fas fa-archive"></i> Archivar';
            
            // Cargar archivos anexos
            fetch('/api/archivos.php?action=notas&nota_id=' + id)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('ver-nota-archivos');
                    if (!data.success || data.archivos.length === 0) {
                        container.innerHTML = '';
                        container.style.display = 'none';
                        return;
                    }
                    
                    container.style.display = 'block';
                    let html = '<h4 style="font-size: 0.9rem; margin-bottom: var(--spacing-sm);"><i class="fas fa-paperclip"></i> Archivos adjuntos</h4>';
                    html += '<div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm);">';
                    data.archivos.forEach(archivo => {
                        html += '<a href="/api/archivos.php?action=descargar&archivo_id=' + archivo.id + '" class="btn btn-ghost btn-sm" download><i class="fas fa-download"></i> ' + archivo.nombre_original + '</a>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                });
            
            document.getElementById('modalVerNota').classList.add('active');
        }
        
        function cerrarModalVer() {
            document.getElementById('modalVerNota').classList.remove('active');
            window.currentNotaId = null;
        }
        
        function pasarAEdicion() {
            const id = window.currentNotaId;
            if (!id) return;
            
            const nota = notasData.find(n => n.id == id);
            if (!nota) return;
            
            // Cerrar modal de lectura
            cerrarModalVer();
            
            // Abrir modal de edición
            document.getElementById('modal-title').textContent = 'Editar Nota';
            document.getElementById('form-action').value = 'editar';
            document.getElementById('nota-id').value = nota.id;
            document.getElementById('titulo').value = nota.titulo || '';
            document.getElementById('contenido').value = nota.contenido;
            // Cargar etiquetas en el selector
            setEtiquetasFromString(nota.etiquetas || '');
            document.getElementById('etiquetas-input').value = '';
            document.getElementById('color_seleccionado').value = nota.color;
            
            document.querySelectorAll('.color-option').forEach(el => {
                el.classList.remove('selected');
                if (el.style.background === nota.color) {
                    el.classList.add('selected');
                }
            });
            
            // Cargar archivos anexos
            fetch('/api/archivos.php?action=notas&nota_id=' + id)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('archivos-anexos-container');
                    if (!data.success || data.archivos.length === 0) {
                        container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem;">Sin archivos anexos</p>';
                        return;
                    }
                    
                    let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--spacing-sm);">';
                    data.archivos.forEach(archivo => {
                        const etiquetas = archivo.etiquetas ? archivo.etiquetas.split(',').map(e => '<span style="display: inline-block; background: ' + (archivo.color_etiqueta || '#e0e0e0') + '; color: #333; padding: 1px 4px; border-radius: 3px; font-size: 0.7rem; margin-right: 2px;">' + e.trim() + '</span>').join('') : '';
                        html += '<div style="border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: var(--spacing-sm); display: flex; flex-direction: column; gap: 4px;"><a href="/api/archivos.php?action=descargar&archivo_id=' + archivo.id + '" style="color: var(--primary); text-decoration: none; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' + archivo.nombre_original + '" download><i class="fas fa-download"></i> ' + archivo.nombre_original.substring(0, 20) + '</a><div style="font-size: 0.75rem; color: var(--text-muted);">' + (archivo.tamaño ? (archivo.tamaño / 1024 / 1024).toFixed(2) + ' MB' : '') + '</div><div style="font-size: 0.75rem;">' + (etiquetas || '<em style="color: var(--text-muted);">sin tags</em>') + '</div></div>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                });
            
            document.getElementById('modalNota').classList.add('active');
        }
        
        function cerrarModal() {
            document.getElementById('modalNota').classList.remove('active');
            // Reset preview state
            togglePreview(false);
        }
        
        function selectColor(color) {
            document.getElementById('color_seleccionado').value = color;
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }
        
        function togglePreview(showPreview) {
            const textarea = document.getElementById('contenido');
            const preview = document.getElementById('contenido-preview');
            const btnEditar = document.getElementById('btn-editar');
            const btnPreview = document.getElementById('btn-preview');
            
            if (showPreview) {
                textarea.style.display = 'none';
                preview.style.display = 'block';
                preview.innerHTML = marked.parse(textarea.value);
                btnEditar.style.opacity = '0.5';
                btnPreview.style.opacity = '1';
            } else {
                textarea.style.display = 'block';
                preview.style.display = 'none';
                btnEditar.style.opacity = '1';
                btnPreview.style.opacity = '0.5';
            }
        }
        
        function updatePreview() {
            const preview = document.getElementById('contenido-preview');
            if (preview.style.display === 'block') {
                preview.innerHTML = marked.parse(document.getElementById('contenido').value);
            }
        }
        
        function mostrarArchivos(tipo, id, event) {
            if (event) {
                event.stopPropagation();
            }
            
            const modalId = 'modalArchivos' + tipo.charAt(0).toUpperCase() + tipo.slice(1) + id;
            const container = 'lista-archivos-' + tipo + '-' + id;
            
            fetch('/api/archivos.php?action=listar')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || data.archivos.length === 0) {
                        document.getElementById(container).innerHTML = '<p style="text-align: center; color: var(--text-muted);">No hay archivos disponibles</p>';
                        return;
                    }
                    
                    fetch('/api/archivos.php?action=' + tipo + 's&' + tipo + '_id=' + id)
                        .then(r => r.json())
                        .then(vinculados => {
                            const vinculadosIds = new Set(vinculados.archivos.map(a => a.id));
                            
                            let html = '';
                            data.archivos.forEach(archivo => {
                                const vinculado = vinculadosIds.has(archivo.id);
                                const etiquetas = archivo.etiquetas ? archivo.etiquetas.split(',').map(e => '<span style="display: inline-block; background: ' + (archivo.color_etiqueta || '#e0e0e0') + '; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 0.75rem; margin-right: 4px;">' + e.trim() + '</span>').join('') : '';
                                html += '<label style="display: block; padding: var(--spacing-sm); border-bottom: 1px solid var(--gray-200); cursor: pointer;"><div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: 4px;"><input type="checkbox" ' + (vinculado ? 'checked' : '') + ' onchange="toggleArchivo(this, ' + archivo.id + ', ' + id + ', \'' + tipo + '\')"><strong>' + archivo.nombre_original + '</strong></div><div style="margin-left: 24px; font-size: 0.85rem; color: var(--text-muted);">' + (etiquetas || '<em>sin etiquetas</em>') + '</div></label>';
                            });
                            document.getElementById(container).innerHTML = html;
                        });
                });
            
            if (!document.getElementById(modalId)) {
                const modal = document.createElement('div');
                modal.id = modalId;
                modal.className = 'modal active';
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn btn-ghost';
                closeBtn.style.flex = '1';
                closeBtn.textContent = 'Cerrar';
                closeBtn.onclick = function() {
                    document.getElementById(modalId).classList.remove('active');
                };
                
                const header = document.createElement('h3');
                header.style.marginBottom = 'var(--spacing-lg)';
                header.innerHTML = '<i class="fas fa-link"></i> Vincular Archivos';
                
                const listContainer = document.createElement('div');
                listContainer.id = container;
                listContainer.style.cssText = 'max-height: 400px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-md);';
                listContainer.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Cargando archivos...</p>';
                
                const buttonDiv = document.createElement('div');
                buttonDiv.style.cssText = 'display: flex; gap: var(--spacing-md); margin-top: var(--spacing-lg);';
                buttonDiv.appendChild(closeBtn);
                
                const content = document.createElement('div');
                content.className = 'modal-content';
                content.style.maxWidth = '600px';
                content.appendChild(header);
                content.appendChild(listContainer);
                content.appendChild(buttonDiv);
                
                modal.appendChild(content);
                document.body.appendChild(modal);
                modal.addEventListener('click', function(e) {
                    if (e.target === this) this.classList.remove('active');
                });
            } else {
                document.getElementById(modalId).classList.add('active');
            }
        }
        
        function toggleArchivo(checkbox, archivoId, id, tipo) {
            if (checkbox.checked) {
                fetch('/api/archivos.php?action=vincular_' + tipo, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'archivo_id=' + archivoId + '&' + tipo + '_id=' + id
                });
            } else {
                fetch('/api/archivos.php?action=desvincular_' + tipo, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'archivo_id=' + archivoId + '&' + tipo + '_id=' + id
                });
            }
        }
        
        function mostrarArchivosAnexos(tipo, id) {
            fetch('/api/archivos.php?action=' + tipo + 's&' + tipo + '_id=' + id)
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('badge-archivos-' + tipo + '-' + id);
                    if (badge) {
                        if (data.success && data.archivos.length > 0) {
                            badge.textContent = data.archivos.length;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar selector de etiquetas
            initEtiquetasSelector();
            
            const modalNota = document.getElementById('modalNota');
            if (modalNota) {
                modalNota.addEventListener('click', function(e) {
                    if (e.target === this) cerrarModal();
                });
            }
            
            const modalVerNota = document.getElementById('modalVerNota');
            if (modalVerNota) {
                modalVerNota.addEventListener('click', function(e) {
                    if (e.target === this) cerrarModalVer();
                });
            }
            
            // Cerrar modales con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (document.getElementById('modalVerNota').classList.contains('active')) {
                        cerrarModalVer();
                    } else if (document.getElementById('modalNota').classList.contains('active')) {
                        cerrarModal();
                    }
                }
            });
            
            // Renderizar Markdown en todas las notas
            if (typeof marked !== 'undefined') {
                marked.setOptions({
                    breaks: true,
                    gfm: true
                });
                document.querySelectorAll('.nota-contenido.markdown-body').forEach(el => {
                    const markdown = el.dataset.markdown;
                    if (markdown) {
                        el.innerHTML = marked.parse(markdown);
                    }
                });
            }
            
            // Cargar badges de archivos
            <?php foreach ($notas as $nota): ?>
                mostrarArchivosAnexos('nota', <?= $nota['id'] ?>);
            <?php endforeach; ?>
        });
    </script>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-sticky-note"></i> Notas</h1>
                </div>
                <div class="top-bar-right">
                    <a href="?archivadas" class="btn btn-ghost">
                        <i class="fas fa-archive"></i>
                        <?= $mostrar_archivadas ? 'Ver activas' : 'Ver archivadas' ?>
                    </a>
                    <button onclick="abrirModalNueva()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nueva Nota
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Barra de búsqueda y vistas -->
                <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; align-items: center; margin-bottom: var(--spacing-lg);">
                    <form method="GET" style="display: flex; gap: var(--spacing-md); flex: 1; min-width: 250px;">
                        <input type="text" name="q" placeholder="Buscar notas..." value="<?= htmlspecialchars($buscar) ?>" class="form-control">
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
                        <button class="view-btn" onclick="cambiarVista('contenido')" title="Contenido" style="border: none; border-right: 1px solid var(--border-color);">
                            <i class="fas fa-align-left"></i>
                        </button>
                        <button class="view-btn" onclick="cambiarVista('detalles')" title="Detalles" style="border: none;">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Filtro de etiquetas -->
                <?php if (!empty($todas_etiquetas)): ?>
                    <div class="etiquetas-filtro">
                        <a href="index.php" class="etiqueta-filtro <?= empty($filtro_etiqueta) ? 'active' : '' ?>">
                            <i class="fas fa-tags"></i> Todas
                        </a>
                        <?php foreach ($todas_etiquetas as $etiq): ?>
                            <a href="?etiqueta=<?= urlencode($etiq) ?>" class="etiqueta-filtro <?= $filtro_etiqueta === $etiq ? 'active' : '' ?>">
                                <?= htmlspecialchars($etiq) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Grid de notas -->
                <?php if (empty($notas)): ?>
                    <div class="card">
                        <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                            <i class="fas fa-sticky-note" style="font-size: 4rem; color: var(--gray-300);"></i>
                            <h3 style="margin-top: var(--spacing-lg); color: var(--text-secondary);">
                                <?= $mostrar_archivadas ? 'No hay notas archivadas' : 'No hay notas' ?>
                            </h3>
                            <p class="text-muted">Crea tu primera nota para comenzar</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="notas-container" data-view="mosaico" id="notasContainer">
                        <div class="notas-grid">
                            <?php foreach ($notas as $nota): ?>
                                <div class="nota-card <?= $nota['fijada'] ? 'fijada' : '' ?>" 
                                     style="border-left-color: <?= htmlspecialchars($nota['color']) ?>;"
                                     onclick="editarNota(<?= $nota['id'] ?>)">
                                    
                                    <?php if ($nota['fijada']): ?>
                                        <i class="fas fa-thumbtack pin-icon"></i>
                                    <?php endif; ?>
                                    
                                    <div class="nota-actions" onclick="event.stopPropagation();">
                                        <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick="mostrarArchivos('nota', <?= $nota['id'] ?>, event)" title="Archivos" id="btn-archivos-nota-<?= $nota['id'] ?>" style="position: relative;">
                                            <i class="fas fa-paperclip"></i>
                                            <span id="badge-archivos-nota-<?= $nota['id'] ?>" style="position: absolute; top: -8px; right: -8px; background: var(--danger); color: white; border-radius: 50%; width: 18px; height: 18px; display: none; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;"></span>
                                        </button>
                                        <a href="?pin=<?= $nota['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="<?= $nota['fijada'] ? 'Desfijar' : 'Fijar' ?>">
                                            <i class="fas fa-thumbtack"></i>
                                        </a>
                                        <a href="?archive=<?= $nota['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="<?= $nota['archivada'] ? 'Desarchivar' : 'Archivar' ?>">
                                            <i class="fas fa-archive"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta nota?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $nota['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-icon btn-sm" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <?php if ($nota['titulo']): ?>
                                        <div class="nota-titulo"><?= htmlspecialchars($nota['titulo']) ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="nota-contenido markdown-body" data-markdown="<?= htmlspecialchars($nota['contenido']) ?>">
                                        <?= nl2br(htmlspecialchars($nota['contenido'])) ?>
                                    </div>
                                    
                                    <?php if ($nota['etiquetas']): ?>
                                        <div class="nota-etiquetas">
                                            <?php foreach (explode(', ', $nota['etiquetas']) as $etiq): ?>
                                                <span class="etiqueta-badge"><?= htmlspecialchars($etiq) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="nota-footer">
                                        <span><?= date('d/m/Y', strtotime($nota['actualizado_en'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Nueva/Editar Nota -->
    <div id="modalNota" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-sticky-note"></i> 
                <span id="modal-title">Nueva Nota</span>
            </h2>
            <form method="POST" class="form" id="formNota">
                <input type="hidden" name="action" id="form-action" value="crear">
                <input type="hidden" name="id" id="nota-id">
                <input type="hidden" name="color" id="color_seleccionado" value="#a8dadc">
                
                <div class="form-group">
                    <label for="titulo">Título (opcional)</label>
                    <input type="text" id="titulo" name="titulo" placeholder="Título de la nota">
                </div>
                
                <div class="form-group">
                    <label for="contenido">Contenido * <small style="color: var(--text-muted); font-weight: normal;">(soporta Markdown)</small></label>
                    <div style="display: flex; gap: var(--spacing-sm); margin-bottom: var(--spacing-sm);">
                        <button type="button" class="btn btn-ghost btn-sm" id="btn-editar" onclick="togglePreview(false)" style="opacity: 1;"><i class="fas fa-edit"></i> Editar</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="btn-preview" onclick="togglePreview(true)"><i class="fas fa-eye"></i> Preview</button>
                    </div>
                    <textarea id="contenido" name="contenido" rows="10" required placeholder="Escribe aquí... Soporta **negrita**, *cursiva*, `código`, listas, tablas..." oninput="updatePreview()"></textarea>
                    <div id="contenido-preview" class="markdown-body" style="display: none; min-height: 200px; max-height: 400px; overflow-y: auto; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);"></div>
                </div>
                
                <div class="form-group">
                    <label for="etiquetas">Etiquetas</label>
                    <input type="hidden" id="etiquetas" name="etiquetas" value="">
                    <div class="etiquetas-selector" id="etiquetas-selector">
                        <div class="etiquetas-selected" id="etiquetas-selected"></div>
                        <div class="etiquetas-input-wrapper">
                            <input type="text" id="etiquetas-input" class="etiquetas-input" placeholder="Buscar o crear etiqueta..." autocomplete="off">
                            <div class="etiquetas-dropdown" id="etiquetas-dropdown"></div>
                        </div>
                    </div>
                    <small style="color: var(--text-muted);">Selecciona etiquetas existentes o escribe para crear nuevas</small>
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                        <?php $colores = ['#a8dadc', '#ffc6d3', '#d4c5f9', '#c7f0db', '#ffe5d9', '#ffb5a7', '#cce2ff', '#fff9e6']; ?>
                        <?php foreach ($colores as $color): ?>
                            <div style="width: 40px; height: 40px; background: <?= $color ?>; border-radius: var(--radius-md); cursor: pointer; border: 3px solid transparent;" 
                                 class="color-option <?= $color === '#a8dadc' ? 'selected' : '' ?>"
                                 onclick="selectColor('<?= $color ?>')"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="border-top: 1px solid var(--gray-200); padding-top: var(--spacing-md); margin-top: var(--spacing-lg);">
                    <h3 style="font-size: 0.95rem; margin-bottom: var(--spacing-sm);"><i class="fas fa-paperclip"></i> Archivos Anexos</h3>
                    <div id="archivos-anexos-container" style="padding: var(--spacing-sm); background: var(--bg-secondary); border-radius: var(--radius-md); min-height: 60px;"></div>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Guardar Nota
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="cerrarModal()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Ver Nota (Solo Lectura) -->
    <div id="modalVerNota" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-lg);">
                <h2 style="margin: 0; display: flex; align-items: center; gap: var(--spacing-sm);">
                    <i class="fas fa-sticky-note" id="ver-nota-icon"></i>
                    <span id="ver-nota-titulo">Nota</span>
                </h2>
                <div style="display: flex; gap: var(--spacing-sm);">
                    <button type="button" class="btn btn-primary btn-sm" onclick="pasarAEdicion()">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="cerrarModalVer()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div id="ver-nota-etiquetas" style="margin-bottom: var(--spacing-md);"></div>
            
            <div id="ver-nota-contenido" class="markdown-body" style="min-height: 150px; max-height: 60vh; overflow-y: auto; padding: var(--spacing-lg); background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid var(--primary);"></div>
            
            <div id="ver-nota-archivos" style="margin-top: var(--spacing-lg); border-top: 1px solid var(--border-color); padding-top: var(--spacing-md);"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--spacing-lg); padding-top: var(--spacing-md); border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted);">
                <span id="ver-nota-fecha"></span>
                <div style="display: flex; gap: var(--spacing-md);">
                    <a href="#" id="ver-nota-pin" class="btn btn-ghost btn-sm"><i class="fas fa-thumbtack"></i></a>
                    <a href="#" id="ver-nota-archive" class="btn btn-ghost btn-sm"><i class="fas fa-archive"></i></a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
