<?php
declare(strict_types=1);

/**
 * Sitemap XML público.
 *
 * Incluye las secciones principales y únicamente noticias públicas que se
 * encuentren publicadas.
 */

define('SKIP_SESSION_START', true);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900, s-maxage=900');

/**
 * Escapa un valor para utilizarlo de forma segura dentro del XML.
 */
function sitemapEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$urls = [
    ['loc' => SITE_URL . '/', 'changefreq' => 'hourly', 'priority' => '1.0'],
    ['loc' => route('listado_noticias'), 'changefreq' => 'hourly', 'priority' => '0.9'],
    ['loc' => route('ultimas'), 'changefreq' => 'hourly', 'priority' => '0.8'],
    ['loc' => route('populares'), 'changefreq' => 'daily', 'priority' => '0.7'],
    ['loc' => route('categoria'), 'changefreq' => 'daily', 'priority' => '0.7'],
    ['loc' => route('ubicacion'), 'changefreq' => 'daily', 'priority' => '0.6'],
    ['loc' => route('fuente'), 'changefreq' => 'daily', 'priority' => '0.6'],
    ['loc' => route('periodistas'), 'changefreq' => 'weekly', 'priority' => '0.6'],
    ['loc' => SITE_URL . '/tiempo', 'changefreq' => 'hourly', 'priority' => '0.6'],
    ['loc' => SITE_URL . '/pobreza', 'changefreq' => 'weekly', 'priority' => '0.6'],
];

try {
    $pdo = db();
    $stmt = $pdo->query(
        "SELECT
            n.id_noticia,
            n.slug,
            n.fecha_publicacion,
            n.fecha_actualizacion,
            c.slug_categoria
         FROM noticias n
         INNER JOIN categorias c
             ON c.id_categoria = n.id_categoria
         WHERE n.estado = 'publicada'
           AND n.privada = 0
         ORDER BY n.fecha_publicacion DESC"
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $noticia) {
        $id = (int) $noticia['id_noticia'];
        $slugNoticia = rawurlencode((string) $noticia['slug']);
        $slugCategoria = rawurlencode((string) $noticia['slug_categoria']);
        $fecha = (string) ($noticia['fecha_actualizacion'] ?: $noticia['fecha_publicacion']);
        $timestamp = strtotime($fecha);

        $urls[] = [
            'loc' => SITE_URL . '/noticia/' . $slugCategoria . '/' . $id . '/' . $slugNoticia,
            'lastmod' => $timestamp !== false ? date(DATE_ATOM, $timestamp) : null,
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];
    }
} catch (Throwable $e) {
    registrarErrorInterno('PUBLIC.SITEMAP.GENERAR', $e);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?= sitemapEscape((string) $url['loc']); ?></loc>
<?php if (!empty($url['lastmod'])): ?>
        <lastmod><?= sitemapEscape((string) $url['lastmod']); ?></lastmod>
<?php endif; ?>
        <changefreq><?= sitemapEscape((string) $url['changefreq']); ?></changefreq>
        <priority><?= sitemapEscape((string) $url['priority']); ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
