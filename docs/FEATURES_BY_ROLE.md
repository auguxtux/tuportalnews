# Funciones por perfil

Los nombres visibles no cambian los valores internos utilizados por permisos y
sesiones.

## Comentarista (`usuario`)

- utilizar un panel azul con accesos directos a actividad, comentarios,
  favoritas, perfil y buscador;
- consultar portada, categorías, fuentes, noticias por lugares, buscadores y
  tiempo;
- consultar medios externos en portada; sus enlaces se abren en el sitio
  original y no forman parte de las noticias de TuPortalNews;
- comentar noticias públicas y gestionar sus propios comentarios;
- guardar favoritas y valorar noticias;
- reportar noticias o comentarios indicando un motivo;
- actualizar perfil, avatar y contraseña;
- solicitar recuperación de contraseña;
- eliminar definitivamente su cuenta y actividad.
- consultar el catálogo público de imágenes y vídeos de NASA mediante temas o
  búsqueda libre.

## Articulista (`periodista`)

Incluye las funciones registradas del Comentarista y además:

- utilizar un panel público verde, independiente del área privada;
- crear, editar y eliminar sus noticias públicas;
- asignar obligatoriamente categoría, fuente y ubicación;
- subir imagen principal, contenido multimedia y contenido TinyMCE validado;
- seleccionar imágenes o vídeos oficiales de NASA desde el editor sin ocupar
  almacenamiento propio;
- elegir una imagen o un vídeo como medio principal, conservando siempre una
  imagen de portada para tarjetas y enlaces compartidos;
- administrar sus fuentes RSS y usar fuentes activas compartidas;
- seleccionar manualmente noticias RSS sin importar duplicados;
- asignar una ubicación común al lote RSS y editar después las excepciones;
- consultar visitas, comentarios y valoraciones de sus publicaciones;
- consultar los comentarios recibidos, agrupados por noticia y enlazados con
  su publicación;
- controlar su cuota y realizar eliminación múltiple limitada;
- al eliminar su cuenta, borrar su contenido y transferir sus fuentes RSS al
  Admin.

## Colaborador (`periodista` con permiso privado)

Conserva todas las funciones públicas del Articulista y además:

- utilizar un panel privado ámbar con navegación independiente;
- crear, editar y eliminar sus noticias privadas;
- utilizar el mismo selector NASA en noticias públicas y privadas;
- buscar y consultar noticias privadas de otros Colaboradores;
- comentar, valorar, relacionar y reportar contenido privado;
- utilizar buscadores y paneles privados independientes;
- consultar su correo corporativo asignado por el Admin y abrir el webmail;
- mantener el contenido privado fuera de portada, buscadores, reportes y
  enlaces públicos.

## Admin (`admin`)

- utilizar un panel morado dividido en resumen, actividad, gestión y
  herramientas;
- gestionar cuentas, estados, perfiles y permisos privados;
- asignar o retirar el correo corporativo de cada Colaborador;
- gestionar noticias públicas y privadas, categorías y relaciones;
- alternar directamente entre tablas administrativas de categorías y fuentes;
- utilizar el catálogo NASA y supervisar noticias con multimedia externa;
- moderar comentarios, reportes y mensajes;
- confirmar reportes para publicar un resumen anónimo en el contenido de su
  mismo ámbito, o revisarlos, desestimarlos y actuar sobre el contenido;
- administrar fuentes RSS y revisar su propiedad;
- elegir qué fuentes RSS activas aparecen en los bloques externos de portada;
- actualizar inmediatamente la caché externa al crear o cambiar un medio
  seleccionado, manteniendo además la renovación automática cada 15 minutos;
- configurar registro, comentarios, cuotas, mantenimiento y minificación;
- distinguir el registro de Comentaristas del registro y aprobación de
  Articulistas;
- revisar sesiones, intentos de acceso, bloqueos, actividad, errores y
  diagnóstico;
- filtrar usuarios por rol y actividad, y consultar conexiones y tiempo de uso
  autenticado aproximado;
- crear, descargar, restaurar y eliminar backups;
- consultar documentación y ejecutar verificaciones previas al despliegue.

## Reglas comunes

- las modificaciones requieren sesión, permiso, método y CSRF adecuados;
- cada usuario solo puede modificar contenido de su propiedad, salvo acciones
  administrativas autorizadas;
- desactivar conserva contenido; eliminar definitivamente ejecuta la limpieza
  asociada;
- las imágenes se validan y optimizan antes de almacenarse;
- la cuota se vuelve a comprobar con el tamaño final del archivo procesado;
- las imágenes y vídeos importados desde RSS deben utilizar HTTPS;
- las credenciales, tokens y errores internos no se muestran al cliente.
- los navegadores de los paneles solo muestran funciones permitidas para el
  perfil correspondiente;
- una categoría o fuente con noticias asociadas no se elimina; desactivarla
  conserva las noticias existentes;
- los reportes confirmados muestran únicamente cantidad y motivos generales;
  nunca identidad, IP ni descripción del denunciante;
- las métricas de conexión son administrativas, aproximadas y no utilizan
  cookies analíticas;
- una sesión se considera en línea cuando ha tenido actividad durante los
  últimos cinco minutos.
- las tarjetas comparten apariencia, pero la dirección vertical u horizontal
  y la densidad compacta se declaran con modificadores independientes;
- los bloques RSS externos muestran hasta cuatro enlaces por medio, no
  importan noticias ni consultan el control de duplicados de los Articulistas;
- una noticia importada puede seguir apareciendo como enlace externo porque
  ambos flujos son independientes;
- los metadatos compatibles enlazan autor, categoría, fuente, ubicación y
  comentarios con sus listados correspondientes;
- toda noticia nueva o editada debe tener categoría, fuente, ubicación e
  imagen principal; las noticias antiguas incompletas usan “Sin ubicación”;
- el portal público lista los reportes confirmados de noticias y comentarios
  públicos; el área privada mantiene un listado independiente y protegido.
- el contenido NASA se enlaza desde sus servidores mediante HTTPS, se atribuye
  a NASA y debe revisarse por si su ficha identifica derechos de terceros.

## Catálogo multimedia NASA

- `/nasa` ofrece 24 temas, búsqueda libre y filtros de imágenes o vídeos;
- los temas de actualidad limitan los resultados al año anterior y al actual;
- las consultas usan caché durante una hora y una copia temporal ante fallos;
- el selector editorial admite una imagen principal, cinco imágenes de galería
  y un vídeo NASA;
- los archivos no se descargan al servidor y no consumen cuota local;
- los vídeos utilizan una versión intermedia cuando está disponible;
- la imagen de portada se mantiene aunque el vídeo encabece la noticia;
- NASA permite generalmente el uso informativo con atribución, pero el autor
  debe comprobar en la ficha si existe material de terceros.

## Consulta pública de pobreza

- compara la tasa de riesgo de pobreza del INE entre España y hasta seis
  comunidades o ciudades autónomas;
- compara el indicador AROPE infantil de Eurostat entre la UE-27 y hasta seis
  países;
- permite elegir el periodo disponible y consultar los valores exactos;
- muestra u oculta el contexto de los gobiernos estatal, autonómicos y
  europeos, sin atribuir causalidad política a los indicadores;
- conserva una caché local para mantener la consulta disponible ante fallos
  temporales de los proveedores oficiales.
