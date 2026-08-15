<?php
declare(strict_types=1);


/**
 * SISTEMA DE RUTAS UNIFICADO
 * Centraliza todas las rutas de la aplicación
 */

// ============================================
// DEFINICIÓN DE RUTAS
// ============================================

// Mapeo de nombres de ruta → archivo físico (sin extensión .php)
$routes = [
    // ========================================
    // PÁGINAS PÚBLICAS
    // ========================================
    'home'               => 'public/portada',
    'noticia'            => 'public/noticia',
    'categoria'          => 'public/categoria',
    'categorias'         => 'public/categoria',
    'periodistas'        => 'public/periodistas',
    'periodista'         => 'public/periodistas',
    'buscar_avanzado'    => 'public/buscar-avanzado',
    'buscar'             => 'buscar',
    'ultimas'            => 'public/ultimas',
    'populares'          => 'public/populares',
    'login'              => 'public/login',
    'registro'           => 'public/registro',
    'contacto'           => 'public/contacto',
    'terminos'           => 'public/terminos',
    'privacidad'         => 'public/privacidad',
    'cookies'            => 'public/cookies',
    'valoraciones'       => 'public/ver-valoraciones',
    'ver-relacionadas'   => 'public/ver-relacionadas',
    'ubicacion'          => 'public/ubicacion',
    'fuente'             => 'public/fuente',
    'recuperar_password' => 'public/recuperar_password',
    'resetear_password'  => 'public/resetear_password',
    'galeria'            => 'public/galeria',
    'portada'            => 'public/portada',
    'listado_noticias'   => 'public/listado-noticias',
    'rss_feed'           => 'public/rss-feed',
    'tiempo'             => 'public/tiempo',
    'pobreza'            => 'public/pobreza',
    'nasa'               => 'public/nasa',
    'reportes_publicos'  => 'public/reportes',
    'sitemap'            => 'sitemap.xml',

    // ========================================
    // ADMINISTRACIÓN
    // ========================================
    'actualizar'                     => 'admin/actualizar',
    'admin_editar_noticia'           => 'admin/editar-noticia',
    'ataques'                        => 'admin/ataques',
    'admin'                          => 'admin/dashboard',
    'admin_periodistas'              => 'admin/periodistas',
    'admin_noticias'                 => 'admin/noticias',
    'admin_categorias'               => 'admin/categorias',
    'admin_comentarios'              => 'admin/comentarios',
    'admin_config'                   => 'admin/configuracion',
    'admin_mensajes'                 => 'admin/mensajes',
    'admin_perfil'                   => 'admin/perfil',
    'admin_nueva_categoria'          => 'admin/nueva-categoria',
    'admin_editar_categoria'         => 'admin/editar-categoria',
    'admin_editar_periodista'        => 'admin/editar-periodista',
    'admin_nuevo_periodista'         => 'admin/nuevo-periodista',
    'admin_usuarios_privados'        => 'admin/usuarios-privados',
    'admin_noticias_privadas_buscar' => 'admin/noticias-privadas',
    'admin_noticias_relacionadas'    => 'admin/noticias-relacionadas',
    'admin_rutas'                    => 'admin/rutas',
    'admin_usuarios_logueados'       => 'admin/usuarios-logueados',
    'admin_usuarios_logueados_tabla' => 'admin/usuarios-logueados-tabla',
    'admin_reportes'                 => 'admin/reportes',
    'admin_fuentes'                  => 'admin/gestion-fuentes',
    'admin_backups'                  => 'admin/backups',
    'admin_backup_ejecutar'          => 'admin/backup-ejecutar',
    'admin_backup_descargar'         => 'admin/backup-descargar',
    'admin_backup_restaurar'         => 'admin/backup-restaurar',
    'admin_documentacion'            => 'admin/documentacion',
    'admin_diagnostico'              => 'admin/diagnostico',
    'admin_minify'                   => 'admin/minify',
    'admin_desactivar_mantenimiento' => 'admin/desactivar-mantenimiento',
    'admin_logs_activity'            => 'admin/logs-activity',
    'comentarios_noticia'            => 'public/comentarios',
    'admin_logs'                     => 'admin/logs',
    'admin_rss'                      => 'admin/rss-config',
    // ========================================
    // PERIODISTA (estándar)
    // ========================================
    'periodista_dashboard' => 'periodista/dashboard',
    'periodista_comentarios_recibidos' => 'periodista/comentarios-noticias',
    'mis_noticias'         => 'periodista/mis-noticias',
    'nueva_noticia'        => 'periodista/nueva-noticia',
    'editar_noticia'       => 'periodista/editar-noticia',
    'eliminar_noticia'     => 'periodista/eliminar-noticia',
    'periodista_perfil'    => 'periodista/perfil',
    'periodista_eliminar_cuenta' => 'periodista/eliminar-cuenta',
    'importar_rss'         => 'periodista/importar-rss',
    // PERIODISTA (Noticias Privadas)
    'privado_dashboard'              => 'privado/dashboard',
    'privado_mis_noticias'           => 'privado/mis-noticias-privadas',
    'privado_buscar'                 => 'privado/buscar-privadas',
    'privado_buscar_comentarios'     => 'privado/buscar-comentarios',
    'privado_noticia'                => 'privado/noticia',
    'privado_comentarios'            => 'privado/comentarios',
    'privado_galeria'                => 'privado/galeria',
    'privado_relacionadas'           => 'privado/relacionadas',
    'privado_procesar_comentario'    => 'privado/procesar-comentario',
    'privado_reportar_noticia'       => 'privado/reportar-noticia',
    'privado_reportar_comentario'    => 'privado/reportar-comentario',
    'privado_procesar_reporte_noticia' => 'privado/procesar-reporte-noticia',
    'privado_procesar_reporte_comentario' => 'privado/procesar-reporte-comentario',
    'privado_valoraciones'            => 'privado/valoraciones',
    'privado_valorar'                 => 'privado/valorar',
    'privado_nueva_noticia'           => 'privado/nueva-noticia',
    'privado_editar_noticia'          => 'privado/editar-noticia',
    'privado_reportes'                => 'privado/reportes',

    // ========================================
    // USUARIO REGISTRADO
    // ========================================
    'usuario_dashboard'    => 'usuario/dashboard',
    'mis_favoritas'        => 'usuario/mis-favoritas',
    'mis_comentarios'      => 'usuario/mis-comentarios',
    'editar_comentario'    => 'usuario/editar-comentario',
    'eliminar_comentario'  => 'usuario/eliminar-comentario',
    'usuario_perfil'       => 'usuario/perfil',
    'usuario_eliminar_cuenta' => 'usuario/eliminar-cuenta',
    'reportar_comentario'  => 'usuario/reportar-comentario',
    'reportar_noticia'     => 'usuario/reportar-noticia',
    'procesar_reporte_comentario' => 'usuario/procesar-reporte-comentario',
    'procesar_reporte_noticia'    => 'usuario/procesar-reporte-noticia',
    'ajax_estadisticas_bd'        => 'ajax/estadisticas-bd',
    'ajax_nasa_asset'             => 'ajax/nasa-asset',
    'ajax_nasa_traducir'          => 'ajax/nasa-traducir',
    'ajax_nasa_ver'               => 'ajax/nasa-ver',
    'procesar_comentario'  => 'public/procesar_comentario',

    // ========================================
    // ACCIONES
    // ========================================
    'procesar_relacion'    => 'admin/procesar-relacion',
    'buscar-avanzado'      => 'public/buscar-avanzado',
    'buscar-comentarios'   => 'public/buscar-comentarios',
    'logout'               => 'logout',
    'cuenta_eliminada'     => 'cuenta-eliminada',
    'procesar_contacto'    => 'public/enviar_contacto',
];

