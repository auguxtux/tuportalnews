<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
Permisos::requerirAdmin();
?>

<!DOCTYPE html>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Noticias - Resumen Ejecutivo del Sistema</title>
    <!-- Font Awesome (Iconos) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo css_url('admin-documentacion.css'); ?>">

</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-top">
            <div class="header-left">
                <button id="btnVolver" class="btn-volver" onclick="history.back()" title="Volver a la página anterior">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver</span>
                </button>
            </div>
            <div class="header-center">
                <h1>
                    <i class="fas fa-newspaper"></i> 
                    Portal de Noticias · TuPortalNews
                    <span class="badge-version">v<?= htmlspecialchars(SITE_VERSION, ENT_QUOTES, 'UTF-8'); ?> · 2026</span>
                </h1>
                <p>Sistema completo de gestión editorial con roles, contenidos privados, optimización multimedia, recursos compartidos y panel de seguridad.</p>
            </div>
            <div class="header-right">
                <a href="<?php echo route('home'); ?>" class="btn-home" title="Ir al inicio">
                    <i class="fas fa-home"></i>
                    <span>Inicio</span>
                </a>
            </div>
        </div>
    </div>

    <div class="highlight">
        <strong>Versión 1.1.4:</strong> imágenes responsive en caché WebP,
        portada optimizada y accesibilidad validada, junto con arquitectura PHP procedimental modular,
        rutas centralizadas, separación completa de contenido público y
        privado, colaciones UTF-8 unificadas, pruebas repetibles, backups con
        retención, clasificación dual de noticias (región + tema), importación
        RSS con detección automática, bloques externos administrables y
        documentación de instalación, verificación y despliegue.
    </div>

    <!-- ============================================================ -->
    <!-- PESTAÑAS INFORMATIVAS -->
    <!-- ============================================================ -->
    <div class="nav-tabs">
        <!-- TABS ACTIVOS (con contenido) -->
        <button class="tab-btn active" data-tab="tab1"><i class="fas fa-users"></i> Roles & Funcionalidades</button>
        <button class="tab-btn" data-tab="tab2"><i class="fas fa-cogs"></i> Módulos técnicos</button>
        <button class="tab-btn" data-tab="tab3"><i class="fas fa-shield-alt"></i> Seguridad & Monitorización</button>
        <button class="tab-btn" data-tab="tab4"><i class="fas fa-palette"></i> Diseño & UI/UX</button>
        <button class="tab-btn" data-tab="tab5"><i class="fas fa-chart-line"></i> Gestión de contenido</button>
        <button class="tab-btn" data-tab="tab6"><i class="fas fa-folder-tree"></i> Arquitectura</button>
        
        <!-- ============================================================ -->
        <button class="tab-btn" data-tab="tab7"><i class="fas fa-plus-circle"></i>Política de Cookies</button>
        <button class="tab-btn" data-tab="tab8"><i class="fas fa-plus-circle"></i>Análisis de la Base de Datos</button>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 1 - ROLES Y FUNCIONALIDADES ADMINISTRATIVAS -->
    <!-- ============================================================ -->
    <div id="tab1" class="tab-content active">
        <h2><i class="fas fa-user-tag"></i> Modelo de usuarios y permisos</h2>
        <p>Cuatro perfiles con visualización y acciones claramente diferenciadas.</p>
        <div class="highlight">
            <strong>Correspondencia técnica:</strong>
            Comentarista = <code>usuario</code>;
            Articulista = <code>periodista</code> sin permiso privado;
            Colaborador = <code>periodista</code> con registro activo en <code>usuarios_privados</code>;
            Admin = <code>admin</code>.
            Los nombres internos se conservan para mantener sesiones, permisos, consultas y rutas compatibles.
        </div>
        <div class="grid-cards">
            <div class="card">
                <div class="role-badge">👑 Admin</div>
                <h3>Control total</h3>
                <ul class="role-list">
                    <li><i class="fas fa-check-circle"></i> Gestión de Comentaristas, Articulistas, Colaboradores y categorías</li>
                    <li><i class="fas fa-compass"></i> Panel morado con navegación por resumen, actividad, gestión y herramientas</li>
                    <li><i class="fas fa-table"></i> Tablas equivalentes y navegación directa para categorías y fuentes</li>
                    <li><i class="fas fa-chart-line"></i> Usuarios conectados, conexiones y tiempo de actividad aproximado por rol</li>
                    <li><i class="fas fa-envelope"></i> Asignación de correo corporativo a Colaboradores</li>
                    <li><i class="fas fa-check-circle"></i> Moderación de comentarios y mensajes de contacto</li>
                    <li><i class="fas fa-check-circle"></i> Gestión de noticias públicas/privadas</li>
                    <li><i class="fas fa-check-circle"></i> Configuración del sitio (registro, comentarios, etc)</li>
                    <li><i class="fas fa-check-circle"></i> Monitor de ataques y bloqueo de IPs</li>
                    <li><i class="fas fa-chart-simple"></i> Estadísticas globales y logs del sistema</li>
                    <li><i class="fas fa-map-marker-alt"></i> Clasificación regional de noticias (19 comunidades autónomas)</li>
                    <li><i class="fas fa-tags"></i> Clasificación temática automática (14 categorías)</li>
                    <li><i class="fas fa-rss"></i> Gestión de fuentes RSS con región asignada y feeds regionales RTVE</li>
                    <li><i class="fas fa-globe"></i> Selección de medios RSS externos visibles en portada, sin importarlos</li>
                </ul>
            </div>
            <div class="card">
                <div class="role-badge">✍️ Articulista</div>
                <h3>Publicación de artículos</h3>
                <ul class="role-list">
                    <li><i class="fas fa-plus-circle"></i> Crear, editar y eliminar sus noticias</li>
                    <li><i class="fas fa-image"></i> Subir imágenes (redimensionado automático 1024px) y vídeos</li>
                    <li><i class="fas fa-rocket"></i> Seleccionar multimedia NASA y elegir imagen o vídeo principal</li>
                    <li><i class="fas fa-tag"></i> Asignar categorías, fuentes y ubicaciones</li>
                    <li><i class="fas fa-lock"></i> <strong>Modo privado</strong> (si tiene permiso especial)</li>
                    <li><i class="fas fa-chart-line"></i> Ver estadísticas de sus noticias</li>
                    <li><i class="fas fa-map-marker-alt"></i> Asignar región (comunidad autónoma) a cada noticia</li>
                    <li><i class="fas fa-robot"></i> Clasificación temática automática al importar RSS</li>
                    <li><i class="fas fa-rss"></i> Importar noticias RSS con región de la fuente y etiquetas visuales</li>
                </ul>
            </div>
            <div class="card">
                <div class="role-badge">🔒 Colaborador</div>
                <h3>Contenido confidencial</h3>
                <ul class="role-list">
                    <li><i class="fas fa-eye-slash"></i> Acceso a área privada /privado</li>
                    <li><i class="fas fa-envelope"></i> Consulta de correo corporativo y acceso al webmail</li>
                    <li><i class="fas fa-newspaper"></i> Publica noticias visibles solo para administradores y usuarios con permiso</li>
                    <li><i class="fas fa-database"></i> Mismo editor enriquecido (TinyMCE, galerías, vídeo)</li>
                    <li><i class="fas fa-rocket"></i> Selector NASA también disponible en noticias privadas</li>
                    <li><i class="fas fa-chart-simple"></i> Información aislada del público general</li>
                </ul>
            </div>
            <div class="card">
                <div class="role-badge">💬 Comentarista</div>
                <h3>Comunidad activa</h3>
                <ul class="role-list">
                    <li><i class="fas fa-comment-dots"></i> Comentar en noticias públicas</li>
                    <li><i class="fas fa-pen"></i> Editar y eliminar sus comentarios</li>
                    <li><i class="fas fa-flag"></i> Reportar comentarios inapropiados</li>
                    <li><i class="fas fa-star"></i> Valorar noticias (1-3 estrellas)</li>
                    <li><i class="fas fa-chart-simple"></i> Historial de comentarios y perfil público</li>
                </ul>
            </div>
        </div>
        <div class="highlight">
            <i class="fas fa-sync-alt"></i> <strong>Flujo de trabajo:</strong> Articulista o Colaborador → Noticia pública o privada → Comentarios y valoraciones → Moderación del Admin.
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 2 - MÓDULOS TÉCNICOS DESTACADOS -->
    <!-- ============================================================ -->
    <div id="tab2" class="tab-content">
        <h2><i class="fas fa-microchip"></i> Arquitectura técnica</h2>
        <div class="two-columns">
            <div>
                <h3><i class="fas fa-server"></i> Backend & almacenamiento</h3>
                <ul class="role-list">
                    <li><i class="fab fa-php"></i> PHP 8.3 / MariaDB</li>
                    <li><i class="fas fa-code-branch"></i> Front controller unificado + routing amigable</li>
                    <li><i class="fas fa-layer-group"></i> Arquitectura procedural modular con bootstrap común y dependencias específicas explícitas</li>
                    <li><i class="fas fa-database"></i> Base de datos relacional (usuarios, noticias, comentarios, valoraciones, intentos de login)</li>
                    <li><i class="fas fa-file-image"></i> Subida optimizada: imágenes generales hasta 1024×1024 px con objetivo de 300 KB</li>
                    <li><i class="fas fa-video"></i> Soporte vídeos locales (MP4, WEBM) y externos (YouTube/Vimeo mediante iframe)</li>
                    <li><i class="fas fa-rocket"></i> Catálogo NASA con búsqueda, 24 temas, caché y multimedia externa HTTPS</li>
                    <li><i class="fas fa-chart-line"></i> Minificación automática CSS/JS (modo desarrollo/producción)</li>
                    <li><i class="fas fa-map-marker-alt"></i> Clasificación dual de noticias: región (19 comunidades) y tema (14 categorías)</li>
                    <li><i class="fas fa-rss"></i> Importador RSS con prevención de duplicados, región automática y etiquetas visuales</li>
                    <li><i class="fas fa-sync-alt"></i> Caché de medios externos renovada cada 15 minutos y al editar su URL</li>
                    <li><i class="fas fa-shield-alt"></i> Rate limiting en login, rotación CSRF, validación de URLs internas</li>
                </ul>
            </div>
            <div>
                <h3><i class="fas fa-laptop-code"></i> Frontend UX</h3>
                <ul class="role-list">
                    <li><i class="fab fa-css3-alt"></i> CSS grid/flex, diseño responsive (3-2-1 columnas)</li>
                    <li><i class="fas fa-image"></i> Galería modal con navegación y pies de foto</li>
                    <li><i class="fab fa-js"></i> TinyMCE para el editor de noticias</li>
                    <li><i class="fas fa-moon"></i> Modo mantenimiento configurable desde panel admin</li>
                    <li><i class="fas fa-search"></i> Búsqueda avanzada con filtros (categoría, fuente, ubicación, fechas)</li>
                </ul>
            </div>
        </div>
        <hr>
        <h3><i class="fas fa-chart-pie"></i> Rendimiento y optimizaciones</h3>
        <div class="grid-cards">
            <div class="card"><i class="fas fa-compress-alt"></i> <strong>Recursos optimizados</strong><br>CSS/JS versionados y servidos según el modo configurado.</div>
            <div class="card"><i class="fas fa-image"></i> <strong>Conversor PNG→JPG</strong><br>Imágenes pesadas se convierten a JPG con fondo blanco, ahorro 85-95%.</div>
            <div class="card"><i class="fas fa-video"></i> <strong>Vídeo externo</strong><br>Soporte YouTube, Vimeo vía URL sin consumo de almacenamiento propio.</div>
            <div class="card"><i class="fas fa-tachometer-alt"></i> <strong>Lazy loading + caché</strong><br>Carga diferida de imágenes y caché del navegador.</div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 3 - SEGURIDAD -->
    <!-- ============================================================ -->
    <div id="tab3" class="tab-content">
        <h2><i class="fas fa-shield-virus"></i> Seguridad y auditoría</h2>
        <div class="two-columns">
            <div>
                <h3>🛡️ Protecciones activas</h3>
                <ul class="role-list">
                    <li><i class="fas fa-ban"></i> Bloqueo por fuerza bruta (5 intentos → 15 min bloqueo, conectado al login)</li>
                    <li><i class="fas fa-ip"></i> IPs bloqueadas permanentemente desde panel /ataques</li>
                    <li><i class="fas fa-csrf"></i> Tokens CSRF con rotación automática tras cada uso</li>
                    <li><i class="fas fa-user-lock"></i> Contraseñas hasheadas (password_hash + rehash automático)</li>
                    <li><i class="fas fa-file-alt"></i> Logs detallados de accesos e intentos fallidos</li>
                    <li><i class="fas fa-shield-alt"></i> Escape contextual de salidas (htmlspecialchars en ~45 outputs)</li>
                    <li><i class="fas fa-link"></i> Validación de URLs internas en redireccionar()</li>
                    <li><i class="fas fa-lock"></i> Protección contra enumeración de emails en registro</li>
                </ul>
            </div>
            <div>
                <h3>📋 Monitor de ataques (admin)</h3>
                <ul class="role-list">
                    <li><i class="fas fa-chart-line"></i> Top 10 IPs y emails con más intentos fallidos</li>
                    <li><i class="fas fa-hourglass-half"></i> Bloqueos temporales activos</li>
                    <li><i class="fas fa-trash-alt"></i> Limpieza selectiva por IP/email</li>
                    <li><i class="fas fa-download"></i> Registro completo de últimos 50 accesos maliciosos</li>
                </ul>
            </div>
        </div>
        <div class="highlight">
            <i class="fas fa-cookie-bite"></i> <strong>Privacidad:</strong> Los usuarios pueden solicitar borrar sus comentarios, los reportes quedan registrados para moderación.
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 4 - DISEÑO Y UI/UX -->
    <!-- ============================================================ -->
    <div id="tab4" class="tab-content">
        <h2><i class="fas fa-paintbrush"></i> Interfaz y experiencia visual</h2>
        <div class="grid-cards">
            <div class="card"><i class="fas fa-mobile-alt"></i> <strong>100% responsive</strong><br>Menú lateral deslizable, cards adaptativas, tipografía legible.</div>
            <div class="card"><i class="fas fa-images"></i> <strong>Galería integrada</strong><br>Modal con flechas, textos descriptivos y contador de imágenes.</div>
            <div class="card"><i class="fas fa-comments"></i> <strong>Comentarios en modal</strong><br>Editor TinyMCE básico, edición/eliminación responsive.</div>
            <div class="card"><i class="fas fa-tachometer-alt"></i> <strong>Panel por perfil</strong><br>Admin morado; Comentarista azul; Articulista verde; Colaborador ámbar. Cada uno dispone de navegación responsive y funciones autorizadas.</div>
        </div>
        <p><i class="fas fa-palette"></i> Paleta predominante: azules profesionales (gradientes), tipografía Sans-Serif, componentes con sombras suaves y hover effects.</p>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 5 - GESTIÓN DE CONTENIDO -->
    <!-- ============================================================ -->
    <div id="tab5" class="tab-content">
        <h2><i class="fas fa-edit"></i> Flujo editorial y contenidos</h2>
        <div class="two-columns">
            <div>
                <h3>Noticias</h3>
                <ul class="role-list">
                    <li><i class="fas fa-heading"></i> Título, subtítulo, contenido enriquecido</li>
                    <li><i class="fas fa-image"></i> Imagen principal + hasta 5 imágenes de galería con texto alternativo</li>
                    <li><i class="fas fa-tag"></i> Categorías, fuente, ubicación geográfica (provincia/internacional)</li>
                    <li><i class="fas fa-map-marker-alt"></i> Región (comunidad autónoma) y clasificación temática automática</li>
                    <li><i class="fas fa-chart-simple"></i> Visitas, valoraciones (1-3 estrellas), "me gusta"</li>
                    <li><i class="fas fa-share-alt"></i> Compartir en redes sociales + copia de enlace</li>
                    <li><i class="fas fa-clock"></i> Estados: borrador / publicada / pendiente / archivada</li>
                </ul>
            </div>
            <div>
                <h3>Comentarios y comunidad</h3>
                <ul class="role-list">
                    <li><i class="fas fa-check-circle"></i> Moderación por estado (aprobado, pendiente, rechazado)</li>
                    <li><i class="fas fa-flag-checkered"></i> Reporte de comentarios ofensivos/spam</li>
                    <li><i class="fas fa-history"></i> Edición limitada por tiempo (propietario/moderador)</li>
                    <li><i class="fas fa-envelope"></i> Formulario de contacto con almacenamiento en BD</li>
                    <li><i class="fas fa-database"></i> Backup manual/automático desde panel de actualización</li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="highlight">
            <i class="fas fa-cloud-upload-alt"></i> <strong>Organización de archivos:</strong> Subidas organizadas en /uploads/noticias, /uploads/perfiles, /uploads/editor y miniaturas generadas automáticamente.
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 6 - ARQUITECTURA DE ARCHIVOS -->
    <!-- ============================================================ -->
    <div id="tab6" class="tab-content">
        <h2><i class="fas fa-folder-tree"></i> Arquitectura de Archivos y Carpetas</h2>
        <p>Estructura organizada del sistema, con la función principal de cada directorio y archivo.</p>
        <!-- ======================================== -->
