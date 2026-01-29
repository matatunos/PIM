<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$usuario_id = $_SESSION['user_id'];
$mensaje = $error = '';

// Determinar el paso actual basado en el POST
$paso = 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['paso'])) {
        $paso = (int)$_POST['paso'];
    }
}

// Si no estamos en POST, intentar mantener el paso desde sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['csv_temp_data'])) {
    $paso = 2;
}

$filas_preview = [];
$mapeo = [];

// PASO 1: Procesar archivo CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    $archivo_temp = $_FILES['archivo']['tmp_name'];
    
    // Leer primeras líneas del CSV para preview
    if (($handle = fopen($archivo_temp, 'r')) !== false) {
        $count = 0;
        while (($fila = fgetcsv($handle, 1000, ',')) !== false && $count < 5) {
            $fila = array_map('trim', $fila);
            $filas_preview[] = $fila;
            $count++;
        }
        fclose($handle);
        
        // Guardar todo el archivo en sesión
        $_SESSION['csv_temp_data'] = [];
        if (($handle = fopen($archivo_temp, 'r')) !== false) {
            while (($fila = fgetcsv($handle, 1000, ',')) !== false) {
                $_SESSION['csv_temp_data'][] = array_map('trim', $fila);
            }
            fclose($handle);
        }
        
        if (count($filas_preview) > 0) {
            $paso = 2; // Avanzar a mapeo
        } else {
            $error = 'El archivo CSV no contiene datos';
        }
    } else {
        $error = 'No se pudo leer el archivo';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso === 2 && isset($_POST['mapeo'])) {
    // PASO 2: Guardar mapeo y avanzar
    $_SESSION['csv_mapeo'] = $_POST['mapeo'];
    $paso = 3;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso === 3 && isset($_POST['confirmar'])) {
    // PASO 3: Importar contactos
    $mapeo = $_SESSION['csv_mapeo'] ?? [];
    $datos = $_SESSION['csv_temp_data'] ?? [];
    
    $importados = 0;
    $errores = 0;
    
    // Saltamos la primera línea si tiene encabezados
    $inicio = isset($_POST['tiene_encabezados']) ? 1 : 0;
    
    for ($i = $inicio; $i < count($datos); $i++) {
        $fila = $datos[$i];
        
        $nombre = '';
        $apellido = '';
        $email = '';
        $telefono = '';
        $telefono_alt = '';
        $empresa = '';
        $cargo = '';
        $direccion = '';
        $ciudad = '';
        $pais = '';
        $notas = '';
        
        // Mapear columnas
        foreach ($mapeo as $idx_columna => $tipo_campo) {
            if (!empty($tipo_campo) && isset($fila[$idx_columna])) {
                $valor = $fila[$idx_columna];
                
                switch ($tipo_campo) {
                    case 'nombre': $nombre = $valor; break;
                    case 'apellido': $apellido = $valor; break;
                    case 'email': $email = $valor; break;
                    case 'telefono': $telefono = $valor; break;
                    case 'telefono_alt': $telefono_alt = $valor; break;
                    case 'empresa': $empresa = $valor; break;
                    case 'cargo': $cargo = $valor; break;
                    case 'direccion': $direccion = $valor; break;
                    case 'ciudad': $ciudad = $valor; break;
                    case 'pais': $pais = $valor; break;
                    case 'notas': $notas = $valor; break;
                }
            }
        }
        
        // Validar que al menos nombre
        if (empty($nombre)) {
            $errores++;
            continue;
        }
        
        // Validar email si existe
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores++;
            continue;
        }
        
        // Verificar duplicado
        if (!empty($email)) {
            $stmt = $pdo->prepare('SELECT id FROM contactos WHERE usuario_id = ? AND email = ? AND borrado_en IS NULL LIMIT 1');
            $stmt->execute([$usuario_id, $email]);
            if ($stmt->fetch()) {
                $errores++;
                continue;
            }
        }
        
        // Insertar
        try {
            $stmt = $pdo->prepare('INSERT INTO contactos (usuario_id, nombre, apellido, email, telefono, telefono_alt, empresa, cargo, direccion, ciudad, pais, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$usuario_id, $nombre, $apellido, $email, $telefono, $telefono_alt, $empresa, $cargo, $direccion, $ciudad, $pais, $notas]);
            $importados++;
        } catch (Exception $e) {
            $errores++;
        }
    }
    
    $mensaje = "Importación completada: $importados contactos importados, $errores errores/duplicados";
    unset($_SESSION['csv_temp_data']);
    unset($_SESSION['csv_mapeo']);
    $paso = 1; // Volver al inicio
}

