## 🎉 ¡Extensión Chrome de PIM - LISTA PARA USAR!

### ✨ Lo que se ha implementado:

#### 1. **Drag & Drop en la Web** 
   - Arrastra URLs a la zona de drop en la página de links
   - Extrae automáticamente el título de la página
   - Rellena el formulario automáticamente

#### 2. **Extensión Chrome Completa**
   - 🔘 Botón en la barra de herramientas
   - 📌 Menú contextual (click derecho)
   - ⌨️ Atajo: **Ctrl+Shift+L**
   - 📝 Rellenar automáticamente título y URL
   - 🎨 Personalizar color e icono
   - 📢 Notificaciones de éxito/error

#### 3. **API REST**
   - Endpoint `/api/links.php` para la extensión
   - Validación de autenticación
   - Sanitización de datos

---

### 🚀 Instalación Rápida (30 segundos)

```bash
# 1. En Chrome, abre:
chrome://extensions/

# 2. Activa "Modo de desarrollador" (arriba a la derecha)

# 3. Click "Cargar extensión sin empaquetar"

# 4. Selecciona la carpeta: /opt/PIM/chrome-extension/

# 5. En el popup de la extensión, ingresa tu URL de PIM:
https://tu-dominio.com/PIM

# ¡Listo! Ya puedes usar la extensión
```

---

### 💡 Cómo Usar

**Opción 1: Desde el Navegador**
```
Click en icono extensión → Personaliza → Guarda
```

**Opción 2: Menú Contextual**
```
Click derecho en la página → "Guardar página en PIM"
```

**Opción 3: Atajo Rápido**
```
Presiona Ctrl+Shift+L en cualquier página
```

**Opción 4: Desde PIM Web**
```
Ve a Links → Arrastra una URL a la zona de drop zone
```

---

### 📂 Estructura de Archivos

```
/opt/PIM/
├── api/
│   └── links.php                    ← Nuevo endpoint API
├── app/links/
│   └── index.php                    ← Actualizado con drag & drop
├── chrome-extension/                ← Extensión Chrome (NUEVA)
│   ├── manifest.json
│   ├── popup.html
│   ├── popup.js
│   ├── background.js
│   ├── content-script.js
│   ├── styles.css
│   ├── README.md
│   ├── images/
│   │   ├── icon-16.svg
│   │   ├── icon-48.svg
│   │   └── icon-128.svg
│   └── ICONS_SETUP.md
├── CHROME_EXTENSION_SETUP.md        ← Documentación completa
└── verify-extension.sh              ← Script de verificación
```

---

### 🔒 Seguridad

- ✅ Autenticación por sesión (cookies)
- ✅ Validación de URLs en servidor
- ✅ Sanitización de datos
- ✅ Protección CSRF (usa sesiones PHP)

---

### 🆘 Troubleshooting

| Error | Solución |
|-------|----------|
| "No autenticado" | Inicia sesión en tu PIM en una pestaña de Chrome |
| "Error de conexión" | Verifica la URL y que sea accesible |
| "El título no se extrae" | Algunos sitios lo bloquean por CORS |
| "No aparece el icono" | Recarga la extensión (botón circular en chrome://extensions/) |

---

### 📞 Documentación Completa

Para instrucciones detalladas, consulta: [CHROME_EXTENSION_SETUP.md](CHROME_EXTENSION_SETUP.md)

---

**¡Disfruta guardando links! 🔗**