<!-- ESTRUCTURA EN ÁRBOL SIMPLIFICADA -->
<!-- ======================================== -->
<div class="tree-container">
    <div class="tree-header">
        <i class="fas fa-diagram-project"></i>
        <h3>Estructura en árbol simplificada</h3>
        <span class="tree-badge">v3 · En desarrollo</span>
    </div>
    
    <div class="tree-content">
        <code class="tree-code">
<span class="tree-comment">📁 / (raíz del proyecto)</span>
├── <span class="file">index.php</span>                      <span class="comment">→ Front controller, enruta todas las peticiones</span>
├── <span class="file">.htaccess</span>                      <span class="comment">→ Reglas de reescritura (HTTPS, URLs, seguridad)</span>
├── <span class="file">logout.php</span>                     <span class="comment">→ Cierra la sesión del usuario</span>
├── <span class="file">maintenance.php</span>                <span class="comment">→ Página de modo mantenimiento</span>
│
├── 📁 <span class="folder">includes/</span>                  <span class="comment">→ Núcleo de la aplicación</span>
│   ├── <span class="file">bootstrap.php</span>               <span class="comment">→ Carga común mínima de cada página</span>
│   ├── <span class="file">config.php</span>                  <span class="comment">→ Constantes, sesión, rutas y helpers globales</span>
│   ├── <span class="file">routes.php</span>                  <span class="comment">→ Mapeo de rutas (nombre → archivo)</span>
│   ├── <span class="file">conexion.php</span>                 <span class="comment">→ Conexión PDO a MySQL</span>
│   ├── <span class="file">auth.php</span>                     <span class="comment">→ Clase de autenticación (login/logout)</span>
│   ├── <span class="file">permisos.php</span>                 <span class="comment">→ Verificación de roles (admin, periodista, usuario)</span>
│   ├── <span class="file">privado.php</span>                  <span class="comment">→ Funciones para área privada</span>
│   ├── <span class="file">minify.php</span>                   <span class="comment">→ Minificación de CSS/JS</span>
│   ├── <span class="file">upload-handler.php</span>            <span class="comment">→ Clase para subir archivos</span>
│   ├── <span class="file">valoraciones.php</span>              <span class="comment">→ Sistema de valoración (1-3 estrellas)</span>
│   │
│   └── 📁 <span class="folder">helpers/</span>                <span class="comment">→ Funciones auxiliares organizadas</span>
│       ├── <span class="file">fechas.php</span>                <span class="comment">→ formatearFecha(), tiempoTranscurrido()</span>
│       ├── <span class="file">texto.php</span>                 <span class="comment">→ truncarTexto(), obtenerPrimerParrafo()</span>
│       ├── <span class="file">validacion.php</span>             <span class="comment">→ validarEmail(), validarTelefono()</span>
│       ├── <span class="file">seguridad.php</span>               <span class="comment">→ limpiarDatos(), obtenerIP()</span>
│       ├── <span class="file">slug.php</span>                   <span class="comment">→ generarSlug()</span>
│       ├── <span class="file">flash.php</span>                  <span class="comment">→ mensajeFlash(), obtenerMensajeFlash()</span>
│       ├── <span class="file">url.php</span>                    <span class="comment">→ base_url(), redireccionar(), current_url()</span>
│       ├── <span class="file">csrf.php</span>                   <span class="comment">→ Tokens CSRF</span>
│       └── <span class="file">login-attempts.php</span>          <span class="comment">→ Control de intentos fallidos</span>
│       ├── <span class="file">clasificacion.php</span>           <span class="comment">→ detectarTemaRss(), diccionarios de temas</span>
│       ├── <span class="file">noticias.php</span>                <span class="comment">→ Consultas de noticias con JOIN a regiones</span>
│
├── 📁 <span class="folder">public/</span>                     <span class="comment">→ Vistas públicas (acceso sin login)</span>
│   ├── <span class="file">portada.php</span>                  <span class="comment">→ Página principal (últimas noticias)</span>
│   ├── <span class="file">noticia.php</span>                  <span class="comment">→ Detalle de noticia con galería y comentarios</span>
│   ├── <span class="file">categoria.php</span>                <span class="comment">→ Noticias por categoría</span>
│   ├── <span class="file">periodistas.php</span>              <span class="comment">→ Listado de periodistas</span>
│   ├── <span class="file">ultimas.php</span>                  <span class="comment">→ Últimas noticias</span>
│   ├── <span class="file">populares.php</span>                <span class="comment">→ Noticias más visitadas</span>
│   ├── <span class="file">buscar-avanzado.php</span>          <span class="comment">→ Búsqueda con múltiples filtros</span>
│   ├── <span class="file">ubicacion.php</span>                <span class="comment">→ Noticias por provincia/lugar internacional</span>
│   ├── <span class="file">fuente.php</span>                   <span class="comment">→ Noticias por fuente</span>
│   ├── <span class="file">login.php</span>                    <span class="comment">→ Formulario de inicio de sesión</span>
│   ├── <span class="file">registro.php</span>                 <span class="comment">→ Registro de usuarios</span>
│   ├── <span class="file">contacto.php</span>                 <span class="comment">→ Formulario de contacto</span>
│   ├── <span class="file">terminos.php</span>                 <span class="comment">→ Términos y condiciones</span>
│   ├── <span class="file">privacidad.php</span>               <span class="comment">→ Política de privacidad</span>
│   ├── <span class="file">ver-valoraciones.php</span>          <span class="comment">→ Estadísticas de valoración</span>
│   └── <span class="file">ver-relacionadas.php</span>          <span class="comment">→ Noticias relacionadas</span>
│   ├── <span class="file">listado-noticias.php</span>        <span class="comment">→ Listado filtrable por región y categoría</span>
│   ├── <span class="file">buscar-comentarios.php</span>      <span class="comment">→ Búsqueda de comentarios</span>
│   ├── <span class="file">tiempo.php</span>                  <span class="comment">→ Predicción meteorológica (AEMET/Open-Meteo)</span>
│
├── 📁 <span class="folder">admin/</span>                      <span class="comment">→ Panel de administración (solo admin)</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment">→ Estadísticas generales</span>
│   ├── <span class="file">usuarios-logueados.php</span>       <span class="comment">→ Gestión de usuarios (CRUD)</span>
│   ├── <span class="file">noticias.php</span>                <span class="comment">→ Gestión de noticias</span>
│   ├── <span class="file">categorias.php</span>               <span class="comment">→ Gestión de categorías</span>
│   ├── <span class="file">periodistas.php</span>              <span class="comment">→ Gestión de periodistas</span>
│   ├── <span class="file">comentarios.php</span>              <span class="comment">→ Moderación de comentarios</span>
│   ├── <span class="file">mensajes.php</span>                 <span class="comment">→ Mensajes de contacto</span>
│   ├── <span class="file">configuracion.php</span>            <span class="comment">→ Configuración general del sitio</span>
│   ├── <span class="file">ataques.php</span>                  <span class="comment">→ Monitor de intentos fallidos de login</span>
│   ├── <span class="file">noticias-privadas.php</span>        <span class="comment">→ Gestión de noticias privadas</span>
│   ├── <span class="file">noticias-relacionadas.php</span>    <span class="comment">→ Relaciones entre noticias</span>
│   └── <span class="file">editar-noticia.php</span>           <span class="comment">→ Editar noticia (admin)</span>
│   ├── <span class="file">gestion-fuentes.php</span>        <span class="comment">→ Gestión de fuentes RSS manuales</span>
│   ├── <span class="file">rss-config.php</span>              <span class="comment">→ Configuración de feeds RSS con región</span>
│
├── 📁 <span class="folder">periodista/</span>                 <span class="comment">→ Panel de periodistas</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment">→ Panel personal de periodista</span>
│   ├── <span class="file">mis-noticias.php</span>             <span class="comment">→ Listado de sus noticias</span>
│   ├── <span class="file">nueva-noticia.php</span>            <span class="comment">→ Crear noticia (con editor TinyMCE)</span>
│   ├── <span class="file">editar-noticia.php</span>           <span class="comment">→ Editar su noticia</span>
│   ├── <span class="file">eliminar-noticia.php</span>         <span class="comment">→ Eliminar su noticia</span>
│   ├── <span class="file">importar-rss.php</span>            <span class="comment">→ Importar noticias RSS con región y tema</span>
│   └── <span class="file">perfil.php</span>                   <span class="comment">→ Perfil del periodista</span>
│
├── 📁 <span class="folder">privado/</span>                    <span class="comment">→ Área privada (periodistas con permiso especial)</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment">→ Panel privado</span>
│   ├── <span class="file">mis-noticias-privadas.php</span>    <span class="comment">→ Noticias privadas del periodista</span>
│   └── <span class="file">buscar-privadas.php</span>          <span class="comment">→ Búsqueda interna en noticias privadas</span>
│
├── 📁 <span class="folder">usuario/</span>                    <span class="comment">→ Panel de usuarios registrados</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment">→ Panel personal de usuario</span>
│   ├── <span class="file">mis-comentarios.php</span>          <span class="comment">→ Listado de sus comentarios</span>
│   ├── <span class="file">editar-comentario.php</span>        <span class="comment">→ Editar su comentario</span>
│   ├── <span class="file">eliminar-comentario.php</span>      <span class="comment">→ Eliminar su comentario</span>
│   ├── <span class="file">reportar-comentario.php</span>      <span class="comment">→ Reportar comentario ofensivo</span>
│   └── <span class="file">perfil.php</span>                   <span class="comment">→ Perfil de usuario</span>
│
├── 📁 <span class="folder">ajax/</span>                       <span class="comment">→ Peticiones asíncronas (AJAX)</span>
│   ├── <span class="file">ajax-buscar-comentarios.php</span>  <span class="comment">→ Buscar comentarios en tiempo real</span>
│   ├── <span class="file">megusta.php</span>                  <span class="comment">→ Dar/quitar "me gusta"</span>
│   ├── <span class="file">valorar.php</span>                  <span class="comment">→ Valorar noticia (1-3 estrellas)</span>
│   └── <span class="file">upload-editor-image.php</span>       <span class="comment">→ Subir imagen desde TinyMCE</span>
│
├── 📁 <span class="folder">partials/</span>                   <span class="comment">→ Plantillas reutilizables</span>
│   ├── <span class="file">header.php</span>                   <span class="comment">→ Cabecera (DOCTYPE, CSS, menú, scripts)</span>
│   ├── <span class="file">footer.php</span>                   <span class="comment">→ Pie de página (cierre de etiquetas)</span>
│   └── <span class="file">menu-unificado.php</span>            <span class="comment">→ Menú dinámico según rol del usuario</span>
│
├── 📁 <span class="folder">assets/</span>                     <span class="comment">→ Recursos estáticos</span>
│   ├── 📁 <span class="folder">css/</span>                    <span class="comment">→ Archivos CSS (originales y minificados)</span>
│   ├── 📁 <span class="folder">js/</span>                     <span class="comment">→ JavaScript en una única fuente versionada</span>
│   └── 📁 <span class="folder">img/</span>                    <span class="comment">→ Imágenes del sistema (logo, favicon, etc.)</span>
│
├── 📁 <span class="folder">uploads/</span>                    <span class="comment">→ Archivos subidos por usuarios</span>
│   ├── 📁 <span class="folder">noticias/</span>               <span class="comment">→ Imágenes y vídeos de noticias</span>
│   ├── 📁 <span class="folder">perfiles/</span>               <span class="comment">→ Avatares de usuarios</span>
│   ├── 📁 <span class="folder">editor/</span>                 <span class="comment">→ Imágenes subidas desde el editor TinyMCE</span>
│   └── 📁 <span class="folder">comentarios/</span>             <span class="comment">→ Imágenes subidas en comentarios</span>
│
├── 📁 <span class="folder">logs/</span>                       <span class="comment">→ Archivos de log</span>
│   └── <span class="file">error.log</span>                    <span class="comment">→ Registro de errores de PHP</span>
│
├── 📁 <span class="folder">cache/</span>                      <span class="comment">→ Archivos de caché</span>
│   └── <span class="file">minify_mode.cache</span>            <span class="comment">→ Modo de minificación (desarrollo/producción)</span>
│
└── 📁 <span class="folder">tmp/</span>                        <span class="comment">→ Archivos temporales</span>
            </code>
    </div>
    
    <div class="tree-footer">
        <i class="fas fa-info-circle"></i>
        <span>Los archivos marcados como <span class="file">archivo.php</span> son controladores/vistas; 
        <span class="folder">carpeta/</span> indica directorio.</span>
    </div>