// ============================================
// RUTAS PARA EL FRONT CONTROLLER (mapeo URL → archivo)
// ============================================
$rutas_front = [
    // Páginas públicas
    ''                       => 'public/portada.php',
    'login'                  => 'public/login.php',
    'registro'               => 'public/registro.php',
    'contacto'               => 'public/contacto.php',
    'categoria'              => 'public/categoria.php',
    'noticia'                => 'public/noticia.php',
    'periodistas'            => 'public/periodistas.php',
    'ultimas'                => 'public/ultimas.php',
    'populares'              => 'public/populares.php',
    'buscar'                 => 'public/buscar-avanzado-resultados.php',
    'buscar-avanzado'        => 'public/buscar-avanzado.php',
    'buscar-comentarios'     => 'public/buscar-comentarios.php',
    'terminos'               => 'public/terminos.php',
    'privacidad'             => 'public/privacidad.php',
    'cookies'                => 'public/cookies.php',
    'public/fuente'          => 'public/fuente.php',
    'fuente'                 => 'public/fuente.php',
    'ubicacion'              => 'public/ubicacion.php',
    'ver-relacionadas'       => 'public/ver-relacionadas.php',
    'ver-valoraciones'       => 'public/ver-valoraciones.php',
    'procesar_comentario'    => 'public/procesar_comentario.php',
    'enviar_contacto'        => 'public/enviar_contacto.php',
    'recuperar_password'     => 'public/recuperar_password.php',
    'resetear_password'      => 'public/resetear_password.php',
    'public/galeria'         => 'public/galeria.php',
    'public_portada'         => 'public/portada.php',
    'tiempo'                 => 'public/tiempo.php',
    'pobreza'                => 'public/pobreza.php',
    'nasa'                   => 'public/nasa.php',
    'reportes'               => 'public/reportes.php',
    'sitemap.xml'            => 'public/sitemap.php',
    
    // Admin
    'admin'                          => 'admin/dashboard.php',
    'admin/'                         => 'admin/dashboard.php',
    'admin/dashboard'                => 'admin/dashboard.php',
    'admin/noticias'                 => 'admin/noticias.php',
    'admin/noticias-privadas'        => 'admin/noticias-privadas.php',
    'admin/categorias'               => 'admin/categorias.php',
    'admin/periodistas'              => 'admin/periodistas.php',
    'admin/usuarios-privados'        => 'admin/usuarios-privados.php',
    'admin/comentarios'              => 'admin/comentarios.php',
    'admin/mensajes'                 => 'admin/mensajes.php',
    'admin/configuracion'            => 'admin/configuracion.php',
    'admin/perfil'                   => 'admin/perfil.php',
    'admin/actualizar'               => 'admin/actualizar.php',
    'admin/ataques'                  => 'admin/ataques.php',
    'admin/minify'                   => 'admin/minify.php',
    'admin/desactivar-mantenimiento' => 'admin/desactivar-mantenimiento.php',
    'admin/nueva-categoria'          => 'admin/nueva-categoria.php',
    'admin/editar-noticia'           => 'admin/editar-noticia.php',
    'admin/editar-categoria'         => 'admin/editar-categoria.php',
    'admin/editar-periodista'        => 'admin/editar-periodista.php',
    'admin/nuevo-periodista'         => 'admin/nuevo-periodista.php',
    'admin/procesar-relacion'        => 'admin/procesar_relacion.php',
    'admin/noticias-relacionadas'    => 'admin/noticias-relacionadas.php',
    'admin/rutas'                    => 'admin/rutas.php',
    'admin/usuarios-logueados'       => 'admin/usuarios-logueados.php',
    'admin/usuarios-logueados-tabla' => 'admin/usuarios-logueados-tabla.php',
    'admin/reportes_comentarios'     => 'admin/reportes_comentarios.php',
    'admin/reportes'                 => 'admin/reportes_comentarios.php',
    'admin/gestion-fuentes'          => 'admin/gestion-fuentes.php',
    'admin/backups'                  => 'admin/backups.php',
    'admin/documentacion'            => 'admin/documentacion.php',
    'admin/backup-ejecutar'          => 'admin/backup-ejecutar.php',
    'admin/backup-descargar'         => 'admin/backup-descargar.php',
    'admin/backup-restaurar'         => 'admin/backup-restaurar.php',
    'public/comentarios'             => 'public/comentarios.php',
    'admin/logs'                     => 'admin/logs.php',
    'admin/diagnostico'              => 'admin/diagnostico.php',
    'admin/logs-activity'            => 'admin/logs-activity.php',
    'admin/rss-config'               => 'admin/rss-config.php',
    // Periodista
    'periodista'                     => 'periodista/dashboard.php',
    'periodista/'                    => 'periodista/dashboard.php',
    'periodista/dashboard'           => 'periodista/dashboard.php',
    'periodista/comentarios-noticias' => 'periodista/comentarios-noticias.php',
    'periodista/mis-noticias'        => 'periodista/mis-noticias.php',
    'periodista/mis-comentarios'     => 'periodista/mis-comentarios.php',
    'periodista/nueva-noticia'       => 'periodista/nueva-noticia.php',
    'periodista/editar-noticia'      => 'periodista/editar-noticia.php',
    'periodista/eliminar-noticia'    => 'periodista/eliminar-noticia.php',
    'periodista/perfil'              => 'periodista/perfil.php',
    'periodista/importar-rss'        => 'periodista/importar-rss.php',
    'periodista/eliminar-cuenta'     => 'periodista/eliminar-cuenta.php',
    
    // Privado
    'privado'                        => 'privado/dashboard.php',
    'privado/'                       => 'privado/dashboard.php',
    'privado/dashboard'              => 'privado/dashboard.php',
    'privado/mis-noticias-privadas'  => 'privado/mis-noticias-privadas.php',
    'privado/buscar-privadas'        => 'privado/buscar-privadas.php',
    'privado/buscar-comentarios'     => 'privado/buscar-comentarios.php',
    'privado/noticia'                => 'privado/noticia.php',
    'privado/comentarios'            => 'privado/comentarios.php',
    'privado/galeria'                => 'privado/galeria.php',
    'privado/relacionadas'           => 'privado/relacionadas.php',
    'privado/procesar-comentario'    => 'privado/procesar-comentario.php',
    'privado/reportar-noticia'       => 'privado/reportar-noticia.php',
    'privado/reportar-comentario'    => 'privado/reportar-comentario.php',
    'privado/procesar-reporte-noticia' => 'privado/procesar-reporte-noticia.php',
    'privado/procesar-reporte-comentario' => 'privado/procesar-reporte-comentario.php',
    'privado/valoraciones'            => 'privado/valoraciones.php',
    'privado/valorar'                 => 'privado/valorar.php',
    'privado/nueva-noticia'           => 'privado/nueva-noticia.php',
    'privado/editar-noticia'          => 'privado/editar-noticia.php',
    'privado/reportes'                => 'privado/reportes.php',
    
    // Usuario
    'usuario'                        => 'usuario/dashboard.php',
    'usuario/'                       => 'usuario/dashboard.php',
    'usuario/dashboard'              => 'usuario/dashboard.php',
    'usuario/mis-favoritas'          => 'usuario/mis-favoritas.php',
    'usuario/mis-comentarios'        => 'usuario/mis-comentarios.php',
    'usuario/editar-comentario'      => 'usuario/editar-comentario.php',
    'usuario/eliminar-comentario'    => 'usuario/eliminar-comentario.php',
    'usuario/perfil'                 => 'usuario/perfil.php',
    'usuario/eliminar-cuenta'        => 'usuario/eliminar-cuenta.php',
    'reportar-comentario'            => 'usuario/reportar-comentario.php',
    'usuario/reportar-comentario'    => 'usuario/reportar-comentario.php',
    'usuario/reportar-noticia'       => 'usuario/reportar-noticia.php',
    'usuario/procesar-reporte-comentario' => 'usuario/procesar_reporte.php',
    'usuario/procesar-reporte-noticia' => 'usuario/procesar_reporte_noticia.php',
    'ajax/estadisticas-bd'            => 'ajax/estadisticas-bd.php',
    'ajax/nasa-asset'                 => 'ajax/nasa-asset.php',
    'ajax/nasa-traducir'              => 'ajax/nasa-traducir.php',
    'ajax/nasa-ver'                   => 'ajax/nasa-ver.php',
    'usuario/procesar_reporte_noticia' => 'usuario/procesar_reporte_noticia.php',
    'usuario/procesar_comentario'    => 'usuario/procesar_comentario.php',
    
    // Acciones
    'logout'                         => 'logout.php',
    'cuenta-eliminada'               => 'cuenta-eliminada.php',
];

