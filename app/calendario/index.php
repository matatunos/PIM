<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// Obtener mes y año actual o del parámetro (GET o POST)
$mes = isset($_POST['mes']) ? (int)$_POST['mes'] : (isset($_GET['mes']) ? (int)$_GET['mes'] : date('n'));
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : (isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y'));

// Validar mes y año
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($anio < 1900 || $anio > 2100) $anio = date('Y');

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
        header('Location: index.php?mes=' . $mes . '&anio=' . $anio);
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
        $result = $stmt->execute([$titulo, $descripcion, $fecha_inicio, $fecha_fin, $hora_inicio, $hora_fin, $ubicacion, $color, $todo_el_dia, $id, $usuario_id]);
        header('Location: index.php?mes=' . $mes . '&anio=' . $anio);
        exit;
    }
}

// Mover evento a papelera
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('UPDATE eventos SET borrado_en = NOW() WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    
    // Registrar en papelera_logs
    $stmt = $pdo->prepare('SELECT titulo FROM eventos WHERE id = ?');
    $stmt->execute([$id]);
    $evento = $stmt->fetch();
    $stmt = $pdo->prepare('INSERT INTO papelera_logs (usuario_id, tipo, item_id, nombre) VALUES (?, ?, ?, ?)');
    $stmt->execute([$usuario_id, 'eventos', $id, $evento['titulo'] ?? 'Sin título']);
    
    header('Location: index.php?mes=' . $mes . '&anio=' . $anio);
    exit;
}

// Obtener eventos del mes
$primer_dia = sprintf('%04d-%02d-01', $anio, $mes);
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));

$stmt = $pdo->prepare('SELECT * FROM eventos WHERE usuario_id = ? AND borrado_en IS NULL AND ((fecha_inicio BETWEEN ? AND ?) OR (fecha_fin BETWEEN ? AND ?) OR (fecha_inicio <= ? AND fecha_fin >= ?)) ORDER BY fecha_inicio, hora_inicio');
$stmt->execute([$usuario_id, $primer_dia, $ultimo_dia, $primer_dia, $ultimo_dia, $primer_dia, $ultimo_dia]);
$eventos = $stmt->fetchAll();

// Agrupar eventos por fecha
$eventos_por_fecha = [];
foreach ($eventos as $evento) {
    $fecha = substr($evento['fecha_inicio'], 0, 10); // Extraer solo YYYY-MM-DD
    if (!isset($eventos_por_fecha[$fecha])) {
        $eventos_por_fecha[$fecha] = [];
    }
    $eventos_por_fecha[$fecha][] = $evento;
}

// Calcular calendario
$primer_dia_mes = mktime(0, 0, 0, $mes, 1, $anio);
$dias_en_mes = date('t', $primer_dia_mes);
$dia_semana_inicio = date('N', $primer_dia_mes); // 1 = Lunes, 7 = Domingo

$mes_anterior = $mes - 1;
$anio_anterior = $anio;
if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $anio_anterior--;
}

$mes_siguiente = $mes + 1;
$anio_siguiente = $anio;
if ($mes_siguiente > 12) {
    $mes_siguiente = 1;
    $anio_siguiente++;
}

$meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias_es = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .calendario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            padding: var(--spacing-lg);
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .mes-titulo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .nav-mes {
            display: flex;
            gap: var(--spacing-sm);
        }
        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .dia-semana {
            background: var(--primary);
            color: white;
            padding: var(--spacing-md);
            text-align: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .dia-celda {
            background: var(--bg-primary);
            min-height: 120px;
            padding: var(--spacing-sm);
            cursor: pointer;
            transition: background var(--transition-fast);
            position: relative;
        }
        .dia-celda:hover {
            background: var(--bg-secondary);
        }
        .dia-celda.otro-mes {
            background: var(--gray-50);
            opacity: 0.5;
        }
        .dia-celda.hoy {
            background: var(--primary-light);
        }
        .dia-numero {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: var(--spacing-xs);
            color: var(--text-primary);
        }
        .evento-mini {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            margin-bottom: 0.2rem;
            border-radius: var(--radius-sm);
            background: var(--primary);
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
        .lista-eventos {
            margin-top: var(--spacing-xl);
        }
        .evento-card {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--spacing-md);
            border-left: 4px solid;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .evento-info h3 {
            margin: 0 0 var(--spacing-sm) 0;
            font-size: 1.2rem;
        }
        .evento-meta {
            display: flex;
            gap: var(--spacing-lg);
            color: var(--text-secondary);
            font-size: 0.9rem;
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
    <script>
        function cerrarModal() {
            document.getElementById('modalEvento').classList.remove('active');
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
                listContainer.style.cssText = 'max-height: 400px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: var(--spacing-md);';
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
        
        const eventosData = <?= json_encode($eventos) ?>;
        
        function abrirModalNuevo(fecha = null) {
            document.getElementById('modal-title').textContent = 'Nuevo Evento';
            document.getElementById('form-action').value = 'crear';
            document.getElementById('formEvento').reset();
            document.getElementById('color_seleccionado').value = '#a8dadc';
            
            if (fecha) {
                document.getElementById('fecha_inicio').value = fecha;
            } else {
                document.getElementById('fecha_inicio').value = '<?= date('Y-m-d') ?>';
            }
            
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            document.querySelector('.color-option').classList.add('selected');
            document.getElementById('modalEvento').classList.add('active');
        }
        
        function editarEvento(id) {
            const evento = eventosData.find(e => e.id == id);
            if (!evento) return;
            
            document.getElementById('modal-title').textContent = 'Editar Evento';
            document.getElementById('form-action').value = 'editar';
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
            
            // Cargar archivos anexos
            fetch('/api/archivos.php?action=eventos&evento_id=' + id)
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
            
            document.getElementById('modalEvento').classList.add('active');
        }
        
        function selectColor(color) {
            document.getElementById('color_seleccionado').value = color;
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }
        
        function toggleHoras() {
            const container = document.getElementById('horas-container');
            const checkbox = document.getElementById('todo_el_dia');
            container.style.display = checkbox.checked ? 'none' : 'grid';
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
            document.getElementById('modalEvento').addEventListener('click', function(e) {
                if (e.target === this) cerrarModal();
            });
            
            // Cargar badges de archivos
            <?php foreach ($eventos as $evento): ?>
                mostrarArchivosAnexos('evento', <?= $evento['id'] ?>);
            <?php endforeach; ?>
        });
    </script>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Calendario</h1>
                </div>
                <div class="top-bar-right">
                    <a href="?mes=<?= date('n') ?>&anio=<?= date('Y') ?>" class="btn btn-ghost">
                        <i class="fas fa-calendar-day"></i>
                        Hoy
                    </a>
                    <button onclick="abrirModalNuevo()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Evento
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="calendario-header">
                    <div class="nav-mes">
                        <a href="?mes=<?= $mes_anterior ?>&anio=<?= $anio_anterior ?>" class="btn btn-ghost">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    <div class="mes-titulo"><?= $meses_es[$mes] ?> <?= $anio ?></div>
                    <div class="nav-mes">
                        <a href="?mes=<?= $mes_siguiente ?>&anio=<?= $anio_siguiente ?>" class="btn btn-ghost">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="calendario-grid">
                    <?php foreach ($dias_es as $dia): ?>
                        <div class="dia-semana"><?= $dia ?></div>
                    <?php endforeach; ?>
                    
                    <?php
                    // Días del mes anterior
                    for ($i = 1; $i < $dia_semana_inicio; $i++) {
                        echo '<div class="dia-celda otro-mes"></div>';
                    }
                    
                    // Días del mes actual
                    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
                        $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                        $es_hoy = $fecha === date('Y-m-d');
                        $clase = $es_hoy ? 'dia-celda hoy' : 'dia-celda';
                        
                        echo "<div class='$clase' onclick='abrirModalNuevo(\"$fecha\")'>";
                        echo "<div class='dia-numero'>$dia</div>";
                        
                        if (isset($eventos_por_fecha[$fecha])) {
                            foreach (array_slice($eventos_por_fecha[$fecha], 0, 3) as $evento) {
                                $color = htmlspecialchars($evento['color']);
                                $titulo = htmlspecialchars($evento['titulo']);
                                echo "<div class='evento-mini' style='background: $color;' onclick='event.stopPropagation(); editarEvento({$evento['id']})'>$titulo</div>";
                            }
                            if (count($eventos_por_fecha[$fecha]) > 3) {
                                echo "<div style='text-align: center; font-size: 0.7rem; color: var(--text-muted);'>+" . (count($eventos_por_fecha[$fecha]) - 3) . " más</div>";
                            }
                        }
                        
                        echo '</div>';
                    }
                    
                    // Días del mes siguiente
                    $total_celdas = $dia_semana_inicio + $dias_en_mes - 1;
                    $celdas_restantes = (7 - ($total_celdas % 7)) % 7;
                    for ($i = 0; $i < $celdas_restantes; $i++) {
                        echo '<div class="dia-celda otro-mes"></div>';
                    }
                    ?>
                </div>
                
                <?php if (!empty($eventos)): ?>
                    <div class="lista-eventos">
                        <h2 style="margin-bottom: var(--spacing-lg);">Próximos eventos</h2>
                        <?php foreach (array_slice($eventos, 0, 10) as $evento): ?>
                            <div class="evento-card" style="border-left-color: <?= htmlspecialchars($evento['color']) ?>;">
                                <div class="evento-info">
                                    <h3><?= htmlspecialchars($evento['titulo']) ?></h3>
                                    <?php if ($evento['descripcion']): ?>
                                        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-sm);">
                                            <?= nl2br(htmlspecialchars($evento['descripcion'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="evento-meta">
                                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($evento['fecha_inicio'])) ?></span>
                                        <?php if ($evento['hora_inicio']): ?>
                                            <span><i class="fas fa-clock"></i> <?= substr($evento['hora_inicio'], 0, 5) ?></span>
                                        <?php endif; ?>
                                        <?php if ($evento['ubicacion']): ?>
                                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($evento['ubicacion']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: var(--spacing-xs);">
                                    <button type="button" onclick="mostrarArchivos('evento', <?= $evento['id'] ?>, event)" class="btn btn-ghost btn-icon btn-sm" title="Archivos" id="btn-archivos-evento-<?= $evento['id'] ?>" style="position: relative;">
                                        <i class="fas fa-paperclip"></i>
                                        <span id="badge-archivos-evento-<?= $evento['id'] ?>" style="position: absolute; top: -8px; right: -8px; background: var(--danger); color: white; border-radius: 50%; width: 18px; height: 18px; display: none; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;"></span>
                                    </button>
                                    <button onclick="editarEvento(<?= $evento['id'] ?>)" class="btn btn-ghost btn-icon btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $evento['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-danger btn-icon btn-sm" onclick="return confirm('¿Eliminar este evento?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Evento -->
    <div id="modalEvento" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-calendar-plus"></i>
                <span id="modal-title">Nuevo Evento</span>
            </h2>
            <form method="POST" class="form" id="formEvento">
                <input type="hidden" name="action" id="form-action" value="crear">
                <input type="hidden" name="id" id="evento-id">
                <input type="hidden" name="color" id="color_seleccionado" value="#a8dadc">
                <input type="hidden" name="mes" value="<?= $mes ?>">
                <input type="hidden" name="anio" value="<?= $anio ?>">
                
                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="3"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha inicio *</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">Fecha fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="todo_el_dia" name="todo_el_dia" onchange="toggleHoras()">
                        Todo el día
                    </label>
                </div>
                
                <div id="horas-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div class="form-group">
                        <label for="hora_inicio">Hora inicio</label>
                        <input type="time" id="hora_inicio" name="hora_inicio">
                    </div>
                    
                    <div class="form-group">
                        <label for="hora_fin">Hora fin</label>
                        <input type="time" id="hora_fin" name="hora_fin">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ubicacion">Ubicación</label>
                    <input type="text" id="ubicacion" name="ubicacion" placeholder="Lugar del evento">
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
                        Guardar Evento
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