</div>
        <div class="arquitectura-grid">
            <!-- Raíz -->
            <div class="folder-card folder-root">
                <div class="folder-header">
                    <i class="fas fa-home"></i> <strong>/ (Raíz)</strong>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-code"></i> <strong>index.php</strong> - Front controller, enruta todas las peticiones</div>
                    <div class="file-item"><i class="fas fa-cog"></i> <strong>.htaccess</strong> - Reglas de reescritura (URLs amigables, HTTPS)</div>
                    <div class="file-item"><i class="fas fa-power-off"></i> <strong>logout.php</strong> - Cierra la sesión del usuario</div>
                    <div class="file-item"><i class="fas fa-tools"></i> <strong>maintenance.php</strong> - Página de modo mantenimiento</div>
                </div>
            </div>

            <!-- includes/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>includes/</strong>
                    <span class="badge-folder">Configuración y núcleo</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-layer-group"></i> <strong>bootstrap.php</strong> - Carga común mínima de configuración y conexión</div>
                    <div class="file-item"><i class="fas fa-cog"></i> <strong>config.php</strong> - Configuración global (BD, rutas, constantes)</div>
                    <div class="file-item"><i class="fas fa-road"></i> <strong>routes.php</strong> - Sistema de rutas (mapeo URL → archivo)</div>
                    <div class="file-item"><i class="fas fa-database"></i> <strong>conexion.php</strong> - Conexión PDO a la base de datos</div>
                    <div class="file-item"><i class="fas fa-user-lock"></i> <strong>auth.php</strong> - Clase Auth (login, registro, sesión)</div>
                    <div class="file-item"><i class="fas fa-shield-alt"></i> <strong>permisos.php</strong> - Verificación de roles y permisos</div>
                    <div class="file-item"><i class="fas fa-user-secret"></i> <strong>privado.php</strong> - Funciones para área privada</div>
                    <div class="file-item"><i class="fas fa-compress"></i> <strong>minify.php</strong> - Sistema de minificación CSS/JS</div>
                    <div class="file-item"><i class="fas fa-upload"></i> <strong>upload-handler.php</strong> - Clase para subir archivos</div>
                    <div class="file-item"><i class="fas fa-chart-line"></i> <strong>valoraciones.php</strong> - Sistema de valoración de noticias</div>
                </div>
            </div>

            <!-- includes/helpers/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>includes/helpers/</strong>
                    <span class="badge-folder">Funciones auxiliares</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-calendar"></i> <strong>fechas.php</strong> - formatearFecha(), tiempoTranscurrido()</div>
                    <div class="file-item"><i class="fas fa-paragraph"></i> <strong>texto.php</strong> - truncarTexto(), obtenerPrimerParrafo()</div>
                    <div class="file-item"><i class="fas fa-check-circle"></i> <strong>validacion.php</strong> - validarEmail(), validarTelefono()</div>
                    <div class="file-item"><i class="fas fa-shield-virus"></i> <strong>seguridad.php</strong> - sanitización y registro interno seguro</div>
                    <div class="file-item"><i class="fas fa-link"></i> <strong>slug.php</strong> - generarSlug() para URLs amigables</div>
                    <div class="file-item"><i class="fas fa-comment-dots"></i> <strong>flash.php</strong> - mensajeFlash(), obtenerMensajeFlash()</div>
                    <div class="file-item"><i class="fas fa-globe"></i> <strong>url.php</strong> - base_url(), redireccionar(), current_url()</div>
                    <div class="file-item"><i class="fas fa-csrf"></i> <strong>csrf.php</strong> - Tokens CSRF para formularios</div>
                    <div class="file-item"><i class="fas fa-ban"></i> <strong>login-attempts.php</strong> - Control de intentos fallidos</div>
                </div>
            </div>

            <!-- public/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>public/</strong>
                    <span class="badge-folder">Vistas públicas</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-home"></i> <strong>index.php</strong> - Página principal (últimas noticias)</div>
                    <div class="file-item"><i class="fas fa-newspaper"></i> <strong>noticia.php</strong> - Visualización de noticia individual</div>
                    <div class="file-item"><i class="fas fa-tags"></i> <strong>categoria.php</strong> - Noticias por categoría</div>
                    <div class="file-item"><i class="fas fa-users"></i> <strong>periodistas.php</strong> - Listado de periodistas</div>
                    <div class="file-item"><i class="fas fa-clock"></i> <strong>ultimas.php</strong> - Últimas noticias</div>
                    <div class="file-item"><i class="fas fa-fire"></i> <strong>populares.php</strong> - Noticias más visitadas</div>
                    <div class="file-item"><i class="fas fa-search"></i> <strong>buscar-avanzado.php</strong> - Formulario de búsqueda</div>
                    <div class="file-item"><i class="fas fa-search-location"></i> <strong>ubicacion.php</strong> - Noticias por ubicación</div>
                    <div class="file-item"><i class="fas fa-building"></i> <strong>fuente.php</strong> - Noticias por fuente</div>
                    <div class="file-item"><i class="fas fa-sign-in-alt"></i> <strong>login.php</strong> - Inicio de sesión</div>
                    <div class="file-item"><i class="fas fa-user-plus"></i> <strong>registro.php</strong> - Registro de usuarios</div>
                    <div class="file-item"><i class="fas fa-envelope"></i> <strong>contacto.php</strong> - Formulario de contacto</div>
                    <div class="file-item"><i class="fas fa-file-contract"></i> <strong>terminos.php</strong> - Términos y condiciones</div>
                    <div class="file-item"><i class="fas fa-star"></i> <strong>ver-valoraciones.php</strong> - Ver valoraciones</div>
                </div>
            </div>

            <!-- admin/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>admin/</strong>
                    <span class="badge-folder">Panel de administración</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-chart-line"></i> <strong>dashboard.php</strong> - Panel principal con estadísticas</div>
                    <div class="file-item"><i class="fas fa-users"></i> <strong>usuarios-logueados.php</strong> - Gestión de usuarios</div>
                    <div class="file-item"><i class="fas fa-newspaper"></i> <strong>noticias.php</strong> - Gestión de noticias</div>
                    <div class="file-item"><i class="fas fa-tags"></i> <strong>categorias.php</strong> - Gestión de categorías</div>
                    <div class="file-item"><i class="fas fa-comments"></i> <strong>comentarios.php</strong> - Moderación de comentarios</div>
                    <div class="file-item"><i class="fas fa-envelope"></i> <strong>mensajes.php</strong> - Mensajes de contacto</div>
                    <div class="file-item"><i class="fas fa-cog"></i> <strong>configuracion.php</strong> - Configuración del sitio</div>
                    <div class="file-item"><i class="fas fa-shield-alt"></i> <strong>ataques.php</strong> - Monitor de intentos de login</div>
                    <div class="file-item"><i class="fas fa-lock"></i> <strong>noticias-privadas.php</strong> - Noticias privadas</div>
                    <div class="file-item"><i class="fas fa-link"></i> <strong>noticias-relacionadas.php</strong> - Relaciones entre noticias</div>
                </div>
            </div>

            <!-- periodista/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>periodista/</strong>
                    <span class="badge-folder">Panel del periodista</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-chart-line"></i> <strong>dashboard.php</strong> - Panel del periodista</div>
                    <div class="file-item"><i class="fas fa-newspaper"></i> <strong>mis-noticias.php</strong> - Listado de sus noticias</div>
                    <div class="file-item"><i class="fas fa-plus-circle"></i> <strong>nueva-noticia.php</strong> - Crear noticia</div>
                    <div class="file-item"><i class="fas fa-edit"></i> <strong>editar-noticia.php</strong> - Editar noticia</div>
                    <div class="file-item"><i class="fas fa-trash"></i> <strong>eliminar-noticia.php</strong> - Eliminar noticia</div>
                    <div class="file-item"><i class="fas fa-user"></i> <strong>perfil.php</strong> - Perfil del periodista</div>
                </div>
            </div>

            <!-- privado/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>privado/</strong>
                    <span class="badge-folder">Área privada (periodistas con permiso)</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-chart-line"></i> <strong>dashboard.php</strong> - Panel privado</div>
                    <div class="file-item"><i class="fas fa-newspaper"></i> <strong>mis-noticias-privadas.php</strong> - Noticias privadas del periodista</div>
                    <div class="file-item"><i class="fas fa-search"></i> <strong>buscar-privadas.php</strong> - Buscar en noticias privadas</div>
                </div>
            </div>

            <!-- usuario/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>usuario/</strong>
                    <span class="badge-folder">Panel del usuario registrado</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-chart-line"></i> <strong>dashboard.php</strong> - Panel del usuario</div>
                    <div class="file-item"><i class="fas fa-comments"></i> <strong>mis-comentarios.php</strong> - Sus comentarios</div>
                    <div class="file-item"><i class="fas fa-edit"></i> <strong>editar-comentario.php</strong> - Editar comentario</div>
                    <div class="file-item"><i class="fas fa-flag"></i> <strong>reportar-comentario.php</strong> - Reportar comentario</div>
                    <div class="file-item"><i class="fas fa-user"></i> <strong>perfil.php</strong> - Perfil del usuario</div>
                </div>
            </div>

            <!-- ajax/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>ajax/</strong>
                    <span class="badge-folder">Peticiones AJAX</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-search"></i> <strong>ajax-buscar-comentarios.php</strong> - Búsqueda de comentarios</div>
                    <div class="file-item"><i class="fas fa-heart"></i> <strong>megusta.php</strong> - Dar/quitar "Me gusta"</div>
                    <div class="file-item"><i class="fas fa-star"></i> <strong>valorar.php</strong> - Valorar noticia</div>
                    <div class="file-item"><i class="fas fa-upload"></i> <strong>upload-editor-image.php</strong> - Subir imagen desde editor</div>
                </div>
            </div>

            <!-- partials/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>partials/</strong>
                    <span class="badge-folder">Plantillas reutilizables</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-heading"></i> <strong>header.php</strong> - Cabecera HTML (incluye CSS/JS)</div>
                    <div class="file-item"><i class="fas fa-copyright"></i> <strong>footer.php</strong> - Pie de página</div>
                    <div class="file-item"><i class="fas fa-bars"></i> <strong>menu-unificado.php</strong> - Menú dinámico según rol</div>
                </div>
            </div>

            <!-- assets/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>assets/</strong>
                    <span class="badge-folder">Recursos estáticos</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fab fa-css3-alt"></i> <strong>css/app-css/</strong> - Archivos CSS (originales y minificados)</div>
                    <div class="file-item"><i class="fab fa-js"></i> <strong>js/app-js/</strong> - JavaScript de la aplicación en una única fuente versionada</div>
                    <div class="file-item"><i class="fas fa-images"></i> <strong>img/</strong> - Imágenes del sistema (logo, favicon, etc.)</div>
                </div>
            </div>

            <!-- uploads/ -->
            <div class="folder-card">
                <div class="folder-header">
                    <i class="fas fa-folder"></i> <strong>uploads/</strong>
                    <span class="badge-folder">Archivos subidos</span>
                </div>
                <div class="folder-content">
                    <div class="file-item"><i class="fas fa-newspaper"></i> <strong>noticias/</strong> - Imágenes y vídeos de noticias</div>
                    <div class="file-item"><i class="fas fa-user-circle"></i> <strong>perfiles/</strong> - Avatares de usuarios</div>
                    <div class="file-item"><i class="fas fa-edit"></i> <strong>editor/</strong> - Imágenes subidas desde TinyMCE</div>
                    <div class="file-item"><i class="fas fa-comment"></i> <strong>comentarios/</strong> - Imágenes en comentarios</div>
                </div>
            </div>
        </div>
    <!-- ============================================================ -->
