# Instalación y configuración

## Requisitos

- PHP 8.3 con PDO MySQL, mbstring, fileinfo, GD, Imagick y ZipArchive.
- MySQL o MariaDB con tablas InnoDB y `utf8mb4_unicode_ci`.
- Apache con `mod_rewrite` y lectura de `.htaccess`.
- Composer.
- Node.js únicamente para validar JavaScript.
- `zip` para los backups completos.

## Código y dependencias

```bash
composer install --no-dev --prefer-dist --no-interaction
```

## Configuración local

Crear `.env` en la raíz. Debe definir, como mínimo:

```text
ENV_PRODUCTION
SITE_URL
DB_HOST
DB_NAME
DB_USER
DB_PASS
DB_CHARSET
SMTP_USER
SMTP_PASSWORD
```

Configurar localmente y sin versionar:

- las credenciales SMTP de Brevo en `.env`;
- `includes/aemet-config.php` para AEMET.

`includes/mail-config.php` conserva únicamente los parámetros SMTP no secretos
y sí forma parte del código. No copiar `.env` ni `includes/aemet-config.php`
entre entornos ni incluirlos en Git.

## Directorios escribibles

El usuario del servidor web necesita escritura únicamente donde la aplicación
genera datos:

- `uploads/`;
- `cache/`;
- `logs/`;
- `tmp/`;
- `storage/`;
- `backups/database/`;
- directorio `assets/css/app-css/min/` cuando se regenere el CSS de producción.

No conceder permisos globales `777`.

## Base de datos

Aplicar, por orden de nombre, las migraciones pendientes de `migrations/`.
Cada migración incluye su comprobación y procedimiento de reversión.
