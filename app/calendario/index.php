<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];

// Obtener vista (mes, semana, dia)
$vista = $_GET['vista'] ?? 'mes';
if (!in_array($vista, ['mes', 'semana', 'dia'])) {
    $vista = 'mes';
}

// Obtener fecha de navegación
$fecha = $_GET['fecha'] ?? date('Y-m-d');
try {
    $fecha_obj = new DateTime($fecha);
} catch (Exception $e) {
    $fecha_obj = new DateTime();
    $fecha = $fecha_obj->format('Y-m-d');
}

// Crear evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $hora_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
    $hora_fin = !empty($_POST['hora_fin']) ? $_POST['hora_fin'] : null;
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $color = $_POST['color'] ?? '#a8dadc';
    $todo_el_dia = isset($_POST['todo_el_dia']) ? 1 : 0;
    
    if (!empty($titulo) && !empty($fecha_inicio)) {
        if (empty($fecha_fin)) $fecha_fin = $fecha_inicio;
        
        $stmt = $pdo->prepare('INSERT INTO eventos (usuario_id, titulo, descripcion, fecha_inicio, fecha_fin, hora_inicio, hora_fin, ubicacion, color, todo_el_dia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $titulo, $descripcion, $fecha_inicio, $fecha_fin, $hora_inicio, $hora_fin, $ubicacion, $color, $todo_el_dia]);
        header('Location: index.php?vista=' . $vista . '&fecha=' . $fecha_inicio);
        exit;
    }
}

// Editar evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $id = (int)($_POST['id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $hora_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
    $hora_fin = !empty($_POST['hora_fin']) ? $_POST['hora_fin'] : null;
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $color = $_POST['color'] ?? '#a8dadc';
    $todo_el_dia = isset($_POST['todo_el_dia']) ? 1 : 0;
    
    if (!empty($titulo) && !empty($fecha_inicio) && $id > 0) {
        if (empty($fecha_fin)) $fecha_fin = $fecha_inicio;
        
        $stmt = $pdo->prepare('UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, hora_inicio = ?, hora_fin = ?, ubicacion = ?, color = ?, todo_el_dia = ? WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$titulo, $descripcion, $fecha_inicio, $fecha_fin, $hora_inicio, $hora_fin, $ubicacion, $color, $todo_el_dia, $id, $usuario_id]);
        header('Location: index.php?vista=' . $vista . '&fecha=' . $fecha_inicio);
        exit;
    }
}