<!-- TAB 6 - ARQUITECTURA DE ARCHIVOS (100% ancho) -->
<!-- ============================================================ -->
<div id="tab6" class="tab-content">
    <div class="arquitectura-fullwidth">
        <h2><i class="fas fa-folder-tree"></i> Arquitectura de Archivos y Carpetas</h2>
        <p>Estructura completa del sistema, con la función principal de cada directorio y archivo.</p>
        
        <!-- ============================================ -->
        <!-- ESTRUCTURA COMPLETA (NO SIMPLIFICADA) -->
        <!-- ============================================ -->
        <div class="tree-container-full">
            <div class="tree-header-full">
                <i class="fas fa-diagram-project"></i>
                <h3>Estructura completa del proyecto</h3>
                <span class="tree-badge">v3 · En desarrollo</span>
            </div>
            
            <div class="tree-content-full">
                <code class="tree-code-full">
<span class="tree-comment">📁 / (raíz del proyecto)</span>
├── <span class="file">index.php</span>                      <span class="comment-full">→ Front controller, enruta todas las peticiones</span>
├── <span class="file">.htaccess</span>                      <span class="comment-full">→ Reglas de reescritura (HTTPS, URLs, seguridad)</span>
├── <span class="file">logout.php</span>                     <span class="comment-full">→ Cierra la sesión del usuario</span>
├── <span class="file">maintenance.php</span>                <span class="comment-full">→ Página de modo mantenimiento</span>
│
├── 📁 <span class="folder">includes/</span>                  <span class="comment-full">→ Núcleo de la aplicación</span>
│   ├── <span class="file">bootstrap.php</span>               <span class="comment-full">→ Carga común mínima de cada página</span>
│   ├── <span class="file">config.php</span>                  <span class="comment-full">→ Constantes, sesión, rutas y helpers globales</span>
│   ├── <span class="file">routes.php</span>                  <span class="comment-full">→ Mapeo de rutas (nombre → archivo)</span>
│   ├── <span class="file">conexion.php</span>                 <span class="comment-full">→ Conexión PDO a MySQL</span>
│   ├── <span class="file">auth.php</span>                     <span class="comment-full">→ Clase de autenticación (login/logout)</span>
│   ├── <span class="file">permisos.php</span>                 <span class="comment-full">→ Verificación de roles (admin, periodista, usuario)</span>
│   ├── <span class="file">privado.php</span>                  <span class="comment-full">→ Funciones para área privada</span>
│   ├── <span class="file">minify.php</span>                   <span class="comment-full">→ Minificación de CSS/JS</span>
│   ├── <span class="file">upload-handler.php</span>            <span class="comment-full">→ Clase para subir archivos</span>
│   ├── <span class="file">valoraciones.php</span>              <span class="comment-full">→ Sistema de valoración (1-3 estrellas)</span>
│   │
│   └── 📁 <span class="folder">helpers/</span>                <span class="comment-full">→ Funciones auxiliares organizadas</span>
│       ├── <span class="file">fechas.php</span>                <span class="comment-full">→ formatearFecha(), tiempoTranscurrido()</span>
│       ├── <span class="file">texto.php</span>                 <span class="comment-full">→ truncarTexto(), obtenerPrimerParrafo()</span>
│       ├── <span class="file">validacion.php</span>             <span class="comment-full">→ validarEmail(), validarTelefono()</span>
│       ├── <span class="file">seguridad.php</span>               <span class="comment-full">→ limpiarDatos(), obtenerIP()</span>
│       ├── <span class="file">slug.php</span>                   <span class="comment-full">→ generarSlug()</span>
│       ├── <span class="file">flash.php</span>                  <span class="comment-full">→ mensajeFlash(), obtenerMensajeFlash()</span>
│       ├── <span class="file">url.php</span>                    <span class="comment-full">→ base_url(), redireccionar(), current_url()</span>
│       ├── <span class="file">csrf.php</span>                   <span class="comment-full">→ Tokens CSRF</span>
│       └── <span class="file">login-attempts.php</span>          <span class="comment-full">→ Control de intentos fallidos</span>
│       ├── <span class="file">clasificacion.php</span>           <span class="comment-full">→ detectarTemaRss(), diccionarios de temas</span>
│       ├── <span class="file">noticias.php</span>                <span class="comment-full">→ Consultas de noticias con JOIN a regiones</span>
│
├── 📁 <span class="folder">public/</span>                     <span class="comment-full">→ Vistas públicas (acceso sin login)</span>
│   ├── <span class="file">portada.php</span>                  <span class="comment-full">→ Página principal (últimas noticias)</span>
│   ├── <span class="file">noticia.php</span>                  <span class="comment-full">→ Detalle de noticia con galería y comentarios</span>
│   ├── <span class="file">categoria.php</span>                <span class="comment-full">→ Noticias por categoría</span>
│   ├── <span class="file">periodistas.php</span>              <span class="comment-full">→ Listado de periodistas</span>
│   ├── <span class="file">ultimas.php</span>                  <span class="comment-full">→ Últimas noticias</span>
│   ├── <span class="file">populares.php</span>                <span class="comment-full">→ Noticias más visitadas</span>
│   ├── <span class="file">buscar-avanzado.php</span>          <span class="comment-full">→ Búsqueda con múltiples filtros</span>
│   ├── <span class="file">ubicacion.php</span>                <span class="comment-full">→ Noticias por provincia/lugar internacional</span>
│   ├── <span class="file">fuente.php</span>                   <span class="comment-full">→ Noticias por fuente</span>
│   ├── <span class="file">login.php</span>                    <span class="comment-full">→ Formulario de inicio de sesión</span>
│   ├── <span class="file">registro.php</span>                 <span class="comment-full">→ Registro de usuarios</span>
│   ├── <span class="file">contacto.php</span>                 <span class="comment-full">→ Formulario de contacto</span>
│   ├── <span class="file">terminos.php</span>                 <span class="comment-full">→ Términos y condiciones</span>
│   ├── <span class="file">privacidad.php</span>               <span class="comment-full">→ Política de privacidad</span>
│   ├── <span class="file">ver-valoraciones.php</span>          <span class="comment-full">→ Estadísticas de valoración</span>
│   └── <span class="file">ver-relacionadas.php</span>          <span class="comment-full">→ Noticias relacionadas</span>
│   ├── <span class="file">listado-noticias.php</span>        <span class="comment-full">→ Listado filtrable por región y categoría</span>
│   ├── <span class="file">buscar-comentarios.php</span>      <span class="comment-full">→ Búsqueda de comentarios</span>
│   ├── <span class="file">tiempo.php</span>                  <span class="comment-full">→ Predicción meteorológica (AEMET/Open-Meteo)</span>
│
├── 📁 <span class="folder">admin/</span>                      <span class="comment-full">→ Panel de administración (solo admin)</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment-full">→ Estadísticas generales</span>
│   ├── <span class="file">usuarios-logueados.php</span>       <span class="comment-full">→ Gestión de usuarios (CRUD)</span>
│   ├── <span class="file">noticias.php</span>                <span class="comment-full">→ Gestión de noticias</span>
│   ├── <span class="file">categorias.php</span>               <span class="comment-full">→ Gestión de categorías</span>
│   ├── <span class="file">periodistas.php</span>              <span class="comment-full">→ Gestión de periodistas</span>
│   ├── <span class="file">comentarios.php</span>              <span class="comment-full">→ Moderación de comentarios</span>
│   ├── <span class="file">mensajes.php</span>                 <span class="comment-full">→ Mensajes de contacto</span>
│   ├── <span class="file">configuracion.php</span>            <span class="comment-full">→ Configuración general del sitio</span>
│   ├── <span class="file">ataques.php</span>                  <span class="comment-full">→ Monitor de intentos fallidos de login</span>
│   ├── <span class="file">noticias-privadas.php</span>        <span class="comment-full">→ Gestión de noticias privadas</span>
│   ├── <span class="file">noticias-relacionadas.php</span>    <span class="comment-full">→ Relaciones entre noticias</span>
│   └── <span class="file">editar-noticia.php</span>           <span class="comment-full">→ Editar noticia (admin)</span>
│   ├── <span class="file">gestion-fuentes.php</span>        <span class="comment-full">→ Gestión de fuentes RSS manuales</span>
│   ├── <span class="file">rss-config.php</span>              <span class="comment-full">→ Configuración de feeds RSS con región</span>
│
├── 📁 <span class="folder">periodista/</span>                 <span class="comment-full">→ Panel de periodistas</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment-full">→ Panel personal de periodista</span>
│   ├── <span class="file">mis-noticias.php</span>             <span class="comment-full">→ Listado de sus noticias</span>
│   ├── <span class="file">nueva-noticia.php</span>            <span class="comment-full">→ Crear noticia (con editor TinyMCE)</span>
│   ├── <span class="file">editar-noticia.php</span>           <span class="comment-full">→ Editar su noticia</span>
│   ├── <span class="file">eliminar-noticia.php</span>         <span class="comment-full">→ Eliminar su noticia</span>
│   ├── <span class="file">importar-rss.php</span>            <span class="comment-full">→ Importar noticias RSS con región y tema</span>
│   └── <span class="file">perfil.php</span>                   <span class="comment-full">→ Perfil del periodista</span>
│
├── 📁 <span class="folder">privado/</span>                    <span class="comment-full">→ Área privada (periodistas con permiso especial)</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment-full">→ Panel privado</span>
│   ├── <span class="file">mis-noticias-privadas.php</span>    <span class="comment-full">→ Noticias privadas del periodista</span>
│   └── <span class="file">buscar-privadas.php</span>          <span class="comment-full">→ Búsqueda interna en noticias privadas</span>
│
├── 📁 <span class="folder">usuario/</span>                    <span class="comment-full">→ Panel de usuarios registrados</span>
│   ├── <span class="file">dashboard.php</span>                <span class="comment-full">→ Panel personal de usuario</span>
│   ├── <span class="file">mis-comentarios.php</span>          <span class="comment-full">→ Listado de sus comentarios</span>
│   ├── <span class="file">editar-comentario.php</span>        <span class="comment-full">→ Editar su comentario</span>
│   ├── <span class="file">eliminar-comentario.php</span>      <span class="comment-full">→ Eliminar su comentario</span>
│   ├── <span class="file">reportar-comentario.php</span>      <span class="comment-full">→ Reportar comentario ofensivo</span>
│   └── <span class="file">perfil.php</span>                   <span class="comment-full">→ Perfil de usuario</span>
│
├── 📁 <span class="folder">ajax/</span>                       <span class="comment-full">→ Peticiones asíncronas (AJAX)</span>
│   ├── <span class="file">ajax-buscar-comentarios.php</span>  <span class="comment-full">→ Buscar comentarios en tiempo real</span>
│   ├── <span class="file">megusta.php</span>                  <span class="comment-full">→ Dar/quitar "me gusta"</span>
│   ├── <span class="file">valorar.php</span>                  <span class="comment-full">→ Valorar noticia (1-3 estrellas)</span>
│   └── <span class="file">upload-editor-image.php</span>       <span class="comment-full">→ Subir imagen desde TinyMCE</span>
│
├── 📁 <span class="folder">partials/</span>                   <span class="comment-full">→ Plantillas reutilizables</span>
│   ├── <span class="file">header.php</span>                   <span class="comment-full">→ Cabecera (DOCTYPE, CSS, menú, scripts)</span>
│   ├── <span class="file">footer.php</span>                   <span class="comment-full">→ Pie de página (cierre de etiquetas)</span>
│   └── <span class="file">menu-unificado.php</span>            <span class="comment-full">→ Menú dinámico según rol del usuario</span>
│
├── 📁 <span class="folder">assets/</span>                     <span class="comment-full">→ Recursos estáticos</span>
│   ├── 📁 <span class="folder">css/</span>                    <span class="comment-full">→ Archivos CSS (originales y minificados)</span>
│   │   ├── <span class="folder">app-css/</span>               <span class="comment-full">→ Archivos CSS de la aplicación</span>
│   │   └── <span class="folder">min/</span>                   <span class="comment-full">→ Archivos CSS minificados</span>
│   ├── 📁 <span class="folder">js/</span>                     <span class="comment-full">→ JavaScript en una única fuente versionada</span>
│   │   └── <span class="folder">app-js/</span>                <span class="comment-full">→ Archivos JS de la aplicación</span>
│   └── 📁 <span class="folder">img/</span>                    <span class="comment-full">→ Imágenes del sistema (logo, favicon, etc.)</span>
│
├── 📁 <span class="folder">uploads/</span>                    <span class="comment-full">→ Archivos subidos por usuarios</span>
│   ├── 📁 <span class="folder">noticias/</span>               <span class="comment-full">→ Imágenes y vídeos de noticias</span>
│   ├── 📁 <span class="folder">perfiles/</span>               <span class="comment-full">→ Avatares de usuarios</span>
│   ├── 📁 <span class="folder">editor/</span>                 <span class="comment-full">→ Imágenes subidas desde el editor TinyMCE</span>
│   └── 📁 <span class="folder">comentarios/</span>             <span class="comment-full">→ Imágenes subidas en comentarios</span>
│
├── 📁 <span class="folder">logs/</span>                       <span class="comment-full">→ Archivos de log</span>
│   └── <span class="file">error.log</span>                    <span class="comment-full">→ Registro de errores de PHP</span>
│
├── 📁 <span class="folder">cache/</span>                      <span class="comment-full">→ Archivos de caché</span>
│   └── <span class="file">minify_mode.cache</span>            <span class="comment-full">→ Modo de minificación (desarrollo/producción)</span>
│
├── 📁 <span class="folder">tmp/</span>                        <span class="comment-full">→ Archivos temporales</span>
│
└── 📁 <span class="folder">backups/</span>                    <span class="comment-full">→ Copias de seguridad</span>
    └── <span class="file">database/</span>                    <span class="comment-full">→ Backups de base de datos</span>
                </code>
            </div>
            
            <div class="tree-footer-full">
                <i class="fas fa-info-circle"></i>
                <span>Los archivos marcados como <span class="file">archivo.php</span> son controladores/vistas; 
                <span class="folder">carpeta/</span> indica directorio.</span>
            </div>
        </div>
    </div>
