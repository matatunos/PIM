<?php
// Configuración de conexión a la base de datos MariaDB

// Cargar variables de entorno desde .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Ignorar comentarios
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'pim_db';
$user = $_ENV['DB_USER'] ?? 'pim_user';
$pass = $_ENV['DB_PASS'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	// En producción, no mostrar detalles del error
	if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
		throw new PDOException($e->getMessage(), (int)$e->getCode());
	} else {
		error_log('Database connection error: ' . $e->getMessage());
		die('Error de conexión a la base de datos. Contacte al administrador.');
	}
}
