# Estructura y flujo

La aplicación conserva una arquitectura PHP procedimental.

- `index.php`: front controller.
- `includes/config.php`: configuración local, constantes, sesión y modo de
  mantenimiento; no carga módulos funcionales.
- `includes/bootstrap.php`: carga común explícita de conexión, rutas, helpers,
  correo, utilidades de noticias y notificaciones.
- `includes/routes.php`: nombres de ruta y destinos físicos.
- `includes/helpers/`: utilidades reutilizables.
- `includes/modules/`: lógica aislada de módulos públicos sin nuevas capas de
  aplicación.
- `includes/data/`: datos locales versionados y sus fuentes documentales.
- `admin/`: administración.
- `periodista/`: funciones públicas del articulista.
- `privado/`: contenido privado para colaboradores autorizados.
- `usuario/`: área del comentarista.
- `public/`: páginas públicas.
- `partials/`: cabecera, footer y navegación compartida.
- `assets/`: CSS, JavaScript e imágenes de interfaz.
- `migrations/`: cambios versionados de esquema o datos estructurales.
- `scripts/`: comprobaciones y tareas técnicas no públicas.

Las imágenes de los bloques RSS externos se generan como miniaturas WebP en
la caché privada y se publican exclusivamente mediante `public/rss-image.php`;
las visitas a portada no descargan ni transforman imágenes de terceros.

Flujo habitual de una página:

1. cargar `bootstrap.php`;
2. comprobar rol o propiedad;
3. validar método, CSRF y entrada;
4. ejecutar consultas preparadas o helpers;
5. preparar datos;
6. mostrar la página o redirigir mediante una ruta registrada.

Los puntos de entrada web y CLI deben cargar `bootstrap.php`. Los archivos
internos que solo necesitan constantes pueden cargar `config.php`, evitando
depender de efectos laterales para disponer de funciones o conexión.

Las noticias privadas deben permanecer separadas de las públicas en consultas,
comentarios, reportes, valoraciones, búsquedas y enlaces.

Los formularios y procesadores de reportes comparten mediante
`includes/helpers/reportes.php` el catálogo de motivos, la normalización de la
entrada y las consultas que delimitan el contenido reportable. El indicador de
ámbito mantiene separadas las noticias y los comentarios públicos y privados.

Los perfiles de Admin, Articulista y Comentarista mantienen controladores y
vistas propios, pero comparten en `includes/helpers/perfil.php` las reglas de
datos personales y contraseña. La autorización, los mensajes y el tratamiento
de avatar permanecen en cada área.

Las lecturas reutilizadas por la portada y los listados públicos se concentran
en `includes/helpers/noticias.php`. Las páginas validan la entrada, llaman al
helper y preparan la vista; no duplican el SQL de conteo, filtros o paginación.

## Recursos de interfaz

- `assets/css/app-css/news-cards.css` define la base común de las tarjetas.
  Cada página añade solamente su variante contextual para evitar que un ajuste
  local rompa otros listados.
- `partials/noticias/tarjeta-listado-publico.php` comparte el HTML de las
  tarjetas equivalentes de fuente y ubicación, conservando sus variantes CSS.
- El resto del CSS reutilizable concentra formularios, tablas y modales.
- `assets/css/app-css/min/` contiene las copias CSS regeneradas para producción.
- JavaScript se sirve desde una única fuente en `assets/js/app-js/`, con una
  versión de caché en la URL; no se mantienen copias `.min.js` idénticas.
- Navegación, cookies, reportes y modales relacionados utilizan scripts comunes.

## Clasificación de noticias

- categoría, fuente, ubicación e imagen principal son datos editoriales
  obligatorios;
- `public/categoria.php`, `public/fuente.php` y `public/ubicacion.php` ofrecen
  listados públicos independientes;
- la ubicación distingue provincia española, lugar internacional u otra
  ubicación textual;
- las importaciones RSS reciben una ubicación común y permiten corregir cada
  noticia posteriormente desde la edición habitual.

## Eliminación y almacenamiento

Las noticias deben eliminarse mediante `eliminarNoticiasCompletamente()`. El
helper comprueba propiedad, elimina la noticia y sus dependencias y retira los
archivos locales que ya no están compartidos.

Los uploads se validan antes de procesarse y vuelven a comprobar la cuota con
el tamaño real de la imagen optimizada o del vídeo almacenado.

## Módulo de pobreza

- `public/pobreza.php`: interfaz, selección validada y preparación de gráficas;
- `includes/modules/pobreza.php`: descarga HTTPS, validación, normalización y
  caché de INE y Eurostat;
- `includes/data/gobiernos-autonomicos.php` y
  `includes/data/gobiernos-europeos.php`: contexto político local versionado;
- `assets/js/app-js/public-pobreza.js`: gráficas y controles progresivos;
- `assets/css/app-css/public-pobreza.css`: presentación responsive.

Las consultas externas usan destinos fijos, verificación TLS, límites de
tiempo y tamaño y caché con renovación exclusiva. Si el proveedor falla, se
sirve temporalmente una copia válida anterior, sin mostrar errores internos.