</div>    
    </div>

    <!-- ============================================================ -->
    <!-- TAB 7 · NUEVO CONTENIDO (vacío - reservado) -->
    <!-- ============================================================ -->
    <!-- TAB 7 - POLÍTICA DE COOKIES -->
<div id="tab7" class="tab-content">
    <div class="cookies-policy-container">
        <div class="cookies-policy-header">
            <h2><i class="fas fa-cookie-bite"></i> Política de Cookies</h2>
            <p>Información completa sobre el uso de cookies en el Portal de Noticias</p>
        </div>

        <!-- Resumen ejecutivo -->
        <div class="cookies-summary">
            <div class="summary-card">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Cumplimos con GDPR</strong>
                    <small>Aviso de cookies visible al entrar</small>
                </div>
            </div>
            <div class="summary-card">
                <i class="fas fa-sliders-h"></i>
                <div>
                    <strong>Configuración granular</strong>
                    <small>Acepta, rechaza o configura</small>
                </div>
            </div>
            <div class="summary-card">
                <i class="fas fa-database"></i>
                <div>
                    <strong>Cookies necesarias</strong>
                    <small>Siempre activas por seguridad</small>
                </div>
            </div>
        </div>

        <!-- ¿Qué son las cookies? -->
        <div class="cookies-section">
            <h3><i class="fas fa-question-circle"></i> ¿Qué son las cookies?</h3>
            <p>Las cookies son pequeños archivos de texto que los sitios web colocan en tu dispositivo para recordar tus acciones y preferencias. No contienen virus y son seguras.</p>
        </div>

        <!-- Tipos de cookies -->
        <div class="cookies-section">
            <h3><i class="fas fa-tag"></i> Tipos de cookies que utilizamos</h3>
            <div class="cookies-types-grid">
                <div class="cookie-type">
                    <span class="cookie-badge necessary">Necesarias</span>
                    <p>Esenciales para el funcionamiento del sitio: autenticación, seguridad, sesión.</p>
                </div>
                <div class="cookie-type">
                    <span class="cookie-badge preferences">Preferencias</span>
                    <p>Recuerdan tus preferencias: idioma, tema oscuro, configuración personal.</p>
                </div>
                <div class="cookie-type">
                    <span class="cookie-badge analytics">Análisis</span>
                    <p>Nos ayudan a mejorar el sitio: Google Analytics, comportamiento de usuarios.</p>
                </div>
            </div>
        </div>

        <!-- Tabla de cookies -->
        <div class="cookies-section">
            <h3><i class="fas fa-table-list"></i> Cookies específicas</h3>
            <div class="table-responsive">
                <table class="cookies-table-detail">
                    <thead>
                        <tr><th>Nombre</th><th>Tipo</th><th>Duración</th><th>Descripción</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>news_session</code></td><td><span class="cookie-badge necessary">Necesaria</span></td><td>Sesión</td><td>Mantiene tu sesión iniciada</td></tr>
                        <tr><td><code>csrf_token</code></td><td><span class="cookie-badge necessary">Necesaria</span></td><td>Sesión</td><td>Protege formularios contra ataques</td></tr>
                        <tr><td><code>visitor_id</code></td><td><span class="cookie-badge preferences">Preferencia</span></td><td>1 año</td><td>Identifica visitantes para valoraciones</td></tr>
                        <tr><td><code>cookie_consent</code></td><td><span class="cookie-badge necessary">Necesaria</span></td><td>1 año</td><td>Guarda tu preferencia de cookies</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cómo gestionar -->
        <div class="cookies-section">
            <h3><i class="fas fa-sliders-h"></i> ¿Cómo gestionar las cookies?</h3>
            <ul>
                <li><strong>Desde nuestro banner:</strong> Al entrar al sitio, puedes aceptar, rechazar o configurar las cookies.</li>
                <li><strong>Configuración del navegador:</strong> Puedes bloquear o eliminar cookies desde la configuración de tu navegador.</li>
            </ul>
            <div class="browser-links">
                <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer"><i class="fab fa-chrome"></i> Chrome</a>
                <a href="https://support.mozilla.org/es/kb/Borrar%20cookies" target="_blank" rel="noopener noreferrer"><i class="fab fa-firefox"></i> Firefox</a>
                <a href="https://support.apple.com/es-es/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer"><i class="fab fa-safari"></i> Safari</a>
                <a href="https://support.microsoft.com/es-es/microsoft-edge/eliminar-las-cookies-en-microsoft-edge" target="_blank" rel="noopener noreferrer"><i class="fab fa-edge"></i> Edge</a>
            </div>
        </div>

        <!-- Fecha y contacto -->
        <div class="cookies-footer">
            <p><i class="fas fa-calendar-alt"></i> Última actualización: <strong>Abril 2026</strong></p>
            <p><i class="fas fa-envelope"></i> Dudas: <a href="mailto:auguxtux@gmail.com">auguxtux@gmail.com</a> | <a href="<?php echo route('contacto'); ?>">Formulario de contacto</a></p>
            <button class="btn-cookie-reset" onclick="resetCookiesAndReload()">
                <i class="fas fa-sync-alt"></i> Resetear preferencias de cookies
            </button>
        </div>
    </div>
    <!-- ======================================== -->
