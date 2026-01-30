# ⚡ PIM - Webhooks y Automatizaciones

Sistema completo para integrar PIM con servicios externos y automatizar flujos de trabajo.

---

## 📋 Índice

- [Webhooks](#webhooks)
- [Automatizaciones](#automatizaciones)
- [Eventos Disponibles](#eventos-disponibles)
- [Ejemplos](#ejemplos)
- [Seguridad](#seguridad)

---

## 🔗 Webhooks

Los webhooks envían notificaciones HTTP POST a URLs externas cuando ocurren eventos en PIM.

### Configuración

1. Ve a **Admin → Webhooks**
2. Click en **+ Nuevo Webhook**
3. Completa:
   - **Nombre**: Identificador del webhook
   - **URL**: Endpoint que recibirá el POST
   - **Evento**: Qué disparará el webhook
   - **Secret** (opcional): Para firmar requests

### Payload del webhook

```json
{
  "id": 123,
  "titulo": "Nueva nota",
  "contenido": "Contenido...",
  "color": "amarillo",
  "_evento": "nota_creada",
  "_usuario_id": 1,
  "_timestamp": 1738275600,
  "_fecha": "2026-01-30 15:30:00"
}
```

### Headers enviados

```
Content-Type: application/json
User-Agent: PIM-Webhook/1.0
X-PIM-Signature: abc123... (si hay secret configurado)
```

### Verificar firma (si usas secret)

```python
# Python
import hmac
import hashlib

def verify_signature(payload, signature, secret):
    expected = hmac.new(
        secret.encode(),
        payload.encode(),
        hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)
```

```javascript
// Node.js
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
    const expected = crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('hex');
    return crypto.timingSafeEqual(
        Buffer.from(expected),
        Buffer.from(signature)
    );
}
```

---

## 🤖 Automatizaciones

Ejecutan acciones automáticamente cuando se cumplen condiciones.

### Estructura

```
Disparador → Condiciones → Acciones
```

### Configuración

1. Ve a **Admin → Automatizaciones**
2. Click en **+ Nueva Automatización**
3. Configura:
   - **Disparador**: Evento que inicia la automatización
   - **Condiciones**: Qué debe cumplirse (todas deben ser verdaderas)
   - **Acciones**: Qué hacer (se ejecutan en orden)

### Operadores de condición

| Operador | Descripción | Ejemplo |
|----------|-------------|---------|
| `igual` | Valor exacto | `color` igual `rojo` |
| `diferente` | No es igual | `prioridad` diferente `baja` |
| `contiene` | Substring | `titulo` contiene `urgente` |
| `no_contiene` | No tiene substring | `titulo` no_contiene `spam` |
| `mayor` | Mayor que | `total_ejecuciones` mayor `10` |
| `menor` | Menor que | `duracion_ms` menor `1000` |
| `vacio` | Sin valor | `descripcion` vacio |
| `no_vacio` | Tiene valor | `email` no_vacio |

### Tipos de acción

#### 1. Notificación

Muestra notificación en PIM:

```json
{
  "tipo": "notificacion",
  "titulo": "Nota urgente creada",
  "mensaje": "Se creó: {{titulo}}"
}
```

#### 2. Webhook

Llama a URL externa:

```json
{
  "tipo": "webhook",
  "url": "https://hooks.slack.com/services/...",
  "metodo": "POST"
}
```

#### 3. Email

Envía correo:

```json
{
  "tipo": "email",
  "destinatario": "admin@ejemplo.com",
  "asunto": "Nueva nota: {{titulo}}",
  "mensaje": "Se creó una nota con ID {{id}}"
}
```

#### 4. Modificar

Actualiza entidad existente:

```json
{
  "tipo": "modificar",
  "tabla": "notas",
  "campos": {
    "fijada": 1,
    "color": "rojo"
  }
}
```

#### 5. Crear

Crea nueva entidad:

```json
{
  "tipo": "crear",
  "tabla": "tareas",
  "campos": {
    "titulo": "Revisar nota: {{titulo}}",
    "prioridad": "alta"
  }
}
```

### Variables

Usa `{{variable}}` para insertar datos del evento:

- `{{id}}` - ID de la entidad
- `{{titulo}}` - Título
- `{{contenido}}` - Contenido
- `{{_usuario_id}}` - ID del usuario
- `{{_fecha}}` - Fecha del evento

---

## 📅 Eventos Disponibles

### Notas

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `nota_creada` | Al crear una nota | `id, titulo, contenido, color` |
| `nota_modificada` | Al modificar nota | `id, titulo, contenido` |
| `nota_eliminada` | Al mover a papelera | `id, titulo` |

### Contactos

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `contacto_creado` | Al crear contacto | `id, nombre, email, telefono, empresa` |
| `contacto_modificado` | Al modificar | `id, nombre, email` |
| `contacto_eliminado` | Al eliminar | `id, nombre` |

### Tareas

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `tarea_creada` | Al crear tarea | `id, titulo, prioridad, estado` |
| `tarea_completada` | Al completar | `id, titulo` |
| `tarea_modificada` | Al modificar | `id, estado` |

### Calendario

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `evento_creado` | Al crear evento | `id, titulo, fecha_inicio` |
| `evento_modificado` | Al modificar | `id, titulo` |

### Links

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `link_creado` | Al guardar link | `id, url, titulo, categoria` |

### Archivos

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `archivo_subido` | Al subir archivo | `id, nombre, tamano, tipo` |

### Sistema

| Evento | Cuándo se dispara | Payload |
|--------|-------------------|---------|
| `usuario_login` | Al iniciar sesión | `usuario_id, username, ip` |

---

## 💡 Ejemplos

### 1. Notificar Slack cuando hay nota urgente

**Webhook**:
- Evento: `nota_creada`
- URL: `https://hooks.slack.com/services/YOUR/WEBHOOK/URL`
- Condición: `titulo` contiene `urgente`

### 2. Auto-etiquetar notas importantes

**Automatización**:
- Disparador: `nota_creada`
- Condiciones:
  - `titulo` contiene `importante`
  - O `color` igual `rojo`
- Acciones:
  1. Modificar nota: `fijada = 1`
  2. Notificación: "Nota marcada como importante"

### 3. Crear tarea al añadir contacto cliente

**Automatización**:
- Disparador: `contacto_creado`
- Condiciones:
  - `empresa` no_vacio
- Acciones:
  1. Crear tarea: "Llamar a {{nombre}}"
  2. Enviar email: "Nuevo cliente agregado"

### 4. Backup automático de notas importantes

**Webhook**:
- Evento: `nota_creada`
- URL: `https://tu-servidor.com/backup-handler`
- Condición: `color` igual `rojo`
- Secret: `tu-secret-seguro`

**Receiver** (Node.js):

```javascript
const express = require('express');
const crypto = require('crypto');
const fs = require('fs');

const app = express();
app.use(express.json());

app.post('/backup-handler', (req, res) => {
    // Verificar firma
    const signature = req.headers['x-pim-signature'];
    const payload = JSON.stringify(req.body);
    const secret = 'tu-secret-seguro';
    
    const expected = crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('hex');
    
    if (signature !== expected) {
        return res.status(401).send('Invalid signature');
    }
    
    // Guardar backup
    const filename = `backup-${req.body.id}-${Date.now()}.json`;
    fs.writeFileSync(`./backups/${filename}`, payload);
    
    res.send('Backup saved');
});

app.listen(3000);
```

### 5. Integración con CRM externo

**Webhook**:
- Evento: `contacto_creado`
- URL: `https://api.hubspot.com/contacts/v1/contact/`
- Headers: `{"Authorization": "Bearer YOUR_TOKEN"}`

### 6. Recordatorio diario de tareas pendientes

**Automatización** (requiere cron):
- Disparador: `cron_diario`
- Cron: `0 9 * * *` (9:00 AM diario)
- Acciones:
  1. Webhook: Enviar resumen a endpoint
  2. Email: Enviar lista de pendientes

---

## 🔒 Seguridad

### Mejores prácticas

1. **Usa HTTPS**: Siempre en producción
2. **Secrets fuertes**: Mínimo 32 caracteres aleatorios
3. **Verifica firmas**: En el receptor del webhook
4. **Limita rate**: Evita loops infinitos
5. **Valida datos**: Sanitiza antes de usar
6. **Timeout**: Máximo 30 segundos por webhook
7. **Logs**: Revisa regularmente los logs

### Generar secret seguro

```bash
# Bash
openssl rand -hex 32

# Python
python3 -c "import secrets; print(secrets.token_hex(32))"

# Node.js
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

### Proteger endpoints

Si creas un endpoint que recibe webhooks:

```php
<?php
// webhook-receiver.php

// 1. Verificar firma
$signature = $_SERVER['HTTP_X_PIM_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$secret = 'tu-secret-aqui';

$expected = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}

// 2. Procesar datos
$data = json_decode($payload, true);

// 3. Hacer algo con los datos
// ... tu lógica ...

http_response_code(200);
echo 'OK';
```

### Prevenir loops infinitos

```
❌ MAL:
Webhook "nota_creada" → Crea otra nota → Dispara "nota_creada" → Loop infinito

✅ BIEN:
Webhook "nota_creada" → Envía a Slack (no crea datos en PIM)
```

### Rate limiting

PIM incluye protección básica:
- Máximo 100 webhooks por minuto por usuario
- Timeout de 30 segundos por request
- Máximo 5 reintentos en caso de error

---

## 📊 Logs y Monitoreo

### Ver logs de webhooks

```sql
SELECT * FROM webhook_logs 
WHERE webhook_id = 1 
ORDER BY fecha DESC 
LIMIT 20;
```

### Ver logs de automatizaciones

```sql
SELECT * FROM automatizacion_logs 
WHERE automatizacion_id = 1 
ORDER BY fecha DESC 
LIMIT 20;
```

### Estadísticas

```sql
-- Webhooks más usados
SELECT w.nombre, w.total_ejecuciones, w.total_errores,
       ROUND(w.total_errores * 100.0 / w.total_ejecuciones, 2) as tasa_error
FROM webhooks w
WHERE w.total_ejecuciones > 0
ORDER BY w.total_ejecuciones DESC;

-- Automatizaciones más ejecutadas
SELECT a.nombre, a.total_ejecuciones,
       DATE(a.ultima_ejecucion) as ultima_vez
FROM automatizaciones a
ORDER BY a.total_ejecuciones DESC;
```

---

## 🛠️ Troubleshooting

### Webhook no se ejecuta

1. Verificar que esté activo
2. Ver logs: `webhook_logs`
3. Probar URL manualmente con curl
4. Verificar firewall/CORS

### Automatización no funciona

1. Verificar condiciones
2. Ver logs: `automatizacion_logs`
3. Campo `condiciones_cumplidas` debe ser `1`
4. Revisar sintaxis JSON de acciones

### Error de timeout

- Reducir complejidad de acciones
- Usar async en receptor
- Aumentar timeout en `event_dispatcher.php`

---

📋 **PIM** - Personal Information Manager  
Documentación Webhooks/Automatizaciones - 30 de enero de 2026
