# 📅 PIM - Sincronización CalDAV/CardDAV

Guía completa para sincronizar tu calendario y contactos de PIM con cualquier dispositivo o aplicación compatible.

---

## 📋 Índice

- [¿Qué es CalDAV/CardDAV?](#qué-es-caldavcardav)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [URLs de Conexión](#urls-de-conexión)
- [Configuración por Cliente](#configuración-por-cliente)
- [Troubleshooting](#troubleshooting)

---

## 🤔 ¿Qué es CalDAV/CardDAV?

**CalDAV** y **CardDAV** son protocolos estándar que permiten sincronizar calendarios y contactos entre diferentes dispositivos y aplicaciones.

### Ventajas

✅ **Sincronización bidireccional**: Los cambios se reflejan en todos los dispositivos  
✅ **Multi-plataforma**: Compatible con iOS, Android, Windows, macOS, Linux  
✅ **Estándar abierto**: Funciona con cualquier aplicación compatible  
✅ **Tiempo real**: Los cambios se sincronizan automáticamente  

---

## ✅ Requisitos

### Servidor

1. **Composer** instalado:
```bash
cd /opt/PIM
composer install
```

2. **Extensión PHP** `php-xml` instalada:
```bash
# Ubuntu/Debian
sudo apt install php-xml

# Verificar
php -m | grep xml
```

3. **mod_rewrite** de Apache activado:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Cliente

Cualquier aplicación que soporte CalDAV/CardDAV:
- iOS/iPadOS (nativo)
- Android (con DAVx⁵)
- macOS (nativo)
- Windows (con Thunderbird + plugins)
- Linux (Evolution, GNOME Calendar, etc.)

---

## 🚀 Instalación

### 1. Instalar dependencias

```bash
cd /opt/PIM
composer install
```

### 2. Verificar permisos

```bash
chmod -R 755 dav/
chmod -R 755 includes/dav/
```

### 3. Probar servidor

Accede a: `https://tu-servidor/PIM/dav/`

Deberías ver una interfaz web simple de SabreDAV.

---

## 🔗 URLs de Conexión

### CalDAV (Calendario)

```
https://tu-servidor/PIM/dav/calendars/TU_USUARIO/default/
```

### CardDAV (Contactos)

```
https://tu-servidor/PIM/dav/addressbooks/TU_USUARIO/contacts/
```

### URL Base (Auto-descubrimiento)

```
https://tu-servidor/PIM/dav/
```

**Credenciales**: Tu usuario y contraseña de PIM

---

## 📱 Configuración por Cliente

### iOS / iPadOS

#### Calendario (CalDAV)

1. **Ajustes** → **Calendario** → **Cuentas** → **Añadir cuenta** → **Otra**
2. Selecciona **Añadir cuenta CalDAV**
3. Introduce:
   - **Servidor**: `tu-servidor/PIM/dav/`
   - **Usuario**: Tu usuario de PIM
   - **Contraseña**: Tu contraseña de PIM
   - **Descripción**: PIM Calendar
4. Toca **Siguiente**
5. Activa **Calendarios**

#### Contactos (CardDAV)

1. **Ajustes** → **Contactos** → **Cuentas** → **Añadir cuenta** → **Otra**
2. Selecciona **Añadir cuenta CardDAV**
3. Introduce los mismos datos que antes
4. Toca **Siguiente**
5. Activa **Contactos**

---

### Android (con DAVx⁵)

#### Instalación

1. Instala **DAVx⁵** desde:
   - [Google Play](https://play.google.com/store/apps/details?id=at.bitfire.davdroid)
   - [F-Droid](https://f-droid.org/packages/at.bitfire.davdroid/)

#### Configuración

1. Abre **DAVx⁵**
2. Toca **+** (añadir cuenta)
3. Selecciona **Login with URL and username**
4. Introduce:
   - **Base URL**: `https://tu-servidor/PIM/dav/`
   - **User name**: Tu usuario
   - **Password**: Tu contraseña
5. Toca **Login**
6. Selecciona los calendarios y libretas de direcciones a sincronizar
7. DAVx⁵ creará una cuenta de Android
8. Los eventos y contactos aparecerán en las apps nativas

#### Apps recomendadas

- **Calendario**: Google Calendar, Simple Calendar
- **Contactos**: Google Contacts, Simple Contacts

---

### macOS

#### Calendario (CalDAV)

1. Abre **Calendar**
2. **Calendar** → **Add Account** → **Other CalDAV Account**
3. Introduce:
   - **Account Type**: Advanced
   - **User Name**: Tu usuario
   - **Password**: Tu contraseña
   - **Server Address**: `tu-servidor`
   - **Server Path**: `/PIM/dav/calendars/TU_USUARIO/default/`
   - **Port**: 443 (SSL habilitado)
4. Click **Sign In**

#### Contactos (CardDAV)

1. Abre **Contacts**
2. **Contacts** → **Add Account** → **Other Contacts Account**
3. Introduce:
   - **Account Type**: CardDAV
   - **User Name**: Tu usuario
   - **Password**: Tu contraseña  
   - **Server Address**: `tu-servidor/PIM/dav/`
4. Click **Sign In**

---

### Windows (Thunderbird)

#### Instalación

1. Instala [Thunderbird](https://www.thunderbird.net/)
2. Instala el addon [TbSync](https://addons.thunderbird.net/thunderbird/addon/tbsync/)
3. Instala [Provider for CalDAV & CardDAV](https://addons.thunderbird.net/thunderbird/addon/dav-4-tbsync/)

#### Configuración

1. Abre **Thunderbird** → **Tools** → **Add-ons** → **TbSync**
2. Click **Account actions** → **Add new account** → **CalDAV & CardDAV**
3. Selecciona **Automatic configuration**
4. Introduce:
   - **Server URL**: `https://tu-servidor/PIM/dav/`
   - **User**: Tu usuario
   - **Password**: Tu contraseña
5. Click **Next**
6. Selecciona calendarios y libretas a sincronizar
7. Click **Synchronize**

---

### Linux (GNOME)

#### GNOME Calendar (CalDAV)

1. Abre **GNOME Calendar**
2. Click en el menú (☰) → **Add Calendar**
3. Selecciona **CalDAV**
4. Introduce:
   - **URL**: `https://tu-servidor/PIM/dav/calendars/TU_USUARIO/default/`
   - **Username**: Tu usuario
   - **Password**: Tu contraseña
5. Click **Add**

#### GNOME Contacts (CardDAV)

1. Abre **GNOME Contacts**
2. Click en el menú (☰) → **Accounts**
3. Click **+** → **Other**
4. Selecciona **CalDAV**
5. Introduce los datos igual que en el calendario
6. Click **Add**

#### Evolution

1. Abre **Evolution**
2. **File** → **New** → **Calendar** o **Address Book**
3. Tipo: **CalDAV** o **CardDAV**
4. Introduce:
   - **URL**: La URL correspondiente
   - **User**: Tu usuario
   - **Password**: Tu contraseña
5. Click **OK**

---

## 🔧 Configuración Avanzada

### HTTPS Requerido

⚠️ **IMPORTANTE**: CalDAV/CardDAV requiere HTTPS en producción. Los clientes pueden rechazar conexiones HTTP no seguras.

#### Obtener certificado SSL gratuito

```bash
# Instalar Certbot
sudo apt install certbot python3-certbot-apache

# Obtener certificado
sudo certbot --apache -d tu-dominio.com

# Renovación automática (ya configurada por Certbot)
```

### Configuración Apache

Añade a tu VirtualHost:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /PIM/dav/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ /PIM/dav/server.php [QSA,L]
</IfModule>

<Directory /var/www/html/PIM/dav>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    
    # Deshabilitar BufferedLogs para evitar problemas con DAV
    php_flag output_buffering off
</Directory>
```

### Configuración Nginx

```nginx
location ^~ /PIM/dav/ {
    alias /var/www/html/PIM/dav/;
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
    
    rewrite ^/PIM/dav/(.*)$ /PIM/dav/server.php/$1 last;
}
```

---

## 🐛 Troubleshooting

### Error: "Could not connect to server"

**Causa**: Problema de red o URL incorrecta

**Solución**:
1. Verifica que la URL sea correcta
2. Prueba acceder desde un navegador
3. Verifica que el servidor esté accesible desde internet
4. Revisa firewall y puertos

```bash
# Verificar que el puerto 443 esté abierto
sudo ufw status
sudo ufw allow 443/tcp
```

### Error: "Authentication failed"

**Causa**: Usuario o contraseña incorrectos

**Solución**:
1. Verifica tus credenciales
2. Prueba iniciar sesión en la web de PIM
3. Si tienes 2FA activado, desactívalo temporalmente para DAV

### Error: "SSL certificate problem"

**Causa**: Certificado SSL inválido o auto-firmado

**Solución**:
1. Usa un certificado válido (Let's Encrypt es gratis)
2. En desarrollo, algunos clientes permiten ignorar errores SSL (no recomendado)

### No aparecen eventos/contactos

**Causa**: Sincronización inicial no completada

**Solución**:
1. Fuerza sincronización manual en el cliente
2. iOS: Ajustes → Calendario → Fetch New Data
3. Android: DAVx⁵ → tu cuenta → Sincronizar ahora
4. Verifica logs del servidor

### Verificar logs

```bash
# Logs de Apache
sudo tail -f /var/log/apache2/error.log

# Logs de PHP
sudo tail -f /var/www/html/PIM/logs/error.log
```

### Activar debug en SabreDAV

Edita `dav/server.php` y añade antes de `$server->exec()`:

```php
// Debug mode
$server->debugExceptions = true;
```

### Probar conexión con curl

```bash
# CalDAV PROPFIND
curl -X PROPFIND \
  -u usuario:contraseña \
  -H "Depth: 1" \
  -H "Content-Type: application/xml" \
  https://tu-servidor/PIM/dav/calendars/usuario/default/

# CardDAV PROPFIND
curl -X PROPFIND \
  -u usuario:contraseña \
  -H "Depth: 1" \
  -H "Content-Type: application/xml" \
  https://tu-servidor/PIM/dav/addressbooks/usuario/contacts/
```

---

## 📊 Limitaciones Actuales

| Característica | Estado |
|----------------|--------|
| Sincronización calendario | ✅ Completa |
| Sincronización contactos | ✅ Completa |
| Eventos recurrentes | ⚠️ Parcial |
| Invitaciones | ❌ No soportado |
| Calendarios compartidos | ❌ No soportado |
| Foto de contacto | ⚠️ Básico |
| Grupos de contactos | ❌ No soportado |

---

## 🔒 Seguridad

### Mejores prácticas

1. **Usa siempre HTTPS** en producción
2. **Contraseñas fuertes** para las cuentas
3. **Limita acceso** con firewall
4. **Backups regulares** de la base de datos
5. **Monitorea logs** en busca de accesos sospechosos

### Deshabilitar DAV para usuarios

Si quieres deshabilitar CalDAV/CardDAV para algunos usuarios:

```sql
-- Añadir campo a la tabla usuarios
ALTER TABLE usuarios ADD COLUMN dav_enabled BOOLEAN DEFAULT 1;

-- Deshabilitar para un usuario
UPDATE usuarios SET dav_enabled = 0 WHERE username = 'usuario';
```

Luego modifica `includes/dav/AuthBackend.php` para verificar este campo.

---

## 🆘 Soporte

- **Documentación SabreDAV**: https://sabre.io/dav/
- **RFC CalDAV**: https://tools.ietf.org/html/rfc4791
- **RFC CardDAV**: https://tools.ietf.org/html/rfc6352
- **Issues**: https://github.com/matatunos/PIM/issues

---

📋 **PIM** - Personal Information Manager  
Documentación CalDAV/CardDAV actualizada: 30 de enero de 2026
