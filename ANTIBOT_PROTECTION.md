# Protección Anti-Bot en PIM

## Descripción General

Se han implementado **3 capas de protección** contra bots automatizados en el formulario de registro:

### 1. **Honeypot Field** ✅ Gratis
- Campo invisible para humanos (`display: none`)
- Si el campo se completa → es un bot
- Los bots automáticos típicamente llenan todos los campos
- **Costo**: Nulo

### 2. **Rate Limiting** ✅ Gratis
- Limita intentos por IP en una ventana de tiempo (por defecto 1 hora)
- Máximo de intentos configurable (por defecto 5)
- Registra intentos en tabla `login_attempts`
- Bloquea temporalmente si se excede el límite
- **Costo**: Nulo (almacenado en BD local)

### 3. **Google reCAPTCHA v3** ⚙️ Opcional/Gratis
- Verificación invisible del lado del cliente
- No requiere interacción del usuario
- Análisis de comportamiento por Google
- Configurable: habilitarse/deshabilitarse en admin
- Gratuito hasta 1M requests/mes
- **Costo**: ~$0.50 por cada 1,000 requests adicionales

## Implementación Técnica

### Base de Datos

**Tabla `login_attempts` (nueva)**
```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    tipo VARCHAR(50) DEFAULT 'login',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_timestamp (ip_address, timestamp)
);
```

**Configuraciones en `config_sitio`**
```
antibot_enabled          | boolean | Habilita/deshabilita protección anti-bot
recaptcha_enabled        | boolean | Habilita/deshabilita reCAPTCHA v3
recaptcha_site_key       | string  | Clave pública de reCAPTCHA
recaptcha_secret_key     | string  | Clave secreta de reCAPTCHA
rate_limit_attempts      | integer | Máximo de intentos permitidos
rate_limit_window        | integer | Ventana de tiempo en segundos (por defecto 3600 = 1h)
```

### Archivos Creados/Modificados

**Nuevo: `/includes/antibot.php`**
- Funciones de validación y utilidades:
  - `getClientIP()` - Obtiene IP real del cliente
  - `validateHoneypot()` - Verifica honeypot field
  - `isRateLimited()` - Comprueba límite de intentos
  - `logAttempt()` - Registra intento
  - `validateRecaptcha()` - Valida token de reCAPTCHA
  - `getAntibotConfig()` - Obtiene configuración
  - `cleanupOldAttempts()` - Limpia intentos antiguos

**Modificado: `/app/auth/register.php`**
- Importa `antibot.php`
- Valida honeypot antes de procesar
- Verifica rate limit
- Valida reCAPTCHA si está habilitado
- Registra intentos fallidos
- Script JS para manejar reCAPTCHA v3

**Modificado: `/app/admin/configuracion.php`**
- Nueva sección "Protección Anti-Bot"
- Toggle para habilitar/deshabilitar anti-bot
- Configuración de límite de intentos
- Sección "reCAPTCHA v3"
- Campos para introducir claves de Google
- Link a Google reCAPTCHA Admin Console

### Flujo de Validación

```
POST /app/auth/register.php
    ↓
1. Verificar honeypot (campo invisible)
   → Si está lleno → rechazar (es un bot)
   ↓
2. Verificar rate limit
   → Si IP excedió intentos → rechazar
   ↓
3. Validar campos del formulario
   → Username, email, contraseña, etc.
   ↓
4. Validar reCAPTCHA (si está habilitado)
   → Verificar token con Google
   ↓
5. Verificar usuario/email no exista
   ↓
6. Crear cuenta → Éxito ✅
```

## Configuración Requerida

### Para Habilitar reCAPTCHA v3

1. **Ir a**: https://www.google.com/recaptcha/admin
2. **Crear nuevo sitio**:
   - Nombre: "PIM"
   - Tipo: reCAPTCHA v3
   - Dominio: ejemplo.com
3. **Copiar claves**:
   - Site Key (pública)
   - Secret Key (secreta)
4. **En PIM Admin**:
   - Ir a Administración → Configuración
   - Habilitar "Google reCAPTCHA v3"
   - Pegar claves
   - Guardar cambios

### Configuración por Defecto

```
antibot_enabled      = 1 (habilitado)
recaptcha_enabled    = 0 (deshabilitado - requiere claves)
rate_limit_attempts  = 5 intentos por hora
rate_limit_window    = 3600 segundos (1 hora)
```

## Estadísticas y Seguridad

### Detecta

- ✅ Scripts de automatización
- ✅ Herramientas de scraping
- ✅ Brute force attacks
- ✅ Intentos masivos de spam
- ✅ Comportamiento anómalo (reCAPTCHA v3)

### No Requiere

- ❌ Intervención del usuario (excepto si reCAPTCHA falla)
- ❌ Compra de licencias
- ❌ Servidor adicional

### Costo

| Componente | Costo |
|-----------|-------|
| Honeypot | Gratis |
| Rate Limiting | Gratis |
| reCAPTCHA v3 | Gratis* |

*Gratis hasta 1M requests/mes. Google cobra ~$0.50 por 1,000 requests adicionales.

## Monitoreo

Para ver intentos fallidos:

```sql
-- Ver intentos por IP
SELECT ip_address, COUNT(*) as intentos, MAX(timestamp) as ultimo
FROM login_attempts
WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
ORDER BY intentos DESC;

-- Ver intentos de bots detectados (honeypot)
SELECT * FROM login_attempts
WHERE tipo = 'register_bot'
ORDER BY timestamp DESC;

-- Limpiar intentos antiguos
DELETE FROM login_attempts WHERE timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

## Cumplimiento RGPD

- ✅ Datos de intentos de login se borran tras 24 horas
- ✅ Solo guarda IP (no datos personales)
- ✅ No acceso a terceros (excepto Google reCAPTCHA)
- ✅ Finalidad explícita: protección contra bots

## Próximas Mejoras (Opcionales)

- Alertas por email si se detectan muchos intentos fallidos
- Análisis de patrones de intentos fallidos
- Integración con fail2ban para bloquear IPs
- Verificación de email con código temporal
- Integración con servicios de IP reputation