// Si estamos en paso 2 o 3, recuperar el preview guardado
if (($paso === 2 || $paso === 3) && isset($_SESSION['csv_temp_data'])) {
    $filas_preview = [];
    $count = 0;
    foreach ($_SESSION['csv_temp_data'] as $fila) {
        if ($count < 5) {
            $filas_preview[] = $fila;
            $count++;
        } else {
            break;
        }
    }
}

// Campos disponibles para mapeo
$campos_disponibles = [
    '' => '-- No importar --',
    'nombre' => 'Nombre *',
    'apellido' => 'Apellido',
    'email' => 'Email',
    'telefono' => 'Teléfono',
    'telefono_alt' => 'Teléfono Alternativo',
    'empresa' => 'Empresa',
    'cargo' => 'Cargo',
    'direccion' => 'Dirección',
    'ciudad' => 'Ciudad',
    'pais' => 'País',
    'notas' => 'Notas'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Contactos - PIM</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-file-import"></i> Importar Contactos</h1>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- PASO 1: Cargar archivo -->
                <?php if ($paso === 1): ?>
                <div style="display: flex; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); flex-wrap: wrap;">
                    <button class="btn btn-primary" style="flex: 1; min-width: 200px;" onclick="cambiarTab('csv')">
                        <i class="fas fa-table"></i> Importar CSV
                    </button>
                    <button class="btn btn-secondary" style="flex: 1; min-width: 200px;" onclick="cambiarTab('vcf')">
                        <i class="fas fa-id-card"></i> Importar VCF (iCloud)
                    </button>
                </div>
                
                <!-- CSV -->
                <div id="tab-csv" class="card" style="display: block;">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-upload"></i> Cargar Archivo CSV</h2>
                    </div>
                    <div class="card-body">
                        <p>Soporta archivos CSV de Google Contacts, iPhone u otros gestores de contactos.</p>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="paso" value="1">
                            
                            <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-lg); border: 2px dashed var(--border-color); border-radius: var(--radius-md); text-align: center;">
                                <label style="cursor: pointer;">
                                    <input type="file" name="archivo" accept=".csv" required style="display: none;" onchange="document.getElementById('filename').textContent = this.files[0].name">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 3em; color: var(--primary); margin-bottom: var(--spacing-md); display: block;"></i>
                                    <p style="margin: 0;">Haz clic o arrastra un archivo CSV aquí</p>
                                    <p id="filename" style="margin: var(--spacing-sm) 0 0 0; color: var(--text-secondary); font-size: 0.9em;">Ningún archivo seleccionado</p>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: var(--spacing-md);">
                                <i class="fas fa-arrow-right"></i> Siguiente
                            </button>
                        </form>
                        
                        <div class="card" style="background: #f0f8ff; border-left: 4px solid var(--info);">
                            <div class="card-body">
                                <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> ¿Cómo exportar tus contactos?</h4>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li><strong>Google Contacts:</strong> Contactos > Más > Exportar → Selecciona tus contactos → Exportar como vCard</li>
                                    <li><strong>iPhone/iCloud:</strong> Abre el contacto → Compartir → Guardar como CSV</li>
                                    <li><strong>Outlook:</strong> Archivo → Abrir y exportar → Exportar a archivo → Archivo CSV</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- VCF -->
                <div id="tab-vcf" class="card" style="display: none;">
                    <div class="card-header">
                        <h2 style="margin: 0;"><i class="fas fa-id-card"></i> Cargar Archivo VCF (iCloud)</h2>
                    </div>
                    <div class="card-body">
                        <p>Importa contactos desde archivos VCF (vCard) de iCloud, Google Contacts u otros gestores.</p>
                        
                        <form id="form-vcf" enctype="multipart/form-data">
                            <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-lg); border: 2px dashed var(--border-color); border-radius: var(--radius-md); text-align: center;">
                                <label style="cursor: pointer;">
                                    <input type="file" name="archivo_vcf" accept=".vcf,.vcard" required style="display: none;" onchange="document.getElementById('vcf-filename').textContent = this.files[0].name">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 3em; color: var(--primary); margin-bottom: var(--spacing-md); display: block;"></i>
                                    <p style="margin: 0;">Haz clic o arrastra un archivo VCF aquí</p>
                                    <p id="vcf-filename" style="margin: var(--spacing-sm) 0 0 0; color: var(--text-secondary); font-size: 0.9em;">Ningún archivo seleccionado</p>
                                </label>
                            </div>
                            
                            <button type="button" class="btn btn-primary" style="width: 100%; margin-bottom: var(--spacing-md);" onclick="cargarVCF()">
                                <i class="fas fa-arrow-right"></i> Procesando...
                            </button>
                            <div id="vcf-status" style="text-align: center; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></div>
                        </form>
                        
                        <!-- Preview de VCF -->
                        <div id="vcf-preview" style="display: none; margin-bottom: var(--spacing-lg);">
                            <div class="card" style="background: #f0f8ff; border-left: 4px solid var(--success);">
                                <div class="card-body">
                                    <h4 style="margin-top: 0;"><i class="fas fa-check-circle"></i> Vista previa</h4>
                                    <div id="vcf-preview-content"></div>
                                    
                                    <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-lg);">
                                        <button class="btn btn-success" onclick="importarVCF()">
                                            <i class="fas fa-download"></i> Importar <?= htmlspecialchars($contacto['nombre'] ?? '') ?>
                                        </button>
                                        <button class="btn btn-secondary" onclick="reiniciarVCF()">
                                            <i class="fas fa-redo"></i> Otra archivo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card" style="background: #f0f8ff; border-left: 4px solid var(--info);">
                            <div class="card-body">
                                <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> ¿Cómo exportar desde iCloud?</h4>
                                <ol style="margin: 0; padding-left: 20px;">
                                    <li><strong>En iCloud.com:</strong> Ve a Contactos</li>
                                    <li><strong>Selecciona contactos:</strong> Usa Cmd/Ctrl+A para todos o elige individuales</li>
                                    <li><strong>Descarga:</strong> Haz clic en el ícono de engranaje → Exportar vCard</li>
                                    <li><strong>Carga el archivo:</strong> Sube el .vcf aquí</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                function cambiarTab(tab) {
                    document.getElementById('tab-csv').style.display = tab === 'csv' ? 'block' : 'none';
                    document.getElementById('tab-vcf').style.display = tab === 'vcf' ? 'block' : 'none';
                    
                    // Cambiar botones de pestaña
                    document.querySelectorAll('.card > .card-header').forEach(h => {
                        const btn = h.closest('.card').previousElementSibling;
                        if (btn) btn.classList.toggle('btn-primary', tab === (h.textContent.includes('CSV') ? 'csv' : 'vcf'));
                        if (btn) btn.classList.toggle('btn-secondary', tab !== (h.textContent.includes('CSV') ? 'csv' : 'vcf'));
                    });
                }
                
                function cargarVCF() {
                    const fileInput = document.querySelector('input[name="archivo_vcf"]');
                    const file = fileInput.files[0];
                    
                    if (!file) {
                        alert('Por favor selecciona un archivo VCF');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('archivo_vcf', file);
                    
                    document.getElementById('vcf-status').textContent = 'Procesando...';
                    
                    fetch('/api/procesar-vcf.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            alert('Error: ' + data.error);
                            document.getElementById('vcf-status').textContent = '';
                            return;
                        }
                        
                        // Mostrar preview
                        let previewHTML = '<p><strong>Se encontraron ' + data.total + ' contacto(s)</strong></p>';
                        previewHTML += '<div style="max-height: 300px; overflow-y: auto;">';
                        
                        data.preview.forEach(c => {
                            previewHTML += '<div style="padding: var(--spacing-md); border-bottom: 1px solid var(--border-color);">';
                            previewHTML += '<strong>' + (c.nombre || 'Sin nombre') + '</strong>';
                            if (c.email) previewHTML += ' - ' + c.email;
                            if (c.telefono) previewHTML += ' - ' + c.telefono;
                            previewHTML += '</div>';
                        });
                        
                        previewHTML += '</div>';
                        
                        document.getElementById('vcf-preview-content').innerHTML = previewHTML;
                        document.getElementById('vcf-preview').style.display = 'block';
                        document.getElementById('vcf-status').textContent = '';
                    })
                    .catch(e => {
                        alert('Error al procesar: ' + e.message);
                        document.getElementById('vcf-status').textContent = '';
                    });
                }
                
                function importarVCF() {
                    document.getElementById('vcf-status').textContent = 'Importando...';
                    
                    fetch('/api/procesar-vcf.php', {
                        method: 'POST',
                        body: new URLSearchParams({confirmar_vcf: '1'})
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            alert('Error: ' + data.error);
                            return;
                        }
                        
                        let mensaje = 'Importación completada:\n';
                        mensaje += '✓ Importados: ' + data.importados + '\n';
                        if (data.duplicados) mensaje += '⚠ Duplicados: ' + data.duplicados + '\n';
                        if (data.errores) mensaje += '✗ Errores: ' + data.errores;
                        
                        alert(mensaje);
                        window.location.href = '/app/contactos/index.php';
                    })
                    .catch(e => {
                        alert('Error: ' + e.message);
                    });
                }
                
                function reiniciarVCF() {
                    document.querySelector('input[name="archivo_vcf"]').value = '';
                    document.getElementById('vcf-filename').textContent = 'Ningún archivo seleccionado';
                    document.getElementById('vcf-preview').style.display = 'none';
                    document.getElementById('vcf-status').textContent = '';
                }
                </script>
                <?php endif; ?>
                
                <!-- PASO 2: Mapear columnas -->
                <?php if ($paso === 2 && count($filas_preview) > 0): ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="paso" value="2">
                    
                    <div class="card" style="margin-bottom: var(--spacing-lg);">
                        <div class="card-header">
                            <h2 style="margin: 0;"><i class="fas fa-columns"></i> Paso 2: Mapear Columnas</h2>
                        </div>
                        <div class="card-body">
                            <p>Hemos encontrado <?= count($_SESSION['csv_temp_data']) ?> registros en el archivo.</p>
                            <p>Selecciona a cuál campo corresponde cada columna del archivo.</p>
                            
                            <div style="overflow-x: auto; margin-bottom: var(--spacing-lg);">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                                            <th style="padding: var(--spacing-md); text-align: left;">Columna</th>
                                            <?php for ($i = 0; $i < count($filas_preview[0]); $i++): ?>
                                                <th style="padding: var(--spacing-md); text-align: left;">Columna <?= $i + 1 ?></th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: var(--spacing-md); font-weight: 600;">Mapear a:</td>
                                            <?php for ($i = 0; $i < count($filas_preview[0]); $i++): ?>
                                                <td style="padding: var(--spacing-md);">
                                                    <select name="mapeo[<?= $i ?>]" class="form-control" style="padding: var(--spacing-sm); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                                        <?php foreach ($campos_disponibles as $valor => $label): ?>
                                                            <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php foreach ($filas_preview as $fila): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: var(--spacing-md); font-size: 0.9em; color: var(--text-secondary);">Ej.:</td>
                                                <?php foreach ($fila as $valor): ?>
                                                    <td style="padding: var(--spacing-md); font-size: 0.9em;">
                                                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                                            <?= htmlspecialchars(substr($valor, 0, 30)) ?>
                                                        </code>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div style="display: flex; gap: var(--spacing-md);">
                                <button type="submit" name="action" value="next" class="btn btn-primary">
                                    <i class="fas fa-arrow-right"></i> Siguiente
                                </button>
                                <a href="?paso=1" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Atrás
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- PASO 3: Confirmación -->
                <?php if ($paso === 3): ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="paso" value="3">
                    <input type="hidden" name="confirmar" value="1">
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;"><i class="fas fa-check-circle"></i> Paso 3: Confirmación</h2>
                        </div>
                        <div class="card-body">
                            <div style="background: #f0f8ff; padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                                <h4 style="margin-top: 0;">Resumen de importación:</h4>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>Total de registros a importar: <strong><?= count($_SESSION['csv_temp_data']) - 1 ?></strong></li>
                                    <li>Los contactos duplicados (por email) serán ignorados</li>
                                    <li>Se requiere al menos el nombre de cada contacto</li>
                                </ul>
                            </div>
                            
                            <p style="color: var(--text-secondary); font-size: 0.9em;">
                                <i class="fas fa-info-circle"></i> Verifica que el mapeo sea correcto antes de continuar.
                            </p>
                            
                            <div style="display: flex; gap: var(--spacing-md);">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-download"></i> Importar Contactos
                                </button>
                                <a href="?paso=2" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Atrás
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
