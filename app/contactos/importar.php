<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo']['tmp_name'];
    if (($handle = fopen($archivo, 'r')) !== false) {
        $primera = true;
        $importados = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if ($primera) { $primera = false; continue; }
            $nombre = $data[0] ?? '';
            $apellido = $data[1] ?? '';
            $email = $data[2] ?? '';
            $telefono = $data[3] ?? '';
            $direccion = $data[4] ?? '';
            $notas = $data[5] ?? '';
            $stmt = $pdo->prepare('INSERT INTO contactos (usuario_id, nombre, apellido, email, telefono, direccion, notas) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], $nombre, $apellido, $email, $telefono, $direccion, $notas]);
            $importados++;
        }
        fclose($handle);
        $mensaje = "Contactos importados: $importados";
    } else {
        $mensaje = 'No se pudo leer el archivo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Contactos</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
<?php include_once '../../includes/navbar.php'; ?>
<div class="container mt-4">
    <h2><i class="fas fa-address-book"></i> Importar Contactos</h2>
    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="archivo" class="form-label">Archivo CSV exportado (Google/iPhone)</label>
            <input type="file" class="form-control" id="archivo" name="archivo" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-file-import"></i> Importar</button>
    </form>
    <p class="mt-3 text-muted">El archivo debe tener columnas: nombre, apellido, email, teléfono, dirección, notas.</p>
</div>
</body>
</html>
