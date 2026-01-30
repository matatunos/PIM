<p align="center">
  <h1 align="center">📋 PIM</h1>
  <p align="center"><strong>Personal Information Manager</strong></p>
  <p align="center">
    <em>Tu centro de productividad personal: notas, contactos, tareas, calendario y más</em>
  </p>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MariaDB-10.5+-003545?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB">
  <img src="https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
</p>

<p align="center">
  <a href="#-características">Características</a> •
  <a href="#-capturas-de-pantalla">Capturas</a> •
  <a href="#-instalación">Instalación</a> •
  <a href="#-extensión-chrome">Extensión</a> •
  <a href="#-documentación">Docs</a>
</p>

---

## 🎯 ¿Qué es PIM?

PIM es una aplicación web **autoalojada** para gestionar tu información personal de forma centralizada y segura. Todo en un solo lugar, en tu propio servidor.

```
┌─────────────────────────────────────────────────────────────────┐
│  📋 PIM - Personal Information Manager                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   📝 Notas      👥 Contactos    ✅ Tareas     📅 Calendario    │
│                                                                 │
│   🔗 Links      📁 Archivos     🤖 IA         🔔 Recordatorios │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## ✨ Características

### 📦 Módulos principales

| Módulo | Descripción | Características |
|--------|-------------|-----------------|
| 📝 **Notas** | Apuntes con Markdown | Colores, etiquetas, fijadas, archivo |
| 👥 **Contactos** | Agenda completa | Importar VCF, favoritos, búsqueda |
| ✅ **Tareas** | To-do y Kanban | Prioridades, fechas, drag & drop |
| 📅 **Calendario** | Eventos y citas | Recordatorios, vista mes/semana |
| 🔗 **Links** | Marcadores | Categorías, iconos, extensión Chrome |
| 📁 **Archivos** | Almacenamiento | Subida múltiple, previews |
| 🤖 **Asistente IA** | Chat inteligente | Integración Ollama/Open WebUI |

### 🔐 Seguridad

- ✅ Autenticación de dos factores (2FA/TOTP)
- ✅ Tokens de API por usuario
- ✅ Roles de usuario (admin/user)
- ✅ Registro de auditoría
- ✅ Protección CSRF
- ✅ Contraseñas hasheadas (bcrypt)

### 🎨 Interfaz

- ✅ Diseño responsive (móvil, tablet, desktop)
- ✅ Tema claro/oscuro
- ✅ Navegación AJAX sin recargas
- ✅ Drag & drop para archivos y tareas
- ✅ Soporte multiidioma (ES/EN)

---

## 📸 Capturas de Pantalla

### Vista principal - Notas
```
┌──────────────────────────────────────────────────────────────────────────┐
│  📋 PIM                                        🔔  👤 nacho  ⚙️         │
├────────────┬─────────────────────────────────────────────────────────────┤
│            │                                                             │
│  📝 Notas  │   📝 Mis Notas                            [+ Nueva Nota]   │
│  👥 Contac │   ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │
│  ✅ Tareas │   │ 📌 Lista    │ │ Ideas       │ │ Reunión     │          │
│  📅 Calend │   │ compras     │ │ proyecto    │ │ lunes       │          │
│  🔗 Links  │   │             │ │             │ │             │          │
│  📁 Archiv │   │ Leche, pan  │ │ Nueva feat  │ │ Puntos a    │          │
│  🤖 IA     │   │ huevos...   │ │ para el...  │ │ tratar...   │          │
│            │   └─────────────┘ └─────────────┘ └─────────────┘          │
│            │      🟡 amarillo    🟢 verde        🔵 azul                 │
└────────────┴─────────────────────────────────────────────────────────────┘
```

### Vista Kanban - Tareas
```
┌──────────────────────────────────────────────────────────────────────────┐
│  ✅ Tareas                                    [Vista Lista] [Kanban ✓]  │
├──────────────────────┬──────────────────────┬────────────────────────────┤
│   📥 Por hacer       │   🔄 En progreso     │   ✅ Completado            │
├──────────────────────┼──────────────────────┼────────────────────────────┤
│ ┌──────────────────┐ │ ┌──────────────────┐ │ ┌──────────────────┐      │
│ │ 🔴 Revisar docs  │ │ │ 🟠 Desarrollo    │ │ │ ✓ Setup inicial  │      │
│ └──────────────────┘ │ │    feature       │ │ └──────────────────┘      │
│ ┌──────────────────┐ │ └──────────────────┘ │ ┌──────────────────┐      │
│ │ 🟢 Otra tarea    │ │                      │ │ ✓ Configurar DB  │      │
│ └──────────────────┘ │                      │ └──────────────────┘      │
└──────────────────────┴──────────────────────┴────────────────────────────┘
```

### Extensión Chrome
```
┌─────────────────────────────┐
│ 🔗 Guardar Link             │
│            👤 nacho         │
├─────────────────────────────┤
│ ┌─────────────────────────┐ │
│ │ Título de la página     │ │
│ └─────────────────────────┘ │
│ ┌─────────────────────────┐ │
│ │ https://ejemplo.com     │ │
│ └─────────────────────────┘ │
│ ┌─────────────────────────┐ │
│ │ Descripción (opcional)  │ │
│ └─────────────────────────┘ │
│                             │
│ Color: 🔵 🟣 🟢 🟡 🟠       │
│                             │
│ ┌─────────────────────────┐ │
│ │     ✓ Guardar Link      │ │
│ └─────────────────────────┘ │
└─────────────────────────────┘
```

---

## 🚀 Instalación

### Requisitos

| Requisito | Versión mínima |
|-----------|----------------|
| PHP | 8.0+ |
| MariaDB/MySQL | 10.5+ / 5.7+ |
| Apache/Nginx | 2.4+ / 1.18+ |
| Extensiones PHP | pdo, pdo_mysql, json, mbstring, zip |

### Instalación rápida

```bash
# 1. Clonar repositorio
git clone https://github.com/matatunos/PIM.git
cd PIM