<!-- ARCHIVOS INVOLUCRADOS EN LA APP -->
<!-- ======================================== -->
<div class="cookies-section">
    <h3><i class="fas fa-file-code"></i> Archivos involucrados en la gestión de cookies</h3>
    <p>Los siguientes archivos son responsables del sistema de cookies y el consentimiento del usuario:</p>
    
    <div class="archivos-grid">
        <div class="archivo-card">
            <div class="archivo-icon"><i class="fab fa-php"></i></div>
            <div class="archivo-info">
                <strong>partials/cookie-consent.php</strong>
                <span class="archivo-desc">Banner de cookies, modal de configuración y lógica de consentimiento</span>
                <span class="archivo-funciones">Funciones: mostrar banner, aceptar/rechazar/ configurar cookies, guardar preferencias en localStorage</span>
            </div>
        </div>
        
        <div class="archivo-card">
            <div class="archivo-icon"><i class="fab fa-php"></i></div>
            <div class="archivo-info">
                <strong>public/cookies.php</strong>
                <span class="archivo-desc">Página completa de política de cookies</span>
                <span class="archivo-funciones">Funciones: informar sobre uso de cookies, listar cookies específicas, enlaces de ayuda</span>
            </div>
        </div>
        
        <div class="archivo-card">
            <div class="archivo-icon"><i class="fab fa-js"></i></div>
            <div class="archivo-info">
                <strong>localStorage (navegador)</strong>
                <span class="archivo-desc">Almacenamiento local del navegador para guardar preferencias</span>
                <span class="archivo-funciones">Variables: 'cookie_consent', 'cookie_preferencias', 'cookie_analiticas'</span>
            </div>
        </div>
        
        <div class="archivo-card">
            <div class="archivo-icon"><i class="fas fa-database"></i></div>
            <div class="archivo-info">
                <strong>Sesiones PHP</strong>
                <span class="archivo-desc">Manejo de sesiones para autenticación</span>
                <span class="archivo-funciones">Variables: news_session, usuario_id, usuario_rol, usuario_nombre</span>
            </div>
        </div>
    </div>
</div>

<!-- ======================================== -->
<!-- FUNCIONES GLOBALES DE LA APP -->
<!-- ======================================== -->
<div class="cookies-section">
    <h3><i class="fas fa-cogs"></i> Funciones globales de la aplicación</h3>
    <p>Estas funciones están disponibles en toda la aplicación y son esenciales para su funcionamiento:</p>
    
    <div class="funciones-grid">
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-database"></i>
                <strong>db()</strong>
                <span class="funcion-archivo">conexion.php</span>
            </div>
            <p class="funcion-desc">Devuelve la conexión PDO a la base de datos.</p>
            <code class="funcion-ejemplo">$pdo = db();</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-shield-alt"></i>
                <strong>limpiarDatos()</strong>
                <span class="funcion-archivo">seguridad.php (helpers)</span>
            </div>
            <p class="funcion-desc">Limpia y normaliza datos de entrada (trim + validación de tipo).</p>
            <code class="funcion-ejemplo">$limpio = limpiarDatos($_POST['nombre']);</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-calendar"></i>
                <strong>formatearFecha()</strong>
                <span class="funcion-archivo">fechas.php (helpers)</span>
            </div>
            <p class="funcion-desc">Formatea una fecha para mostrar al usuario.</p>
            <code class="funcion-ejemplo">echo formatearFecha($noticia['fecha']);</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-link"></i>
                <strong>base_url()</strong>
                <span class="funcion-archivo">url.php (helpers)</span>
            </div>
            <p class="funcion-desc">Genera URLs absolutas del sitio.</p>
            <code class="funcion-ejemplo">&lt;img src="<span class="funcion-codigo">&lt;?php echo base_url('assets/img/logo.png'); ?&gt;</span>" alt="Logo"&gt;</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-sign-in-alt"></i>
                <strong>estaLogueado()</strong>
                <span class="funcion-archivo">funciones.php</span>
            </div>
            <p class="funcion-desc">Verifica si el usuario tiene sesión activa.</p>
            <code class="funcion-ejemplo">if (estaLogueado()) { ... }</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-envelope"></i>
                <strong>mensajeFlash()</strong>
                <span class="funcion-archivo">flash.php (helpers)</span>
            </div>
            <p class="funcion-desc">Guarda mensajes temporales entre páginas.</p>
            <code class="funcion-ejemplo">mensajeFlash('success', 'Guardado correctamente');</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-tachometer-alt"></i>
                <strong>redirigirSegunRol()</strong>
                <span class="funcion-archivo">funciones.php</span>
            </div>
            <p class="funcion-desc">Redirige al dashboard según el rol del usuario.</p>
            <code class="funcion-ejemplo">redirigirSegunRol();</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-tag"></i>
                <strong>generarSlug()</strong>
                <span class="funcion-archivo">slug.php (helpers)</span>
            </div>
            <p class="funcion-desc">Genera slugs para URLs amigables.</p>
            <code class="funcion-ejemplo">$slug = generarSlug("Mi título"); // "mi-titulo"</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-map-marker-alt"></i>
                <strong>detectarTemaRss()</strong>
                <span class="funcion-archivo">clasificacion.php (helpers)</span>
            </div>
            <p class="funcion-desc">Detecta automáticamente el tema de una noticia RSS por palabras clave.</p>
            <code class="funcion-ejemplo">$tema = detectarTemaRss($titulo, $extracto);</code>
        </div>
        
        <div class="funcion-card">
            <div class="funcion-header">
                <i class="fas fa-csrf"></i>
                <strong>generarTokenCSRF() / verificarTokenCSRF()</strong>
                <span class="funcion-archivo">csrf.php (helpers)</span>
            </div>
            <p class="funcion-desc">Protege formularios contra ataques CSRF.</p>
            <code class="funcion-ejemplo">&lt;input type="hidden" name="csrf_token" value="<span class="funcion-codigo">&lt;?php echo generarTokenCSRF(); ?&gt;</span>"&gt;</code>
        </div>
    </div>
