<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// Crear tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? null;
    $lista = trim($_POST['lista'] ?? 'Personal');
    
    if (!empty($titulo)) {
        $stmt = $pdo->prepare('INSERT INTO tareas (usuario_id, titulo, descripcion, prioridad, fecha_vencimiento, lista) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $titulo, $descripcion, $prioridad, $fecha_vencimiento, $lista]);
        $mensaje = 'Tarea creada exitosamente';
    }
}

// Completar/Descompletar tarea
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare('UPDATE tareas SET completada = NOT completada, completada_en = IF(completada = 0, NOW(), NULL) WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    header('Location: index.php');
    exit;
}

// Mover tarea a papelera
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('UPDATE tareas SET borrado_en = NOW() WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    
    // Registrar en papelera_logs
    $stmt = $pdo->prepare('SELECT titulo FROM tareas WHERE id = ?');
    $stmt->execute([$id]);
    $tarea = $stmt->fetch();
    $stmt = $pdo->prepare('INSERT INTO papelera_logs (usuario_id, tipo, item_id, nombre) VALUES (?, ?, ?, ?)');
    $stmt->execute([$usuario_id, 'tareas', $id, $tarea['titulo'] ?? 'Sin título']);
    
    header('Location: index.php');
    exit;
}

// Editar tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $tarea_id = $_POST['tarea_id'] ?? null;
    
    if ($tarea_id) {
        // Verificar que la tarea pertenece al usuario
        $stmt = $pdo->prepare('SELECT id FROM tareas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$tarea_id, $usuario_id]);
        
        if ($stmt->rowCount()) {
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $prioridad = $_POST['prioridad'] ?? 'media';
            $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? null;
            $lista = trim($_POST['lista'] ?? 'Personal');
            
            if (!empty($titulo)) {
                $stmt = $pdo->prepare('
                    UPDATE tareas 
                    SET titulo = ?, descripcion = ?, prioridad = ?, 
                        fecha_vencimiento = ?, lista = ?, actualizado_en = NOW()
                    WHERE id = ? AND usuario_id = ?
                ');
                $stmt->execute([
                    $titulo, $descripcion, $prioridad, 
                    $fecha_vencimiento, $lista, $tarea_id, $usuario_id
                ]);
                $mensaje = 'Tarea actualizada correctamente';
            }
        }
    }
}

// Obtener filtros
$filtro_completada = $_GET['completada'] ?? 'pendientes';
$filtro_prioridad = $_GET['prioridad'] ?? 'todas';
$filtro_lista = $_GET['lista'] ?? 'todas';

// Construir query con filtros seguros
$sql = 'SELECT * FROM tareas WHERE usuario_id = ? AND borrado_en IS NULL';
$params = [$usuario_id];

if ($filtro_completada === 'pendientes') {
    $sql .= ' AND completada = 0';
} elseif ($filtro_completada === 'completadas') {
    $sql .= ' AND completada = 1';
}

if ($filtro_prioridad !== 'todas') {
    $sql .= ' AND prioridad = ?';
    $params[] = $filtro_prioridad;
}

if ($filtro_lista !== 'todas') {
    $sql .= ' AND lista = ?';
    $params[] = $filtro_lista;
}

$sql .= ' ORDER BY completada ASC, 
          CASE prioridad 
            WHEN "urgente" THEN 1 
            WHEN "alta" THEN 2 
            WHEN "media" THEN 3 
            WHEN "baja" THEN 4 
          END ASC, 
          fecha_vencimiento ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tareas = $stmt->fetchAll();

// Obtener listas únicas
$stmt = $pdo->prepare('SELECT DISTINCT lista FROM tareas WHERE usuario_id = ? AND borrado_en IS NULL ORDER BY lista');
$stmt->execute([$usuario_id]);
$listas = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas - PIM</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .filters {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
            margin-bottom: var(--spacing-xl);
            align-items: center;
        }
        .filter-group {
            display: flex;
            gap: var(--spacing-sm);
            align-items: center;
        }
        .filter-group select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
        }
        .task-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        .task-item {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            transition: all var(--transition-base);
        }
        .task-item:hover {
            box-shadow: var(--shadow-md);
        }
        .task-item.completed {
            opacity: 0.6;
        }
        .task-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid var(--gray-400);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all var(--transition-fast);
        }
        .task-checkbox:hover {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        .task-checkbox.checked {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        .task-body {
            flex: 1;
        }
        .task-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-sm);
        }
        .task-title {
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--text-primary);
        }
        .task-item.completed .task-title {
            text-decoration: line-through;
        }
        .task-meta {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .task-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .priority-urgente { background: var(--danger); color: white; }
        .priority-alta { background: var(--warning); color: var(--gray-900); }
        .priority-media { background: var(--primary); color: white; }
        .priority-baja { background: var(--gray-300); color: var(--gray-700); }
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
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <script>
        // Función para editar tarea
        function editarTarea(id, tarea) {
            document.getElementById('editar-tarea-id').value = id;
            document.getElementById('editar-titulo').value = tarea.titulo;
            document.getElementById('editar-descripcion').value = tarea.descripcion || '';
            document.getElementById('editar-prioridad').value = tarea.prioridad;
            document.getElementById('editar-fecha_vencimiento').value = tarea.fecha_vencimiento || '';
            document.getElementById('editar-lista').value = tarea.lista;
            
            // Cargar archivos anexos
            fetch('/api/archivos.php?action=tareas&tarea_id=' + id)
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
            
            document.getElementById('modalEditar').classList.add('active');
        }
        
        function mostrarArchivos(tipo, id) {
            const modalId = 'modalArchivos' + tipo.charAt(0).toUpperCase() + tipo.slice(1) + id;
            const container = document.getElementById('lista-archivos-' + tipo + '-' + id);
            
            fetch('/api/archivos.php?action=listar')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || data.archivos.length === 0) {
                        container.innerHTML = '<p style="text-align: center; color: var(--text-muted);">No hay archivos disponibles</p>';
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
                            container.innerHTML = html;
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
                listContainer.id = 'lista-archivos-' + tipo + '-' + id;
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
            document.getElementById('modalNueva').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
            
            document.getElementById('modalEditar').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
            
            // Cargar badges de archivos
            <?php foreach ($tareas as $tarea): ?>
                mostrarArchivosAnexos('tarea', <?= $tarea['id'] ?>);
            <?php endforeach; ?>
        });
    </script>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-tasks"></i> Tareas</h1>
                </div>
                <div class="top-bar-right">
                    <button onclick="document.getElementById('modalNueva').classList.add('active')" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nueva Tarea
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <!-- Filtros -->
                <div class="filters">
                    <div class="filter-group">
                        <label>Estado:</label>
                        <select onchange="location.href='?completada='+this.value+'&prioridad=<?= htmlspecialchars($filtro_prioridad) ?>&lista=<?= htmlspecialchars($filtro_lista) ?>'">
                            <option value="pendientes" <?= $filtro_completada === 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="completadas" <?= $filtro_completada === 'completadas' ? 'selected' : '' ?>>Completadas</option>
                            <option value="todas" <?= $filtro_completada === 'todas' ? 'selected' : '' ?>>Todas</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Prioridad:</label>
                        <select onchange="location.href='?completada=<?= htmlspecialchars($filtro_completada) ?>&prioridad='+this.value+'&lista=<?= htmlspecialchars($filtro_lista) ?>'">
                            <option value="todas" <?= $filtro_prioridad === 'todas' ? 'selected' : '' ?>>Todas</option>
                            <option value="urgente" <?= $filtro_prioridad === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                            <option value="alta" <?= $filtro_prioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
                            <option value="media" <?= $filtro_prioridad === 'media' ? 'selected' : '' ?>>Media</option>
                            <option value="baja" <?= $filtro_prioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Lista:</label>
                        <select onchange="location.href='?completada=<?= htmlspecialchars($filtro_completada) ?>&prioridad=<?= htmlspecialchars($filtro_prioridad) ?>&lista='+this.value">
                            <option value="todas">Todas</option>
                            <?php foreach ($listas as $lista): ?>
                                <option value="<?= htmlspecialchars($lista) ?>" <?= $filtro_lista === $lista ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lista) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Lista de Tareas -->
                <div class="task-list">
                    <?php if (empty($tareas)): ?>
                        <div class="card">
                            <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                                <i class="fas fa-clipboard-check" style="font-size: 4rem; color: var(--gray-300);"></i>
                                <h3 style="margin-top: var(--spacing-lg); color: var(--text-secondary);">No hay tareas</h3>
                                <p class="text-muted">Crea tu primera tarea para comenzar</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tareas as $tarea): ?>
                            <div class="task-item <?= $tarea['completada'] ? 'completed' : '' ?>">
                                <a href="?toggle=<?= $tarea['id'] ?>" class="task-checkbox <?= $tarea['completada'] ? 'checked' : '' ?>">
                                    <?php if ($tarea['completada']): ?>
                                        <i class="fas fa-check"></i>
                                    <?php endif; ?>
                                </a>
                                
                                <div class="task-body">
                                    <div class="task-header">
                                        <div class="task-title"><?= htmlspecialchars($tarea['titulo']) ?></div>
                                        <span class="priority-badge priority-<?= htmlspecialchars($tarea['prioridad']) ?>">
                                            <?= htmlspecialchars($tarea['prioridad']) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($tarea['descripcion']): ?>
                                        <div style="color: var(--text-secondary); margin-bottom: var(--spacing-sm);">
                                            <?= nl2br(htmlspecialchars($tarea['descripcion'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="task-meta">
                                        <span><i class="fas fa-list"></i> <?= htmlspecialchars($tarea['lista']) ?></span>
                                        <?php if ($tarea['fecha_vencimiento']): ?>
                                            <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($tarea['fecha_vencimiento'])) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-clock"></i> Creada: <?= date('d/m/Y', strtotime($tarea['creado_en'])) ?></span>
                                    </div>
                                </div>
                                
                                <div class="task-actions">
                                    <button type="button" class="btn btn-secondary btn-icon btn-sm" onclick="mostrarArchivos('tarea', <?= $tarea['id'] ?>)" title="Archivos" id="btn-archivos-tarea-<?= $tarea['id'] ?>" style="position: relative;">
                                        <i class="fas fa-paperclip"></i>
                                        <span id="badge-archivos-tarea-<?= $tarea['id'] ?>" style="position: absolute; top: -8px; right: -8px; background: var(--danger); color: white; border-radius: 50%; width: 18px; height: 18px; display: none; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;"></span>
                                    </button>
                                    <button type="button" class="btn btn-primary btn-icon btn-sm" onclick="editarTarea(<?= $tarea['id'] ?>, <?= htmlspecialchars(json_encode($tarea)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $tarea['id'] ?>" class="btn btn-danger btn-icon btn-sm" onclick="return confirm('¿Eliminar esta tarea?')">>
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Nueva Tarea -->
    <div id="modalNueva" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">Nueva Tarea</h2>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="crear">
                
                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="prioridad">Prioridad</label>
                    <select id="prioridad" name="prioridad">
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fecha_vencimiento">Fecha Límite</label>
                    <input type="date" id="fecha_vencimiento" name="fecha_vencimiento">
                </div>
                
                <div class="form-group">
                    <label for="lista">Lista</label>
                    <input type="text" id="lista" name="lista" value="Personal" list="listas-existentes">
                    <datalist id="listas-existentes">
                        <?php foreach ($listas as $lista): ?>
                            <option value="<?= htmlspecialchars($lista) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Crear Tarea
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalNueva').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Editar Tarea -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">Editar Tarea</h2>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" id="editar-tarea-id" name="tarea_id">
                
                <div class="form-group">
                    <label for="editar-titulo">Título *</label>
                    <input type="text" id="editar-titulo" name="titulo" required>
                </div>
                
                <div class="form-group">
                    <label for="editar-descripcion">Descripción</label>
                    <textarea id="editar-descripcion" name="descripcion" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editar-prioridad">Prioridad</label>
                    <select id="editar-prioridad" name="prioridad">
                        <option value="baja">Baja</option>
                        <option value="media">Media</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editar-fecha_vencimiento">Fecha Límite</label>
                    <input type="date" id="editar-fecha_vencimiento" name="fecha_vencimiento">
                </div>
                
                <div class="form-group">
                    <label for="editar-lista">Lista</label>
                    <input type="text" id="editar-lista" name="lista" list="listas-existentes-editar">
                    <datalist id="listas-existentes-editar">
                        <?php foreach ($listas as $lista): ?>
                            <option value="<?= htmlspecialchars($lista) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div style="border-top: 1px solid var(--gray-200); padding-top: var(--spacing-md); margin-top: var(--spacing-lg);">
                    <h3 style="font-size: 0.95rem; margin-bottom: var(--spacing-sm);"><i class="fas fa-paperclip"></i> Archivos Anexos</h3>
                    <div id="archivos-anexos-container" style="padding: var(--spacing-sm); background: var(--bg-secondary); border-radius: var(--radius-md); min-height: 60px;"></div>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Guardar Cambios
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalEditar').classList.remove('active')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    

</body>
</html>
