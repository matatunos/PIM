#!/usr/bin/env php
<?php
/**
 * Script para crear notas en PIM desde línea de comandos
 * 
 * Uso:
 *   php crear-nota.php -t "Título" -c "Contenido"
 *   php crear-nota.php -t "Título" -f archivo.md
 *   php crear-nota.php -t "Título" -c "Contenido" --color "#e8f5e9" --etiquetas "tag1,tag2"
 *   echo "Contenido" | php crear-nota.php -t "Título" --stdin
 * 
 * Opciones:
 *   -t, --titulo      Título de la nota (requerido)
 *   -c, --contenido   Contenido de la nota
 *   -f, --file        Leer contenido desde archivo
 *   --stdin           Leer contenido desde stdin (pipe)
 *   --color           Color de la nota (hex, default: #fff9e6)
 *   --etiquetas       Etiquetas separadas por coma
 *   -u, --usuario     ID o username del usuario (default: primer usuario)
 *   --fijar           Fijar la nota
 *   -h, --help        Mostrar ayuda
 */

require_once __DIR__ . '/../config/database.php';

// Colores predefinidos
$COLORES = [
    'amarillo' => '#fff9e6',
    'verde'    => '#e8f5e9',
    'azul'     => '#e3f2fd',
    'rojo'     => '#ffebee',
    'morado'   => '#f3e5f5',
    'naranja'  => '#fff3e0',
    'cyan'     => '#e0f7fa',
    'gris'     => '#f5f5f5',
];

function mostrarAyuda() {
    global $COLORES;
    echo <<<HELP
╔══════════════════════════════════════════════════════════════════╗
║                    PIM - Crear Nota CLI                          ║
╠══════════════════════════════════════════════════════════════════╣
║ Uso:                                                             ║
║   php crear-nota.php -t "Título" -c "Contenido"                  ║
║   php crear-nota.php -t "Título" -f archivo.md                   ║
║   php crear-nota.php -t "Título" --stdin < archivo.md            ║
║   echo "texto" | php crear-nota.php -t "Título" --stdin          ║
╠══════════════════════════════════════════════════════════════════╣
║ Opciones:                                                        ║
║   -t, --titulo      Título de la nota (requerido)                ║
║   -c, --contenido   Contenido de la nota                         ║
║   -f, --file        Leer contenido desde archivo                 ║
║   --stdin           Leer contenido desde stdin (pipe)            ║
║   --color           Color (hex o nombre predefinido)             ║
║   --etiquetas       Etiquetas separadas por coma                 ║
║   -u, --usuario     ID o username del usuario                    ║
║   --listar-usuarios Mostrar usuarios disponibles                 ║
║   --fijar           Fijar la nota al inicio                      ║
║   --json            Salida en formato JSON                       ║
║   -q, --quiet       Modo silencioso                              ║
║   -h, --help        Mostrar esta ayuda                           ║
╠══════════════════════════════════════════════════════════════════╣
║ Colores predefinidos:                                            ║

HELP;
    foreach ($COLORES as $nombre => $hex) {
        echo "║   $nombre" . str_repeat(' ', 12 - strlen($nombre)) . "=> $hex" . str_repeat(' ', 36) . "║\n";
    }
    echo <<<HELP
╠══════════════════════════════════════════════════════════════════╣
║ Ejemplos:                                                        ║
║   php crear-nota.php -t "Mi nota" -c "Hola mundo"                ║
║   php crear-nota.php -t "Docs" -f README.md --color verde        ║
║   php crear-nota.php -t "Log" --stdin --etiquetas "sistema,log"  ║
║   php crear-nota.php -t "Nota" -c "Texto" -u nacho               ║
╚══════════════════════════════════════════════════════════════════╝

HELP;
    exit(0);
}

function listarUsuarios() {
    global $pdo;
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║                    Usuarios disponibles                         ║\n";
    echo "╠══════════════════════════════════════════════════════════════════╣\n";
    
    $stmt = $pdo->query('SELECT id, username, nombre_completo, email FROM usuarios WHERE activo = 1 ORDER BY id');
    while ($u = $stmt->fetch()) {
        $nombre = $u['nombre_completo'] ?: $u['email'] ?: '-';
        printf("║  ID: %-4d │ Usuario: %-12s │ %-24s ║\n", 
            $u['id'], 
            substr($u['username'], 0, 12), 
            substr($nombre, 0, 24)
        );
    }
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    echo "\nUso: php crear-nota.php -t \"Título\" -c \"Contenido\" -u <usuario|id>\n";
    exit(0);
}

function error($mensaje) {
    fwrite(STDERR, "❌ Error: $mensaje\n");
    exit(1);
}

function success($mensaje, $quiet = false) {
    if (!$quiet) {
        echo "✅ $mensaje\n";
    }
}

// Parsear argumentos
$opciones = getopt('t:c:f:u:hq', [
    'titulo:',
    'contenido:',
    'file:',
    'stdin',
    'color:',
    'etiquetas:',
    'usuario:',
    'listar-usuarios',
    'fijar',
    'json',
    'quiet',
    'help'
]);

