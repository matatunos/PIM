# 📸 Capturas de Pantalla de PIM

Este directorio contiene las capturas de pantalla de las diferentes funcionalidades de PIM.

## Archivos

### Módulos principales

| Archivo | Descripción |
|---------|-------------|
| `dashboard.png` / `dashboard.txt` | Panel de control principal |
| `notas.png` / `notas.txt` | Sistema de notas con Markdown |
| `kanban.png` / `kanban.txt` | Vista Kanban de tareas |
| `calendario.png` / `calendario.txt` | Calendario de eventos |
| `contactos.png` / `contactos.txt` | Gestor de contactos |
| `links.png` / `links.txt` | Gestor de links con categorías |

## Descripción de funcionalidades

### 📊 Dashboard
**Ubicación**: `Inicio` → `Dashboard`

El panel de control principal que muestra:
- ✅ Resumen de estadísticas (notas, contactos, tareas, eventos)
- 📈 Gráficos de actividad
- 🔔 Notificaciones recientes
- 📌 Notas fijadas
- 🎯 Tareas próximas

**Características**:
- Vista rápida de tu productividad
- Acceso directo a últimas actividades
- Widgets personalizables

---

### 📝 Notas
**Ubicación**: `Notas`

Sistema completo de notas con:
- ✏️ Editor Markdown con vista previa
- 🎨 Colores personalizables (8 colores)
- 🏷️ Etiquetas ilimitadas
- 📌 Opción de fijar notas
- 🗑️ Papelera de reciclaje
- 🔍 Búsqueda por texto/etiquetas

**Características**:
- Soporte completo de Markdown (títulos, listas, código, enlaces, imágenes)
- Almacenamiento automático de borradores
- Historial de cambios
- Colores y etiquetas personalizadas
- Fijado de notas importantes

**Acciones rápidas**:
- `Ctrl+N` - Nueva nota
- `Ctrl+S` - Guardar
- `Ctrl+/` - Atajo de comandos
- `@` - Mencionar contacto
- `#` - Referenciar tarea

---

### ✅ Tareas - Vista Kanban
**Ubicación**: `Tareas` → `[Kanban]`

Tablero tipo Trello con:
- 📥 **Por hacer** - Tareas nuevas
- 🔄 **En progreso** - Tareas activas
- ✅ **Completado** - Tareas terminadas

**Características**:
- 🎯 4 niveles de prioridad (urgente, alta, media, baja)
- 📅 Fechas límite con recordatorio
- 👤 Asignación a usuarios
- 🏷️ Etiquetas personalizadas
- 💬 Comentarios en tareas
- 📎 Adjuntar archivos
- ⏳ Estimación de tiempo
- 🔁 Subtareas

**Controles**:
- ➡️ Arrastra entre columnas
- 📝 Haz clic para editar detalles
- 🗑️ Arrastra a papelera para eliminar
- 🔔 Notificación al vencer plazo

---

### 📅 Calendario
**Ubicación**: `Calendario`

Calendario completo con eventos:
- 📆 Vista mensual, semanal y diaria
- 🔵🟣🟢🟡🔴 Código de colores por categoría
- 🔔 Recordatorios (10 min, 1 hora, 1 día antes)
- 📍 Ubicación del evento
- 👥 Asistentes (próximamente)
- 📹 Conferencia (Zoom, Teams, Meet)
- 📝 Descripción detallada

**Características**:
- Crear eventos arrastrando en el calendario
- Eventos recurrentes (diario, semanal, mensual, anual)
- Búsqueda de eventos
- Vista de lista de eventos próximos
- Exportar a .ics (próximamente)

---

### 👥 Contactos
**Ubicación**: `Contactos`

Base de datos de contactos con:
- 👤 Foto de perfil (upload o Gravatar)
- 📧 Email(s) múltiples
- 📱 Teléfono(s) múltiples
- 🏢 Empresa
- 🏠 Dirección completa
- 🌐 Sitio web
- 🤝 Relación (cliente, proveedor, amigo, etc.)
- ⭐ Favoritos
- 🏷️ Etiquetas

**Características**:
- 📥 Importar desde VCF (.vcf/.ics)
- 📤 Exportar contactos
- 🔍 Búsqueda por nombre, email, empresa
- 💬 Ver historial de interacciones
- 📝 Notas sobre el contacto
- 🔗 Enlazar a tareas/eventos

---

### 🔗 Links / Marcadores
**Ubicación**: `Links`

Gestor de links con:
- 🏷️ Categorías personalizables
- 🎨 Icono automático o manual
- 📸 Vista previa de sitio web
- ⭐ Favoritos
- 📝 Descripción y anotaciones
- 🔍 Búsqueda rápida
- 📋 Exportar a HTML

**Características**:
- 🧩 **Extensión Chrome**: Clic derecho → "Guardar en PIM"
- 🏘️ Agrupar por categoría
- 📊 Estadísticas de uso
- ⏱️ Links visitados recientemente
- 🗑️ Papelera con opción de recuperar

**Categorías por defecto**:
- 💼 Trabajo
- 📚 Aprendizaje
- 🎮 Entretenimiento
- 🛒 Compras
- 🔧 Herramientas
- 📰 Noticias

---

## 💡 Tips para capturar pantallas

Si deseas actualizar las capturas:

```bash
# Navega a cada sección en tu navegador
# Usa F12 → Device Emulation para responsive
# Captura con Ctrl+Shift+S (Chrome) o similar
# Guarda como PNG con nombre descriptivo

# Para convertir a ASCII (opcional)
convert imagen.png -resize 100x30 ascii:-
```

---

## 🎨 Paleta de colores

PIM usa una paleta consistente:

| Color | Uso | Código |
|-------|-----|--------|
| 🔵 Azul | Trabajo, por defecto | #4F46E5 |
| 🟣 Púrpura | Documentación, especial | #7C3AED |
| 🟢 Verde | Completado, éxito | #10B981 |
| 🟡 Amarillo | En progreso, advertencia | #F59E0B |
| 🔴 Rojo | Urgente, error | #EF4444 |
| ⚪ Blanco | Neutral | #FFFFFF |
| ⚫ Negro | Contraste | #1F2937 |

---

## 📋 Últimas actualizaciones

- **30/01/2026**: Actualización de captura con nuevas características
- **28/01/2026**: Agregadas vistas adicionales
- **25/01/2026**: Documentación inicial
