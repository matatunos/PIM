# 🐳 PIM - Despliegue con Docker

Guía completa para ejecutar PIM usando Docker Compose.

---

## 📋 Requisitos Previos

- **Docker** 20.10 o superior
- **Docker Compose** 2.0 o superior
- **2GB RAM** mínimo (4GB recomendado con IA)
- **5GB espacio** en disco (10GB con servicios de IA)

### Instalar Docker

**Linux (Ubuntu/Debian)**:
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
```

**macOS/Windows**: Descargar [Docker Desktop](https://www.docker.com/products/docker-desktop)

---

## 🚀 Inicio Rápido

### Método 1: Script automático (Recomendado)

```bash
./docker-start.sh
```

El script te guiará por:
1. Verificación de requisitos
2. Creación del archivo `.env`
3. Opción de servicios de IA
4. Construcción e inicio de contenedores

### Método 2: Manual

1. **Crear archivo de configuración**:
```bash
cp .env.docker .env
nano .env  # Editar contraseñas
```

2. **Iniciar sin IA**:
```bash
docker compose up -d
```

3. **Iniciar con IA** (Ollama + Open WebUI):
```bash
docker compose --profile ai up -d
```

4. **Acceder a la aplicación**:
- PIM: http://localhost:8080
- Open WebUI (si activado): http://localhost:3000

---

## 🏗️ Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                    Docker Network (pim-network)             │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │   pim-web    │───▶│   pim-db     │    │ pim-ollama   │ │
│  │ PHP 8.2      │    │ MariaDB 10.11│    │ (opcional)   │ │
│  │ Apache       │    │              │    │              │ │
│  │ :8080        │    │ :3306        │    │ :11434       │ │
│  └──────────────┘    └──────────────┘    └──────────────┘ │
│         │                                        │          │
│         └────────────┐                 ┌─────────┘          │
│                      │                 │                    │
│               ┌──────▼─────────────────▼──┐                │
│               │   pim-openwebui          │                 │
│               │   (opcional)             │                 │
│               │   :3000                  │                 │
│               └──────────────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

### Servicios

| Servicio | Puerto | Descripción | Perfil |
|----------|--------|-------------|--------|
| **pim-web** | 8080 | Aplicación PHP + Apache | Siempre |
| **pim-db** | 3306 | Base de datos MariaDB | Siempre |
| **pim-ollama** | 11434 | LLM local (IA) | `ai` |
| **pim-openwebui** | 3000 | Interfaz web para IA | `ai` |

### Volúmenes Persistentes

| Volumen | Contenido |
|---------|-----------|
| `db_data` | Base de datos MariaDB |
| `uploads_data` | Archivos subidos por usuarios |
| `logs_data` | Logs de aplicación |
| `ollama_data` | Modelos de IA descargados |
| `openwebui_data` | Configuración de Open WebUI |

---

## ⚙️ Configuración

### Variables de entorno (.env)

```bash
# Base de datos
DB_ROOT_PASSWORD=rootpassword_change_me
DB_NAME=pim_db
DB_USER=pim_user
DB_PASS=pim_secure_password_123

# Aplicación
APP_PORT=8080
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=change-this-to-a-random-secret-key

# IA (opcional)
OPENWEBUI_API_KEY=your-api-key
OPENWEBUI_URL=http://openwebui:3000
OLLAMA_URL=http://ollama:11434
```

⚠️ **IMPORTANTE**: Cambia todas las contraseñas antes de usar en producción.

### Generar JWT Secret seguro

```bash
openssl rand -base64 32
```

---

## 📦 Comandos Útiles

### Gestión básica

```bash
# Iniciar todos los servicios
docker compose up -d

# Iniciar con servicios de IA
docker compose --profile ai up -d

# Parar todos los servicios
docker compose down

# Parar y eliminar volúmenes (⚠️ borra datos)
docker compose down -v

# Reiniciar un servicio
docker compose restart web

# Ver estado
docker compose ps

# Ver logs
docker compose logs -f

# Ver logs de un servicio específico
docker compose logs -f web
```

### Mantenimiento

```bash
# Entrar al contenedor web
docker compose exec web bash

# Entrar a la base de datos
docker compose exec db mysql -u pim_user -p pim_db

# Ver uso de recursos
docker stats

# Limpiar imágenes antiguas
docker system prune -a

# Backup de base de datos
docker compose exec db mysqldump -u pim_user -p pim_db > backup.sql

# Restaurar backup
docker compose exec -T db mysql -u pim_user -p pim_db < backup.sql
```

### Actualizar PIM

```bash
# Obtener últimos cambios
git pull

# Reconstruir imágenes
docker compose build

# Reiniciar con nueva versión
docker compose up -d
```

---

## 🤖 Servicios de IA (Opcional)

### Activar Ollama + Open WebUI

```bash
docker compose --profile ai up -d
```

### Descargar un modelo de IA

```bash
# Entrar al contenedor de Ollama
docker compose exec ollama bash

