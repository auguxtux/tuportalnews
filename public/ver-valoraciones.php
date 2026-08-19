<?php
declare(strict_types=1);


/**
 * VENTANA EMERGENTE DE VALORACIONES
 * Muestra estadísticas detalladas de valoraciones y datos de la noticia
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/valoraciones.php';
require_once __DIR__ . '/../includes/privado.php';

$id_noticia = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$valoracionPrivada = defined('VALORACION_PRIVADA') && VALORACION_PRIVADA === true;

if (!$id_noticia) {
    die('ID de noticia no válido');
}

if ($valoracionPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    die('Noticia no encontrada');
}

$pdo = db();

// Obtener datos completos de la noticia (con autor, categoría y comentarios)
$stmt = $pdo->prepare("
    SELECT n.*, 
           u.nombre as autor_nombre, 
           u.avatar as autor_avatar,
           c.nombre_categoria, 
           c.id_categoria,
           (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia AND estado = 'aprobado') as total_comentarios
    FROM noticias n
    JOIN usuarios u ON n.id_autor = u.id_usuario
    JOIN categorias c ON n.id_categoria = c.id_categoria
    WHERE n.id_noticia = ?
      AND n.estado = 'publicada'
      AND n.privada = ?
    LIMIT 1
");
$stmt->execute([$id_noticia, $valoracionPrivada ? 1 : 0]);
$noticia = $stmt->fetch();

if (!$noticia) {
    http_response_code(404);
    die('Noticia no encontrada');
}

$titulo_pagina = $noticia['titulo'];

$stats = Valoraciones::getEstadisticas($id_noticia);

// Obtener todas las valoraciones individuales
$stmt = $pdo->prepare("
    SELECT m.*, u.nombre as usuario_nombre 
    FROM megusta_noticias m
    LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
    WHERE m.id_noticia = ?
    ORDER BY m.fecha_megusta DESC
    LIMIT 50
");
$stmt->execute([$id_noticia]);
$valoraciones = $stmt->fetchAll();

// Obtener últimos comentarios (3 más recientes)
$stmt = $pdo->prepare("
    SELECT c.*, u.nombre as usuario_nombre, u.avatar as usuario_avatar
    FROM comentarios c
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE c.id_noticia = ? AND c.estado = 'aprobado'
    ORDER BY c.fecha_comentario DESC
    LIMIT 3
");
$stmt->execute([$id_noticia]);
$ultimos_comentarios = $stmt->fetchAll();

// DETERMINAR SI EL USUARIO PUEDE VOTAR
$puedeVotar = false;
$miValoracion = 0;
$mensaje_voto = '';

if (isset($_SESSION['usuario_id'])) {
    $resultado = Valoraciones::puedeVotarUsuario($id_noticia, $_SESSION['usuario_id']);
    $puedeVotar = $resultado['puede'];
    $mensaje_voto = $resultado['mensaje'];
    $voto = Valoraciones::getVotoActual($id_noticia);
    $miValoracion = $voto ?: 0;
} else {
    $identifier = Valoraciones::getVisitorIdentifier();
    $resultado = Valoraciones::puedeVotarVisitante($id_noticia, $identifier);
    $puedeVotar = $resultado['puede'];
    $mensaje_voto = $resultado['mensaje'];
    $voto = Valoraciones::getVotoActual($id_noticia);
    $miValoracion = $voto ?: 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Valoraciones - <?php echo htmlspecialchars($noticia['titulo']); ?></title>

    <link rel="stylesheet" href="<?php echo css_url('public-ver-valoraciones.css'); ?>">


</head>
<body>
    <div class="public-ver-valoraciones-container">
        <h1 class="public-ver-valoraciones-titulo">📊 Detalles de la noticia</h1>
        <a href="<?php echo route($valoracionPrivada ? 'privado_noticia' : 'noticia', ['id' => $id_noticia]); ?>" class="public-ver-valoraciones-btn btn-small">

                ⬅️ Volver a la noticia
            </a>
        <?php if ($noticia['autor_avatar']): ?>

                    <img src="<?php echo base_url('uploads/perfiles/' . $noticia['autor_avatar']); ?>" 

                         alt="<?php echo htmlspecialchars($noticia['autor_nombre']); ?>"

                         class="public-ver-valoraciones-autor-imagen">
                         
                <?php else: ?>

                    <img src="<?php echo base_url('assets/img/default-avatar.png'); ?>" 

                         alt="Avatar por defecto"
                         class="public-ver-valoraciones-autor-imagen">
                         
                <?php endif; ?>

        <div style="margin-bottom: 15px;">
            
        </div>
        <div class="public-ver-valoraciones-clean"></div>
        
        <div class="public-ver-valoraciones-noticia-completa">
            <div class="public-ver-valoraciones-noticia-header">
                
                
                <div class="public-ver-valoraciones-noticia-info">
                    <div class="public-ver-valoraciones-noticia-titulo">
                        <?php echo htmlspecialchars($noticia['titulo']); ?>

                    </div>
                    
                    <div class="public-ver-valoraciones-noticia-meta">
                        <div class="public-ver-valoraciones-meta-item">
                            <span class="public-ver-valoraciones-meta-label">✍️ Articulista:</span>
                            <span class="public-ver-valoraciones-meta-value">
                                <a href="<?php echo route('periodista', ['id' => $noticia['id_autor']]); ?>" class="public-ver-valoraciones-enlace-periodista">

                                    <?php echo htmlspecialchars($noticia['autor_nombre']); ?>

                                </a>
                            </span>
                        </div>
                        
                        <div class="public-ver-valoraciones-meta-item">
                            <span class="public-ver-valoraciones-meta-label">📂 Categoría:</span>
                            <span class="public-ver-valoraciones-meta-value">
                                <a href="<?php echo route('categoria', ['id' => $noticia['id_categoria']]); ?>" 

                                   class="public-ver-valoraciones-categoria-link">
                                    <?php echo htmlspecialchars($noticia['nombre_categoria']); ?>

                                </a>
                            </span>
                        </div>
                        
                        <?php if ($noticia['fuente']): ?>

                            <div class="public-ver-valoraciones-meta-item">
                                <span class="public-ver-valoraciones-meta-label">🔗 Fuente:</span>
                                <span class="public-ver-valoraciones-meta-value">
                                    <a href="<?php echo route('fuente', ['nombre' => $noticia['fuente']]); ?>" class="public-ver-valoraciones-enlace-fuente">

                                        <?php echo htmlspecialchars($noticia['fuente']); ?>

                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>

                        
                        <div class="public-ver-valoraciones-meta-item">
                            <span class="public-ver-valoraciones-meta-label">📅 Publicado:</span>
                            <span class="public-ver-valoraciones-meta-value"><?php echo formatearFecha($noticia['fecha_publicacion']); ?></span>

                        </div>
                        
                        <?php if ($noticia['fecha_actualizacion']): ?>

                            <div class="public-ver-valoraciones-meta-item">
                                <span class="public-ver-valoraciones-meta-label">✏️ Actualizado:</span>
                                <span class="public-ver-valoraciones-meta-value">
                                    <?php echo formatearFecha($noticia['fecha_actualizacion']); ?>

                                    <?php if ($puedeVotar && $miValoracion > 0): ?>

                                        <span class="public-ver-valoraciones-badge-actualizada">¡Puedes votar de nuevo!</span>
                                    <?php endif; ?>

                                </span>
                            </div>
                        <?php endif; ?>

                        
                        <div class="public-ver-valoraciones-meta-item">
                            <span class="public-ver-valoraciones-meta-label">👁️ Visitas:</span>
                            <span class="public-ver-valoraciones-meta-value public-ver-valoraciones-visitas-destacadas">
                                <?php echo number_format($noticia['visitas']); ?>

                            </span>
                        </div>
                        
                        <div class="public-ver-valoraciones-meta-item">
                            <span class="public-ver-valoraciones-meta-label">💬 Comentarios:</span>
                            <span class="public-ver-valoraciones-meta-value">
                                <strong><?php echo number_format($noticia['total_comentarios']); ?></strong>

                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
       
        <h2 class="public-ver-valoraciones-subtitulo">📊 Valoración de la noticia</h2>
        <div class="public-ver-valoraciones-explicacion">
            <strong>¿Cómo se calcula?</strong>
            Cada persona elige entre 1 y 3 estrellas. La media general reúne los votos
            de cuentas registradas y visitantes sin sesión. Cada persona mantiene un
            único voto por noticia y puede cambiarlo posteriormente.
        </div>
        <div class="public-ver-valoraciones-stats">
            <div class="public-ver-valoraciones-stat-item">
                <span class="public-ver-valoraciones-stat-label">📊 Total:</span>
                <span class="public-ver-valoraciones-stat-value">
                    <span class="public-ver-valoraciones-stat-badge <?php echo $stats['clasificacion']['clase']; ?>">

                        <?php echo $stats['clasificacion']['texto']; ?>

                    </span>
                    <span style="margin-left: 10px; color: #666;">
                        <?php echo (int) ($stats['totales']['total'] ?? 0); ?> votos · Media:

                        <strong><?php echo number_format((float) ($stats['totales']['media'] ?? 0), 2); ?> / 3</strong>

                    </span>
                </span>
            </div>
            
            <div class="public-ver-valoraciones-stat-item">
                <span class="public-ver-valoraciones-stat-label">👥 Cuentas registradas:</span>
                <span class="public-ver-valoraciones-stat-value">
                    <?php echo $stats['registrados']['total'] ?? 0; ?> votos · 

                    Media: <strong><?php echo number_format((float) ($stats['registrados']['media'] ?? 0), 2); ?></strong>

                </span>
            </div>
            
            <div class="public-ver-valoraciones-stat-item">
                <span class="public-ver-valoraciones-stat-label">🌐 Visitantes sin sesión:</span>
                <span class="public-ver-valoraciones-stat-value">
                    <?php echo $stats['visitantes']['total'] ?? 0; ?> votos · 

                    Media: <strong><?php echo number_format((float) ($stats['visitantes']['media'] ?? 0), 2); ?></strong>

                </span>
            </div>
        </div>

        <div class="public-ver-valoraciones-distribucion" aria-label="Distribución total de valoraciones">
            <div class="public-ver-valoraciones-distribucion-item">
                <span>⭐ No me gusta</span>
                <strong><?php echo (int) ($stats['totales']['votos_1'] ?? 0); ?></strong>
            </div>
            <div class="public-ver-valoraciones-distribucion-item">
                <span>⭐⭐ Regular</span>
                <strong><?php echo (int) ($stats['totales']['votos_2'] ?? 0); ?></strong>
            </div>
            <div class="public-ver-valoraciones-distribucion-item">
                <span>⭐⭐⭐ Buena noticia</span>
                <strong><?php echo (int) ($stats['totales']['votos_3'] ?? 0); ?></strong>
            </div>
        </div>
        
        <!-- ÚLTIMAS VALORACIONES -->
        <?php if (!empty($valoraciones)): ?>

            <h3 class="public-ver-valoraciones-lista-titulo">Últimas valoraciones</h3>
            <div class="public-ver-valoraciones-lista-valoraciones">
                <?php foreach ($valoraciones as $v): ?>

                    <div class="public-ver-valoraciones-valoracion-item">
                        <span class="public-ver-valoraciones-estrella-<?php echo $v['valoracion']; ?>">

                            <?php echo str_repeat('⭐', $v['valoracion']); ?>

                        </span>
                        <span class="public-ver-valoraciones-votante">
                            <?php if (($v['tipo_usuario'] ?? '') === 'registrado'): ?>
                                <?php echo htmlspecialchars((string) ($v['usuario_nombre'] ?? 'Cuenta eliminada')); ?>
                                <small>Cuenta registrada</small>
                            <?php else: ?>
                                Visitante
                                <small>Sin sesión</small>
                            <?php endif; ?>

                        </span>
                        <span style="float: right; color: #999; font-size: 0.75rem;">
                            <?php echo tiempoTranscurrido($v['fecha_megusta']); ?>

                        </span>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

        
        <!-- SECCIÓN DE VOTACIÓN -->
        <div class="public-ver-valoraciones-votacion-section">
            <h3>⭐ ¿Qué te parece esta noticia?</h3>
            
            <?php if ($miValoracion > 0): ?>

                <div class="public-ver-valoraciones-mensaje-voto public-ver-valoraciones-mensaje-voto-success">
                    Has votado: 
                    <span class="public-ver-valoraciones-estrella-<?php echo $miValoracion; ?>">

                        <?php echo str_repeat('⭐', $miValoracion); ?>

                    </span>
                </div>
            <?php endif; ?>

            
            <?php if ($mensaje_voto && !$puedeVotar): ?>

                <div class="public-ver-valoraciones-mensaje-voto public-ver-valoraciones-mensaje-voto-warning">
                    ⚠️ <?php echo htmlspecialchars($mensaje_voto, ENT_QUOTES, 'UTF-8'); ?>

                </div>
            <?php endif; ?>

            
            <?php if ($puedeVotar): ?>

                <div class="public-ver-valoraciones-estrellas-container">
                    <button class="public-ver-valoraciones-btn-estrella <?php echo $miValoracion == 1 ? 'activo' : ''; ?>" 

                            data-noticia="<?php echo $id_noticia; ?>" 

                            data-valor="1" title="⭐ No me gusta">★</button>
                    <button class="public-ver-valoraciones-btn-estrella <?php echo $miValoracion == 2 ? 'activo' : ''; ?>" 

                            data-noticia="<?php echo $id_noticia; ?>" 

                            data-valor="2" title="⭐⭐ No está mal">★★</button>
                    <button class="public-ver-valoraciones-btn-estrella <?php echo $miValoracion == 3 ? 'activo' : ''; ?>" 

                            data-noticia="<?php echo $id_noticia; ?>" 

                            data-valor="3" title="⭐⭐⭐ Buena noticia">★★★</button>
                </div>
                <p style="text-align: center; color: #666; font-size: 0.9rem; margin-top: 5px;">
                    Tu valoración sustituye a la anterior si vuelves a votar.
                </p>
            <?php elseif (!isset($_SESSION['usuario_id'])): ?>

                <div style="text-align: center;">
                    <p>Los visitantes también pueden votar:</p>
                    <div class="public-ver-valoraciones-estrellas-container">
                        <button class="public-ver-valoraciones-btn-estrella" data-noticia="<?php echo $id_noticia; ?>" data-valor="1" title="⭐ No me gusta">★</button>

                        <button class="public-ver-valoraciones-btn-estrella" data-noticia="<?php echo $id_noticia; ?>" data-valor="2" title="⭐⭐ No está mal">★★</button>

                        <button class="public-ver-valoraciones-btn-estrella" data-noticia="<?php echo $id_noticia; ?>" data-valor="3" title="⭐⭐⭐ Buena noticia">★★★</button>

                    </div>
                    <p style="font-size: 0.85rem; color: #666;">
                        Tu voto se asocia a un identificador aleatorio de sesión, no a tu IP.
                        Si vuelves a votar, se actualizará tu valoración anterior.
                    </p>
                </div>
            <?php endif; ?>

        </div>
        
        <!-- ÚLTIMOS COMENTARIOS -->
        <?php if (!empty($ultimos_comentarios)): ?>

            <div class="public-ver-valoraciones-comentarios-mini">
                <div class="public-ver-valoraciones-comentarios-header">
                    <span class="public-ver-valoraciones-comentarios-total">
                        💬 Últimos comentarios (<?php echo $noticia['total_comentarios']; ?>)

                    </span>
                    <a href="<?php echo route($valoracionPrivada ? 'privado_comentarios' : 'noticia', ['id' => $id_noticia]); ?>#comentarios" class="public-ver-valoraciones-comentarios-link">

                        Ver todos →
                    </a>
                </div>
                
                <div class="public-ver-valoraciones-lista-comentarios-mini">
                    <?php foreach ($ultimos_comentarios as $com): ?>

                        <div class="public-ver-valoraciones-comentario-mini-item">
                            <img src="<?php echo base_url('uploads/perfiles/' . ($com['usuario_avatar'] ?? 'default.jpg')); ?>" 

                                 alt="<?php echo htmlspecialchars($com['usuario_nombre']); ?>"

                                 class="public-ver-valoraciones-comentario-mini-avatar">
                            <div class="public-ver-valoraciones-comentario-mini-contenido">
                                <span class="public-ver-valoraciones-comentario-mini-autor"><?php echo htmlspecialchars($com['usuario_nombre']); ?>:</span>

                                <span class="public-ver-valoraciones-comentario-mini-texto"><?php echo htmlspecialchars(truncarTexto($com['contenido'], 60)); ?></span>

                                <div class="public-ver-valoraciones-comentario-mini-fecha"><?php echo tiempoTranscurrido($com['fecha_comentario']); ?></div>

                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        <?php elseif ($noticia['total_comentarios'] > 0): ?>

            <div class="public-ver-valoraciones-comentarios-mini" style="border-left-color: #999;">
                <div class="public-ver-valoraciones-comentarios-header">
                    <span class="public-ver-valoraciones-comentarios-total">💬 <?php echo $noticia['total_comentarios']; ?> comentarios</span>

                    <a href="<?php echo route($valoracionPrivada ? 'privado_comentarios' : 'noticia', ['id' => $id_noticia]); ?>#comentarios" class="public-ver-valoraciones-comentarios-link">

                        Ver comentarios →
                    </a>
                </div>
            </div>
        <?php else: ?>

            <div class="public-ver-valoraciones-comentarios-mini public-ver-valoraciones-comentarios-vacio">
                <div class="public-ver-valoraciones-comentarios-header">
                    <span class="public-ver-valoraciones-comentarios-total">💬 Comentarios</span>
                    <span class="public-ver-valoraciones-comentarios-contador">0</span>
                </div>
                <p class="public-ver-valoraciones-comentarios-vacio-texto">
                    📭 Todavía no hay comentarios en esta noticia.
                </p>
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <a href="<?php echo route($valoracionPrivada ? 'privado_comentarios' : 'noticia', ['id' => $id_noticia]); ?>#comentarios"
                       class="public-ver-valoraciones-comentarios-link">
                        Escribir el primer comentario →
                    </a>
                <?php elseif (!$valoracionPrivada): ?>
                    <p class="public-ver-valoraciones-comentarios-acceso">
                        🔑 <a href="<?php echo route('login'); ?>">Inicia sesión</a>
                        o <a href="<?php echo route('registro'); ?>">regístrate</a> para comentar.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        
        <button class="public-ver-valoraciones-btn btn-small" onclick="window.location.href='<?php echo htmlspecialchars(route($valoracionPrivada ? 'privado_noticia' : 'noticia', ['id' => $id_noticia]), ENT_QUOTES, 'UTF-8'); ?>'" style="border: none;background: none;font-size: 1.1rem;width: 100%;">⬅️ Volver a la noticia</button>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const botones = document.querySelectorAll('.public-ver-valoraciones-btn-estrella');
        
        botones.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const noticiaId = this.dataset.noticia;
                const valoracion = this.dataset.valor;
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.content : '';
                
                this.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
                
                botones.forEach(b => b.disabled = true);
                
                fetch(<?php echo json_encode(
                    $valoracionPrivada
                        ? route('privado_valorar')
                        : base_url('ajax/valorar.php'),
                    JSON_UNESCAPED_SLASHES
                ); ?>, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id_noticia=' + encodeURIComponent(noticiaId)
                        + '&valoracion=' + encodeURIComponent(valoracion)
                        + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Error al procesar el voto');
                        botones.forEach(b => b.disabled = false);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar el voto');
                    botones.forEach(b => b.disabled = false);
                });
            });
        });
    });
    </script>
</body>
</html>
