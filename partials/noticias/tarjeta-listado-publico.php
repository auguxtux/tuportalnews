<?php
declare(strict_types=1);

/**
 * Tarjeta compartida por los listados públicos de fuente y ubicación.
 *
 * Variables esperadas:
 * - $noticiaTarjeta: datos de la noticia.
 * - $varianteTarjeta: `source` o `location`.
 */

$noticiaTarjeta = is_array($noticiaTarjeta ?? null)
    ? $noticiaTarjeta
    : [];
$varianteTarjeta = in_array(
    $varianteTarjeta ?? '',
    ['source', 'location'],
    true
) ? $varianteTarjeta : 'source';

$idNoticiaTarjeta = (int) ($noticiaTarjeta['id_noticia'] ?? 0);
$urlNoticiaTarjeta = route('noticia', ['id' => $idNoticiaTarjeta]);
$avatarTarjeta = !empty($noticiaTarjeta['autor_avatar'])
    ? basename((string) $noticiaTarjeta['autor_avatar'])
    : '';
$claseExternaTarjeta = !empty($noticiaTarjeta['id_fuente_rss'])
    ? ' news-card--external'
    : '';
?>
<article class="tarjeta-noticia news-card news-card--vertical news-card--<?php echo $varianteTarjeta; ?> news-card--public<?php echo $claseExternaTarjeta; ?>">
    <h2 class="tarjeta-titulo news-card__title">
        <a href="<?php echo htmlspecialchars($urlNoticiaTarjeta, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) ($noticiaTarjeta['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </h2>

    <?php if (!empty($noticiaTarjeta['subtitulo'])): ?>
        <h3 class="tarjeta-subtitulo news-card__subtitle">
            <?php echo htmlspecialchars((string) $noticiaTarjeta['subtitulo'], ENT_QUOTES, 'UTF-8'); ?>
        </h3>
    <?php endif; ?>

    <div class="tarjeta-imagen news-card__media">
        <a href="<?php echo htmlspecialchars($urlNoticiaTarjeta, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo mostrarImagenNoticia(
                $noticiaTarjeta,
                'tarjeta-imagen-img',
                '📷'
            ); ?>
        </a>
    </div>

    <div class="tarjeta-metadatos news-card__meta news-card__meta--standard">
        <div class="metadato-autor">
            <?php if ($avatarTarjeta !== ''): ?>
                <img
                    src="<?php echo htmlspecialchars(base_url('uploads/perfiles/' . rawurlencode($avatarTarjeta)), ENT_QUOTES, 'UTF-8'); ?>"
                    alt=""
                    width="20"
                    height="20"
                    class="avatar-mini"
                    loading="lazy"
                >
            <?php elseif ($varianteTarjeta === 'source'): ?>
                <span class="avatar-mini-placeholder">👤</span>
            <?php else: ?>
                <img
                    src="<?php echo htmlspecialchars(base_url('assets/img/default-avatar.png'), ENT_QUOTES, 'UTF-8'); ?>"
                    alt=""
                    width="20"
                    height="20"
                    class="avatar-mini"
                    loading="lazy"
                >
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars(route('periodistas', ['id' => (int) ($noticiaTarjeta['id_autor'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) ($noticiaTarjeta['autor_nombre'] ?? 'Autor'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>

        <div class="metadato-fecha">
            📅 <?php echo htmlspecialchars(formatearFecha((string) ($noticiaTarjeta['fecha_publicacion'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div class="metadato-categoria">
            📁 <a href="<?php echo htmlspecialchars(route('categoria', ['id' => (int) ($noticiaTarjeta['id_categoria'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($noticiaTarjeta['nombre_categoria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>

        <div class="metadato-visitas">
            👁️ <?php echo number_format((int) ($noticiaTarjeta['visitas'] ?? 0)); ?>
        </div>

        <div class="metadato-comentarios">
            <a href="<?php echo htmlspecialchars(route('comentarios_noticia', ['id' => $idNoticiaTarjeta]), ENT_QUOTES, 'UTF-8'); ?>">
                💬 <?php echo (int) ($noticiaTarjeta['total_comentarios'] ?? 0); ?>
            </a>
        </div>
    </div>

    <div class="tarjeta-acciones news-card__actions">
        <a href="<?php echo htmlspecialchars($urlNoticiaTarjeta, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small news-card__button">
            Leer más →
        </a>
    </div>
</article>
