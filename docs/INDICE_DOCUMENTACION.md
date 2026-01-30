# 📚 Índice de Documentación de PIM

## 📖 Archivos principales

### README.md
El archivo principal del proyecto con toda la información esencial.

**Secciones principales**:
- [¿Qué es PIM?](#-qué-es-pim) - Introducción y descripción
- [Características](#-características) - Tabla de módulos y funcionalidades
- [Capturas de Pantalla](#-capturas-de-pantalla) - Demos visuales
- [Características Avanzadas](#-características-avanzadas) - IA, API, Seguridad
- [Instalación](#-instalación) - Guía de setup
- [Extensión Chrome](#-extensión-chrome) - Instalación y uso
- [Integración IA](#-integración-con-ia) - Ollama/Open WebUI
- [Desarrollo](#-desarrollo) - Para contribuidores
- [Guías de Uso](#-guías-de-uso) - Tutoriales prácticos
- [Contribuciones](#-contribuir) - Cómo participar
- [Roadmap](#-roadmap-planificación) - Plan futuro
- [Licencia](#-licencia) - MIT

---

## 📸 Screenshots y Documentación

### Ubicación: `/docs/screenshots/`

#### 📋 README.md
Guía completa sobre las capturas de pantalla y cómo entenderlas.
- Descripción de cada módulo
- Elementos visuales
- Características especiales
- Paleta de colores

**Ver**: [docs/screenshots/README.md](../../docs/screenshots/README.md)

---

### Módulos documentados

#### 1. 📊 Dashboard
**Archivo**: `dashboard-detailed.txt` (140 líneas)

Pantallazo principal que muestra:
- Barra de navegación superior
- Menú lateral izquierdo
- Tarjetas de resumen (4 estadísticas)
- Acceso rápido a módulos
- Notas destacadas (3 tarjetas)
- Eventos del día
- Notificaciones recientes

**Incluye**:
- Estadísticas del proyecto
- Acciones disponibles
- Colores usados
- Dispositivos soportados
- Navegación rápida con teclas

**Ver**: [docs/screenshots/dashboard-detailed.txt](../../docs/screenshots/dashboard-detailed.txt)

---

#### 2. 📝 Notas
**Archivo**: `notas-detailed.txt` (165 líneas)

Sistema de captura de ideas con:
- Barra de búsqueda y filtros
- Etiquetado personalizable
- Grid de notas con colores
- Editor Markdown avanzado
- Autoguardado
- Historial de versiones

**Incluye**:
- Controles superiores
- Editor con vista previa
- Opciones de notas
- Colores disponibles
- Búsqueda y filtrado
- Atajos de teclado (Ctrl+N, Ctrl+S, etc.)
- Estadísticas (23 notas, 38 etiquetas, etc.)

**Ver**: [docs/screenshots/notas-detailed.txt](../../docs/screenshots/notas-detailed.txt)

---

#### 3. ✅ Tareas (Kanban)
**Archivo**: `kanban-detailed.txt` (185 líneas)

Tablero tipo Trello con:
- 3 columnas (Por hacer, En progreso, Completado)
- Arrastrar y soltar entre columnas
- Prioridades color-codificadas
- Subtareas y comentarios
- Visualización de progreso

**Incluye**:
- 12 tareas de ejemplo distribuidas
- Detalles de cada tarea
- Funcionalidades (crear, editar, filtrar)
- Estadísticas (50% completadas)
- Vistas alternativas (Lista, Gantt, Estadísticas)
- Atajos de teclado

**Ver**: [docs/screenshots/kanban-detailed.txt](../../docs/screenshots/kanban-detailed.txt)

---

#### 4. 📅 Calendario
**Archivo**: `calendario-detailed.txt` (200 líneas)

Gestor completo de eventos con:
- Vista mensual, semanal y diaria
- Código de colores por categoría
- Recordatorios configurables
- Participantes y conferencias
- Recurrencia de eventos

**Incluye**:
- 3 vistas diferentes (Mensual, Semanal, Diaria)
- Modal de creación de eventos
- Categorías (Reunión, Evento, Objetivo, etc.)
- Recordatorios (5 min, 10 min, 1 hora, 1 día)
- Integración con contactos
- Atajos de teclado

**Ver**: [docs/screenshots/calendario-detailed.txt](../../docs/screenshots/calendario-detailed.txt)

---

#### 5. 👥 Contactos
**Archivo**: `contactos-detailed.txt` (190 líneas)

Base de datos de relaciones con:
- Lista de contactos con búsqueda
- Panel de detalles completo
- Información de contacto (email, teléfono, dirección)
- Importación/exportación VCF
- Historial de interacciones
- Etiquetado y categorización

**Incluye**:
- 156 contactos totales
- Campos personalizables
- Importación desde VCF
- Exportación a múltiples formatos
- Búsqueda y filtros
- Operaciones en lote
- Detección de duplicados

**Ver**: [docs/screenshots/contactos-detailed.txt](../../docs/screenshots/contactos-detailed.txt)

---

#### 6. 🔗 Links/Marcadores
**Archivo**: `links-detailed.txt` (195 líneas)

Gestor de bookmarks con:
- Categorías jerárquicas
- Vista grid y lista
- Integración con extensión Chrome
- Captura rápida desde navegador
- Búsqueda por categoría/etiqueta
- Comentarios y anotaciones

**Incluye**:
- 93 links guardados
- Categorías personalizables (10 total)
- 24 etiquetas únicas
- Extensión Chrome para captura rápida
- Importación desde navegador
- Exportación a HTML/CSV/JSON
- Estadísticas de uso

**Ver**: [docs/screenshots/links-detailed.txt](../../docs/screenshots/links-detailed.txt)

---

## 📚 Documentación de Proyectos

### En el raíz del proyecto

| Documento | Propósito |
|-----------|-----------|
| [README.md](../../README.md) | Documentación principal (959 líneas) |
| [QUICK_START.md](../../QUICK_START.md) | Inicio rápido en 5 minutos |
| [CHROME_EXTENSION_SETUP.md](../../CHROME_EXTENSION_SETUP.md) | Instalación de extensión |
| [TESTING_2FA.md](../../TESTING_2FA.md) | Configurar autenticación 2FA |
| [2FA_DIAGNOSTICS.md](../../2FA_DIAGNOSTICS.md) | Troubleshooting de 2FA |
| [ANTIBOT_PROTECTION.md](../../ANTIBOT_PROTECTION.md) | Seguridad anti-bot |
| [OPENWEBUI_README.md](../../OPENWEBUI_README.md) | Integración con Open WebUI |
| [SETUP_CHECKLIST.sh](../../SETUP_CHECKLIST.sh) | Verificador de instalación |

### En `/docs/`

| Documento | Propósito |
|-----------|-----------|
| [screenshots/README.md](../../docs/screenshots/README.md) | Guía de capturas de pantalla |
| [OPENWEBUI_INTEGRATION.md](../../docs/OPENWEBUI_INTEGRATION.md) | Integración IA avanzada |
| [manual-usuario.html](../../docs/manual-usuario.html) | Manual completo en HTML |

---

## 🔍 Cómo usar esta documentación

### Para usuarios nuevos
1. Lee [README.md](../../README.md) - Intro general
2. Sigue [QUICK_START.md](../../QUICK_START.md) - Setup rápido
3. Mira screenshots en [screenshots/README.md](../../docs/screenshots/README.md)
4. Lee [Guías de Uso](#-guías-de-uso) en README.md

### Para instaladores
1. Sigue [Instalación](#-instalación) en README.md
2. Usa [SETUP_CHECKLIST.sh](../../SETUP_CHECKLIST.sh) para validar
3. Para HTTPS: lee sección de instalación en README.md

### Para usuarios 2FA
1. Lee [TESTING_2FA.md](../../TESTING_2FA.md)
2. Si hay problemas: [2FA_DIAGNOSTICS.md](../../2FA_DIAGNOSTICS.md)

### Para integración IA
1. Lee [Integración IA](#-integración-con-ia) en README.md
2. Detalles técnicos: [OPENWEBUI_INTEGRATION.md](../../docs/OPENWEBUI_INTEGRATION.md)

### Para desarrolladores
1. Lee [Desarrollo](#-desarrollo) en README.md
2. Clona y ejecuta localmente
3. Contribuye: [Contribuir](#-contribuir)

### Para extensión Chrome
1. Lee [Extensión Chrome](#-extensión-chrome) en README.md
2. Sigue [CHROME_EXTENSION_SETUP.md](../../CHROME_EXTENSION_SETUP.md)

---

## 🎯 Mapas de navegación

### Por propósito

**Quiero instalar PIM**
```
README.md → Instalación
         → QUICK_START.md (rápido)
         → SETUP_CHECKLIST.sh (validar)
         → HTTPS (Let's Encrypt)
```

**Quiero usar PIM**
```
README.md → Características (¿qué módulos hay?)
         → Guías de Uso (tutoriales)
         → screenshots/ → Documentación (ver cómo se ve)
         → manual-usuario.html (guía visual)
```

**Quiero la extensión Chrome**
```
README.md → Extensión Chrome
         → CHROME_EXTENSION_SETUP.md (pasos)
         → QUICK_START.md (usar extensión)
```

**Quiero IA inteligente**
```
README.md → Integración IA
         → OPENWEBUI_README.md (vista general)
         → docs/OPENWEBUI_INTEGRATION.md (técnico)
```

**Quiero seguridad 2FA**
```
README.md → Características Avanzadas (Seguridad)
         → TESTING_2FA.md (setup)
         → 2FA_DIAGNOSTICS.md (si falla)
```

**Quiero desarrollar**
```
README.md → Desarrollo
         → Contribuir
         → Clonar repo y seguir instrucciones
```

---

## 📊 Estadísticas de documentación

| Métrica | Valor |
|---------|-------|
| Total líneas documentation | 2,000+ |
| Archivos de documentación | 8 principales |
| Screenshots documentados | 6 módulos |
| Tablas informativas | 20+ |
| Ejemplos de código | 15+ |
| Atajos de teclado | 40+ |
| Imágenes/diagramas | ASCII art |

---

## ✅ Checklist de lectura recomendada

- [ ] Leí README.md (¿Qué es PIM?)
- [ ] Leí mi sección de interés
- [ ] Vi capturas de pantalla
- [ ] Realicé el setup/instalación
- [ ] Probé las características principales
- [ ] Leí guías específicas de módulos
- [ ] Pasé por troubleshooting si hay problemas
- [ ] Estoy listo para usar/contribuir

---

## 🔗 Enlaces útiles

- **GitHub**: https://github.com/matatunos/PIM
- **Issues**: https://github.com/matatunos/PIM/issues
- **Wiki** (próximamente): Guías comunitarias
- **Discussions**: Preguntas y respuestas

---

**Última actualización**: 30 de enero de 2026  
**Versión**: PIM 2.1.0  
**Documentador**: GitHub Copilot
