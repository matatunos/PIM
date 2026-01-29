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
                $stmt = $pdo->prepare('INSERT IGNORE INTO etiquetas (nombre) VALUES (?)');
                $stmt->execute([$etiqueta]);
                
                $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre = ?');
                $stmt->execute([$etiqueta]);
                $etiqueta_id = $stmt->fetchColumn();
                
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
                $stmt = $pdo->prepare('INSERT IGNORE INTO etiquetas (nombre) VALUES (?)');
                $stmt->execute([$etiqueta]);
                
                $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre = ?');
                $stmt->execute([$etiqueta]);
                $etiqueta_id = $stmt->fetchColumn();
                
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
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
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

// Obtener todas las etiquetas
$stmt = $pdo->prepare('SELECT DISTINCT e.nombre FROM etiquetas e 
                       INNER JOIN nota_etiqueta ne ON e.id = ne.etiqueta_id
                       INNER JOIN notas n ON ne.nota_id = n.id
                       WHERE n.usuario_id = ? ORDER BY e.nombre');
$stmt->execute([$usuario_id]);
$todas_etiquetas = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
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
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            border-left: 4px solid;
            position: relative;
            cursor: pointer;
        }
        .nota-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
        .nota-contenido::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, var(--bg-primary));
        }
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
            top: var(--spacing-md);
            right: var(--spacing-md);
            display: flex;
            gap: var(--spacing-xs);
            opacity: 0;
            transition: opacity var(--transition-base);
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
            document.getElementById('modalNota').classList.add('active');
        }
        
        function editarNota(id) {
            const nota = notasData.find(n => n.id == id);
            if (!nota) return;
            
            document.getElementById('modal-title').textContent = 'Editar Nota';
            document.getElementById('form-action').value = 'editar';
            document.getElementById('nota-id').value = nota.id;
            document.getElementById('titulo').value = nota.titulo || '';
            document.getElementById('contenido').value = nota.contenido;
            document.getElementById('etiquetas').value = nota.etiquetas || '';
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
        }
        
        function selectColor(color) {
            document.getElementById('color_seleccionado').value = color;
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
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
            const modalNota = document.getElementById('modalNota');
            if (modalNota) {
                modalNota.addEventListener('click', function(e) {
                    if (e.target === this) cerrarModal();
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
                                        <a href="?delete=<?= $nota['id'] ?>" class="btn btn-danger btn-icon btn-sm" onclick="return confirm('¿Eliminar esta nota?')" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                    
                                    <?php if ($nota['titulo']): ?>
                                        <div class="nota-titulo"><?= htmlspecialchars($nota['titulo']) ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="nota-contenido">
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
                    <label for="contenido">Contenido *</label>
                    <textarea id="contenido" name="contenido" rows="10" required placeholder="Escribe aquí..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="etiquetas">Etiquetas (separadas por comas)</label>
                    <input type="text" id="etiquetas" name="etiquetas" placeholder="trabajo, personal, ideas">
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

</body>
</html>
