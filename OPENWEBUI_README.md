# 🤖 PIM + Open WebUI Integration

Integración completa entre PIM (Personal Information Manager) y Open WebUI con Ollama para acceso a IA desde tu gestor personal.

## ⚡ Instalación Rápida

```bash
cd /opt/PIM
sudo bash bin/setup-openwebui-sync.sh
```

El instalador te guiará a través de:
- Generación de JWT_SECRET seguro
- Configuración de Open WebUI (IP/puerto)
- Validación de conectividad
- Configuración automática de cron

## 📱 Acceder al Chat IA

- **Menú sidebar**: IA & Chat → Chat IA
- **URL directa**: `http://localhost/app/ai-assistant.php`
- **Panel de admin**: `http://localhost/app/admin/configuracion.php`

## 🎯 Características

✨ **Chat IA integrado** en la aplicación  
📄 **Acceso automático** a tus documentos y notas  
🔄 **Sincronización automática** cada X minutos (configurable)  
🔐 **JWT firmado** para autenticación segura  
⚙️ **Configuración flexible** de host/puerto  
🧪 **Prueba de conexión** desde panel admin  
📊 **Historial de sesiones** guardado en BD  
🔍 **Búsqueda fulltext** en documentos y notas  

## 📁 Archivos Principales

| Archivo | Descripción |
|---------|------------|
| `/api/ai-documents.php` | API con 3 endpoints: get_documents, get_notes, search |
| `/app/ai-assistant.php` | Widget modal de chat |
| `/bin/sync-openwebui.sh` | Script de sincronización automática |
| `/bin/setup-openwebui-sync.sh` | Instalador interactivo |
| `/OPEN_WEBUI_INTEGRATION.md` | Documentación completa |

## 🔐 Seguridad

- ✅ JWT firmado con HS256 (expira en 8 horas)
- ✅ API Key almacenada en `.env` (nunca en git)
- ✅ Rate limiting: 10 req/min
- ✅ Aislamiento de datos por usuario
- ✅ Logging de eventos de seguridad
- ✅ Input sanitization y validación

## 🚀 Configuración

1. **JWT_SECRET**: Generado automáticamente o desde `openssl rand -base64 32`
2. **OPENWEBUI_API_KEY**: Desde Settings > API Keys en Open WebUI
3. **Host/Puerto**: Configurable en panel de admin
4. **Intervalo sincronización**: 1-1440 minutos

## 📊 Bases de Datos

Se crean 3 nuevas tablas:

```sql
- configuracion_ia          -- Configuración de Open WebUI
- chat_sessions            -- Historial de chats
- sync_history            -- Historial de sincronización
```

## 🔧 Comandos Útiles

```bash
# Test de conectividad manual
/opt/PIM/bin/sync-openwebui.sh

# Ver logs de sincronización
tail -f /opt/PIM/logs/sync-openwebui.log

# Ver entrada en cron
sudo cat /etc/cron.d/pim-sync-openwebui

# Ejecutar setup nuevamente
sudo bash /opt/PIM/bin/setup-openwebui-sync.sh
```

## 📖 Documentación Completa

Ver [OPEN_WEBUI_INTEGRATION.md](./OPEN_WEBUI_INTEGRATION.md) para:
- Instalación paso a paso
- Configuración manual
- Troubleshooting detallado
- Testing y validación
- Casos de uso avanzados

## ❓ Preguntas Frecuentes

**¿Dónde está Open WebUI?**
Por defecto en `192.168.1.19:3000`, configurable en panel admin.

**¿Qué datos se sincronizan?**
Documentos y notas del usuario actual, solo texto (no archivos binarios).

**¿Se sincroniza en ambas direcciones?**
No, solo PIM → Open WebUI (unidireccional).

**¿Todos los usuarios pueden usar IA?**
Sí, pero cada uno solo accede a sus propios documentos.

**¿Cómo generar API Key de Open WebUI?**
Settings > API Keys > Create New API Key en Open WebUI.

## 🆘 Soporte

Si encuentras problemas:
1. Ver sección "Troubleshooting" en [OPEN_WEBUI_INTEGRATION.md](./OPEN_WEBUI_INTEGRATION.md)
2. Ejecutar script de diagnóstico: `bash bin/test-setup.sh`
3. Revisar logs: `/opt/PIM/logs/sync-openwebui.log`

## 📝 Licencia

Parte del proyecto PIM. Ver `LICENSE` para detalles.

---

**v1.0.0** - 29 de enero de 2026
