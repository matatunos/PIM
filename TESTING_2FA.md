# Instrucciones de Prueba para 2FA

## Problema 1: Iconos No Visible

**¿Dónde verificar?**
1. Ve a: `/app/perfil/2fa.php`
2. Busca los botones - deberían mostrar iconos (escudo, sincronización, X, etc.)

**¿Qué debe verse?**
- Botones con iconos de Font Awesome
- Título con icono de escudo
- Alertas con iconos de check o exclamación
- Modales con iconos de información

**¿Cómo se solucionó?**
- Font Awesome CDN fue agregado en línea 107 de 2fa.php
- Los iconos usan la clase `fas fa-<nombre>` (Font Awesome Solid)
- CDN URL: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/all.min.css`

**Si los iconos siguen sin verse:**
1. Abre Developer Tools (F12)
2. Ve a la pestaña Network
3. Busca "all.min.css"
4. Si tiene status 404 o error, el CDN no se cargó
5. Si está OK (200), entonces Font Awesome está cargado pero CSS podría estar ocultando los iconos

---

## Problema 2: TOTP Validation Failing

**¿Dónde probar?**
1. Ve a `/app/perfil/2fa.php?paso=configurar`
2. Escanea el código QR con tu app de autenticador (Google Authenticator, Microsoft Authenticator, etc.)
3. Ingresa el código de 6 dígitos que muestra tu app

**¿Qué cambió?**
1. El validador ahora acepta códigos de ±2 períodos (120 segundos en total)
2. Mensaje de error mejorado para mostrar el código esperado
3. El código esperado se muestra cuando hay error

**Pasos para debuggear:**

### Paso 1: Sincronización de Hora
Tu teléfono y el servidor DEBEN tener la misma hora (aproximadamente).

**Cómo verificar:**
```bash
# En el servidor:
date +%s  # Mostrará timestamp Unix actual

# En tu teléfono:
- Android: Configuración > Sistema > Fecha y Hora > Sincronizar automáticamente
- iOS: Configuración > General > Fecha y Hora > Hora automática
```

### Paso 2: Usar la Página de Validación

Ve a `/validate-totp.php` (esta página ya está autenticada)

**Qué verás:**
- Hora actual del servidor
- Código TOTP actual válido
- Lista de TODOS los códigos válidos en la ventana de ±2 períodos
- Campo para ingresar y validar códigos

**Qué hacer:**
1. Abre tu app de autenticador
2. Mira el código que muestra (debería coincidir con "Código TOTP Actual")
3. Si coincide, ingresa ese código en `/validate-totp.php`
4. Si valida exitosamente, el problema es con el formulario 2fa.php
5. Si no valida, hay un problema de sincronización de hora

### Paso 3: Si Valida en validate-totp.php pero no en 2fa.php

**Posibles causas:**
1. El secreto almacenado en sesión es diferente al del QR escaneado
2. El formulario POST está modificando el código de alguna manera
3. Problema con cómo se decodifica el Base32

**Cómo debuggear:**
1. Abre la consola del navegador (F12 > Console)
2. En el formulario de 2FA, antes de enviar, abre Developer Tools
3. Ve a la pestaña Network
4. Envía el formulario y busca la solicitud POST a 2fa.php
5. Mira el body de la solicitud para ver exactamente qué se envió

---

## Código de Referencia

### TOTP Verification (totp.php línea 104-122)
```php
public static function verifyCode($secret, $code, $discrepancy = 2) {
    // Asegurar que el código es string y tiene longitud correcta
    $code = (string) $code;
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    
    $currentTimeSlice = floor(time() / 30);
    
    // Valida ±2 períodos (120 segundos)
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
        if ($calculatedCode === $code) {
            return true;
        }
    }
    
    return false;
}
```

### Generación de Código
```php
public static function getCode($secret, $timeSlice = null) {
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    
    // HMAC-SHA1 con período de 30 segundos
    $decoded = self::base32Decode($secret);
    $hmac = hash_hmac('sha1', pack('N2', 0, $timeSlice), $decoded, true);
    
    $offset = ord($hmac[19]) & 0xf;
    $code = (ord($hmac[$offset]) & 0x7f) << 24 |
            (ord($hmac[$offset + 1]) & 0xff) << 16 |
            (ord($hmac[$offset + 2]) & 0xff) << 8 |
            (ord($hmac[$offset + 3]) & 0xff);
    
    return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
}
```

---

## Archivos Modificados

1. **2fa-fix.css**: Estilos completos para modales, QR, botones
2. **2fa.php**: 
   - Línea 104-107: Font Awesome CDN
   - Línea 31-33: Error message mejorado
3. **totp.php**: 
   - Línea 108: `$discrepancy = 2` (era 1)
   - Línea 110-112: Type casting mejorado
4. **Nuevos archivos de prueba**:
   - `/validate-totp.php`: Validador TOTP con interfaz clara
   - `/test-totp-advanced.php`: Información de debugging avanzada

---

## Resumen de Estado

✅ **COMPLETADO:**
- CSS para QR code y modales
- Estilos responsivos
- Font Awesome CDN integrado
- Botones con iconos
- Error messages con información útil
- Validación TOTP con ventana expandida
- Páginas de debugging disponibles

⚠️ **EN INVESTIGACIÓN:**
- TOTP validation sigue fallando cuando usuario ingresa código
- Necesita verificación de sincronización de hora
- Necesita prueba con /validate-totp.php

---

## Próximos Pasos

1. **Usuario debe:**
   - Verificar que su teléfono tiene la hora correcta
   - Probar en `/validate-totp.php` con el código que muestra su app
   - Comparar código esperado con código en la app

2. **Si valida en validate-totp.php:**
   - El problema está en cómo 2fa.php procesa el código
   - Revisar console del navegador (F12)
   - Revisar Network tab para ver POST body

3. **Si no valida en validate-totp.php:**
   - El problema es sincronización de hora
   - User debe ajustar reloj de su teléfono
   - O el QR se escaneó incorrectamente (probar otra vez)
