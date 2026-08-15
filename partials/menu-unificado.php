<?php
declare(strict_types=1);


/**
 * MENÚ UNIFICADO - Se adapta según el rol del usuario
 * Este archivo reemplaza a menu-admin.php, menu-periodista.php, menu-usuario.php y menu-publico.php
 */

// Asegurar que tenemos las funciones necesarias
if (!function_exists('usuarioEsPrivado')) {
    require_once __DIR__ . '/../includes/privado.php';
}

// Determinar el rol actual (o null si no está logueado)
$rol = $_SESSION['usuario_rol'] ?? null;
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$avatar = $_SESSION['usuario_avatar'] ?? 'default.png';

// Determinar si el periodista tiene acceso privado
$tiene_privado = usuarioEsPrivado();

// Determinar la ruta de perfil según el rol
$perfil_route = match($rol) {
    'admin'      => 'admin_perfil',
    'periodista' => 'periodista_perfil',
    'usuario'    => 'usuario_perfil',
    default      => 'perfil'
};

// Determinar si está logueado
$esta_logueado = isset($_SESSION['usuario_id']);
?>

<nav class="menu menu-<?php echo $rol ?? 'publico'; ?>">

    <ul>

        <!-- ======================================== -->
        <!-- INICIO (siempre visible) -->
        <!-- ======================================== -->
        <li class="<?php echo is_active('home'); ?>">

            <a href="<?php echo base_url(); ?>">🏠&nbsp;&nbsp; Inicio</a>

        </li>
        
        <!-- ======================================== -->
        <!-- OPCIONES PARA ADMIN -->
        <!-- ======================================== -->
        <?php if ($rol === 'admin'): ?>

        <li class="separador"></li>
        <li class="menu-seccion"><span>🛠️ Administración</span></li>

        <li class="<?php echo is_active('admin'); ?>">

            <a href="<?php echo route('admin'); ?>">📊&nbsp;&nbsp; Panel Admin</a>

        </li>

        <li class="separador"></li>
        <li class="menu-seccion"><span>📰 Contenido</span></li>
        <li class="<?php echo is_active('admin_noticias'); ?>">

            <a href="<?php echo route('admin_noticias'); ?>">📰&nbsp;&nbsp; Noticias</a>

        </li>
        <li class="<?php echo is_active('admin_noticias_privadas_buscar'); ?>">

            <a href="<?php echo route('admin_noticias_privadas_buscar'); ?>">🔒&nbsp;&nbsp; Noticias privadas</a>

        </li>
        <li class="<?php echo is_active('admin_categorias'); ?>">

            <a href="<?php echo route('admin_categorias'); ?>">📁&nbsp;&nbsp; Categorías</a>

        </li>
        <li class="<?php echo is_active('admin_comentarios'); ?>">

            <a href="<?php echo route('admin_comentarios'); ?>">💬&nbsp;&nbsp; Comentarios</a>

        </li>
        <li class="<?php echo is_active('admin_reportes'); ?>">

            <a href="<?php echo route('admin_reportes'); ?>">🚩&nbsp;&nbsp; Reportes</a>

        </li>
        <li class="<?php echo is_active('admin_noticias_relacionadas'); ?>">

            <a href="<?php echo route('admin_noticias_relacionadas'); ?>">🔗&nbsp;&nbsp; Noticias relacionadas</a>

        </li>

        <li class="separador"></li>
        <li class="menu-seccion"><span>👥 Perfiles y comunicación</span></li>
        <li class="<?php echo is_active('admin_usuarios_logueados'); ?>">

            <a href="<?php echo route('admin_usuarios_logueados'); ?>">👥&nbsp;&nbsp; Gestionar perfiles</a>

        </li>
        <li class="<?php echo is_active('admin_usuarios_logueados_tabla'); ?>">

            <a href="<?php echo route('admin_usuarios_logueados_tabla'); ?>">📋&nbsp;&nbsp; Listado de perfiles</a>

        </li>
        <li class="<?php echo is_active('admin_periodistas'); ?>">

            <a href="<?php echo route('admin_periodistas'); ?>">✍️&nbsp;&nbsp; Articulistas</a>

        </li>
        <li class="<?php echo is_active('admin_mensajes'); ?>">

            <a href="<?php echo route('admin_mensajes'); ?>">✉️&nbsp;&nbsp; Mensajes de Contacto</a>

        </li>
        <li class="<?php echo is_active('admin_usuarios_privados'); ?>">

            <a href="<?php echo route('admin_usuarios_privados'); ?>">🔒&nbsp;&nbsp; Colaboradores</a>

        </li>
        <li class="separador"></li>
        <li class="menu-seccion"><span>📡 Fuentes y RSS</span></li>
        <li class="<?php echo is_active('admin_rss'); ?>">

            <a href="<?php echo route('admin_rss'); ?>">📡&nbsp;&nbsp; RSS Fuentes</a>
        </li>
        <li class="<?php echo is_active('admin_fuentes'); ?>">

            <a href="<?php echo route('admin_fuentes'); ?>">📰&nbsp;&nbsp; Fuentes de Noticias</a>

        </li>

        <li class="separador"></li>
        <li class="menu-seccion"><span>⚙️ Sistema</span></li>
        <li class="<?php echo is_active('admin_config'); ?>">

            <a href="<?php echo route('admin_config'); ?>">⚙️&nbsp;&nbsp; Configuración</a>

        </li>
        <li class="<?php echo is_active('admin_backups'); ?>">

            <a href="<?php echo route('admin_backups'); ?>">💾&nbsp;&nbsp; Backups</a>

        </li>
        <li class="<?php echo is_active('admin_minify'); ?>">

            <a href="<?php echo route('admin_minify'); ?>">🗜️&nbsp;&nbsp; Minificación</a>

        </li>
        <li class="<?php echo is_active('ataques'); ?>">

            <a href="<?php echo route('ataques'); ?>">🛡️&nbsp;&nbsp; Ataques</a>

        </li>
        <li class="<?php echo is_active('actualizar'); ?>">

            <a href="<?php echo route('actualizar'); ?>">🔄&nbsp;&nbsp; Actualizar</a>

        </li>
        <li class="<?php echo is_active('admin_logs'); ?>">

            <a href="<?php echo route('admin_logs'); ?>">📋&nbsp;&nbsp; Logs del sistema</a>

        </li>
        <li class="<?php echo is_active('admin_logs_activity'); ?>">

            <a href="<?php echo route('admin_logs_activity'); ?>">🧾&nbsp;&nbsp; Registro de actividad</a>

        </li>
        <li class="<?php echo is_active('admin_rutas'); ?>">

            <a href="<?php echo route('admin_rutas'); ?>">🗺️&nbsp;&nbsp; Diagnóstico de Rutas</a>

        </li>
        <li class="<?php echo is_active('admin_diagnostico'); ?>">

            <a href="<?php echo route('admin_diagnostico'); ?>">🔍&nbsp;&nbsp; Diagnóstico del Sistema</a>

        </li>
        <li class="<?php echo is_active('admin_documentacion'); ?>">

            <a href="<?php echo route('admin_documentacion'); ?>">📚&nbsp;&nbsp; Documentación</a>

        </li>

        <?php endif; ?>

        
        <!-- ======================================== -->
        <!-- OPCIONES PARA PERIODISTA -->
        <!-- ======================================== -->
        <?php if ($rol === 'periodista'): ?>

        <li class="separador"></li>
        <li class="menu-seccion"><span>✍️ Área de <?php echo $tiene_privado ? 'Colaborador' : 'Articulista'; ?></span></li>
        <li class="<?php echo is_active('periodista_dashboard'); ?>">

            <a href="<?php echo route('periodista_dashboard'); ?>">📊&nbsp;&nbsp; Panel de <?php echo $tiene_privado ? 'Colaborador' : 'Articulista'; ?></a>

        </li>
        <li class="<?php echo is_active('mis_noticias'); ?>">

            <a href="<?php echo route('mis_noticias'); ?>">📰&nbsp;&nbsp; Mis Noticias</a>

        </li>
        <li class="<?php echo is_active('nueva_noticia'); ?>">

            <a href="<?php echo route('nueva_noticia'); ?>">➕&nbsp;&nbsp; Nueva Noticia</a>

        </li>
        <li class="<?php echo is_active('importar_rss'); ?>">

            <a href="<?php echo route('importar_rss'); ?>">📡&nbsp;&nbsp; Importar RSS</a>

        </li>
        <li class="<?php echo is_active('mis_comentarios'); ?>">

            <a href="<?php echo route('mis_comentarios'); ?>">💬&nbsp;&nbsp; Mis Comentarios</a>

        </li>
        <li class="<?php echo is_active('periodista_comentarios_recibidos'); ?>">

            <a href="<?php echo route('periodista_comentarios_recibidos'); ?>">💬&nbsp;&nbsp; Comentarios recibidos</a>

        </li>
        
        <!-- ======================================== -->
        <!-- PANEL PRIVADO PARA PERIODISTAS CON PERMISO -->
        <!-- ======================================== -->
        <?php if ($tiene_privado): ?>

        <li class="separador"></li>
        <li class="menu-seccion">
            <span>🔒 Área Privada</span>
        </li>
        <li class="<?php echo is_active('privado_dashboard'); ?>">

            <a href="<?php echo route('privado_dashboard'); ?>">🔒&nbsp;&nbsp; Panel privado</a>

        </li>
        <li class="<?php echo is_active('privado_nueva_noticia'); ?>">

            <a href="<?php echo route('privado_nueva_noticia'); ?>">➕&nbsp;&nbsp; Nueva Noticia Privada</a>

        </li>
        <li class="<?php echo is_active('privado_mis_noticias'); ?>">

            <a href="<?php echo route('privado_mis_noticias'); ?>">📰&nbsp;&nbsp; Mis Noticias Privadas</a>

        </li>
        <li class="<?php echo is_active('privado_buscar'); ?>">

            <a href="<?php echo route('privado_buscar'); ?>">🔍&nbsp;&nbsp; Buscar Privadas</a>

        </li>
        <li class="<?php echo is_active('privado_buscar_comentarios'); ?>">

            <a href="<?php echo route('privado_buscar_comentarios'); ?>">💬&nbsp;&nbsp; Buscar comentarios privados</a>

        </li>
        <li class="<?php echo is_active('privado_reportes'); ?>">

            <a href="<?php echo route('privado_reportes'); ?>">🚩&nbsp;&nbsp; Reportes privados confirmados</a>

        </li>
        <?php endif; ?>

        
        <?php endif; ?>

        
        <!-- ======================================== -->
        <!-- OPCIONES PARA USUARIO NORMAL -->
        <!-- ======================================== -->
        <?php if ($rol === 'usuario'): ?>

        <li class="separador"></li>
        <li class="menu-seccion"><span>💬 Área de Comentarista</span></li>

        <li class="<?php echo is_active('usuario_dashboard'); ?>">

            <a href="<?php echo route('usuario_dashboard'); ?>">💬&nbsp;&nbsp; Panel de Comentarista</a>

        </li>
        <li class="<?php echo is_active('mis_comentarios'); ?>">

            <a href="<?php echo route('mis_comentarios'); ?>">💬&nbsp;&nbsp; Mis Comentarios</a>

        </li>
        <?php endif; ?>

        
        <!-- ======================================== -->
        <!-- PERFIL (para todos los roles logueados) -->
        <!-- ======================================== -->
        <?php if ($esta_logueado): ?>

        <li class="separador"></li>
        <li class="menu-seccion"><span>⚙️ Mi cuenta</span></li>

        <li class="<?php echo is_active($perfil_route); ?>">

            <a href="<?php echo route($perfil_route); ?>">👤&nbsp;&nbsp; Mi Perfil</a>

        </li>
        <li class="<?php echo is_active('mis_favoritas'); ?>">

            <a href="<?php echo route('mis_favoritas'); ?>">❤️&nbsp;&nbsp; Mis Favoritas</a>

        </li>
        <?php endif; ?>

        
        <!-- ======================================== -->
        <!-- OPCIONES PÚBLICAS (para todos los roles) -->
        <!-- ======================================== -->

        <li class="separador"></li>
        <li class="menu-seccion"><span>🌐 Portal público</span></li>

        <li class="<?php echo is_active('tiempo'); ?>">

            <a href="<?php echo route('tiempo'); ?>">🌤️&nbsp;&nbsp;  El Tiempo</a>

        </li>

        <li class="<?php echo is_active('pobreza'); ?>">

            <a href="<?php echo route('pobreza'); ?>">📊&nbsp;&nbsp; Pobreza en España</a>

        </li>

        <li class="<?php echo is_active('nasa'); ?>">

            <a href="<?php echo route('nasa'); ?>">🚀&nbsp;&nbsp; NASA Multimedia</a>

        </li>

        <li class="<?php echo is_active('listado_noticias'); ?>">

            <a href="<?php echo route('listado_noticias'); ?>">📋&nbsp;&nbsp; Listado de Noticias</a>

        </li>
        <li class="<?php echo is_active('ultimas'); ?>">

            <a href="<?php echo route('ultimas'); ?>">📰&nbsp;&nbsp; Últimas Noticias</a>

        </li>
        <li class="<?php echo is_active('populares'); ?>">

            <a href="<?php echo route('populares'); ?>">🔥&nbsp;&nbsp; Noticias Populares</a>

        </li>
        <li class="<?php echo is_active('reportes_publicos'); ?>">

            <a href="<?php echo route('reportes_publicos'); ?>">🚩&nbsp;&nbsp; Reportes confirmados</a>

        </li>
        <li class="<?php echo is_active('categorias'); ?>">

            <a href="<?php echo route('categorias'); ?>">📂&nbsp;&nbsp; Noticias por Categorías</a>

        </li>
        <li class="<?php echo is_active('ubicacion'); ?>">

            <a href="<?php echo route('ubicacion'); ?>">📍&nbsp;&nbsp; Noticias por lugares</a>

        </li>
        <li class="<?php echo is_active('fuente'); ?>">

            <a href="<?php echo route('fuente'); ?>">📰&nbsp;&nbsp; Noticias por Fuentes</a>

        </li>
        <li class="<?php echo is_active('periodistas'); ?>">

            <a href="<?php echo route('periodistas'); ?>">✍️&nbsp;&nbsp; Articulistas</a>

        </li>
        <li class="<?php echo is_active('buscar'); ?>">

            <a href="<?php echo route('buscar-avanzado'); ?>">🔍&nbsp;&nbsp; Buscar Noticias</a>

        </li>
        <li class="<?php echo is_active('buscar-comentarios'); ?>">

            <a href="<?php echo route('buscar-comentarios'); ?>">🔍&nbsp;&nbsp; Buscar Comentarios</a>

        </li>
        <li class="<?php echo is_active('contacto'); ?>">

            <a href="<?php echo route('contacto'); ?>">📧&nbsp;&nbsp; Contactar</a>

        </li>
        <!-- ======================================== -->
        <!-- LOGIN / REGISTRO (si no está logueado) -->
        <!-- ======================================== -->
        <?php if (!$esta_logueado): ?>

        <li class="<?php echo is_active('login'); ?>">

            <a href="<?php echo route('login'); ?>">🔑&nbsp;&nbsp; Iniciar sesión</a>

        </li>
        <li class="<?php echo is_active('registro'); ?>">

            <a href="<?php echo route('registro'); ?>">📝&nbsp;&nbsp; Registrarse</a>

        </li>
        <?php endif; ?>

        
        <!-- ======================================== -->
        <!-- CERRAR SESIÓN (solo para logueados) -->
        <!-- ======================================== -->
        <?php if ($esta_logueado): ?>

        <li>
            <a href="<?php echo route('logout'); ?>">🚪&nbsp;&nbsp; Cerrar sesión</a>

        </li>
        <?php endif; ?>

        
    </ul>
</nav>
