# Documentación técnica

Esta documentación describe TuPortalNews 1.1.6, evolución de la primera
versión estable del nuevo historial limpio.

- `INSTALLATION.md`: requisitos, configuración local y permisos.
- `FEATURES_BY_ROLE.md`: funciones disponibles para cada perfil.
- `STRUCTURE.md`: organización y flujo de ejecución.
- `TESTING.md`: comprobaciones automáticas y manuales.
- `DEPLOYMENT.md`: despliegue, migraciones y reversión.

Los datos reales, credenciales, uploads, logs, cachés y backups no forman
parte del repositorio.

## Base estable de 1.1.6

- tarjetas de noticias unificadas con variantes independientes por página,
  estructura vertical u horizontal explícita y densidad compacta independiente;
- administrador Root único configurado localmente, protegido frente a cambios
  y con capacidad exclusiva para gestionar otros administradores;
- promoción transaccional de Colaborador a Admin y reversión de Admin a
  Colaborador, conservando contenidos y permisos privados coherentes;
- portada con seis últimas noticias en tres columnas de escritorio y dos en
  pantallas pequeñas, sin metadatos secundarios en esas tarjetas;
- bloques de medios externos administrables, separados de las noticias
  importadas, con enlaces al sitio original e imágenes obtenidas del RSS;
- caché RSS actualizada cada 15 minutos y preparada inmediatamente al crear o
  cambiar una fuente seleccionada para los bloques externos;
- miniaturas RSS convertidas fuera de la petición pública a WebP 256×144 y 320×180,
  servidas desde caché local con conservación de navegador de 30 días;
- tarjetas de noticias externas con variantes WebP responsive de 320, 480,
  640 y 960 píxeles, manteniendo la imagen original en la noticia completa;
- imágenes locales de tarjetas con derivados WebP de 320, 480 y 640 píxeles y logo
  de cabecera reducido a un WebP específico de 96×96;
- portada con contraste WCAG validado y nombres accesibles específicos en los
  enlaces de cada noticia del slider;
- página pública “Noticias por lugares” para provincias, ubicaciones
  internacionales y otros lugares;
- categoría, fuente, ubicación e imagen principal obligatorias en el flujo
  editorial, incluida la ubicación común de las importaciones RSS;
- comentarios recibidos por Articulista agrupados y enlazados por noticia;
- paneles diferenciados por perfil mediante símbolos, colores y navegación
  responsive: Admin, Comentarista, Articulista y Colaborador;
- configuración administrativa organizada por bloques con ayudas breves y
  accesos directos;
- gestión de categorías y fuentes unificada mediante tablas administrativas
  equivalentes y navegación entre ambos listados;
- aclaración de los ajustes independientes para registro de Comentaristas y
  solicitudes de Articulistas;
- ubicación e imagen principal obligatorias para nuevas noticias y ediciones;
- normalización de noticias antiguas sin ubicación mediante el texto
  “Sin ubicación”;
- consulta comparativa de pobreza reforzada con proveedores oficiales,
  cachés independientes y presentación responsive;
- selector RSS integrado en la edición de noticias y mejoras de tolerancia a
  fallos en meteorología y recursos externos;
- validación defensiva de entradas escalares en formularios y filtros;
- controles de rol reforzados en la administración de usuarios y colaboradores;
- operaciones destructivas verificadas mediante POST, CSRF y confirmación;
- saneamiento reforzado de comentarios, noticias, enlaces y vídeos embebidos;
- errores internos ocultos al cliente y registros sin mensajes sensibles;
- comprobación integral de rutas, recursos, cabeceras y dependencias.

## Catálogo multimedia y fuentes oficiales

- catálogo público de NASA Images con 24 accesos temáticos y búsqueda libre;
- filtros de imágenes, vídeos y material reciente, con caché y límites de red;
- selección de imágenes y vídeos NASA desde el editor sin copiarlos al servidor;
- imagen principal y galería NASA con atribución; vídeo NASA reproducido por HTTPS;
- elección de imagen o vídeo como medio principal, conservando una miniatura para tarjetas;
- presentación responsive 40/60 para vídeo principal y texto en escritorio.

## Administración de usuarios

- listado de usuarios con actividad reciente en el panel Admin;
- filtros de conexión por perfil: Comentarista, Articulista, Colaborador y
  Admin;
- contador de conexiones, última actividad y tiempo autenticado aproximado;
- actualización de actividad limitada a una escritura por minuto y tolerante
  a fallos;
- correo corporativo opcional para Colaboradores con acceso directo al webmail;
- documentación de privacidad actualizada para las métricas administrativas.

## Seguridad y mantenimiento

- CSS y JavaScript compartidos, versionados y sin paquetes históricos;
- eliminación integral de noticias mediante una única función;
- restauración de archivos preparada y verificada en un directorio temporal;
- logs críticos sin mensajes completos de excepciones;
- multimedia RSS externa limitada a HTTPS;
- cuota de almacenamiento comprobada también con el tamaño final del upload;
- consulta pública de pobreza con datos oficiales del INE, Eurostat, ONU y
  UNICEF;
- comparativas por comunidades y países, tablas, gráficas y contexto político;
- series internacionales separadas para pobreza multidimensional y pobreza
  infantil, sin mezclar metodologías;
- comparación infantil relativa europea, tendencia mundial bajo la línea
  internacional de 3 dólares e índice multidimensional global PNUD/OPHI;
- caché tolerante a fallos y protegida frente a renovaciones simultáneas.
