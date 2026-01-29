<?php
// API de Notificaciones
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

require_once '../config/database.php';
header('Content-Type: application/json');

$usuario_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'obtener';

try {
    if ($action === 'obtener') {
        // Obtener notificaciones pendientes
        
        // 1. Eventos próximos (dentro de 30 minutos)
        $stmt = $pdo->prepare('
            SELECT 
                e.id,
                "evento" as tipo,
                e.titulo,
                CONCAT("El evento ", e.titulo, " comienza en menos de 30 minutos") as descripcion,
                e.fecha_inicio,
                e.recordatorio_minutos
            FROM eventos e
            WHERE e.usuario_id = ?
            AND e.fecha_inicio > NOW()
            AND e.fecha_inicio <= DATE_ADD(NOW(), INTERVAL 30 MINUTE)
            AND NOT EXISTS (
                SELECT 1 FROM notificaciones n 
                WHERE n.usuario_id = e.usuario_id 
                AND n.tipo = "evento" 
                AND n.referencia_id = e.id 
                AND n.visto = 0
                AND DATE(n.fecha_envio) = CURDATE()
            )
        ');
        $stmt->execute([$usuario_id]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Tareas vencidas hoy
        $stmt = $pdo->prepare('
            SELECT 
                t.id,
                "tarea" as tipo,
                t.titulo,
                CONCAT("Tarea vencida: ", t.titulo) as descripcion,
                t.fecha_vencimiento,
                0 as recordatorio_minutos
            FROM tareas t
            WHERE t.usuario_id = ?
            AND t.completada = 0
            AND DATE(t.fecha_vencimiento) <= CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM notificaciones n 
                WHERE n.usuario_id = t.usuario_id 
                AND n.tipo = "tarea" 
                AND n.referencia_id = t.id 
                AND n.visto = 0
                AND DATE(n.fecha_envio) = CURDATE()
            )
        ');
        $stmt->execute([$usuario_id]);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notificaciones = array_merge($eventos, $tareas);
        
        // Guardar notificaciones en la tabla
        foreach ($notificaciones as &$notif) {
            $stmt = $pdo->prepare('
                INSERT INTO notificaciones 
                (usuario_id, tipo, referencia_id, titulo, descripcion, visto)
                VALUES (?, ?, ?, ?, ?, 0)
            ');
            $stmt->execute([
                $usuario_id,
                $notif['tipo'],
                $notif['id'],
                $notif['titulo'],
                $notif['descripcion']
            ]);
            
            $notif['id_notif'] = $pdo->lastInsertId();
        }
        
        http_response_code(200);
        echo json_encode([
            'exito' => true,
            'notificaciones' => $notificaciones,
            'cantidad' => count($notificaciones)
        ]);
        
    } elseif ($action === 'marcar_visto') {
        // Marcar notificación como vista
        $id_notif = $_POST['id'] ?? null;
        
        if (!$id_notif) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de notificación requerido']);
            exit;
        }
        
        $stmt = $pdo->prepare('
            UPDATE notificaciones
            SET visto = 1, leido_en = NOW()
            WHERE id = ? AND usuario_id = ?
        ');
        $stmt->execute([$id_notif, $usuario_id]);
        
        http_response_code(200);
        echo json_encode(['exito' => true]);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en el servidor',
        'mensaje' => $e->getMessage()
    ]);
}
?>