</div>

<!-- ======================================== -->
<!-- TABLAS DE LA BASE DE DATOS -->
<!-- ======================================== -->
<div class="cookies-section">
    <h3><i class="fas fa-database"></i> Tablas principales de la base de datos</h3>
    <p>Estructura de datos que almacena toda la información del portal:</p>
    
    <div class="tablas-grid">
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-users"></i> <strong>usuarios</strong></div>
            <div class="tabla-campos">id_usuario, email, password, nombre, telefono, ciudad, rol, estado, avatar</div>
            <div class="tabla-desc">Almacena todos los usuarios del sistema (admin, periodistas, usuarios normales)</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-newspaper"></i> <strong>noticias</strong></div>
            <div class="tabla-campos">id_noticia, titulo, slug, contenido, imagen_principal, video_nombre, id_autor, id_categoria, visitas, privada</div>
            <div class="tabla-desc">Contiene todas las noticias (públicas y privadas)</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-tags"></i> <strong>categorias</strong></div>
            <div class="tabla-campos">id_categoria, nombre_categoria, slug_categoria, descripcion, activa</div>
            <div class="tabla-desc">Clasificación de noticias por temas</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-comments"></i> <strong>comentarios</strong></div>
            <div class="tabla-campos">id_comentario, id_noticia, id_usuario, contenido, estado, fecha_comentario</div>
            <div class="tabla-desc">Comentarios de usuarios en las noticias</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-star"></i> <strong>megusta_noticias</strong></div>
            <div class="tabla-campos">id_megusta, id_noticia, id_usuario, tipo_usuario, valoracion (1-3)</div>
            <div class="tabla-desc">Valoraciones de los usuarios sobre las noticias</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-cog"></i> <strong>configuracion</strong></div>
            <div class="tabla-campos">id_config, clave, valor, tipo</div>
            <div class="tabla-desc">Configuración general del sitio (nombre, límites, permisos)</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-map-marker-alt"></i> <strong>provincias</strong></div>
            <div class="tabla-campos">id_provincia, nombre, slug, id_comunidad</div>
            <div class="tabla-desc">Provincias de España para geolocalización de noticias</div>
        </div>
        
        <div class="tabla-card">
            <div class="tabla-header"><i class="fas fa-shield-virus"></i> <strong>login_attempts</strong></div>
            <div class="tabla-campos">id_attempt, email, ip, intentos, bloqueado_hasta</div>
            <div class="tabla-desc">Registro de intentos fallidos de login para seguridad</div>
        </div>
    </div>
</div>
</div>

    <!-- ============================================================ -->
    <!-- TAB 8 · NUEVO CONTENIDO (vacío - reservado) -->
    <!-- ============================================================ -->
    <!-- TAB 8 - ANÁLISIS DE BASE DE DATOS -->
