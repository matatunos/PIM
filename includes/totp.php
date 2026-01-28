<?php
/**
 * Librería TOTP (Time-based One-Time Password) para autenticación de dos factores
 */

class TOTP {
    
    /**
     * Genera un secreto aleatorio en Base32
     */
    public static function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Genera códigos de respaldo
     */
    public static function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%08d', random_int(0, 99999999));
        }
        return $codes;
    }
    
    /**
     * Decodifica un secreto Base32
     */
    private static function base32Decode($secret) {
        // Base32 alphabet (RFC 4648)
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        
        // Convertir a mayúsculas y eliminar padding
        $secret = strtoupper(str_replace('=', '', $secret));
        
        $binaryString = '';
        
        foreach (str_split($secret) as $char) {
            $pos = strpos($base32chars, $char);
            if ($pos === false) {
                return false;
            }
            // Convertir cada carácter a 5 bits
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        
        // Convertir bits de vuelta a bytes, eliminando bits de relleno
        $bytes = '';
        for ($i = 0; $i < strlen($binaryString) - 4; $i += 8) {
            $byte = substr($binaryString, $i, 8);
            $bytes .= chr(bindec($byte));
        }
        
        return $bytes;
    }
    
    /**
     * Genera el código TOTP actual
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        
        $secretkey = self::base32Decode($secret);
        
        // Pack time into 8-byte big-endian binary string
        // IMPORTANTE: Debe ser exactamente 8 bytes (64 bits)
        $time = pack('N2', 0, $timeSlice);
        
        // Hash it with HMAC-SHA1
        $hm = hash_hmac('SHA1', $time, $secretkey, true);
        
        // Use last nibble of result as index/offset
        $offset = ord(substr($hm, -1)) & 0x0F;
        
        // Grab 4 bytes of the result
        $hashpart = substr($hm, $offset, 4);
        
        // Unpack binary value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        
        // Remove most significant bit (get 31-bit value)
        $value = $value & 0x7FFFFFFF;
        
        // Modulo to get 6 digit code
        $modulo = pow(10, 6);
        
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verifica un código TOTP
     * Verifica el código actual y los ±2 períodos de tiempo (120 segundos de ventana)
     */
    public static function verifyCode($secret, $code, $discrepancy = 2) {
        // Asegurar que el código es string y tiene la longitud correcta
        $code = (string) $code;
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
        
        $currentTimeSlice = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Genera URL para código QR de Google Authenticator
     */
    public static function getQRCodeUrl($username, $secret, $issuer = 'PIM') {
        $url = 'otpauth://totp/' . urlencode($issuer) . ':' . urlencode($username) 
             . '?secret=' . $secret 
             . '&issuer=' . urlencode($issuer);
        
        // Usar qrserver.com (simple y confiable)
        return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($url);
    }
    
    /**
     * Genera URL OTPAuth para configuración manual
     */
    public static function getOTPAuthUrl($username, $secret, $issuer = 'PIM') {
        return 'otpauth://totp/' . urlencode($issuer) . ':' . urlencode($username) 
             . '?secret=' . $secret 
             . '&issuer=' . urlencode($issuer);
    }
}