# 2. Crear base de datos
mysql -u root -p < db/schema.sql

# 3. Configurar conexión
cp config/config.example.php config/config.php
nano config/config.php

# 4. Configurar permisos
chmod 755 -R .
chmod 777 -R assets/uploads logs

# 5. Acceder
# http://tu-servidor/PIM
# Usuario: admin / Contraseña: admin123
```

### Con Docker (próximamente)

```bash
docker-compose up -d
```

---

## 🧩 Extensión Chrome

La extensión permite guardar enlaces desde cualquier página web **sin necesidad de abrir PIM**.

### ✨ Características

- 🔐 **Preconfigurada**: Se descarga con tu token de usuario
- 📌 **Un clic**: Guarda la página actual instantáneamente
- 🖱️ **Menú contextual**: Clic derecho → "Guardar en PIM"
- 🏷️ **Personalizable**: Elige categoría, color e icono
- 🔔 **Notificaciones**: Confirmación de guardado

### 📥 Instalación

1. **Inicia sesión** en PIM
2. Ve a **Perfil → Descargar Extensión**
3. Extrae el ZIP descargado
4. Abre `chrome://extensions/`
5. Activa **Modo desarrollador**
6. Clic en **Cargar descomprimida**
7. Selecciona la carpeta extraída

> 💡 **Nota**: La extensión viene preconfigurada con tu usuario. No necesitas configurar nada.

---

## 🤖 Integración con IA

PIM puede integrarse con **Ollama** u **Open WebUI** para asistencia inteligente.

### Funcionalidades

| Función | Descripción |
|---------|-------------|
| 💬 Chat | Conversación con modelos de lenguaje |
| 📝 Resumen | Resume tus notas automáticamente |
| 🔍 Búsqueda | Busca en tu información con lenguaje natural |
| ✍️ Redacción | Ayuda a escribir y mejorar textos |

### Configuración

```bash
# En .env o config.php
OLLAMA_URL=http://localhost:11434
OPENWEBUI_URL=http://localhost:8080
OPENWEBUI_API_KEY=tu-api-key
```

---

## 📁 Estructura del Proyecto

