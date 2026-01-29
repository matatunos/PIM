<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];

/**
 * Parsea un archivo VCF y extrae los contactos
 * @param string $contenido Contenido del archivo VCF
 * @return array Array de contactos parseados
 */
function parse_vcf($contenido) {
    $contactos = [];
    
    // Dividir por BEGIN:VCARD
    $vcards = preg_split('/BEGIN:VCARD/', $contenido, -1, PREG_SPLIT_NO_EMPTY);
    
    foreach ($vcards as $vcard) {
        // Agregar BEGIN:VCARD de vuelta para parsear
        $vcard = 'BEGIN:VCARD' . $vcard;
        
        $contacto = [
            'nombre' => '',
            'apellido' => '',
            'email' => '',
            'telefono' => '',
            'telefono_alt' => '',
            'empresa' => '',
            'cargo' => '',
            'direccion' => '',
            'ciudad' => '',
            'pais' => '',
            'notas' => ''
        ];
        
        // Extraer líneas
        $lineas = explode("\n", $vcard);
        $linea_anterior = '';
        
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            
            // Manejar continuaciones (líneas que comienzan con espacio)
            if (!empty($linea) && ($linea[0] === ' ' || $linea[0] === "\t")) {
                $linea_anterior .= substr($linea, 1);
                continue;
            } else {
                if (!empty($linea_anterior)) {
                    procesar_linea_vcf($linea_anterior, $contacto);
                }
                $linea_anterior = $linea;
            }
        }
        
        // Procesar última línea
        if (!empty($linea_anterior)) {
            procesar_linea_vcf($linea_anterior, $contacto);
        }
        
        // Si tiene al menos nombre, agregarlo
        if (!empty($contacto['nombre'])) {
            $contactos[] = $contacto;
        }
    }
    
    return $contactos;
}

/**
 * Procesa una línea individual del VCF
 */
function procesar_linea_vcf(&$linea, &$contacto) {
    if (strpos($linea, ':') === false) {
        return;
    }
    
    list($clave, $valor) = explode(':', $linea, 2);
    $valor = unescapar_vcard($valor);
    
    // Extraer el tipo de campo (sin parámetros)
    $tipo_campo = strtoupper(explode(';', $clave)[0]);
    
    switch ($tipo_campo) {
        case 'FN':
            // Full Name - si no tenemos nombre, usar esto
            if (empty($contacto['nombre'])) {
                $contacto['nombre'] = trim($valor);
            }
            break;
            
        case 'N':
            // Nombre estructurado: LastName;FirstName;MiddleName;Prefix;Suffix
            $partes = explode(';', $valor);
            if (isset($partes[1])) {
                $contacto['nombre'] = trim($partes[1]);
            }
            if (isset($partes[0])) {
                $contacto['apellido'] = trim($partes[0]);
            }
            break;
            
        case 'EMAIL':
            if (empty($contacto['email']) && filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                $contacto['email'] = trim($valor);
            }
            break;
            
        case 'TEL':
            // Teléfono - limpiar solo números y +
            $telefono_limpio = preg_replace('/[^0-9+]/', '', $valor);
            
            // Determinar tipo de teléfono
            if (strpos(strtolower($clave), 'mobile') !== false || strpos(strtolower($clave), 'cell') !== false) {
                if (empty($contacto['telefono'])) {
                    $contacto['telefono'] = $telefono_limpio;
                } else if (empty($contacto['telefono_alt'])) {
                    $contacto['telefono_alt'] = $telefono_limpio;
                }
            } else if (strpos(strtolower($clave), 'home') !== false || strpos(strtolower($clave), 'work') !== false) {
                if (empty($contacto['telefono_alt'])) {
                    $contacto['telefono_alt'] = $telefono_limpio;
                } else if (empty($contacto['telefono'])) {
                    $contacto['telefono'] = $telefono_limpio;
                }
            } else {
                if (empty($contacto['telefono'])) {
                    $contacto['telefono'] = $telefono_limpio;
                } else if (empty($contacto['telefono_alt'])) {
                    $contacto['telefono_alt'] = $telefono_limpio;
                }
            }
            break;
            
        case 'ORG':
            // Organización/Empresa
            $contacto['empresa'] = trim($valor);
            break;
            
        case 'TITLE':
            // Cargo
            $contacto['cargo'] = trim($valor);
            break;
            
        case 'ADR':
            // Dirección: PO Box;Extended;Street;Locality;Region;Postal;Country
            $partes = explode(';', $valor);
            if (isset($partes[2]) && !empty(trim($partes[2]))) {
                $contacto['direccion'] = trim($partes[2]);
            }
            if (isset($partes[3]) && !empty(trim($partes[3]))) {
                $contacto['ciudad'] = trim($partes[3]);
            }
            if (isset($partes[6]) && !empty(trim($partes[6]))) {
                $contacto['pais'] = trim($partes[6]);
            }
            break;
            
        case 'NOTE':
            // Notas
            if (empty($contacto['notas'])) {
                $contacto['notas'] = trim($valor);
            } else {
                $contacto['notas'] .= "\n" . trim($valor);
            }
            break;
    }
}

