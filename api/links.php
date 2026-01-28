<?php
require_once '../config/config.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Headers JSON
    header('Content-Type: application/json');

    // Obtener datos
    $data = json_decode(file_get_contents('php://input'), true);

    // Validar token de sesión o autenticación
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }

    $usuario_id = $_SESSION['user_id'];

    // Crear categoría
    if (isset($data['action']) && $data['action'] === 'crear_categoria') {
        $nombre = trim($data['nombre'] ?? '');
        
        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre de la categoría es requerido']);
            exit;
        }
        
        try {
            // Insertar categoría en la tabla link_categorias
            $stmt = $pdo->prepare('INSERT INTO link_categorias (usuario_id, nombre) VALUES (?, ?)');
            $stmt->execute([$usuario_id, $nombre]);
            
            echo json_encode(['success' => true, 'message' => 'Categoría creada exitosamente']);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                http_response_code(400);
                echo json_encode(['error' => 'Esta categoría ya existe']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear la categoría']);
            }
        }
        exit;
    }

    // Validar datos requeridos
    $titulo = trim($data['titulo'] ?? '');
    $url = trim($data['url'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');
    $icono = trim($data['icono'] ?? 'fa-link');
    $categoria = trim($data['categoria'] ?? 'General');
    $color = $data['color'] ?? '#a8dadc';

    // Validar campos requeridos
    if (empty($titulo)) {
        http_response_code(400);
        echo json_encode(['error' => 'El título es requerido']);
        exit;
    }

    if (empty($url)) {
        http_response_code(400);
        echo json_encode(['error' => 'La URL es requerida']);
        exit;
    }

    // Validar URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'URL inválida']);
        exit;
    }

    try {
        // Insertar link
        $stmt = $pdo->prepare('INSERT INTO links (usuario_id, titulo, url, descripcion, icono, categoria, color) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $titulo, $url, $descripcion, $icono, $categoria, $color]);
        
        $link_id = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => $link_id,
            'message' => 'Link guardado exitosamente'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar el link: ' . $e->getMessage()]);
    }
    exit;
}

// GET: Obtener categorías
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_categories') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }

    $usuario_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare('SELECT DISTINCT categoria FROM links WHERE usuario_id = ? ORDER BY categoria');
        $stmt->execute([$usuario_id]);
        $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['categorias' => $categorias]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener categorías']);
    }
    exit;
}

// Método no permitido
http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['error' => 'Método no permitido']);

// PUT: Actualizar link
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    $usuario_id = $_SESSION['user_id'];
    $link_id = $data['id'] ?? null;
    
    if (!$link_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID del link es requerido']);
        exit;
    }
    
    // Verificar que el link pertenece al usuario
    $stmt = $pdo->prepare('SELECT id FROM links WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$link_id, $usuario_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes permiso para editar este link']);
        exit;
    }
    
    $titulo = trim($data['titulo'] ?? '');
    $url = trim($data['url'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');
    $icono = trim($data['icono'] ?? 'fa-link');
    $categoria = trim($data['categoria'] ?? 'General');
    $color = $data['color'] ?? '#a8dadc';
    
    if (empty($titulo)) {
        http_response_code(400);
        echo json_encode(['error' => 'El título es requerido']);
        exit;
    }
    
    if (empty($url)) {
        http_response_code(400);
        echo json_encode(['error' => 'La URL es requerida']);
        exit;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'URL inválida']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE links SET titulo = ?, url = ?, descripcion = ?, icono = ?, categoria = ?, color = ? WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$titulo, $url, $descripcion, $icono, $categoria, $color, $link_id, $usuario_id]);
        
        echo json_encode(['success' => true, 'message' => 'Link actualizado exitosamente']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar el link']);
    }
    exit;
}

// DELETE: Eliminar link o categoría
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    $usuario_id = $_SESSION['user_id'];
    
    // Eliminar categoría
    if (isset($data['action']) && $data['action'] === 'eliminar_categoria') {
        $nombre = trim($data['nombre'] ?? '');
        
        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre de la categoría es requerido']);
            exit;
        }
        
        try {
            // Mover todos los links de esta categoría a "General"
            $stmt = $pdo->prepare('UPDATE links SET categoria = ? WHERE categoria = ? AND usuario_id = ?');
            $stmt->execute(['General', $nombre, $usuario_id]);
            
            echo json_encode(['success' => true, 'message' => 'Categoría eliminada, links movidos a General']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar la categoría']);
        }
        exit;
    }
    
    // Eliminar link
    $link_id = $data['id'] ?? null;
    
    if (!$link_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID del link es requerido']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('DELETE FROM links WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$link_id, $usuario_id]);
        
        echo json_encode(['success' => true, 'message' => 'Link eliminado exitosamente']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar el link']);
    }
    exit;
}
?>

