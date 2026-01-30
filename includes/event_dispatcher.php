<?php
/**
 * Motor de eventos y webhooks
 * Dispara webhooks y automatizaciones cuando ocurren eventos
 */

require_once __DIR__ . '/../config/database.php';

class EventDispatcher {
    private $pdo;
    private $webhookExecutor;
    private $automationEngine;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->webhookExecutor = new WebhookExecutor($pdo);
        $this->automationEngine = new AutomationEngine($pdo);
    }
    
    /**
     * Disparar evento
     * @param string $evento Código del evento (ej: 'nota_creada')
     * @param int $usuario_id ID del usuario
     * @param array $payload Datos del evento
     */
    public function dispatch($evento, $usuario_id, $payload = []) {
        // Añadir metadatos
        $payload['_evento'] = $evento;
        $payload['_usuario_id'] = $usuario_id;
        $payload['_timestamp'] = time();
        $payload['_fecha'] = date('Y-m-d H:i:s');
        
        // Ejecutar webhooks en background (async)
        $this->webhookExecutor->execute($evento, $usuario_id, $payload);
        
        // Ejecutar automatizaciones
        $this->automationEngine->process($evento, $usuario_id, $payload);
    }
}

class WebhookExecutor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function execute($evento, $usuario_id, $payload) {
        // Buscar webhooks activos para este evento
        $stmt = $this->pdo->prepare('
            SELECT id, url, metodo, headers, secret 
            FROM webhooks 
            WHERE usuario_id = ? AND evento = ? AND activo = 1
        ');
        $stmt->execute([$usuario_id, $evento]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $payload);
        }
    }
    
    private function sendWebhook($webhook, $payload) {
        $start_time = microtime(true);
        
        try {
            // Preparar headers
            $headers = [
                'Content-Type: application/json',
                'User-Agent: PIM-Webhook/1.0'
            ];
            
            // Añadir headers personalizados
            if (!empty($webhook['headers'])) {
                $custom_headers = json_decode($webhook['headers'], true);
                foreach ($custom_headers as $key => $value) {
                    $headers[] = "$key: $value";
                }
            }
            
            // Firmar request si hay secret
            if (!empty($webhook['secret'])) {
                $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
                $headers[] = 'X-PIM-Signature: ' . $signature;
            }
            
            // Enviar request
            $ch = curl_init($webhook['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $webhook['metodo'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $duration = round((microtime(true) - $start_time) * 1000);
            
            // Guardar log
            $this->logWebhook($webhook['id'], $payload['_evento'], $webhook['url'], 
                             $webhook['metodo'], $headers, $payload, 
                             $http_code, $response, $error, $duration);
            
            // Actualizar estadísticas
            $this->updateStats($webhook['id'], ($http_code >= 200 && $http_code < 300));
            
        } catch (Exception $e) {
            $duration = round((microtime(true) - $start_time) * 1000);
            $this->logWebhook($webhook['id'], $payload['_evento'], $webhook['url'], 
                             $webhook['metodo'], [], $payload, 
                             0, null, $e->getMessage(), $duration);
            $this->updateStats($webhook['id'], false);
        }
    }
    
    private function logWebhook($webhook_id, $evento, $url, $method, $headers, $payload, 
                                $response_code, $response_body, $error, $duration) {
        $stmt = $this->pdo->prepare('
            INSERT INTO webhook_logs 
            (webhook_id, evento, request_url, request_method, request_headers, 
             request_body, response_code, response_body, error, duracion_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $webhook_id,
            $evento,
            $url,
            $method,
            json_encode($headers),
            json_encode($payload),
            $response_code,
            $response_body,
            $error,
            $duration
        ]);
    }
    
    private function updateStats($webhook_id, $success) {
        $error_field = $success ? '' : ', total_errores = total_errores + 1';
        $this->pdo->prepare("
            UPDATE webhooks 
            SET ultima_ejecucion = NOW(), 
                total_ejecuciones = total_ejecuciones + 1
                $error_field
            WHERE id = ?
        ")->execute([$webhook_id]);
    }
}

class AutomationEngine {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function process($disparador, $usuario_id, $payload) {
        // Buscar automatizaciones activas para este disparador
        $stmt = $this->pdo->prepare('
            SELECT id, nombre, condiciones, acciones 
            FROM automatizaciones 
            WHERE usuario_id = ? AND disparador = ? AND activo = 1
        ');
        $stmt->execute([$usuario_id, $disparador]);
        $automatizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($automatizaciones as $auto) {
            $this->executeAutomation($auto, $payload);
        }
    }
    
    private function executeAutomation($auto, $payload) {
        $start_time = microtime(true);
        $entidad_id = $payload['id'] ?? null;
        
        try {
            // Evaluar condiciones
            $condiciones = json_decode($auto['condiciones'], true) ?: [];
            $cumple_condiciones = $this->evaluateConditions($condiciones, $payload);
            
            if (!$cumple_condiciones) {
                $this->logAutomation($auto['id'], $payload['_evento'], $entidad_id, 
                                    false, [], true, null, 0);
                return;
            }
            
            // Ejecutar acciones
            $acciones = json_decode($auto['acciones'], true);
            $resultados = $this->executeActions($acciones, $payload);
            
            $duration = round((microtime(true) - $start_time) * 1000);
            
            // Log exitoso
            $this->logAutomation($auto['id'], $payload['_evento'], $entidad_id, 
                                true, $resultados, true, null, $duration);
            
            // Actualizar estadísticas
            $this->updateStats($auto['id']);
            
        } catch (Exception $e) {
            $duration = round((microtime(true) - $start_time) * 1000);
            $this->logAutomation($auto['id'], $payload['_evento'], $entidad_id, 
                                false, [], false, $e->getMessage(), $duration);
        }
    }
    
    private function evaluateConditions($condiciones, $payload) {
        if (empty($condiciones)) {
            return true; // Sin condiciones = siempre ejecutar
        }
        
        foreach ($condiciones as $condicion) {
            $campo = $condicion['campo'];
            $operador = $condicion['operador'];
            $valor_esperado = $condicion['valor'];
            $valor_actual = $payload[$campo] ?? null;
            
            $cumple = $this->evaluateCondition($valor_actual, $operador, $valor_esperado);
            
            if (!$cumple) {
                return false; // Todas las condiciones deben cumplirse (AND)
            }
        }
        
        return true;
    }
    
    private function evaluateCondition($valor_actual, $operador, $valor_esperado) {
        switch ($operador) {
            case 'igual':
                return $valor_actual == $valor_esperado;
            case 'diferente':
                return $valor_actual != $valor_esperado;
            case 'contiene':
                return stripos($valor_actual, $valor_esperado) !== false;
            case 'no_contiene':
                return stripos($valor_actual, $valor_esperado) === false;
            case 'mayor':
                return $valor_actual > $valor_esperado;
            case 'menor':
                return $valor_actual < $valor_esperado;
            case 'vacio':
                return empty($valor_actual);
            case 'no_vacio':
                return !empty($valor_actual);
            default:
                return false;
        }
    }
    
    private function executeActions($acciones, $payload) {
        $resultados = [];
        
        foreach ($acciones as $accion) {
            $tipo = $accion['tipo'];
            $resultado = ['tipo' => $tipo, 'exito' => false];
            
            try {
                switch ($tipo) {
                    case 'webhook':
                        $resultado['exito'] = $this->actionWebhook($accion, $payload);
                        break;
                    case 'notificacion':
                        $resultado['exito'] = $this->actionNotificacion($accion, $payload);
                        break;
                    case 'email':
                        $resultado['exito'] = $this->actionEmail($accion, $payload);
                        break;
                    case 'modificar':
                        $resultado['exito'] = $this->actionModificar($accion, $payload);
                        break;
                    case 'crear':
                        $resultado['exito'] = $this->actionCrear($accion, $payload);
                        break;
                }
                
                $resultado['mensaje'] = 'Acción ejecutada correctamente';
            } catch (Exception $e) {
                $resultado['error'] = $e->getMessage();
            }
            
            $resultados[] = $resultado;
        }
        
        return $resultados;
    }
    
    private function actionWebhook($accion, $payload) {
        $executor = new WebhookExecutor($this->pdo);
        $webhook = [
            'id' => 0,
            'url' => $accion['url'],
            'metodo' => $accion['metodo'] ?? 'POST',
            'headers' => json_encode($accion['headers'] ?? []),
            'secret' => $accion['secret'] ?? null
        ];
        $executor->sendWebhook($webhook, $payload);
        return true;
    }
    
    private function actionNotificacion($accion, $payload) {
        // Crear notificación en sistema
        $stmt = $this->pdo->prepare('
            INSERT INTO notificaciones (usuario_id, titulo, contenido, tipo, icono)
            VALUES (?, ?, ?, ?, ?)
        ');
        return $stmt->execute([
            $payload['_usuario_id'],
            $accion['titulo'],
            $accion['mensaje'],
            'automatizacion',
            '⚡'
        ]);
    }
    
    private function actionEmail($accion, $payload) {
        // Enviar email
        $to = $accion['destinatario'];
        $subject = $this->replaceVariables($accion['asunto'], $payload);
        $message = $this->replaceVariables($accion['mensaje'], $payload);
        $headers = "From: PIM <noreply@pim.local>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    private function actionModificar($accion, $payload) {
        // Modificar entidad existente
        $tabla = $accion['tabla'];
        $id = $payload['id'];
        $campos = $accion['campos'];
        
        $sets = [];
        $valores = [];
        foreach ($campos as $campo => $valor) {
            $sets[] = "$campo = ?";
            $valores[] = $this->replaceVariables($valor, $payload);
        }
        $valores[] = $id;
        $valores[] = $payload['_usuario_id'];
        
        $sql = "UPDATE $tabla SET " . implode(', ', $sets) . " WHERE id = ? AND usuario_id = ?";
        return $this->pdo->prepare($sql)->execute($valores);
    }
    
    private function actionCrear($accion, $payload) {
        // Crear nueva entidad
        $tabla = $accion['tabla'];
        $campos = $accion['campos'];
        $campos['usuario_id'] = $payload['_usuario_id'];
        
        $columnas = array_keys($campos);
        $valores = array_map(function($v) use ($payload) {
            return $this->replaceVariables($v, $payload);
        }, array_values($campos));
        
        $placeholders = str_repeat('?,', count($campos) - 1) . '?';
        $sql = "INSERT INTO $tabla (" . implode(',', $columnas) . ") VALUES ($placeholders)";
        
        return $this->pdo->prepare($sql)->execute($valores);
    }
    
    private function replaceVariables($texto, $payload) {
        // Reemplazar variables tipo {{variable}}
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($payload) {
            return $payload[$matches[1]] ?? $matches[0];
        }, $texto);
    }
    
    private function logAutomation($auto_id, $disparador, $entidad_id, $condiciones_cumplidas, 
                                    $resultados, $exito, $error, $duration) {
        $stmt = $this->pdo->prepare('
            INSERT INTO automatizacion_logs 
            (automatizacion_id, disparador, entidad_id, condiciones_cumplidas, 
             acciones_ejecutadas, exito, error, duracion_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $auto_id,
            $disparador,
            $entidad_id,
            $condiciones_cumplidas ? 1 : 0,
            json_encode($resultados),
            $exito ? 1 : 0,
            $error,
            $duration
        ]);
    }
    
    private function updateStats($auto_id) {
        $this->pdo->prepare('
            UPDATE automatizaciones 
            SET ultima_ejecucion = NOW(), total_ejecuciones = total_ejecuciones + 1
            WHERE id = ?
        ')->execute([$auto_id]);
    }
}

// Función helper para disparar eventos desde cualquier parte del código
function trigger_event($evento, $usuario_id, $payload = []) {
    global $pdo;
    static $dispatcher = null;
    
    if ($dispatcher === null) {
        $dispatcher = new EventDispatcher($pdo);
    }
    
    $dispatcher->dispatch($evento, $usuario_id, $payload);
}
