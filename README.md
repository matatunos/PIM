# PIM (Personal Information Manager)

Aplicación web modular y responsiva en PHP, JavaScript y MariaDB para gestión personal de información.

## ✨ Características principales
- ✅ Gestión de usuarios (admin/no-admin)
- ✅ Importación de contactos (Google/iPhone)
- ✅ Gestor de notas
- ✅ Gestor de archivos
- ✅ Calendario, tareas, recordatorios
- ✅ Etiquetas y búsqueda avanzada
- ✅ Gestor de links avanzado (Drag & Drop + Extensión Chrome)
- ✅ Soporte multilingüe
- ✅ Modularidad y seguridad
- ✅ UI responsiva con Font Awesome

## 🔗 Sistema de Gestión de Links

### Funcionalidades
1. **Drag & Drop Web**: Arrastra URLs directamente en la página
2. **Extensión Chrome**: Guarda links desde cualquier sitio web
   - Botón en la barra de herramientas
   - Menú contextual (click derecho)
   - Atajo: **Ctrl+Shift+L**
3. **Extracción Automática**: Título y URL se rellenan automáticamente
4. **Personalización**: Elige categoría, icono y color
5. **Notificaciones**: Confirmación de guardado

📚 [Ver documentación de Links](CHROME_EXTENSION_SETUP.md)
📚 [Inicio rápido](QUICK_START.md)

## Estructura de carpetas
- `/app/` módulos principales
- `/assets/` recursos estáticos (css, js, fonts, img)
- `/config/` configuración
- `/db/` scripts y backups de base de datos
- `/templates/` vistas HTML/PHP
- `/includes/` utilidades y helpers
- `/api/` endpoints REST
- `/chrome-extension/` extensión para Chrome

## Instalación
1. Clona el repositorio
2. Configura la base de datos en `/config/database.php`
3. Instala dependencias front-end si aplica
4. Accede vía navegador

### Instalación de la Extensión Chrome
1. Abre `chrome://extensions/`
2. Activa "Modo de desarrollador"
3. Click en "Cargar extensión sin empaquetar"
4. Selecciona la carpeta `/chrome-extension/`
5. Configura la URL de tu PIM en el popup

## Seguridad
- Separación de lógica y vistas
- Acceso restringido por roles
- Validación de archivos y formularios
- Autenticación por sesión (extensión Chrome)
- Sanitización de datos en API

## 📚 Documentación Adicional
- [Guía de Extensión Chrome](CHROME_EXTENSION_SETUP.md)
- [Inicio Rápido](QUICK_START.md)

## Licencia
MIT

