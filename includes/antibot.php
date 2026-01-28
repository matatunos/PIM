<?php
/**
 * Funciones Anti-Bot y Rate Limiting
 * Incluye: Honeypot, Rate Limiting, reCAPTCHA v3
 */

/**
 * Obtener IP real del cliente
 */
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * Verificar honeypot (campo oculto que solo bots llenan)
 */
function validateHoneypot() {
    // El campo website debe estar vacío (para humanos)
    if (!empty($_POST['website'] ?? '')) {
        return false; // Es un bot
    }
    return true;
}

/**
 * Registrar intento de login/registro
 */
function logAttempt($type = 'login', $pdo = null) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $ip = getClientIP();
        $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, tipo) VALUES (?, ?)');
        $stmt->execute([$ip, $type]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Verificar si la IP ha excedido el rate limit
 */
function isRateLimited($type = 'register', $pdo = null) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Obtener configuración
        $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
        $stmt->execute(['antibot_enabled']);
        $antibot_config = $stmt->fetch();
        
        if (!$antibot_config || $antibot_config['valor'] !== '1') {
            return false; // Anti-bot deshabilitado
        }
        
        $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
        $stmt->execute(['rate_limit_attempts']);
        $max_attempts = (int)($stmt->fetch()['valor'] ?? 5);
        
        $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
        $stmt->execute(['rate_limit_window']);
        $window = (int)($stmt->fetch()['valor'] ?? 3600);
        
        // Verificar intentos en el último período
        $ip = getClientIP();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE ip_address = ? AND tipo = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute([$ip, $type, $window]);
        $result = $stmt->fetch();
        
        return $result['count'] >= $max_attempts;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Obtener número de intentos realizados
 */
function getAttemptCount($type = 'register', $pdo = null) {
    global $pdo;
    
    if (!$pdo) return 0;
    
    try {
        $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
        $stmt->execute(['rate_limit_window']);
        $window = (int)($stmt->fetch()['valor'] ?? 3600);
        
        $ip = getClientIP();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE ip_address = ? AND tipo = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute([$ip, $type, $window]);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Validar reCAPTCHA v3
 */
function validateRecaptcha($token, $pdo = null) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Obtener configuración
        $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
        $stmt->execute(['recaptcha_enabled']);
        $recaptcha_config = $stmt->fetch();
        
        if (!$recaptcha_config || $recaptcha_config['valor'] !== '1') {
            return true; // reCAPTCHA deshabilitado, pasar validación
        }
        
        $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
        $stmt->execute(['recaptcha_secret_key']);
        $secret_key = $stmt->fetch()['valor'] ?? '';
        
        if (empty($secret_key)) {
            return true; // Sin clave secreta configurada
        }
        
        if (empty($token)) {
            return false;
        }
        
        // Verificar token con Google
        $response = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded' . "\r\n",
                    'content' => http_build_query([
                        'secret' => $secret_key,
                        'response' => $token
                    ])
                ]
            ])
        );
        
        if (!$response) {
            return false;
        }
        
        $result = json_decode($response, true);
        
        // Retornar verdadero si el score es >= 0.5 (probabilidad de ser humano)
        return isset($result['success']) && $result['success'] && ($result['score'] ?? 0) >= 0.5;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener configuración de anti-bot
 */
function getAntibotConfig($pdo = null) {
    global $pdo;
    
    if (!$pdo) return null;
    
    try {
        $config = [];
        $keys = ['antibot_enabled', 'recaptcha_enabled', 'recaptcha_site_key', 'rate_limit_attempts'];
        
        foreach ($keys as $key) {
            $stmt = $pdo->prepare('SELECT valor FROM config_sitio WHERE clave = ?');
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            $config[$key] = $result['valor'] ?? '';
        }
        
        return $config;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Limpiar intentos antiguos (ejecutar periódicamente)
 */
function cleanupOldAttempts($pdo = null) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Eliminar intentos más antiguos de 24 horas
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        return $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }
}
?>