<div id="tab8" class="tab-content">
    <div class="db-container">
        <!-- Cabecera -->
        <div class="db-header">
            <h2><i class="fas fa-database"></i> Análisis de la Base de Datos</h2>
            <p>Estructura, relaciones y características del sistema de almacenamiento</p>
        </div>

        <!-- Resumen ejecutivo -->
        <div class="db-summary">
            <div class="summary-card-db">
                <i class="fas fa-table"></i>
                <div class="summary-number">14</div>
                <div class="summary-label">Tablas</div>
            </div>
            <div class="summary-card-db">
                <i class="fas fa-link"></i>
                <div class="summary-number">15+</div>
                <div class="summary-label">Relaciones</div>
            </div>
            <div class="summary-card-db">
                <i class="fas fa-key"></i>
                <div class="summary-number">12</div>
                <div class="summary-label">Claves Primarias</div>
            </div>
            <div class="summary-card-db">
                <i class="fas fa-database"></i>
                <div class="summary-number">~1.5GB</div>
                <div class="summary-label">Tamaño aprox.</div>
            </div>
        </div>

        <!-- ======================================== -->
        <!-- MODELO ENTIDAD-RELACIÓN (MER) -->
        <!-- ======================================== -->
        <div class="db-section">
            <div class="db-section-header">
                <i class="fas fa-project-diagram"></i>
                <h3>Modelo Entidad-Relación (MER)</h3>
                <span class="db-badge">Diseño conceptual</span>
            </div>
            <p class="db-section-desc">Representación de las entidades principales y sus relaciones en el sistema:</p>
            
            <div class="mer-diagram">
                <div class="entity-box entity-main">
                    <div class="entity-title"><i class="fas fa-users"></i> USUARIOS</div>
                    <div class="entity-attributes">
                        <span class="pk">🔑 id_usuario (PK)</span>
                        <span>📧 email</span>
                        <span>🔒 password</span>
                        <span>👤 nombre</span>
                        <span>📞 telefono</span>
                        <span>🏙️ ciudad</span>
                        <span>🎭 rol interno (admin, periodista, usuario)</span>
                        <span>⚡ estado</span>
                        <span>🖼️ avatar</span>
                    </div>
                </div>

                <div class="relationship">
                    <div class="rel-line">
                        <div class="rel-cardinality">1</div>
                        <div class="rel-line-horiz"></div>
                        <div class="rel-cardinality">N</div>
                    </div>
                    <div class="rel-label">escribe</div>
                </div>

                <div class="entity-box">
                    <div class="entity-title"><i class="fas fa-newspaper"></i> NOTICIAS</div>
                    <div class="entity-attributes">
                        <span class="pk">🔑 id_noticia (PK)</span>
                        <span>📌 titulo</span>
                        <span>🔗 slug</span>
                        <span>📄 contenido</span>
                        <span>🖼️ imagen_principal</span>
                        <span>🎬 video_nombre</span>
                        <span>👁️ visitas</span>
                        <span>🔒 privada</span>
                        <span class="fk">🔗 id_autor (FK → usuarios)</span>
                        <span class="fk">📁 id_categoria (FK → categorias)</span>
                    </div>
                </div>

                <div class="relationship">
                    <div class="rel-line">
                        <div class="rel-cardinality">1</div>
                        <div class="rel-line-horiz"></div>
                        <div class="rel-cardinality">N</div>
                    </div>
                    <div class="rel-label">pertenece</div>
                </div>

                <div class="entity-box">
                    <div class="entity-title"><i class="fas fa-tags"></i> CATEGORIAS</div>
                    <div class="entity-attributes">
                        <span class="pk">🔑 id_categoria (PK)</span>
                        <span>📂 nombre_categoria</span>
                        <span>🔗 slug_categoria</span>
                        <span>📝 descripcion</span>
                        <span>✅ activa</span>
                    </div>
                </div>
            </div>

            <div class="relationship-horizontal">
                <div class="entity-small">USUARIOS</div>
                <div class="rel-info">1 ──────────────────────────── N</div>
                <div class="entity-small">COMENTARIOS</div>
            </div>
            <div class="relationship-horizontal">
                <div class="entity-small">NOTICIAS</div>
                <div class="rel-info">1 ──────────────────────────── N</div>
                <div class="entity-small">COMENTARIOS</div>
            </div>
            <div class="relationship-horizontal">
                <div class="entity-small">USUARIOS</div>
                <div class="rel-info">1 ──────────────────────────── N</div>
                <div class="entity-small">MEGUSTA_NOTICIAS</div>
            </div>
            <div class="relationship-horizontal">
                <div class="entity-small">NOTICIAS</div>
                <div class="rel-info">1 ──────────────────────────── N</div>
                <div class="entity-small">MEGUSTA_NOTICIAS</div>
            </div>
        </div>

        <!-- ======================================== -->
        <!-- DIAGRAMA DE RELACIONES (MER simplificado) -->
        <!-- ======================================== -->
        <div class="db-section">
            <div class="db-section-header">
                <i class="fas fa-code-branch"></i>
                <h3>Diagrama de Relaciones (Modelo Relacional)</h3>
                <span class="db-badge">Estructura física</span>
            </div>
            
            <div class="relational-diagram">
                <div class="rel-table">
                    <div class="rel-table-header">usuarios</div>
                    <div class="rel-table-row pk">id_usuario <span class="type">INT</span></div>
                    <div class="rel-table-row">email <span class="type">VARCHAR</span></div>
                    <div class="rel-table-row">password <span class="type">VARCHAR</span></div>
                    <div class="rel-table-row">nombre <span class="type">VARCHAR</span></div>
                    <div class="rel-table-row">rol <span class="type">ENUM</span></div>
                </div>

                <div class="rel-arrow">↓ 1 : N ↑</div>

                <div class="rel-table">
                    <div class="rel-table-header">noticias</div>
                    <div class="rel-table-row pk">id_noticia <span class="type">INT</span></div>
                    <div class="rel-table-row">titulo <span class="type">VARCHAR</span></div>
                    <div class="rel-table-row fk">id_autor <span class="type">INT</span></div>
                    <div class="rel-table-row fk">id_categoria <span class="type">INT</span></div>
                </div>

                <div class="rel-arrow">↓ 1 : N ↑</div>

                <div class="rel-table">
                    <div class="rel-table-header">comentarios</div>
                    <div class="rel-table-row pk">id_comentario <span class="type">INT</span></div>
                    <div class="rel-table-row fk">id_noticia <span class="type">INT</span></div>
                    <div class="rel-table-row fk">id_usuario <span class="type">INT</span></div>
                    <div class="rel-table-row">contenido <span class="type">TEXT</span></div>
                </div>
            </div>

            <div class="relational-diagram second">
                <div class="rel-table">
                    <div class="rel-table-header">noticias</div>
                    <div class="rel-table-row pk">id_noticia <span class="type">INT</span></div>
                    <div class="rel-table-row fk">id_categoria <span class="type">INT</span></div>
                </div>

                <div class="rel-arrow">↓ N : 1 ↑</div>

                <div class="rel-table">
                    <div class="rel-table-header">categorias</div>
                    <div class="rel-table-row pk">id_categoria <span class="type">INT</span></div>
                    <div class="rel-table-row">nombre_categoria <span class="type">VARCHAR</span></div>
                </div>
            </div>
        </div>

        <!-- ======================================== -->
        <!-- TABLAS PRINCIPALES Y SUS CAMPOS -->
        <!-- ======================================== -->
        <div class="db-section">
            <div class="db-section-header">
                <i class="fas fa-table-list"></i>
                <h3>Tablas principales del sistema</h3>
                <span class="db-badge">14 tablas</span>
            </div>

            <div class="tables-grid">
                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-users"></i>
                        <strong>usuarios</strong>
                        <span class="table-badge">Núcleo</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_usuario</span> - Identificador único</div>
                        <div class="detail-field">📧 email - Correo electrónico (único)</div>
                        <div class="detail-field">🔒 password - Contraseña hasheada</div>
                        <div class="detail-field">👤 nombre - Nombre completo</div>
                        <div class="detail-field">📞 telefono - Teléfono de contacto</div>
                        <div class="detail-field">🏙️ ciudad - Ciudad de residencia</div>
                        <div class="detail-field">🎭 rol interno - admin | periodista | usuario</div>
                        <div class="detail-field">⚡ estado - activo | inactivo | bloqueado</div>
                        <div class="detail-field">🖼️ avatar - Foto de perfil</div>
                        <div class="detail-field">📅 fecha_registro - Fecha de alta</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-newspaper"></i>
                        <strong>noticias</strong>
                        <span class="table-badge">Principal</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_noticia</span> - Identificador único</div>
                        <div class="detail-field">📌 titulo - Título de la noticia</div>
                        <div class="detail-field">🔗 slug - URL amigable</div>
                        <div class="detail-field">📄 contenido - Cuerpo de la noticia (HTML)</div>
                        <div class="detail-field">🖼️ imagen_principal - Imagen destacada</div>
                        <div class="detail-field">🎬 video_nombre - Video local o externo</div>
                        <div class="detail-field">👁️ visitas - Contador de visitas</div>
                        <div class="detail-field">🔒 privada - 0=pública, 1=privada</div>
                        <div class="detail-field fk">👤 id_autor - FK → usuarios</div>
                        <div class="detail-field fk">📁 id_categoria - FK → categorias</div>
                        <div class="detail-field fk">📍 id_region - FK → regiones</div>
                        <div class="detail-field">🏷️ tema_detectado - Categoría temática automática</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-tags"></i>
                        <strong>categorias</strong>
                        <span class="table-badge">Clasificación</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_categoria</span> - Identificador único</div>
                        <div class="detail-field">📂 nombre_categoria - Nombre (Deportes, Política...)</div>
                        <div class="detail-field">🔗 slug_categoria - URL amigable</div>
                        <div class="detail-field">✅ activa - 1=activa, 0=inactiva</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-comments"></i>
                        <strong>comentarios</strong>
                        <span class="table-badge">Interacción</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_comentario</span> - Identificador único</div>
                        <div class="detail-field fk">📰 id_noticia - FK → noticias</div>
                        <div class="detail-field fk">👤 id_usuario - FK → usuarios</div>
                        <div class="detail-field">💬 contenido - Texto del comentario</div>
                        <div class="detail-field">📅 fecha_comentario - Fecha y hora</div>
                        <div class="detail-field">⚡ estado - aprobado | pendiente | rechazado</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-star"></i>
                        <strong>megusta_noticias</strong>
                        <span class="table-badge">Valoración</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_megusta</span> - Identificador único</div>
                        <div class="detail-field fk">📰 id_noticia - FK → noticias</div>
                        <div class="detail-field fk">👤 id_usuario - FK → usuarios</div>
                        <div class="detail-field">⭐ valoracion - 1=mala, 2=regular, 3=buena</div>
                        <div class="detail-field">📅 fecha_megusta - Fecha de valoración</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-cog"></i>
                        <strong>configuracion</strong>
                        <span class="table-badge">Sistema</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_config</span> - Identificador único</div>
                        <div class="detail-field">🔧 clave - Nombre del parámetro</div>
                        <div class="detail-field">📝 valor - Valor del parámetro</div>
                        <div class="detail-field">📋 tipo - texto | numero | booleano</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <strong>regiones</strong>
                        <span class="table-badge">Geografía</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_region</span> - Identificador único</div>
                        <div class="detail-field">📍 nombre - Nombre de la comunidad autónoma</div>
                        <div class="detail-field">🔗 slug - URL amigable</div>
                    </div>
                </div>

                <div class="table-card-db" onclick="toggleTableDetail(this)">
                    <div class="table-card-header">
                        <i class="fas fa-rss"></i>
                        <strong>fuentes_rss</strong>
                        <span class="table-badge">RSS</span>
                        <i class="fas fa-chevron-down table-toggle"></i>
                    </div>
                    <div class="table-card-detail">
                        <div class="detail-field"><span class="pk">🔑 id_fuente</span> - Identificador único</div>
                        <div class="detail-field">📡 nombre - Nombre de la fuente</div>
                        <div class="detail-field">🔗 url - URL del feed RSS</div>
                        <div class="detail-field">⚡ activa - 1=activa, 0=inactiva</div>
                        <div class="detail-field">🌐 mostrar_externas - 1=visible en bloques externos</div>
                        <div class="detail-field fk">📍 id_region - FK → regiones (región de origen)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================== -->
        <!-- CARACTERÍSTICAS PRINCIPALES -->
        <!-- ======================================== -->
        <div class="db-section">
            <div class="db-section-header">
                <i class="fas fa-chart-line"></i>
                <h3>Características principales de la BD</h3>
                <span class="db-badge">Análisis</span>
            </div>

            <div class="features-grid">
                <div class="feature-card-db">
                    <i class="fas fa-lock"></i>
                    <strong>Seguridad de datos</strong>
                    <p>Contraseñas hasheadas con `password_hash()`, tokens CSRF, control de intentos fallidos de login</p>
                </div>
                <div class="feature-card-db">
                    <i class="fas fa-link"></i>
                    <strong>Integridad referencial</strong>
                    <p>Claves foráneas con ON DELETE CASCADE para mantener consistencia</p>
                </div>
                <div class="feature-card-db">
                    <i class="fas fa-tachometer-alt"></i>
                    <strong>Índices optimizados</strong>
                    <p>Índices en campos de búsqueda frecuente (slug, email, fecha)</p>
                </div>
                <div class="feature-card-db">
                    <i class="fas fa-language"></i>
                    <strong>Juego de caracteres</strong>
                    <p>utf8mb4 con soporte completo para emojis y caracteres especiales</p>
                </div>
                <div class="feature-card-db">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Seguridad adicional</strong>
                    <p>Tabla `login_attempts` para prevenir fuerza bruta, IPs bloqueadas</p>
                </div>
                <div class="feature-card-db">
                    <i class="fas fa-chart-pie"></i>
                    <strong>Análisis de datos</strong>
                    <p>Campos de estadísticas (visitas, valoración_promedio, total_valoraciones)</p>
                </div>
            </div>
        </div>

        <!-- ======================================== -->
        <!-- ESTADÍSTICAS Y MÉTRICAS -->
        <!-- ======================================== -->
        <div class="db-section">
            <div class="db-section-header">
                <i class="fas fa-chart-simple"></i>
                <h3>Métricas y estadísticas</h3>
                <span class="db-badge">Estado actual</span>
            </div>

            <div class="stats-db-grid" id="dbStatsContainer">
                <div class="stat-db-card">
                    <i class="fas fa-spinner fa-pulse"></i>
                    <span>Cargando estadísticas...</span>
                </div>
            </div>
        </div>

        <!-- ======================================== -->
        <!-- DICCIONARIO DE DATOS RESUMIDO -->
        <!-- ======================================== -->
        <div class="db-section">
            <div class="db-section-header">
                <i class="fas fa-book"></i>
                <h3>Diccionario de datos resumido</h3>
                <span class="db-badge">Referencia rápida</span>
            </div>

            <div class="dictionary-grid">
                <div class="dict-card">
                    <div class="dict-term">PK</div>
                    <div class="dict-def">Primary Key - Identificador único de cada registro</div>
                </div>
                <div class="dict-card">
                    <div class="dict-term">FK</div>
                    <div class="dict-def">Foreign Key - Referencia a otra tabla</div>
                </div>
                <div class="dict-card">
                    <div class="dict-term">ENUM</div>
                    <div class="dict-def">Valores predefinidos (admin, periodista, usuario)</div>
                </div>
                <div class="dict-card">
                    <div class="dict-term">TIMESTAMP</div>
                    <div class="dict-def">Fecha y hora automática (current_timestamp)</div>
                </div>
                <div class="dict-card">
                    <div class="dict-term">CASCADE</div>
                    <div class="dict-def">Eliminación en cascada de registros relacionados</div>
                </div>
                <div class="dict-card">
                    <div class="dict-term">NULL</div>
                    <div class="dict-def">Valor opcional, permite campo vacío</div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<footer>
    <i class="fas fa-code-branch"></i> Arquitectura procedural modular · PHP nativo con bootstrap común y helpers <br>
    Documentación técnica: <strong>bootstrap, helpers, routing centralizado, optimización multimedia y monitor de ataques</strong> · v<?= htmlspecialchars(SITE_VERSION, ENT_QUOTES, 'UTF-8'); ?> · Portal Noticias
</footer>
</div>

<script>
    // Control de pestañas
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            // desactivar todos
            tabs.forEach(b => b.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            // activar seleccionado
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Cargar estadísticas de la base de datos vía AJAX
function cargarEstadisticasBD() {
    const container = document.getElementById('dbStatsContainer');
    if (!container) return;
    
    fetch(<?php echo json_encode(route('ajax_estadisticas_bd'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)
        .then(response => {
            if (!response.ok) {
                throw new Error('No se pudieron cargar las estadísticas');
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error('Respuesta de estadísticas no válida');
            }

            container.innerHTML = `
                <div class="stat-db-card"><div class="stat-db-number">${data.usuarios}</div><div class="stat-db-label">Usuarios activos</div></div>
                <div class="stat-db-card"><div class="stat-db-number">${data.noticias}</div><div class="stat-db-label">Noticias publicadas</div></div>
                <div class="stat-db-card"><div class="stat-db-number">${data.comentarios}</div><div class="stat-db-label">Comentarios</div></div>
                <div class="stat-db-card"><div class="stat-db-number">${data.categorias}</div><div class="stat-db-label">Categorías</div></div>
                <div class="stat-db-card"><div class="stat-db-number">${data.valoraciones}</div><div class="stat-db-label">Valoraciones</div></div>
                <div class="stat-db-card"><div class="stat-db-number">${data.tablas}</div><div class="stat-db-label">Tablas totales</div></div>
            `;
        })
        .catch(() => {
            container.innerHTML = `
                <div class="stat-db-card"><div class="stat-db-number">⚠️</div><div class="stat-db-label">Estadísticas no disponibles</div></div>
            `;
        });
}

// Función para expandir/colapsar detalles de tablas
function toggleTableDetail(element) {
    element.classList.toggle('expanded');
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarEstadisticasBD();
});
</script>
</body>
</html>
