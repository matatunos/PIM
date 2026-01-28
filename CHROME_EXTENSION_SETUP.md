# 🔗 PIM - Sistema Completo de Gestión de Links

## ¿Qué se ha implementado?

### 1️⃣ **Funcionalidad Drag & Drop en la Web**
📁 Archivo: [app/links/index.php](app/links/index.php)

- **Zona de drop zone** en la página de links
- Arrastra URLs desde la barra de direcciones
- Extrae automáticamente el título de la página
- Rellena el formulario modal automáticamente
- Solo necesitas añadir descripción y personalizar si lo deseas

### 2️⃣ **Extensión de Chrome**
📁 Carpeta: `chrome-extension/`

Una extensión completa para guardar links desde cualquier página web.

**Características:**
- ✅ Botón en la barra de herramientas
- ✅ Menú contextual (click derecho)
- ✅ Atajo de teclado: **Ctrl+Shift+L**
- ✅ Rellenar automáticamente título y URL
- ✅ Personalizar antes de guardar
- ✅ Notificaciones de éxito/error

### 3️⃣ **API REST**
📁 Archivo: [api/links.php](api/links.php)

Endpoint para guardar links desde la extensión:
```
POST /api/links.php
```

Requiere autenticación de sesión.

---

## 📦 Archivos Creados/Modificados

### Backend
- `api/links.php` - Endpoint API para guardar links (NUEVO)
- `app/links/index.php` - Añadido: endpoint para extraer título, drop zone, JavaScript

### Extensión Chrome
```
chrome-extension/
├── manifest.json        - Configuración de la extensión
├── popup.html          - Interfaz del popup
├── popup.js            - Lógica del popup
├── background.js       - Menú contextual y notificaciones
├── content-script.js   - Scripts en las páginas web
├── styles.css          - Estilos
├── images/             - Iconos SVG
│   ├── icon-16.svg
│   ├── icon-48.svg
│   └── icon-128.svg
├── README.md           - Instrucciones de instalación
└── ICONS_SETUP.md      - Guía para generar PNGs
```

---

## 🚀 Cómo Instalar y Usar

### Instalación de la Extensión Chrome

1. **Abre Chrome Extensions**
   - Ve a `chrome://extensions/`
   - Activa "Modo de desarrollador" (arriba a la derecha)

2. **Carga la extensión**
   - Click en "Cargar extensión sin empaquetar"
   - Selecciona la carpeta `chrome-extension/`

3. **Configura tu PIM**
   - Haz clic en el icono de la extensión
   - Ingresa la URL de tu PIM (ej: `https://tu-dominio.com/PIM`)
   - Guarda la configuración

### Uso

**Desde el navegador:**
1. Click en el icono de la extensión → Rellena el formulario → Guarda
2. Click derecho en la página → "Guardar página en PIM"
3. Click derecho en un enlace → "Guardar enlace en PIM"
4. Presiona **Ctrl+Shift+L** para guardar rápidamente

**Desde la web (PIM):**
1. Ve a la sección Links
2. Arrastra una URL a la zona de drop zone
3. Se rellena automáticamente
4. Personaliza y guarda

---

## 🔄 Flujo de Funcionamiento

### Opción 1: Extensión Chrome
```
Usuario: Click en extensión
         ↓
Popup: Se rellena título + URL de página actual
         ↓
Usuario: Personaliza si lo desea
         ↓
Extensión: POST a /api/links.php
         ↓
API: Valida y guarda en BD
         ↓
Notificación: Éxito/Error
```

### Opción 2: Menú Contextual
```
Usuario: Click derecho → "Guardar en PIM"
         ↓
Extension: Extrae título de la página
         ↓
API: Guarda automáticamente
         ↓
Notificación: Éxito/Error
```

### Opción 3: Drag & Drop (Web)
```
Usuario: Arrastra URL a zona drop
         ↓
JavaScript: Obtiene título de la página
         ↓
Modal: Se abre pre-rellenado
         ↓
Usuario: Personaliza si lo desea
         ↓
Form POST: Guarda en BD
```

---

## 🔐 Seguridad

- La extensión usa **cookies de sesión** para autenticarse
- El usuario debe estar logueado en su PIM en Chrome
- El endpoint `/api/links.php` requiere sesión válida
- Las URLs se validan en servidor
- Los datos se sanitizan antes de guardar

---

## 📝 Requisitos

- URL de PIM accesible desde internet
- Usuario autenticado en Chrome (sesión activa)
- Endpoint `/api/links.php` disponible
- CORS puede ser necesario configurar si la extensión está en diferente dominio

---

## 🐛 Troubleshooting

| Problema | Solución |
|----------|----------|
| "No autenticado" | Inicia sesión en tu PIM en una pestaña de Chrome |
| "Error de conexión" | Verifica la URL de tu PIM y que sea accesible |
| El título no se extrae | Algunos sitios pueden bloquearlo por CORS |
| La extensión no se carga | Activa "Modo de desarrollador" en chrome://extensions/ |
| Icons no se ven | Convierte los SVGs a PNG (ver ICONS_SETUP.md) |

---

## 📚 Próximas Mejoras (Opcional)

- [ ] Sincronización con navegadores Firefox/Edge
- [ ] Búsqueda rápida de links desde el popup
- [ ] Reordenación de links por drag & drop
- [ ] Empaquetado de la extensión para Chrome Web Store
- [ ] Historial de links guardados
- [ ] Exportar/importar links

---

## 📞 Soporte

- Revisa los logs de la extensión en `chrome://extensions/` → Detalles
- Abre la consola del navegador para ver errores
- Comprueba que el servidor responde correctamente

---

**¡Lista para usar! 🎉**
