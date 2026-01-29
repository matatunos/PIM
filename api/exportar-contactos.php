<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];

// Obtener todos los contactos del usuario (sin incluir borrados)
$stmt = $pdo->prepare('
    SELECT id, nombre, apellido, email, telefono, telefono_alt, direccion, ciudad, pais, empresa, cargo
    FROM contactos
    WHERE usuario_id = ? AND borrado_en IS NULL
    ORDER BY nombre, apellido
');
$stmt->execute([$usuario_id]);
$contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar archivo VCF (vCard Format)
$vcf_content = '';

foreach ($contactos as $contacto) {
    // Inicio de vCard
    $vcf_content .= "BEGIN:VCARD\r\n";
    $vcf_content .= "VERSION:3.0\r\n";
    
    // Nombre completo (FN: Full Name)
    $nombre_completo = trim($contacto['nombre'] . ' ' . $contacto['apellido']);
    $vcf_content .= "FN:" . self_escape_vcard($nombre_completo) . "\r\n";
    
    // Nombre estructurado (N: Last Name;First Name)
    $apellido = $contacto['apellido'] ?? '';
    $vcf_content .= "N:" . self_escape_vcard($apellido) . ";" . self_escape_vcard($contacto['nombre']) . ";;;\r\n";
    
    // Teléfono principal
    if (!empty($contacto['telefono'])) {
        $vcf_content .= "TEL;TYPE=MOBILE:" . preg_replace('/[^0-9+]/', '', $contacto['telefono']) . "\r\n";
    }
    
    // Teléfono alternativo
    if (!empty($contacto['telefono_alt'])) {
        $vcf_content .= "TEL;TYPE=HOME:" . preg_replace('/[^0-9+]/', '', $contacto['telefono_alt']) . "\r\n";
    }
    
    // Email
    if (!empty($contacto['email'])) {
        $vcf_content .= "EMAIL;TYPE=INTERNET:" . self_escape_vcard($contacto['email']) . "\r\n";
    }
    
    // Empresa
    if (!empty($contacto['empresa'])) {
        $vcf_content .= "ORG:" . self_escape_vcard($contacto['empresa']) . "\r\n";
    }
    
    // Cargo/Título
    if (!empty($contacto['cargo'])) {
        $vcf_content .= "TITLE:" . self_escape_vcard($contacto['cargo']) . "\r\n";
    }
    
    // Dirección
    if (!empty($contacto['direccion']) || !empty($contacto['ciudad']) || !empty($contacto['pais'])) {
        $adr_parts = array(
            '',  // Post Office Box
            '',  // Extended Address
            trim($contacto['direccion'] ?? ''),  // Street Address
            trim($contacto['ciudad'] ?? ''),     // Locality
            '',  // Region
            '',  // Postal Code
            trim($contacto['pais'] ?? '')        // Country Name
        );
        $vcf_content .= "ADR;TYPE=WORK:" . implode(';', array_map('self_escape_vcard', $adr_parts)) . "\r\n";
    }
    
    // Fin de vCard
    $vcf_content .= "END:VCARD\r\n";
}

// Si no hay contactos, crear un VCF válido pero vacío
if (empty($contactos)) {
    $vcf_content = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Sin contactos\r\nN:;;;;\r\nEND:VCARD\r\n";
}

// Configurar headers para descargar el archivo
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="contactos.vcf"');
header('Content-Length: ' . strlen($vcf_content));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Enviar contenido
echo $vcf_content;
exit;

/**
 * Escapa caracteres especiales en vCard
 */
function self_escape_vcard($text) {
    // Escapar caracteres especiales según RFC 6868
    $text = str_replace('\\', '\\\\', $text);  // Backslash primero
    $text = str_replace(',', '\\,', $text);    // Comas
    $text = str_replace(';', '\\;', $text);    // Punto y coma
    $text = str_replace("\r\n", '\\n', $text); // Saltos de línea
    $text = str_replace("\r", '\\n', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
}
