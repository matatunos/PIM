<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];

// Obtener columnas del Kanban
$columnas = [
    'pendiente' => ['titulo' => 'Pendiente', 'estado' => 0, 'color' => '#fee4e4'],
    'progreso' => ['titulo' => 'En Progreso', 'estado' => 'progreso', 'color' => '#fef3c7'],
    'completada' => ['titulo' => 'Completada', 'estado' => 1, 'color' => '#dcfce7']
];

// Obtener tareas
$stmt = $pdo->prepare('
    SELECT * FROM tareas 
    WHERE usuario_id = ? AND borrado_en IS NULL 
    ORDER BY 
        CASE prioridad
            WHEN "urgente" THEN 1
            WHEN "alta" THEN 2
            WHEN "media" THEN 3
            WHEN "baja" THEN 4
        END,
        fecha_vencimiento ASC
');
$stmt->execute([$usuario_id]);
$todas_tareas = $stmt->fetchAll();

// Agrupar tareas por estado
$tareas_por_estado = [
    0 => [],      // Pendiente
    'progreso' => [],  // En progreso
    1 => []       // Completada
];

// Crear una columna virtual para "En progreso"
// Si no existe campo de estado, usamos fecha_vencimiento cercana como "progreso"
foreach ($todas_tareas as $tarea) {
    if ($tarea['completada']) {
        $tareas_por_estado[1][] = $tarea;
    } else {
        $tareas_por_estado[0][] = $tarea;
    }
}

// Obtener listas disponibles
$stmt = $pdo->prepare('
    SELECT DISTINCT lista FROM tareas 
    WHERE usuario_id = ? AND borrado_en IS NULL
    ORDER BY lista
');
$stmt->execute([$usuario_id]);
$listas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener prioridades
$prioridades = ['baja' => '#a3e635', 'media' => '#fbbf24', 'alta' => '#f87171', 'urgente' => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban - Tareas - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .kanban-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--spacing-lg);
            padding: var(--spacing-lg);
            overflow-x: auto;
        }
        
        .kanban-column {
            background: #f9fafb;
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            min-height: 600px;
            display: flex;
            flex-direction: column;
            border: 2px solid var(--border-color);
        }
        
        .kanban-column-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--border-color);
        }
        
        .kanban-column-title {
            font-weight: 600;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .kanban-column-count {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .kanban-cards {
            flex: 1;
            overflow-y: auto;
            padding: 4px;
            min-height: 400px;
        }
        
        .kanban-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            cursor: grab;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .kanban-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .kanban-card.priority-urgente { border-left-color: #ef4444; }
        .kanban-card.priority-alta { border-left-color: #f87171; }
        .kanban-card.priority-media { border-left-color: #fbbf24; }
        .kanban-card.priority-baja { border-left-color: #a3e635; }
        
        .kanban-card-title {
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }
        
        .kanban-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85em;
            color: var(--text-secondary);
        }
        
        .kanban-card-priority {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 600;
            color: white;
        }
        
        .kanban-card-date {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .kanban-empty {
            text-align: center;
            color: var(--text-secondary);
            padding: var(--spacing-lg);
            font-style: italic;
        }
        
        .kanban-filters {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }
        
        .kanban-filter-btn {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9em;
        }
        
        .kanban-filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .kanban-container {
                grid-template-columns: 1fr;
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
                    <h1 class="page-title"><i class="fas fa-kanban"></i> Vista Kanban</h1>
                </div>
                <div class="top-bar-right">
                    <a href="index.php" class="btn btn-secondary" style="margin-left: var(--spacing-md);">
                        <i class="fas fa-list"></i> Vista Lista
                    </a>
                </div>
            </div>
            
            <div class="content-area">
                <div style="padding: var(--spacing-lg); background: white; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); border: 1px solid var(--border-color);">
                    <h3 style="margin-top: 0; margin-bottom: var(--spacing-md);">Filtros</h3>
                    <div class="kanban-filters">
                        <button type="button" class="kanban-filter-btn active" onclick="filtrarPorLista('')">
                            <i class="fas fa-list"></i> Todas las listas
                        </button>
                        <?php foreach ($listas as $lista): ?>
                            <button type="button" class="kanban-filter-btn" onclick="filtrarPorLista('<?= htmlspecialchars($lista) ?>')">
                                <i class="fas fa-folder"></i> <?= htmlspecialchars($lista) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="kanban-container">
                    <?php foreach ($tareas_por_estado as $estado => $tareas): ?>
                        <?php 
                        $titulo = $estado === 0 ? 'Pendiente' : ($estado === 1 ? 'Completada' : 'En Progreso');
                        $color_bg = $estado === 0 ? '#fee4e4' : ($estado === 1 ? '#dcfce7' : '#fef3c7');
                        $icono = $estado === 0 ? 'circle' : ($estado === 1 ? 'check-circle' : 'spinner');
                        ?>
                        <div class="kanban-column" style="border-color: <?= $color_bg ?>; background: <?= $color_bg ?>20;">
                            <div class="kanban-column-header">
                                <div class="kanban-column-title">
                                    <i class="fas fa-<?= $icono ?>"></i>
                                    <?= $titulo ?>
                                </div>
                                <div class="kanban-column-count"><?= count($tareas) ?></div>
                            </div>
                            
                            <div class="kanban-cards" data-estado="<?= $estado ?>" id="kanban-<?= $estado ?>">
                                <?php if (empty($tareas)): ?>
                                    <div class="kanban-empty">
                                        <p><i class="fas fa-inbox"></i></p>
                                        <p>No hay tareas</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($tareas as $tarea): ?>
                                        <div class="kanban-card priority-<?= htmlspecialchars($tarea['prioridad']) ?>" data-tarea-id="<?= $tarea['id'] ?>" draggable="true" ondragstart="dragStart(event)" ondragend="dragEnd(event)" onclick="editarTarea(<?= $tarea['id'] ?>)">
                                            <div class="kanban-card-title"><?= htmlspecialchars(substr($tarea['titulo'], 0, 40)) ?></div>
                                            <?php if ($tarea['descripcion']): ?>
                                                <p style="margin: 0 0 var(--spacing-sm) 0; font-size: 0.9em; color: var(--text-secondary);">
                                                    <?= htmlspecialchars(substr($tarea['descripcion'], 0, 60)) ?>...
                                                </p>
                                            <?php endif; ?>
                                            <div class="kanban-card-meta">
                                                <span class="kanban-card-priority" style="background: <?= $prioridades[$tarea['prioridad']] ?>;">
                                                    <?= ucfirst($tarea['prioridad']) ?>
                                                </span>
                                                <?php if ($tarea['fecha_vencimiento']): ?>
                                                    <div class="kanban-card-date">
                                                        <i class="fas fa-calendar"></i>
                                                        <?= date('d/m/y', strtotime($tarea['fecha_vencimiento'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let draggedElement = null;
        
        function dragStart(e) {
            draggedElement = e.target.closest('.kanban-card');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', draggedElement.innerHTML);
            draggedElement.style.opacity = '0.5';
        }
        
        function dragEnd(e) {
            draggedElement.style.opacity = '1';
        }
        
        document.querySelectorAll('.kanban-cards').forEach(columna => {
            columna.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            columna.addEventListener('drop', (e) => {
                e.preventDefault();
                
                const tareaId = draggedElement.dataset.tareaId;
                const estadoDestino = columna.dataset.estado;
                
                // Mover tarea en la UI
                columna.appendChild(draggedElement);
                
                // Actualizar en la base de datos
                actualizarTarea(tareaId, estadoDestino);
            });
        });
        
        function actualizarTarea(tareaId, nuevoEstado) {
            fetch('/api/tareas/actualizar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: tareaId,
                    completada: nuevoEstado === '1' ? 1 : 0
                })
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al actualizar la tarea');
            });
        }
        
        function editarTarea(tareaId) {
            window.location.href = '/app/tareas/index.php?editar=' + tareaId;
        }
        
        function filtrarPorLista(lista) {
            // Implementar filtrado por lista
            console.log('Filtrar por lista:', lista);
        }
    </script>
</body>
</html>