// Rutas con prefijo /public/ (compatibilidad)
$rutas_public = [

    'public/login'                => 'public/login.php',
    'public/registro'             => 'public/registro.php',
    'public/contacto'             => 'public/contacto.php',
    'public/periodistas'          => 'public/periodistas.php',
    'public/ultimas'              => 'public/ultimas.php',
    'public/populares'            => 'public/populares.php',
    'public/buscar-avanzado'      => 'public/buscar-avanzado.php',
    'public/buscar-comentarios'   => 'public/buscar-comentarios.php',
    'public/terminos'             => 'public/terminos.php',
    'public/privacidad'           => 'public/privacidad.php',
    'public/cookies'              => 'public/cookies.php',
    'public/fuente'               => 'public/fuente.php',
    'public/ubicacion'            => 'public/ubicacion.php',
    'public/ver-relacionadas'     => 'public/ver-relacionadas.php',
    'public/ver-valoraciones'     => 'public/ver-valoraciones.php',
    'public/procesar_comentario'  => 'public/procesar_comentario.php',
    'public/enviar_contacto'      => 'public/enviar_contacto.php',
    'public/noticia'              => 'public/noticia.php',
    'public/categoria'            => 'public/categoria.php',
    'public/periodista'           => 'public/periodistas.php',
    'public/recuperar_password'   => 'public/recuperar_password.php',
    'public/resetear_password'    => 'public/resetear_password.php',
    'public/portada'              => 'public/portada.php',
    'public/listado-noticias'     => 'public/listado-noticias.php',
    'public/rss-feed'             => 'public/rss-feed.php',
    'public/tiempo'               => 'public/tiempo.php',
    'public/pobreza'              => 'public/pobreza.php',
    'public/nasa'                 => 'public/nasa.php',
    'public/reportes'             => 'public/reportes.php',
];

