<?php
/**
 * Verificador de notificaciones para cron jobs
 * Ejecutar cada minuto: * * * * * /usr/bin/php /opt/PIM/bin/check-notifications.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    // Obtener todos los usuarios activos
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE activo = 1');
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($usuarios as $usuario_id) {
        // 1. Eventos próximos (dentro de 30 minutos desde ahora)
        $stmt = $pdo->prepare('
            SELECT 
                e.id,
                "evento" as tipo,
                e.titulo,
                CONCAT("El evento ", e.titulo, " comienza en menos de 30 minutos") as descripcion,
                e.fecha_inicio
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
                AND TIMESTAMPDIFF(MINUTE, n.fecha_envio, NOW()) < 60
            )
        ');
        $stmt->execute([$usuario_id]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insertar notificaciones de eventos
        foreach ($eventos as $evento) {
            $stmt = $pdo->prepare('
                INSERT INTO notificaciones 
                (usuario_id, tipo, referencia_id, titulo, descripcion, visto)
                VALUES (?, ?, ?, ?, ?, 0)
            ');
            $stmt->execute([
                $usuario_id,
                $evento['tipo'],
                $evento['id'],
                $evento['titulo'],
                $evento['descripcion']
            ]);
            
            logAction('notificacion', 'crear_evento', 
                "Notificación creada para evento: {$evento['titulo']}", true);
        }
        
        // 2. Tareas vencidas hoy (que aún no se han notificado)
        $stmt = $pdo->prepare('
            SELECT 
                t.id,
                "tarea" as tipo,
                t.titulo,
                CONCAT("Tarea vencida: ", t.titulo) as descripcion
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
        
        // Insertar notificaciones de tareas
        foreach ($tareas as $tarea) {
            $stmt = $pdo->prepare('
                INSERT INTO notificaciones 
                (usuario_id, tipo, referencia_id, titulo, descripcion, visto)
                VALUES (?, ?, ?, ?, ?, 0)
            ');
            $stmt->execute([
                $usuario_id,
                $tarea['tipo'],
                $tarea['id'],
                $tarea['titulo'],
                $tarea['descripcion']
            ]);
            
            logAction('notificacion', 'crear_tarea', 
                "Notificación creada para tarea: {$tarea['titulo']}", true);
        }
    }
    
    // Limpiar notificaciones antiguas (más de 7 días y vistas)
    $stmt = $pdo->prepare('
        DELETE FROM notificaciones
        WHERE visto = 1
        AND DATE_ADD(fecha_envio, INTERVAL 7 DAY) < NOW()
    ');
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        logAction('sistema', 'limpiar_notificaciones', 
            "Se eliminaron {$stmt->rowCount()} notificaciones antiguas", true);
    }
    
    exit(0);
    
} catch (Exception $e) {
    logAction('sistema', 'error_notificaciones', 
        "Error en check-notifications.php: " . $e->getMessage(), false);
    exit(1);
}

/**
 * Función auxiliar para registrar acciones
 */
function logAction($tipo_evento, $accion, $descripcion = '', $exitoso = true) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO logs_acceso 
            (tipo_evento, accion, descripcion, exitoso, fecha_hora)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$tipo_evento, $accion, $descripcion, $exitoso ? 1 : 0]);
    } catch (Exception $e) {
        // Fallar silenciosamente en caso de error en logs
    }
}
?>
