# Extensión Chrome - PIM Links

Extensión de Chrome para guardar y gestionar links directamente desde tu navegador hacia tu PIM.

## Características

✅ **Guardar links con un clic** - Botón en la barra de herramientas
✅ **Menú contextual** - Click derecho en cualquier página o enlace → "Guardar en PIM"
✅ **Rellenar automáticamente** - Título y URL de la página actual
✅ **Personalización** - Elige categoría, icono y color antes de guardar
✅ **Atajo de teclado** - Ctrl+Shift+L para guardar rápidamente
✅ **Notificaciones** - Confirma cuando el link se guardó correctamente

## Instalación

### Pasos:

1. **Descarga la extensión**
   - Copia la carpeta `chrome-extension` a tu máquina

2. **Abre Chrome Extensions**
   - Ve a `chrome://extensions/` en Chrome
   - Activa "Modo de desarrollador" (arriba a la derecha)

3. **Carga la extensión**
   - Haz clic en "Cargar extensión sin empaquetar"
   - Selecciona la carpeta `chrome-extension`

4. **Configura tu PIM**
   - Haz clic en el icono de la extensión en la barra de herramientas
   - Ingresa la URL de tu PIM (ej: `https://tu-dominio.com/PIM`)
   - Guarda la configuración

## Uso

### Opción 1: Desde el popup
1. Haz clic en el icono de la extensión
2. Se rellena automáticamente con el título y URL de la página actual
3. Personaliza si lo deseas (descripción, categoría, icono, color)
4. Haz clic en "Guardar Link"

### Opción 2: Menú contextual (Click derecho)
1. Click derecho en la página → "Guardar página en PIM"
2. O click derecho en un enlace → "Guardar enlace en PIM"
3. Se guardará automáticamente (puedes personalizar en el popup)

### Opción 3: Atajo de teclado
- **Ctrl+Shift+L** en cualquier página para guardar rápidamente

## Requisitos

- Tu PIM debe estar accesible desde internet
- Debes estar autenticado en tu PIM en Chrome (la extensión usa las cookies de sesión)
- El endpoint API `/api/links.php` debe estar disponible

## Configuración

La URL de tu PIM se guarda localmente en el navegador. Puedes cambiarla en cualquier momento:
1. Haz clic en el icono de engranaje en el popup
2. Modifica la URL
3. Guarda

## Solución de problemas

**"No autenticado"**
- Asegúrate de que has iniciado sesión en tu PIM en una pestaña de Chrome
- Intenta actualizar la página de PIM en Chrome

**"Error de conexión"**
- Verifica que la URL de tu PIM es correcta
- Comprueba que tu PIM es accesible desde internet

**La extensión no se carga**
- Asegúrate de que tienes el "Modo de desarrollador" activado en `chrome://extensions/`
- Recarga la extensión

## Estructura de archivos

```
chrome-extension/
├── manifest.json          # Configuración de la extensión
├── popup.html            # UI del popup
├── popup.js              # Lógica del popup
├── background.js         # Menú contextual y notificaciones
├── content-script.js     # Scripts en las páginas web
├── styles.css            # Estilos del popup
├── images/               # Iconos (16x16, 48x48, 128x128)
└── README.md            # Este archivo
```

## Desarrollo

Si quieres modificar la extensión:

1. Haz cambios en los archivos
2. Ve a `chrome://extensions/`
3. Haz clic en el botón de "Recargar" (circular) en la tarjeta de la extensión
4. Los cambios se aplicarán inmediatamente

## Licencia

Mismo que el proyecto PIM

## Soporte

Si tienes problemas, verifica:
- Que tu PIM está funcionando correctamente
- Que tienes acceso a `/api/links.php`
- Que estás autenticado en tu PIM