```
PIM/
├── 📂 api/                 # Endpoints REST
│   ├── links.php
│   ├── archivos.php
│   └── ...
├── 📂 app/                 # Módulos de la aplicación
│   ├── notas/
│   ├── contactos/
│   ├── tareas/
│   ├── calendario/
│   ├── links/
│   ├── archivos/
│   ├── perfil/
│   └── admin/
├── 📂 assets/              # Recursos estáticos
│   ├── css/
│   ├── js/
│   ├── fonts/
│   └── uploads/
├── 📂 bin/                 # Scripts CLI
│   ├── crear-nota.php
│   ├── backup-db.sh
│   └── ...
├── 📂 chrome-extension/    # Extensión de navegador
├── 📂 config/              # Configuración
├── 📂 db/                  # Esquemas y migraciones
├── 📂 docs/                # Documentación
├── 📂 includes/            # Librerías y helpers
└── 📂 templates/           # Plantillas HTML
```

---

## ⌨️ CLI - Línea de Comandos

PIM incluye herramientas de línea de comandos para automatización.

### Crear notas desde terminal

```bash
# Nota simple
php bin/crear-nota.php -t "Título" -c "Contenido"

# Con color y etiquetas
php bin/crear-nota.php -t "Servidor" -c "Config actualizada" \
    --color verde --etiquetas "sistema,servidor"

# Desde archivo markdown
php bin/crear-nota.php -t "Documentación" -f README.md --color azul

# Desde pipe (salida de comandos)
df -h | php bin/crear-nota.php -t "Espacio disco" --stdin

# Para usuario específico
php bin/crear-nota.php -t "Nota" -c "Texto" -u nacho

# Ver ayuda
php bin/crear-nota.php --help
```

### Otros scripts

```bash
# Backup de base de datos
./bin/backup-db.sh

# Restaurar backup
./bin/restore-db.sh backup_2026-01-30.sql

# Reset de contraseña
php bin/reset-password.php admin nueva_contraseña
```

---

## 📚 Documentación

| Documento | Descripción |
|-----------|-------------|
| [📖 Manual de Usuario](docs/manual-usuario.html) | Guía completa con capturas |
| [🚀 Inicio Rápido](QUICK_START.md) | Empezar en 5 minutos |
| [🧩 Extensión Chrome](CHROME_EXTENSION_SETUP.md) | Instalación de la extensión |
| [🤖 Integración IA](docs/OPENWEBUI_INTEGRATION.md) | Configurar Ollama/Open WebUI |
| [🔐 Autenticación 2FA](TESTING_2FA.md) | Configurar dos factores |

---

## 🛠️ Desarrollo

### Requisitos de desarrollo

```bash
# Instalar dependencias (si usas composer)
composer install

# Ejecutar tests
php vendor/bin/phpunit

# Validar código
php dev-tools/validate-simple.php
```

### Variables de entorno

```bash
# .env
DB_HOST=localhost
DB_NAME=pim_db
DB_USER=pim_user
DB_PASS=tu_contraseña

APP_DEBUG=false
APP_URL=https://tu-dominio.com/PIM

# Opcional: IA
OLLAMA_URL=http://localhost:11434
OPENWEBUI_API_KEY=sk-xxxxx
```

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas!

1. Fork el proyecto
2. Crea una rama (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Añade nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

---

## 📋 Roadmap

- [x] Módulo de notas con Markdown
- [x] Gestión de contactos (importar VCF)
- [x] Tareas con vista Kanban
- [x] Calendario de eventos
- [x] Gestor de links con extensión Chrome
- [x] Autenticación 2FA
- [x] Integración con Ollama/Open WebUI
- [x] API con autenticación por token
- [ ] App móvil (PWA)
- [ ] Docker compose
- [ ] Sincronización CalDAV/CardDAV
- [ ] Plugins/extensiones

---

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver [LICENSE](LICENSE) para más detalles.

---

<p align="center">
  <strong>📋 PIM</strong> - Tu información, tu servidor, tu control.
</p>

<p align="center">
  Made with ❤️ by <a href="https://github.com/matatunos">matatunos</a>
</p>

<p align="center">
  <a href="#-pim">⬆️ Volver arriba</a>
</p>