/**
 * Desescapa caracteres especiales de vCard
 */
function unescapar_vcard($texto) {
    // Revertir el escaping de RFC 6868
    $texto = str_replace('\\n', "\n", $texto);
    $texto = str_replace('\\N', "\n", $texto);
    $texto = str_replace('\\,', ',', $texto);
    $texto = str_replace('\\;', ';', $texto);
    $texto = str_replace('\\\\', '\\', $texto);
    return $texto;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_vcf']) && $_FILES['archivo_vcf']['error'] === UPLOAD_ERR_OK) {
    $archivo_temp = $_FILES['archivo_vcf']['tmp_name'];
    
    // Leer archivo
    $contenido = file_get_contents($archivo_temp);
    
    if ($contenido === false) {
        die(json_encode(['error' => 'No se pudo leer el archivo']));
    }
    
    // Parsear VCF
    $contactos_vcf = parse_vcf($contenido);
    
    if (empty($contactos_vcf)) {
        die(json_encode(['error' => 'No se encontraron contactos válidos en el archivo']));
    }
    
    // Guardar en sesión para confirmación
    $_SESSION['vcf_contactos'] = $contactos_vcf;
    
    // Devolver preview
    echo json_encode([
        'success' => true,
        'total' => count($contactos_vcf),
        'preview' => array_slice($contactos_vcf, 0, 5)
    ]);
    exit;
}

// Importar contactos desde sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_vcf'])) {
    $contactos_vcf = $_SESSION['vcf_contactos'] ?? [];
    
    if (empty($contactos_vcf)) {
        die(json_encode(['error' => 'No hay contactos para importar']));
    }
    
    $importados = 0;
    $duplicados = 0;
    $errores = 0;
    
    foreach ($contactos_vcf as $contacto) {
        if (empty($contacto['nombre'])) {
            $errores++;
            continue;
        }
        
        // Validar email si existe
        if (!empty($contacto['email']) && !filter_var($contacto['email'], FILTER_VALIDATE_EMAIL)) {
            $errores++;
            continue;
        }
        
        // Verificar duplicado por email
        if (!empty($contacto['email'])) {
            $stmt = $pdo->prepare('SELECT id FROM contactos WHERE usuario_id = ? AND email = ? AND borrado_en IS NULL LIMIT 1');
            $stmt->execute([$usuario_id, $contacto['email']]);
            if ($stmt->fetch()) {
                $duplicados++;
                continue;
            }
        }
        
        // Insertar contacto
        try {
            $stmt = $pdo->prepare('
                INSERT INTO contactos (usuario_id, nombre, apellido, email, telefono, telefono_alt, empresa, cargo, direccion, ciudad, pais, notas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $usuario_id,
                $contacto['nombre'],
                $contacto['apellido'],
                $contacto['email'],
                $contacto['telefono'],
                $contacto['telefono_alt'],
                $contacto['empresa'],
                $contacto['cargo'],
                $contacto['direccion'],
                $contacto['ciudad'],
                $contacto['pais'],
                $contacto['notas']
            ]);
            $importados++;
        } catch (Exception $e) {
            $errores++;
        }
    }
    
    unset($_SESSION['vcf_contactos']);
    
    echo json_encode([
        'success' => true,
        'importados' => $importados,
        'duplicados' => $duplicados,
        'errores' => $errores
    ]);
    exit;
}

// Por defecto, error
http_response_code(400);
echo json_encode(['error' => 'Solicitud inválida']);