// Listar usuarios
if (isset($opciones['listar-usuarios'])) {
    listarUsuarios();
}

// Mostrar ayuda
if (isset($opciones['h']) || isset($opciones['help']) || $argc === 1) {
    mostrarAyuda();
}

// Obtener título
$titulo = $opciones['t'] ?? $opciones['titulo'] ?? null;
if (empty($titulo)) {
    error("El título es requerido. Usa -t o --titulo");
}

// Obtener contenido
$contenido = null;

// Desde argumento
if (isset($opciones['c']) || isset($opciones['contenido'])) {
    $contenido = $opciones['c'] ?? $opciones['contenido'];
}

// Desde archivo
if (isset($opciones['f']) || isset($opciones['file'])) {
    $archivo = $opciones['f'] ?? $opciones['file'];
    if (!file_exists($archivo)) {
        error("El archivo '$archivo' no existe");
    }
    $contenido = file_get_contents($archivo);
}

// Desde stdin
if (isset($opciones['stdin'])) {
    stream_set_blocking(STDIN, false);
    $contenido = '';
    while ($linea = fgets(STDIN)) {
        $contenido .= $linea;
    }
    if (empty(trim($contenido))) {
        // Si no hay datos en stdin, esperar entrada interactiva
        stream_set_blocking(STDIN, true);
        echo "Escribe el contenido (Ctrl+D para terminar):\n";
        $contenido = stream_get_contents(STDIN);
    }
}

if (empty(trim($contenido))) {
    error("El contenido es requerido. Usa -c, -f o --stdin");
}

// Obtener color
$color = $opciones['color'] ?? '#fff9e6';
if (isset($COLORES[$color])) {
    $color = $COLORES[$color];
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    error("Color inválido: $color. Usa formato hex (#RRGGBB) o nombre predefinido");
}

// Obtener usuario
$usuario_param = $opciones['u'] ?? $opciones['usuario'] ?? null;
$usuario_id = null;

if ($usuario_param) {
    if (is_numeric($usuario_param)) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario_param]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $stmt->execute([$usuario_param]);
    }
    $usuario = $stmt->fetch();
    if (!$usuario) {
        error("Usuario '$usuario_param' no encontrado");
    }
    $usuario_id = $usuario['id'];
} else {
    // Usar primer usuario
    $stmt = $pdo->query('SELECT id FROM usuarios ORDER BY id LIMIT 1');
    $usuario = $stmt->fetch();
    if (!$usuario) {
        error("No hay usuarios en la base de datos");
    }
    $usuario_id = $usuario['id'];
}

// Opciones adicionales
$fijar = isset($opciones['fijar']) ? 1 : 0;
$json_output = isset($opciones['json']);
$quiet = isset($opciones['q']) || isset($opciones['quiet']);

// Insertar nota
try {
    $stmt = $pdo->prepare('INSERT INTO notas (usuario_id, titulo, contenido, color, fijada) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$usuario_id, $titulo, $contenido, $color, $fijar]);
    $nota_id = $pdo->lastInsertId();

    // Procesar etiquetas
    $etiquetas_creadas = [];
    if (!empty($opciones['etiquetas'])) {
        $etiquetas = array_filter(array_map('trim', explode(',', $opciones['etiquetas'])));
        foreach ($etiquetas as $etiqueta) {
            // Buscar si existe
            $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre = ? AND usuario_id = ?');
            $stmt->execute([$etiqueta, $usuario_id]);
            $etiqueta_id = $stmt->fetchColumn();

            // Si no existe, crearla
            if (!$etiqueta_id) {
                $stmt = $pdo->prepare('INSERT INTO etiquetas (usuario_id, nombre, color) VALUES (?, ?, ?)');
                $stmt->execute([$usuario_id, $etiqueta, '#2196f3']);
                $etiqueta_id = $pdo->lastInsertId();
            }

            // Asociar a la nota
            $stmt = $pdo->prepare('INSERT INTO nota_etiqueta (nota_id, etiqueta_id) VALUES (?, ?)');
            $stmt->execute([$nota_id, $etiqueta_id]);
            $etiquetas_creadas[] = $etiqueta;
        }
    }

    // Salida
    if ($json_output) {
        echo json_encode([
            'success' => true,
            'nota_id' => (int)$nota_id,
            'titulo' => $titulo,
            'color' => $color,
            'etiquetas' => $etiquetas_creadas,
            'fijada' => (bool)$fijar,
            'usuario_id' => (int)$usuario_id
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        success("Nota creada con ID: $nota_id", $quiet);
        if (!$quiet && !empty($etiquetas_creadas)) {
            echo "   Etiquetas: " . implode(', ', $etiquetas_creadas) . "\n";
        }
    }

} catch (PDOException $e) {
    if ($json_output) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]) . "\n";
        exit(1);
    }
    error("Error al crear nota: " . $e->getMessage());
}