$rutas_front = array_merge($rutas_front, $rutas_public);

// ============================================
// FUNCIONES DE RUTAS
// ============================================

/**
 * Genera una URL a partir del nombre de la ruta
 * @param string $name Nombre de la ruta
 * @param array $params Parámetros GET
 * @return string URL completa
 */
function route($name, $params = []) {
    global $routes;
    static $noticiaUrlCache = [];
    
    // ============================================
    // RUTA ESPECIAL: Noticias (URL amigable con categoría)
    // ============================================
    if ($name === 'noticia' && isset($params['id'])) {
        $id = (int) $params['id'];

        if (isset($noticiaUrlCache[$id])) {
            return $noticiaUrlCache[$id];
        }

        $slug_noticia = '';
        $slug_categoria = '';
        
        try {
            $pdo = db();
            // Obtenemos el slug de la noticia y el de su categoría en una sola consulta
            $stmt = $pdo->prepare("
                SELECT n.slug as slug_noticia, c.slug_categoria as slug_categoria
                FROM noticias n
                JOIN categorias c ON n.id_categoria = c.id_categoria
                WHERE n.id_noticia = ?
            ");
            $stmt->execute([$id]);
            $resultado = $stmt->fetch();
            
            if ($resultado) {
                $slug_categoria = $resultado['slug_categoria'];
                $slug_noticia = $resultado['slug_noticia'];
                // Creamos la URL amigable con categoría
                $noticiaUrlCache[$id] = SITE_URL . '/noticia/' . $slug_categoria . '/' . $id . '/' . $slug_noticia;
                return $noticiaUrlCache[$id];
            }
        } catch (Exception $e) {
            // Si hay un error, no hacemos nada y usamos el fallback
        }
        
        // Fallback a la URL amigable anterior (sin categoría) por si algo falla
        if (!empty($slug_noticia)) {
            $noticiaUrlCache[$id] = SITE_URL . '/noticia/' . $id . '/' . $slug_noticia;
            return $noticiaUrlCache[$id];
        }
        
        // Fallback definitivo a la URL antigua
        $noticiaUrlCache[$id] = SITE_URL . '/public/noticia?id=' . $id;
        return $noticiaUrlCache[$id];
    }
    
    // ============================================
    // RUTAS NORMALES (no tocar)
    // ============================================
    if (!isset($routes[$name])) {
        return SITE_URL;
    }
    $url = SITE_URL . '/' . $routes[$name];
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

/**
 * Redirige a una ruta por nombre
 * @param string $name Nombre de la ruta
 * @param array $params Parámetros GET
 */
function redirect_to($name, $params = []) {
    header('Location: ' . route($name, $params));
    exit;
}

/**
 * Obtiene la URL actual
 * @return string URL actual
 */

/**
 * Verifica si la ruta actual está activa (para el menú)
 * @param string $name Nombre de la ruta
 * @param string $class Clase CSS a devolver si está activa
 * @return string Clase CSS o vacío
 */
function is_active($name, $class = 'active') {
    $current = str_replace(SITE_URL, '', current_url());
    $current = strtok($current, '?');
    global $routes;
    $path = $routes[$name] ?? '';
    return strpos($current, $path) !== false ? $class : '';
}

/**
 * Obtiene el archivo correspondiente a una URL (para el front controller)
 * @param string $request Ruta solicitada
 * @return string|null Archivo a cargar o null
 */
function get_route_file($request) {
    global $rutas_front;
    
    if (isset($rutas_front[$request])) {
        return $rutas_front[$request];
    }
    
    return null;
}

/**
 * Procesa rutas con parámetros (ej: /noticia/123)
 * @param string $request Ruta solicitada
 * @param array $partes Partes de la ruta
 * @return string|null Archivo a cargar o null
 */
function process_dynamic_route($request, $partes) {
    $count = count($partes);
    // /noticia/slug-categoria/ID/slug-noticia (nuevo formato)
if ($count == 4 && $partes[0] == 'noticia' && is_numeric($partes[2])) {
    $_GET['id'] = $partes[2];
    return 'public/noticia.php';
}

// /noticia/ID/slug-noticia (formato anterior, por compatibilidad)
if (($count == 2 || $count == 3) && $partes[0] == 'noticia' && is_numeric($partes[1])) {
    $_GET['id'] = $partes[1];
    return 'public/noticia.php';
}
    
    // /categoria/123
    if ($count == 2 && $partes[0] == 'categoria' && is_numeric($partes[1])) {
        $_GET['id'] = $partes[1];
        return 'public/categoria.php';
    }
    
    // /periodista/123
    if ($count == 2 && $partes[0] == 'periodista' && is_numeric($partes[1])) {
        $_GET['id'] = $partes[1];
        return 'public/periodistas.php';
    }
    
    // /admin/editar-noticia/123
    if ($count == 3 && $partes[0] == 'admin' && $partes[1] == 'editar-noticia' && is_numeric($partes[2])) {
        $_GET['id'] = $partes[2];
        return 'admin/editar-noticia.php';
    }
    
    // /admin/editar-categoria/123
    if ($count == 3 && $partes[0] == 'admin' && $partes[1] == 'editar-categoria' && is_numeric($partes[2])) {
        $_GET['id'] = $partes[2];
        return 'admin/editar-categoria.php';
    }
    
    // /admin/editar-periodista/123
    if ($count == 3 && $partes[0] == 'admin' && $partes[1] == 'editar-periodista' && is_numeric($partes[2])) {
        $_GET['id'] = $partes[2];
        return 'admin/editar-periodista.php';
    }
    
    // /periodista/editar-noticia/123
    if ($count == 3 && $partes[0] == 'periodista' && $partes[1] == 'editar-noticia' && is_numeric($partes[2])) {
        $_GET['id'] = $partes[2];
        return 'periodista/editar-noticia.php';
    }
    
    // /privado/mis-noticias/123
    if ($count == 3 && $partes[0] == 'privado' && $partes[1] == 'mis-noticias' && is_numeric($partes[2])) {
        $_GET['id'] = $partes[2];
        return 'privado/mis-noticias-privadas.php';
    }
    
    // /recuperar_password
    if ($count === 1 && $partes[0] === 'recuperar_password') {
        return 'public/recuperar_password.php';
    }
    
    // /resetear_password
    if ($count === 1 && $partes[0] === 'resetear_password') {
        return 'public/resetear_password.php';
    }
    
    return null;
}
