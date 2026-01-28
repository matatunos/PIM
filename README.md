# PIM (Personal Information Manager)

Aplicación web modular y responsiva en PHP, JavaScript y MariaDB para gestión personal de información.

## Características principales
- Gestión de usuarios (admin/no-admin)
- Importación de contactos (Google/iPhone)
- Gestor de notas
- Gestor de archivos
- Calendario, tareas, recordatorios
- Etiquetas y búsqueda avanzada
- Soporte multilingüe
- Modularidad y seguridad
- UI responsiva con Font Awesome

## Estructura de carpetas
- `/app/` módulos principales
- `/assets/` recursos estáticos (css, js, fonts, img)
- `/config/` configuración
- `/db/` scripts y backups de base de datos
- `/templates/` vistas HTML/PHP
- `/includes/` utilidades y helpers

## Instalación
1. Clona el repositorio
2. Configura la base de datos en `/config/database.php`
3. Instala dependencias front-end si aplica
4. Accede vía navegador

## Seguridad
- Separación de lógica y vistas
- Acceso restringido por roles
- Validación de archivos y formularios

## Licencia
MIT