# Descargar modelo (ej: llama2)
ollama pull llama2

# Listar modelos instalados
ollama list

# Probar modelo
ollama run llama2 "Hola, ¿cómo estás?"
```

### Modelos recomendados

| Modelo | Tamaño | Descripción |
|--------|--------|-------------|
| `llama2` | 3.8GB | Modelo general, buen equilibrio |
| `mistral` | 4.1GB | Rápido y eficiente |
| `codellama` | 3.8GB | Especializado en código |
| `phi` | 1.6GB | Ligero, para equipos limitados |

### Configurar Open WebUI

1. Accede a http://localhost:3000
2. Crea una cuenta (primer usuario es admin)
3. Ve a Settings → Connections
4. Verifica que Ollama URL sea: `http://ollama:11434`

---

## 🔒 Seguridad

### Producción

1. **Cambiar contraseñas**:
   - DB_ROOT_PASSWORD
   - DB_PASS
   - JWT_SECRET
   - WEBUI_SECRET_KEY

2. **Usar HTTPS**:
```bash
# Añadir Nginx reverse proxy con SSL
docker compose -f docker-compose.yml -f docker-compose.ssl.yml up -d
```

3. **Firewall**:
```bash
# Permitir solo puerto web
sudo ufw allow 8080/tcp
sudo ufw enable
```

4. **Backups automáticos**:
```bash
# Cron para backup diario
0 2 * * * docker compose exec db mysqldump -u pim_user -p$DB_PASS pim_db | gzip > /backups/pim_$(date +\%Y\%m\%d).sql.gz
```

### Límites de recursos

Editar `docker-compose.yml`:

```yaml
services:
  web:
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 512M
        reservations:
          cpus: '0.5'
          memory: 256M
```

---

## 🐛 Troubleshooting

### Contenedor web no inicia

```bash
# Ver logs detallados
docker compose logs web

# Verificar permisos
docker compose exec web ls -la /var/www/html

# Recrear contenedor
docker compose up -d --force-recreate web
```

### Error de conexión a base de datos

```bash
# Verificar que DB esté corriendo
docker compose ps db

# Ver logs de DB
docker compose logs db

# Verificar credenciales en .env
cat .env | grep DB_

# Probar conexión manual
docker compose exec web php -r "new PDO('mysql:host=db;dbname=pim_db', 'pim_user', 'password');"
```

### Problemas con Ollama

```bash
# Verificar si está corriendo
docker compose ps ollama

# Ver logs
docker compose logs ollama

# Reiniciar
docker compose restart ollama

# Verificar modelos instalados
docker compose exec ollama ollama list
```

### Puerto en uso

```bash
# Cambiar puerto en .env
echo "APP_PORT=9090" >> .env

# Reiniciar
docker compose up -d
```

### Limpiar y reiniciar

```bash
# Parar todo
docker compose down

# Eliminar volúmenes (⚠️ borra datos)
docker compose down -v

# Limpiar caché de Docker
docker system prune -a

# Reconstruir desde cero
docker compose build --no-cache
docker compose up -d
```

---

## 📊 Monitoreo

### Ver métricas en tiempo real

```bash
docker stats
```

### Ver uso de disco

```bash
docker system df
docker volume ls
docker volume inspect pim_db_data
```

### Health checks

```bash
# Estado de salud de servicios
docker compose ps --format json | jq '.[] | {name: .Name, health: .Health}'

# Verificar endpoint de salud
curl http://localhost:8080/
```

---

## 🌐 Despliegue en Producción

### Con dominio propio

1. **Configurar DNS**:
   - Apuntar `pim.tudominio.com` a tu IP

2. **Usar Nginx Proxy Manager** (recomendado):
```bash
# docker-compose.prod.yml
version: '3.8'
services:
  nginx-proxy:
    image: jc21/nginx-proxy-manager:latest
    ports:
      - "80:80"
      - "443:443"
      - "81:81"
    volumes:
      - nginx_data:/data
      - letsencrypt:/etc/letsencrypt
```

3. **Configurar SSL automático** en Nginx Proxy Manager UI

### Variables de entorno para producción

```bash
APP_ENV=production
APP_DEBUG=false
APP_PORT=80  # Si usas proxy
```

---

## 📚 Recursos Adicionales

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/)
- [Ollama Models](https://ollama.ai/library)
- [Open WebUI Docs](https://docs.openwebui.com/)
- [PIM GitHub](https://github.com/matatunos/PIM)

---

## 🆘 Soporte

- GitHub Issues: https://github.com/matatunos/PIM/issues
- Documentación: [README.md](../README.md)
- Manual de usuario: [manual-usuario.md](manual-usuario.md)

---

📋 **PIM** - Personal Information Manager  
Documentación Docker actualizada: 30 de enero de 2026
