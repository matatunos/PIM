<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = '';
$error = '';

// Crear webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_webhook') {
    $nombre = trim($_POST['nombre'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $evento = $_POST['evento'] ?? '';
    $metodo = $_POST['metodo'] ?? 'POST';
    $headers = trim($_POST['headers'] ?? '');
    $secret = trim($_POST['secret'] ?? '');
    
    if (!empty($nombre) && !empty($url) && !empty($evento)) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'URL inválida';
        } else {
            $stmt = $pdo->prepare('INSERT INTO webhooks (usuario_id, nombre, url, evento, metodo, headers, secret) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$usuario_id, $nombre, $url, $evento, $metodo, $headers, $secret]);
            $mensaje = 'Webhook creado correctamente';
        }
    } else {
        $error = 'Faltan campos obligatorios';
    }
}

// Eliminar webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_webhook') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare('DELETE FROM webhooks WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    $mensaje = 'Webhook eliminado';
}

// Activar/Desactivar webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_webhook') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare('UPDATE webhooks SET activo = NOT activo WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario_id]);
    $mensaje = 'Estado actualizado';
}

// Obtener webhooks
$stmt = $pdo->prepare('SELECT * FROM webhooks WHERE usuario_id = ? ORDER BY fecha_creacion DESC');
$stmt->execute([$usuario_id]);
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener eventos disponibles
$stmt = $pdo->query('SELECT * FROM eventos_disponibles WHERE activo = 1 ORDER BY categoria, nombre');
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$eventos_por_categoria = [];
foreach ($eventos as $evento) {
    $eventos_por_categoria[$evento['categoria']][] = $evento;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooks - PIM</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <main class="px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">🔗 Webhooks</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearWebhookModal">
                        + Nuevo Webhook
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
                    Los webhooks te permiten recibir notificaciones HTTP cuando ocurren eventos en PIM.
                    <a href="../../docs/viewer.php?doc=WEBHOOKS_AUTOMATIZACIONES.md" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-book"></i> Ver documentación</a>
                </p>

                <!-- Lista de webhooks -->
                <?php if (empty($webhooks)): ?>
                    <div class="alert alert-info">
                        <h5>No tienes webhooks configurados</h5>
                        <p>Los webhooks te permiten integrar PIM con servicios externos como Slack, Discord, Zapier, etc.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearWebhookModal">
                            Crear mi primer webhook
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($webhooks as $webhook): ?>
                        <div class="webhook-card <?= $webhook['activo'] ? '' : 'inactive' ?>">
                            <div class="webhook-header">
                                <div>
                                    <h5 class="mb-1">
                                        <?= htmlspecialchars($webhook['nombre']) ?>
                                        <?php if (!$webhook['activo']): ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </h5>
                                    <span class="badge badge-evento bg-primary"><?= htmlspecialchars($webhook['evento']) ?></span>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($webhook['metodo']) ?></span>
                                </div>
                                <div class="btn-group">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_webhook">
                                        <input type="hidden" name="id" value="<?= $webhook['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $webhook['activo'] ? '❚❚ Pausar' : '▶ Activar' ?>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmarEliminar(<?= $webhook['id'] ?>)">
                                        🗑️ Eliminar
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <code><?= htmlspecialchars($webhook['url']) ?></code>
                            </div>
                            
                            <div class="webhook-stats">
                                <span><strong>Ejecuciones:</strong> <?= $webhook['total_ejecuciones'] ?></span>
                                <span><strong>Errores:</strong> <?= $webhook['total_errores'] ?></span>
                                <?php if ($webhook['ultima_ejecucion']): ?>
                                    <span><strong>Última:</strong> <?= date('d/m/Y H:i', strtotime($webhook['ultima_ejecucion'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal Crear Webhook -->
    <div class="modal fade" id="crearWebhookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="crear_webhook">
                    <div class="modal-header">
                        <h5 class="modal-title">Nuevo Webhook</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Notificar Slack">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">URL Destino</label>
                            <input type="url" name="url" class="form-control" required placeholder="https://hooks.slack.com/services/...">
                            <small class="form-text text-muted">URL que recibirá el POST con los datos</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Evento</label>
                                <select name="evento" class="form-select" required>
                                    <?php foreach ($eventos_por_categoria as $categoria => $evs): ?>
                                        <optgroup label="<?= ucfirst($categoria) ?>">
                                            <?php foreach ($evs as $ev): ?>
                                                <option value="<?= htmlspecialchars($ev['codigo']) ?>">
                                                    <?= htmlspecialchars($ev['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Método HTTP</label>
                                <select name="metodo" class="form-select">
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                    <option value="PUT">PUT</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Secret (opcional)</label>
                            <input type="text" name="secret" class="form-control" placeholder="Para firmar requests con HMAC">
                            <small class="form-text text-muted">Se enviará header X-PIM-Signature con hash HMAC-SHA256</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Headers personalizados (opcional)</label>
                            <textarea name="headers" class="form-control" rows="3" placeholder='{"Authorization": "Bearer token123"}'></textarea>
                            <small class="form-text text-muted">Formato JSON</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Webhook</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form hidden para eliminar -->
    <form id="formEliminar" method="post" class="d-none">
        <input type="hidden" name="action" value="eliminar_webhook">
        <input type="hidden" name="id" id="eliminarId">
    </form>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarEliminar(id) {
            if (confirm('¿Eliminar este webhook? Esta acción no se puede deshacer.')) {
                document.getElementById('eliminarId').value = id;
                document.getElementById('formEliminar').submit();
            }
        }
    </script>
</body>
</html>
