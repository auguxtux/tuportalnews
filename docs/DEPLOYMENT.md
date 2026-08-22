# Despliegue y reversión

## Preparación

1. Trabajar y validar en una rama de desarrollo creada desde `main`.
2. Ejecutar `scripts/check-version.sh`.
3. Confirmar un árbol Git limpio y dependencias bloqueadas.
4. Crear un backup reciente de producción.
5. Revisar las migraciones pendientes y su reversión.
6. Crear el commit y la etiqueta de versión autorizados.

## Despliegue

Desplegar únicamente código y migraciones. Conservar en producción:

- `.env` y credenciales locales;
- configuración SMTP y AEMET;
- base de datos y sesiones;
- uploads, logs, cachés y backups.

Las cachés de INE, Eurostat, ONU y UNICEF ubicadas en `storage/cache/` son
datos locales y no deben versionarse. El servidor necesita salida HTTPS hacia
los endpoints oficiales de estos organismos; una caché anterior permite
tolerar una caída temporal, pero la primera carga requiere acceso a los
proveedores. ONU consulta como máximo seis países por petición y cada
proveedor conserva su error y caché de forma independiente. Las fuentes
internacionales de actualización lenta se renuevan semanalmente; la tabla MPI
se renueva mensualmente.

El catálogo NASA necesita salida HTTPS hacia `images-api.nasa.gov` y
`images-assets.nasa.gov`. Su caché vive en `storage/cache/nasa/`, no se
versiona y puede recrearse automáticamente. Antes de desplegar el módulo deben
aplicarse las migraciones de `video_tipo` y `medio_principal`.

Aplicar las migraciones pendientes una a una y ejecutar su verificación.
No copiar la base de datos de desarrollo a producción.

Para los bloques RSS externos aplicar:

- `20260820_seleccionar_fuentes_rss_externas.sql`: añade la bandera
  `mostrar_externas` a `fuentes_rss`, inicialmente desactivada. Después del
  despliegue el Admin elige los medios visibles y ejecuta una vez
  `scripts/actualizar-cache-rss.php` para preparar sus cachés.

El cron de `scripts/actualizar-cache-rss.php` debe ejecutarse cada 15 minutos.
La portada y `/public/rss-feed` solo leen `storage/cache/rss/` y nunca esperan
a los proveedores durante una visita pública. El mismo cron valida y convierte
las imágenes externas a WebP 320×180 en `storage/cache/rss/images/`; requiere
Imagick con soporte WebP. `/public/rss-image` sirve esas miniaturas con caché
inmutable de 30 días y sin iniciar sesión. `cron/limpiar-rss.php` retira las
miniaturas que llevan más de tres días sin renovarse.

La misma tarea prepara variantes WebP de 320, 640 y 960 píxeles para las
imágenes externas de noticias públicas. Las tarjetas eligen la variante con
`srcset`; la página completa conserva el recurso original. Una renovación
descarga cada original una sola vez aunque genere los tres tamaños.

Las imágenes subidas de noticias públicas conservan también su original y la
tarea prepara derivados WebP de 320 y 640 píxeles para las tarjetas. Estos
derivados comparten el endpoint privado de caché y se regeneran automáticamente
si cambia la fecha de modificación del archivo fuente.

Para 1.0.0 revisar y aplicar, si todavía están pendientes:

- `20260813_eliminar_fecha_nacimiento_usuarios.sql`: elimina la columna que
  la aplicación ya no solicita; requiere backup porque los valores anteriores
  no pueden reconstruirse;
- `20260813_sincronizar_valoraciones_noticias.sql`: recalcula los acumulados
  desde los votos existentes y puede repetirse de forma segura.
- `20260813_asignar_ubicacion_noticias.sql`: asigna “Sin ubicación” únicamente
  a noticias antiguas que carecen de una ubicación válida.

## Verificación posterior

- comprobar portada, login, categorías, fuentes, noticias por lugares, noticia,
  tiempo y catálogo NASA;
- comprobar que cada fuente activa marcada como externa muestra cuatro enlaces
  en una pestaña nueva y que desmarcarla retira su bloque;
- comprobar que las tarjetas y metadatos coinciden con desarrollo y regenerar
  las copias CSS antes de activar el modo de producción;
- comprobar permisos de administrador, articulista y colaborador;
- ejecutar el flujo afectado por cada migración;
- revisar respuestas 500 y logs recientes;
- confirmar versión y commit desplegados.

Confirmar además que los scripts se sirven desde
`assets/js/app-js/` y que las antiguas rutas JavaScript `/min/*.min.js` no son
necesarias.

## Backups y restauración

La restauración de archivos extrae y valida primero el ZIP en `tmp/`. Cada
archivo se sustituye mediante un nombre temporal, pero MySQL y el sistema de
archivos no comparten una única transacción. Por ello sigue siendo obligatorio
crear un backup reciente antes de una restauración completa y verificar el
resultado antes de reabrir el portal.

## Reversión

1. Detener el despliegue ante la primera prueba fallida.
2. Recuperar el commit o artefacto anterior sin reescribir el historial.
3. Ejecutar la reversión documentada de la migración afectada o restaurar el
   backup previo si la migración no es reversible.
4. Conservar los datos y archivos locales de producción.
5. Repetir las comprobaciones de la versión anterior.
