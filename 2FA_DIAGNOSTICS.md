# 2FA Setup - Guía de Diagnóstico

## Estado Actual

### ✅ COMPLETADO
- **CSS**: Página completamente estilizada con Flexbox, responsive, modales funcionales
- **Font Awesome**: CDN agregado (iconos deberían verse ahora)
- **Estructura HTML**: Todos los pasos bien organizados
- **Validación TOTP**: Ventana expandida a ±2 períodos (120 segundos)

### ⚠️ PROBLEMA REPORTADO
Usuario: "El código que ingreso dice que es incorrecto"

---

## Solución Paso a Paso

### 1️⃣ Verificar que los iconos se ven

**Abre**: `/app/perfil/2fa.php`

**Deberías ver**:
- Título con icono de escudo 🛡️
- Botón "Habilitar 2FA" con icono
- En alertas: iconos de check ✓ o exclamación ⚠️

**Si no ves iconos**:
- Abre Developer Tools (F12)
- Ve a Network tab
- Busca "all.min.css" (Font Awesome)
- Debería ser Status 200 (OK)
- Si 404: El CDN no se cargó (problema de internet)

---

### 2️⃣ Sincronizar la Hora de Tu Teléfono

**⚠️ ESTE ES EL PROBLEMA MÁS COMÚN**

El código TOTP depende de que tu teléfono y el servidor tengan la MISMA hora (o muy similar).

**Android:**
1. Configuración > Sistema > Hora y Región
2. Activa "Hora automática" y "Zona horaria automática"
3. O ajusta manualmente la hora exacta

**iOS:**
1. Configuración > General > Fecha y Hora
2. Activa "Hora automática"
3. O ajusta manualmente la hora

**Cómo verificar el tiempo del servidor**:
- Abre la consola del navegador (F12 > Console)
- Pega esto: `new Date().toString()`
- Comparalo con la hora de tu teléfono
- Deben ser iguales (o máximo 30 segundos de diferencia)

---

### 3️⃣ Probar con la Página de Validación

Una vez tu teléfono tenga la hora correcta:

**Abre**: `https://tudominio.com/validate-totp.php`

**Qué verás**:
- Hora actual del servidor
- Código TOTP actual (en verde grande)
- Lista de códigos válidos
- Campo para ingresar códigos

**Qué hacer**:
1. Abre tu app de autenticador (Google Authenticator, Microsoft Authenticator, Authy, etc.)
2. Mira el código de 6 dígitos que muestra para "PIM"
3. Compáralo con el "Código TOTP Actual" en la página
4. **Deben ser idénticos**

**Si son idénticos**:
- ✅ Tu teléfono está sincronizado correctamente
- ✅ El QR se escaneó bien
- Ahora intenta el formulario 2FA.php

**Si son DIFERENTES**:
- ❌ Tu teléfono NO está sincronizado
- Sigue los pasos de "Sincronizar la Hora" arriba
- Espera 30 segundos y vuelve a intentar

---

### 4️⃣ Completar Setup en 2FA.php

Una vez que `validate-totp.php` funcione:

1. Abre: `/app/perfil/2fa.php?paso=configurar`
2. Haz click en "Habilitar 2FA"
3. Escanea el código QR con tu app de autenticador
4. Ingresa el código que muestra tu app
5. Haz click en "Verificar y Activar"

**Si falla nuevamente**:
- Abre Developer Tools (F12)
- Copia exactamente el código que ingresaste
- Abre `/debug-post.php`
- Ingresa el mismo código
- Esto mostrará qué está pasando en el servidor

---

## Páginas de Debugging Disponibles

| Página | URL | Propósito |
|--------|-----|----------|
| Validador TOTP | `/validate-totp.php` | Validar códigos en tiempo real |
| Debug POST | `/debug-post.php` | Ver qué se envía al servidor |
| Info Avanzada | `/test-totp-advanced.php` | Información detallada del TOTP |

---

## Cambios Realizados

### 2fa.php
- Línea 104-107: Font Awesome CDN agregado
- Línea 31-33: Mensaje de error mejorado
- Línea 173: Form action preserva GET parameter `?paso=configurar`

### totp.php
- Línea 108: `$discrepancy = 2` (era 1) - ventana expandida
- Línea 110-112: Type casting para código

### 2fa-fix.css
- Estilos completos para modales
- Centrado de QR con Flexbox
- Responsive design

---

## Checklist de Diagnóstico

- [ ] ¿Ves los iconos en la página 2FA?
- [ ] ¿La hora de tu teléfono es la misma que el servidor?
- [ ] ¿El código en validate-totp.php coincide con el de tu app?
- [ ] ¿El botón "Habilitar 2FA" funciona y muestra el QR?
- [ ] ¿El QR se puede escanear?
- [ ] ¿El código que muestra tu app después de escanear es válido?

**Si todo es "Sí"**: El problema está resuelto ✅

**Si alguno es "No"**: Especifica cuál para debugging más detallado.

---

## Código de Validación de Referencia

```php
// Verificación TOTP con ventana ±2 períodos
public static function verifyCode($secret, $code, $discrepancy = 2) {
    $code = (string) $code;
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    
    $currentTimeSlice = floor(time() / 30);
    
    // Valida hasta 120 segundos (4 períodos de 30s)
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
        if ($calculatedCode === $code) {
            return true;
        }
    }
    
    return false;
}
```

El secreto se almacena en Base32, se convierte a binario, y se usa HMAC-SHA1 para generar códigos.

---

## ¿Todavía no funciona?

1. Verifica en `/debug-post.php` que el secret sea válido
2. Compara el secret con el que está en tu autenticador
3. Si son diferentes: Re-escanea el QR
4. Si son iguales pero el código no valida: Problema de hora del teléfono
