# Pruebas

## Comprobación automática

Desde la raíz del proyecto:

```bash
scripts/check-version.sh
```

Opcionalmente se puede indicar otro dominio:

```bash
scripts/check-version.sh https://dominio-de-prueba.example
```

El script valida PHP, JavaScript, destinos de rutas, dependencias Composer,
formato del diff, páginas públicas y bloqueo de archivos internos.

## Pruebas manuales mínimas

### Paneles por perfil (1.0.0)

- abrir los paneles de Admin, Comentarista, Articulista y Colaborador;
- comprobar colores, símbolos, navegación responsive y enlaces permitidos;
- confirmar que un Articulista sin permiso privado no accede al panel privado;
- revisar las tablas de Categorías y Fuentes, incluida su navegación mutua;
- comprobar en Configuración las ayudas de registro, moderación,
  almacenamiento, mantenimiento y minificación sin cambiar valores críticos.

### Visitante

- portada, categorías, fuentes, noticias por lugares, buscadores, noticia,
  tiempo, pobreza, NASA y RSS;
- comprobar tarjetas en móvil, tableta y escritorio, incluyendo imágenes sin
  deformación y enlaces de sus metadatos;
- registro, login y recuperación de contraseña;
- archivos internos inaccesibles.

### Comentarista

- perfil y logout;
- comentar y editar comentarios propios;
- favoritos, valoraciones y reportes;
- imposibilidad de acceder a administración o contenido privado.

### Articulista

- crear, editar y eliminar noticias propias;
- subir imagen principal y contenido del editor;
- seleccionar imágenes y un vídeo NASA, comprobar sus créditos y alternar
  imagen/vídeo como medio principal;
- gestionar fuentes RSS e importar manualmente;
- importar varias noticias RSS con una ubicación común y comprobar que ninguna
  se guarda sin categoría, fuente, ubicación o imagen principal;
- abrir Comentarios recibidos y verificar agrupación y enlaces por noticia;
- mantener inaccesible el contenido privado sin autorización.

### Colaborador

- conservar todas las funciones del articulista público;
- crear, buscar, comentar y reportar contenido privado;
- comprobar que su correo corporativo aparece en el panel y que el botón abre
  `https://webmail.erun.es` en una pestaña nueva;
- confirmar que contenido y acciones privadas no aparecen en páginas públicas.
- crear y editar una noticia con imagen, vídeo MP4 y PDF en la galería;
  comprobar reproducción, apertura del documento, descripciones, cuota y
  eliminación de los archivos sustituidos.

### Administrador

- usuarios, periodistas, categorías, noticias y comentarios;
- asignar, modificar y retirar un correo `@erun.es` desde Colaboradores, y
  rechazar direcciones de otros dominios;
- reportes, fuentes, ataques, logs, configuración y minificación;
- confirmar un reporte público y otro privado, comprobar su aviso solo en el
  ámbito correspondiente y verificar que no revela datos del denunciante;
- filtrar los listados `/public/reportes` y `/privado/reportes` por noticias y
  comentarios, comprobando que no mezclan sus ámbitos;
- crear, descargar y restaurar un backup controlado;
- diagnóstico y documentación.
- filtrar perfiles por rol y estado de conexión, y comprobar que los contadores
  aumentan tras un nuevo login y al mantener actividad durante más de un minuto.
- iniciar sesión como Admin secundario y comprobar que el panel y la guía
  muestran ese nivel, sus límites y los enlaces “Mis Articulistas” y “Mis
  Colaboradores”;
- crear un Articulista y un Colaborador con ese Admin, comprobar que aparecen
  en sus filtros y que permite activar, desactivar, bloquear y eliminar esas
  cuentas;
- comprobar que el Admin secundario puede conceder o retirar acceso privado,
  pero no puede editar cuentas ajenas ni actuar sobre otro Admin o el Root;
- iniciar sesión como Root y confirmar que la guía muestra administración
  global, promoción a Admin y reversión de Admin a Colaborador.

### Regresiones críticas

- eliminar una noticia de prueba desde Noticias y desde Reportes y confirmar
  que desaparecen comentarios, relaciones, reportes y archivos locales;
- crear un backup completo y verificar que una restauración controlada termina
  con base de datos y archivos confirmados;
- borrar `cookie_consent` del almacenamiento local y probar aceptar, rechazar,
  configurar, Escape y cierre exterior;
- comprobar que una noticia RSS solo utiliza imágenes o vídeos HTTPS;
- subir una imagen y un vídeo cerca de la cuota y confirmar que el tamaño final
  no permite superar el límite;
- confirmar que Administración y archivos internos siguen inaccesibles sin
  sesión.

Registrar cualquier prueba no realizada antes de etiquetar una versión.

### Módulo NASA

- abrir `/nasa`, comprobar las 24 tarjetas y realizar una búsqueda libre;
- filtrar solo imágenes, solo vídeos y ambos tipos;
- comprobar Imágenes recientes, Vídeos recientes, Noticias, Proyectos y Misiones;
- desde una noticia nueva, abrir el selector modal, añadir una imagen principal,
  imágenes de galería y un vídeo sin perder el formulario;
- publicar con vídeo principal y comprobar reparto 40/60 en escritorio y una
  columna en móvil;
- editar la noticia, cambiar a imagen principal y confirmar que la miniatura se
  mantiene en portada y listados;
- comprobar que el endpoint de vídeo devuelve 403 sin sesión y que solo se
  aceptan recursos de `images-assets.nasa.gov`.

### Módulo de pobreza

- abrir `/pobreza` sin filtros y confirmar las referencias de España y UE-27;
- seleccionar hasta seis comunidades y seis países de cada proveedor, cambiar
  el periodo y comprobar las gráficas y tablas de INE, Eurostat, ONU y UNICEF;
- confirmar que ONU muestra pobreza multidimensional (ODS 1.2.2) y UNICEF
  pobreza infantil bajo la línea nacional, en paneles separados;
- comprobar la pobreza infantil relativa de Eurostat, la tendencia mundial de
  Banco Mundial–UNICEF y las fichas comparables MPI de PNUD/OPHI;
- buscar opciones, limpiar la selección y abrir/cerrar el formulario;
- mostrar y ocultar los gobiernos estatal, regionales y europeos;
- comprobar el diseño y el desplazamiento de tablas en móvil y escritorio;
- repetir la carga con los proveedores externos inaccesibles y confirmar que
  se usa la caché local sin dejar la página en blanco; si un proveedor carece
  de caché, su aviso no debe ocultar los demás paneles.