// Mover evento a papelera
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('SELECT titulo FROM eventos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    $evento = $stmt->fetch();
    
    $stmt = $pdo->prepare('UPDATE eventos SET borrado_en = NOW() WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    
    $stmt = $pdo->prepare('INSERT INTO papelera_logs (usuario_id, tipo, item_id, nombre) VALUES (?, ?, ?, ?)');
    $stmt->execute([$usuario_id, 'eventos', $id, $evento['titulo'] ?? 'Sin título']);
    
    header('Location: index.php?vista=' . $vista . '&fecha=' . $fecha);
    exit;
}

// Obtener todos los eventos del usuario
$stmt = $pdo->prepare('SELECT * FROM eventos WHERE usuario_id = ? AND borrado_en IS NULL ORDER BY fecha_inicio, hora_inicio');
$stmt->execute([$usuario_id]);
$eventos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <style>
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }
        .view-toggle {
            display: flex;
            gap: var(--spacing-sm);
        }
        .view-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
            color: var(--text-secondary);
        }
        .view-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .view-btn:hover {
            border-color: var(--primary);
        }
        .nav-buttons {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }
        .fecha-actual {
            font-size: 1.2rem;
            font-weight: 600;
            min-width: 200px;
            text-align: center;
        }
        .fc {
            font-family: inherit;
        }
        .fc .fc-button-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .fc .fc-button-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .fc-daygrid-day.fc-day-today {
            background-color: var(--primary-light) !important;
        }
        .fc .fc-col-header-cell {
            background: var(--gray-100);
            color: var(--text-primary);
            font-weight: 600;
        }
        .fc-event {
            border: none !important;
            cursor: pointer !important;
        }
        .fc-event-title {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .fc-daygrid-day-number {
            padding: var(--spacing-xs);
        }
        #calendar {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
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
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
            color: var(--text-primary);
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group textarea {
            width: 100%;
            padding: var(--spacing-sm);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 1rem;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .color-options {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .color-option.selected {
            border-color: var(--text-primary);
            box-shadow: 0 0 0 2px var(--bg-primary);
        }
        .modal-buttons {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
            justify-content: flex-end;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1rem;
            transition: all var(--transition-fast);
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-secondary {
            background: var(--gray-200);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .checkbox-group {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Calendario</h1>
                </div>
            </div>
            
            <div class="content-area">
                <div class="calendar-header">
                    <div class="fecha-actual" id="fechaActual">
                        <?php 
                        if ($vista === 'mes') {
                            echo $fecha_obj->format('F Y');
                        } elseif ($vista === 'semana') {
                            $lunes = clone $fecha_obj;
                            $lunes->modify('Monday this week');
                            $domingo = clone $lunes;
                            $domingo->modify('+6 days');
                            echo $lunes->format('d/m') . ' - ' . $domingo->format('d/m/Y');
                        } else {
                            echo $fecha_obj->format('d/m/Y');
                        }
                        ?>
                    </div>
                    
                    <div class="view-toggle">
                        <button class="view-btn <?= $vista === 'dia' ? 'active' : '' ?>" onclick="cambiarVista('dayGridDay')">
                            <i class="fas fa-square"></i> Día
                        </button>
                        <button class="view-btn <?= $vista === 'semana' ? 'active' : '' ?>" onclick="cambiarVista('dayGridWeek')">
                            <i class="fas fa-calendar-week"></i> Semana
                        </button>
                        <button class="view-btn <?= $vista === 'mes' ? 'active' : '' ?>" onclick="cambiarVista('dayGridMonth')">
                            <i class="fas fa-calendar"></i> Mes
                        </button>
                    </div>
                    
                    <div class="nav-buttons">
                        <button class="btn btn-secondary" onclick="navegar(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="btn btn-secondary" onclick="irHoy()">
                            Hoy
                        </button>
                        <button class="btn btn-secondary" onclick="navegar(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="btn btn-primary" onclick="abrirModalNuevo()">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    </div>
                </div>
                
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    
    <!-- Modal de evento -->
    <div class="modal" id="modalEvento">
        <div class="modal-content">
            <h2 id="modal-title"><i class="fas fa-calendar-plus"></i> Nuevo Evento</h2>
            
            <form id="formEvento" method="POST">
                <input type="hidden" name="action" id="form-action" value="crear">
                <input type="hidden" name="id" id="evento-id">
                
                <div class="form-group">
                    <label>Título *</label>
                    <input type="text" id="titulo" name="titulo" required>
                </div>
                
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea id="descripcion" name="descripcion"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div class="form-group">
                        <label>Fecha inicio *</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="todo_el_dia" name="todo_el_dia" onchange="toggleHoras()">
                    <label for="todo_el_dia">Evento de todo el día</label>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);" id="horarios">
                    <div class="form-group">
                        <label>Hora inicio</label>
                        <input type="time" id="hora_inicio" name="hora_inicio">
                    </div>
                    
                    <div class="form-group">
                        <label>Hora fin</label>
                        <input type="time" id="hora_fin" name="hora_fin">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Ubicación</label>
                    <input type="text" id="ubicacion" name="ubicacion">
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <div class="color-options">
                        <input type="hidden" id="color_seleccionado" name="color" value="#a8dadc">
                        <div class="color-option selected" style="background: #a8dadc;" onclick="seleccionarColor('#a8dadc', this)"></div>
                        <div class="color-option" style="background: #ff6b6b;" onclick="seleccionarColor('#ff6b6b', this)"></div>
                        <div class="color-option" style="background: #4ecdc4;" onclick="seleccionarColor('#4ecdc4', this)"></div>
                        <div class="color-option" style="background: #ffe66d;" onclick="seleccionarColor('#ffe66d', this)"></div>
                        <div class="color-option" style="background: #95e1d3;" onclick="seleccionarColor('#95e1d3', this)"></div>
                        <div class="color-option" style="background: #f38181;" onclick="seleccionarColor('#f38181', this)"></div>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btn-delete" style="display: none;" onclick="eliminarEvento()">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script>
        const eventosData = <?= json_encode($eventos) ?>;
        const vistaActual = '<?= $vista ?>';
        const fechaActual = '<?= $fecha ?>';
        
        let calendar;
        let vistaMap = {
            'dia': 'dayGridDay',
            'semana': 'dayGridWeek',
            'mes': 'dayGridMonth'
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            // Mapear eventos para FullCalendar
            const eventos = eventosData.map(e => ({
                id: e.id,
                title: e.titulo,
                start: e.fecha_inicio + (e.hora_inicio ? 'T' + e.hora_inicio : ''),
                end: e.fecha_fin + (e.hora_fin ? 'T' + e.hora_fin : ''),
                backgroundColor: e.color,
                borderColor: e.color,
                allDay: e.todo_el_dia == 1,
                extendedProps: {
                    descripcion: e.descripcion,
                    ubicacion: e.ubicacion,
                    color: e.color,
                    id: e.id
                }
            }));
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: vistaMap[vistaActual] || 'dayGridMonth',
                initialDate: fechaActual,
                headerToolbar: false,
                locale: 'es',
                height: 'auto',
                contentHeight: 'auto',
                events: eventos,
                dateClick: function(info) {
                    abrirModalNuevo(info.dateStr);
                },
                eventClick: function(info) {
                    editarEvento(info.event.id);
                }
            });
            
            calendar.render();
        });
        
        function cambiarVista(vista) {
            if (calendar) {
                calendar.changeView(vista);
                actualizarBotones(vista);
            }
        }
        
        function actualizarBotones(vista) {
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const vistaTexto = Object.keys(vistaMap).find(k => vistaMap[k] === vista);
            if (vistaTexto === 'dia') {
                document.querySelectorAll('.view-btn')[0].classList.add('active');
            } else if (vistaTexto === 'semana') {
                document.querySelectorAll('.view-btn')[1].classList.add('active');
            } else {
                document.querySelectorAll('.view-btn')[2].classList.add('active');
            }
        }
        
        function navegar(dir) {
            if (calendar) {
                if (dir > 0) {
                    calendar.next();
                } else {
                    calendar.prev();
                }
            }
        }
        
        function irHoy() {
            if (calendar) {
                calendar.today();
            }
        }
        
        function cerrarModal() {
            document.getElementById('modalEvento').classList.remove('active');
        }
        
        function abrirModalNuevo(fecha = null) {
            document.getElementById('modal-title').textContent = '✚ Nuevo Evento';
            document.getElementById('form-action').value = 'crear';
            document.getElementById('btn-delete').style.display = 'none';
            document.getElementById('formEvento').reset();
            document.getElementById('color_seleccionado').value = '#a8dadc';
            
            if (fecha) {
                document.getElementById('fecha_inicio').value = fecha;
            } else {
                document.getElementById('fecha_inicio').value = new Date().toISOString().split('T')[0];
            }
            
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            document.querySelector('.color-option').classList.add('selected');
            
            document.getElementById('modalEvento').classList.add('active');
        }
        
        function editarEvento(id) {
            const evento = eventosData.find(e => e.id == id);
            if (!evento) return;
            
            document.getElementById('modal-title').textContent = '✎ Editar Evento';
            document.getElementById('form-action').value = 'editar';
            document.getElementById('btn-delete').style.display = 'inline-block';
            document.getElementById('evento-id').value = evento.id;
            document.getElementById('titulo').value = evento.titulo;
            document.getElementById('descripcion').value = evento.descripcion || '';
            document.getElementById('fecha_inicio').value = evento.fecha_inicio.substring(0, 10);
            document.getElementById('fecha_fin').value = evento.fecha_fin ? evento.fecha_fin.substring(0, 10) : '';
            document.getElementById('hora_inicio').value = evento.hora_inicio || '';
            document.getElementById('hora_fin').value = evento.hora_fin || '';
            document.getElementById('ubicacion').value = evento.ubicacion || '';
            document.getElementById('todo_el_dia').checked = evento.todo_el_dia == 1;
            document.getElementById('color_seleccionado').value = evento.color;
            
            toggleHoras();
            
            document.querySelectorAll('.color-option').forEach(el => {
                el.classList.remove('selected');
                if (el.style.background === evento.color) {
                    el.classList.add('selected');
                }
            });
            
            document.getElementById('modalEvento').classList.add('active');
        }
        
        function eliminarEvento() {
            if (confirm('¿Eliminar este evento?')) {
                const id = document.getElementById('evento-id').value;
                window.location.href = '?delete=' + id;
            }
        }
        
        function seleccionarColor(color, element) {
            document.querySelectorAll('.color-option').forEach(el => {
                el.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('color_seleccionado').value = color;
        }
        
        function toggleHoras() {
            const horarios = document.getElementById('horarios');
            const todoElDia = document.getElementById('todo_el_dia').checked;
            horarios.style.display = todoElDia ? 'none' : 'grid';
        }
        
        document.getElementById('formEvento').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        document.getElementById('modalEvento').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
    </script>
</body>
</html>
