# Integración Open WebUI + RAG para PIM

## Descripción

Esta versión integra **Open WebUI** con el PIM, permitiendo:
- 🤖 Chat con IA desde el PIM usando Ollama
- 📄 **RAG (Retrieval Augmented Generation)**: La IA puede leer y responder preguntas sobre tus documentos
- 🔄 Sincronización automática de documentos y notas con Open WebUI
- 💬 Interfaz de chat integrada en el PIM

## Arquitectura

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   PIM Web   │────▶│ Open WebUI  │────▶│   Ollama    │
│  (PHP/JS)   │     │  (RAG/API)  │     │   (LLM)     │
└─────────────┘     └─────────────┘     └─────────────┘
       │                   ▲
       │                   │
       ▼                   │
┌─────────────┐           │
│  MariaDB    │           │
│ (docs/notas)│───────────┘
└─────────────┘   sync cada 15min
```

## Componentes

### API Endpoints

| Endpoint | Descripción |
|----------|-------------|
| `/api/ai-documents.php` | API de documentos/notas para sincronización |
| `/api/openwebui-proxy.php` | Proxy para comunicación con Open WebUI |
| `/api/ollama-proxy.php` | Proxy directo para Ollama |

### Scripts de Sincronización

| Script | Descripción |
|--------|-------------|
| `/bin/sync-openwebui.sh` | Sincroniza documentos y notas con Open WebUI |
| `/bin/setup-openwebui-sync.sh` | Configura la sincronización inicial |

### Interfaz de Usuario

- **Chat IA** (`/app/ai-assistant.php`): Chat con selector de documentos para RAG
- **Configuración** (`/app/admin/configuracion.php`): Panel para configurar Open WebUI

## Configuración

### Variables de Entorno (.env)

```env
# API Key de Open WebUI (generar desde Open WebUI > Settings > Account)
OPENWEBUI_API_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# Host de Open WebUI (opcional, también configurable en BD)
OPENWEBUI_HOST=192.168.1.19
OPENWEBUI_PORT=8080
```

### Base de Datos

La configuración de Open WebUI se almacena en la tabla `configuracion_ia`:

```sql
CREATE TABLE configuracion_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor TEXT,
    descripcion VARCHAR(255),
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO configuracion_ia (clave, valor, descripcion) VALUES
('openwebui_host', '192.168.1.19', 'Host de Open WebUI'),
('openwebui_port', '8080', 'Puerto de Open WebUI'),
('sync_documents', '1', 'Sincronizar documentos'),
('sync_notes', '1', 'Sincronizar notas');
```

El historial de sincronización se guarda en `sync_history`:

```sql
CREATE TABLE sync_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    mensaje TEXT,
    documentos_procesados INT DEFAULT 0,
    sincronizado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## Uso

### Chat con Documentos (RAG)

1. Ve a **Chat IA** en el menú lateral
2. Haz clic en **"Documentos"** para ver los archivos sincronizados
3. Selecciona uno o más documentos
4. Escribe tu pregunta
5. La IA buscará en el contenido de los documentos y responderá

**Ejemplo:**
- Selecciona "Memoria_Constructiva.pdf"
- Pregunta: "¿Qué materiales se especifican para la cimentación?"
- La IA extraerá la información del PDF y responderá

### Sincronización Manual

```bash
# Ejecutar sincronización manualmente
bash /opt/PIM/bin/sync-openwebui.sh

# Ver logs
tail -f /opt/PIM/logs/sync-openwebui.log
```

### Sincronización Automática (Cron)

La sincronización está configurada para ejecutarse cada 15 minutos:

```cron
*/15 * * * * /bin/bash /opt/PIM/bin/sync-openwebui.sh >> /opt/PIM/logs/cron-sync.log 2>&1
```

## Requisitos

- **Open WebUI** corriendo en la red local (puerto 8080)
- **Ollama** corriendo en la red local (puerto 11434)
- Modelos de Ollama instalados (ej: `llama3.2:3b`, `qwen2.5:14b`)
- PHP 8.x con extensión cURL
- jq (para el script de sincronización)

## Solución de Problemas

### El chat no responde

1. Verificar que Open WebUI esté corriendo:
   ```bash
   curl http://192.168.1.19:8080/api/health
   ```

2. Verificar la API key en `.env`

3. Revisar logs:
   ```bash
   tail -f /opt/PIM/logs/sync-openwebui.log
   ```

### Los documentos no aparecen en el chat

1. Ejecutar sincronización manual:
   ```bash
   bash /opt/PIM/bin/sync-openwebui.sh
   ```

2. Verificar que los documentos estén procesados en Open WebUI:
   ```bash
   curl -H "Authorization: Bearer $OPENWEBUI_API_KEY" \
        http://192.168.1.19:8080/api/v1/files/
   ```

### Error de jq al sincronizar

Asegúrate de que jq esté instalado:
```bash
apt install jq
```

## Changelog v2.6.0

### Nuevas Características
- ✅ Integración completa con Open WebUI
- ✅ Chat con IA desde el PIM
- ✅ Selector de documentos para RAG
- ✅ Sincronización automática de documentos y notas
- ✅ Subida de archivos reales (PDFs) a Open WebUI
- ✅ Proxy PHP para evitar problemas de CORS
- ✅ Panel de configuración de IA en administración

### Archivos Nuevos
- `api/ai-documents.php` - API de documentos
- `api/openwebui-proxy.php` - Proxy para Open WebUI
- `api/ollama-proxy.php` - Proxy para Ollama
- `app/ai-assistant.php` - Chat con IA + RAG
- `bin/sync-openwebui.sh` - Script de sincronización
- `bin/setup-openwebui-sync.sh` - Setup inicial

### Archivos Modificados
- `includes/sidebar.php` - Añadido enlace a Chat IA
- `app/admin/configuracion.php` - Sección de configuración Open WebUI
- `config/database.php` - Constantes para JWT y API keys
- `db/schema.sql` - Nuevas tablas configuracion_ia y sync_history
