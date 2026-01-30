<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';
$error = '';

// Crear automatización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_auto') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $disparador = $_POST['disparador'] ?? '';
    $condiciones = $_POST['condiciones'] ?? '[]';
    $acciones = $_POST['acciones'] ?? '[]';
    
    if (!empty($nombre) && !empty($disparador) && !empty($acciones)) {
        $stmt = $pdo->prepare('INSERT INTO automatizaciones (usuario_id, nombre, descripcion, disparador, condiciones, acciones) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $nombre, $descripcion, $disparador, $condiciones, $acciones]);
        $mensaje = 'Automatización creada correctamente';
    } else {
        $error = 'Faltan campos obligatorios';
    }
}

// Obtener automatizaciones
$stmt = $pdo->prepare('SELECT * FROM automatizaciones WHERE usuario_id = ? ORDER BY fecha_creacion DESC');
$stmt->execute([$usuario_id]);
$automatizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener eventos disponibles
$stmt = $pdo->query('SELECT * FROM eventos_disponibles WHERE activo = 1 ORDER BY categoria, nombre');
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatizaciones - PIM</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        .auto-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: white;
        }
        .auto-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .condicion-item, .accion-item {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid #007bff;
        }
        .accion-item {
            border-left-color: #28a745;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">⚡ Automatizaciones</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearAutoModal">
                        + Nueva Automatización
                    </button>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-success alert-dismissible">
                        <?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <p class="text-muted mb-4">
                    Las automatizaciones ejecutan acciones cuando se cumplen condiciones específicas.
                    <a href="../../docs/AUTOMATIZACIONES.md" target="_blank">Ver documentación</a>
                </p>

                <!-- Lista de automatizaciones -->
                <?php if (empty($automatizaciones)): ?>
                    <div class="alert alert-info">
                        <h5>No tienes automatizaciones configuradas</h5>
                        <p>Ejemplos: Etiquetar automáticamente notas urgentes, enviar notificaciones cuando se completan tareas, etc.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearAutoModal">
                            Crear mi primera automatización
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($automatizaciones as $auto): ?>
                        <?php
                            $condiciones = json_decode($auto['condiciones'], true) ?: [];
                            $acciones = json_decode($auto['acciones'], true) ?: [];
                        ?>
                        <div class="auto-card <?= $auto['activo'] ? '' : 'inactive' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <?= htmlspecialchars($auto['nombre']) ?>
                                        <?php if (!$auto['activo']): ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($auto['descripcion']) ?></p>
                                    <small class="text-muted">
                                        <strong>Disparador:</strong> <?= htmlspecialchars($auto['disparador']) ?> • 
                                        <strong>Ejecuciones:</strong> <?= $auto['total_ejecuciones'] ?>
                                    </small>
                                </div>
                                <div class="btn-group">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_auto">
                                        <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $auto['activo'] ? '❚❚' : '▶' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <?php if (!empty($condiciones)): ?>
                                <div class="mb-2">
                                    <strong>🎯 Condiciones:</strong>
                                    <?php foreach ($condiciones as $cond): ?>
                                        <div class="condicion-item">
                                            <code><?= htmlspecialchars($cond['campo']) ?></code>
                                            <span class="badge bg-info"><?= htmlspecialchars($cond['operador']) ?></span>
                                            <code><?= htmlspecialchars($cond['valor']) ?></code>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div>
                                <strong>⚙️ Acciones:</strong>
                                <?php foreach ($acciones as $accion): ?>
                                    <div class="accion-item">
                                        <span class="badge bg-success"><?= htmlspecialchars($accion['tipo']) ?></span>
                                        <?php if ($accion['tipo'] === 'notificacion'): ?>
                                            <?= htmlspecialchars($accion['titulo']) ?>
                                        <?php elseif ($accion['tipo'] === 'webhook'): ?>
                                            <?= htmlspecialchars($accion['url']) ?>
                                        <?php elseif ($accion['tipo'] === 'email'): ?>
                                            a <?= htmlspecialchars($accion['destinatario']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal Crear Automatización -->
    <div class="modal fade" id="crearAutoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="post" id="formAutomatizacion">
                    <input type="hidden" name="action" value="crear_auto">
                    <input type="hidden" name="condiciones" id="condicionesJSON">
                    <input type="hidden" name="acciones" id="accionesJSON">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva Automatización</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Auto-etiquetar notas urgentes">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Disparador</label>
                            <select name="disparador" class="form-select" required>
                                <?php foreach ($eventos as $ev): ?>
                                    <option value="<?= htmlspecialchars($ev['codigo']) ?>">
                                        <?= htmlspecialchars($ev['nombre']) ?> (<?= $ev['categoria'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">🎯 Condiciones (todas deben cumplirse)</label>
                            <div id="condicionesList"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarCondicion()">+ Añadir condición</button>
                        </div>

                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">⚙️ Acciones (se ejecutan en orden)</label>
                            <div id="accionesList"></div>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="agregarAccion()">+ Añadir acción</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Automatización</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        let condiciones = [];
        let acciones = [];

        function agregarCondicion() {
            const id = Date.now();
            condiciones.push({id, campo: '', operador: 'contiene', valor: ''});
            renderCondiciones();
        }

        function agregarAccion() {
            const id = Date.now();
            acciones.push({id, tipo: 'notificacion', titulo: '', mensaje: ''});
            renderAcciones();
        }

        function eliminarCondicion(id) {
            condiciones = condiciones.filter(c => c.id !== id);
            renderCondiciones();
        }

        function eliminarAccion(id) {
            acciones = acciones.filter(a => a.id !== id);
            renderAcciones();
        }

        function renderCondiciones() {
            const html = condiciones.map(c => `
                <div class="condicion-item mb-2">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm" placeholder="Campo" 
                                   value="${c.campo}" onchange="condiciones.find(x=>x.id===${c.id}).campo=this.value">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" onchange="condiciones.find(x=>x.id===${c.id}).operador=this.value">
                                <option value="igual" ${c.operador==='igual'?'selected':''}>Es igual a</option>
                                <option value="diferente" ${c.operador==='diferente'?'selected':''}>Es diferente de</option>
                                <option value="contiene" ${c.operador==='contiene'?'selected':''}>Contiene</option>
                                <option value="no_contiene" ${c.operador==='no_contiene'?'selected':''}>No contiene</option>
                                <option value="mayor" ${c.operador==='mayor'?'selected':''}>Mayor que</option>
                                <option value="menor" ${c.operador==='menor'?'selected':''}>Menor que</option>
                                <option value="vacio" ${c.operador==='vacio'?'selected':''}>Está vacío</option>
                                <option value="no_vacio" ${c.operador==='no_vacio'?'selected':''}>No está vacío</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="text" class="form-control form-control-sm" placeholder="Valor" 
                                   value="${c.valor}" onchange="condiciones.find(x=>x.id===${c.id}).valor=this.value">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-danger" onclick="eliminarCondicion(${c.id})">×</button>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('condicionesList').innerHTML = html;
        }

        function renderAcciones() {
            const html = acciones.map(a => `
                <div class="accion-item mb-2">
                    <div class="row">
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" onchange="acciones.find(x=>x.id===${a.id}).tipo=this.value; renderAcciones()">
                                <option value="notificacion" ${a.tipo==='notificacion'?'selected':''}>Notificación</option>
                                <option value="webhook" ${a.tipo==='webhook'?'selected':''}>Webhook</option>
                                <option value="email" ${a.tipo==='email'?'selected':''}>Email</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            ${a.tipo === 'notificacion' ? `
                                <input type="text" class="form-control form-control-sm" placeholder="Título" 
                                       value="${a.titulo||''}" onchange="acciones.find(x=>x.id===${a.id}).titulo=this.value">
                            ` : a.tipo === 'webhook' ? `
                                <input type="url" class="form-control form-control-sm" placeholder="URL" 
                                       value="${a.url||''}" onchange="acciones.find(x=>x.id===${a.id}).url=this.value">
                            ` : `
                                <input type="email" class="form-control form-control-sm" placeholder="Destinatario" 
                                       value="${a.destinatario||''}" onchange="acciones.find(x=>x.id===${a.id}).destinatario=this.value">
                            `}
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-danger" onclick="eliminarAccion(${a.id})">×</button>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('accionesList').innerHTML = html;
        }

        document.getElementById('formAutomatizacion').addEventListener('submit', function(e) {
            // Limpiar el campo id temporal antes de guardar
            const condicionesLimpias = condiciones.map(({id, ...rest}) => rest);
            const accionesLimpias = acciones.map(({id, ...rest}) => rest);
            
            document.getElementById('condicionesJSON').value = JSON.stringify(condicionesLimpias);
            document.getElementById('accionesJSON').value = JSON.stringify(accionesLimpias);
        });
    </script>
</body>
</html>
