<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_pagina = 'Vivienda en España | Precios y desahucios';
$meta_descripcion = 'Evolución del precio de la vivienda y de los desahucios en España, con gráficos interactivos de EPData.';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="preconnect" href="https://embed.epdata.es">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('news-cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('public-estadirticas.css'), ENT_QUOTES, 'UTF-8'); ?>">

  <main id="contenido">
    <section id="inicio" class="hero">
      <div class="container hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Vivienda · precios · desahucios</p>
          <h1>Vivienda y desahucios en España</h1>
          <p class="hero-lead">Una presentación visual y organizada de la evolución del precio de la vivienda y de distintos indicadores relacionados con los desahucios.</p>
          <div class="hero-actions"><a class="button button-primary" href="#precio-vivienda">Comenzar</a><a class="button button-secondary" href="#indice">Ver índice</a></div>
        </div>
        <aside class="hero-card" aria-label="Resumen temático">
          <div class="hero-card-icon" aria-hidden="true">⌂</div>
          <p class="hero-card-label">Indicadores incluidos</p>
          <p class="hero-number">3</p>
          <p class="hero-card-text">bloques temáticos sobre precio de la vivienda, desahucios por impago del alquiler o la hipoteca y evolución general de los desahucios.</p>
          <p class="hero-card-note">Las gráficas se cargan directamente desde EPData y pueden actualizarse independientemente de esta página.</p>
        </aside>
      </div>
    </section>

    <section id="indice" class="chapter-navigation" aria-label="Índice de contenidos">
      <div class="container">
        <div class="chapter-nav-head">
          <div><span class="section-kicker">Navegación rápida</span><h2>Índice de contenidos</h2><p>Selecciona una sección para consultar directamente el indicador que te interese.</p></div>
          <button id="chapterMenuToggle" class="chapter-menu-toggle" type="button" aria-expanded="true" aria-controls="chapterMenu"><span aria-hidden="true">☰</span><span>Mostrar / ocultar índice</span></button>
        </div>
        <nav id="chapterMenu" class="chapter-menu" aria-label="Secciones del recurso">
          <a class="chapter-link" href="#precio-vivienda" data-section="precio-vivienda"><span class="chapter-number">01</span><span class="chapter-copy"><strong>Precio de la vivienda</strong><small>Evolución durante los últimos 10 años</small></span></a>
          <a class="chapter-link" href="#desahucios-causa" data-section="desahucios-causa"><span class="chapter-number">02</span><span class="chapter-copy"><strong>Alquiler e hipoteca</strong><small>Desahucios según la causa del impago</small></span></a>
          <a class="chapter-link" href="#desahucios" data-section="desahucios"><span class="chapter-number">03</span><span class="chapter-copy"><strong>Desahucios</strong><small>Evolución general del indicador</small></span></a>
        </nav>
      </div>
    </section>

    <div class="reading-layout container">
      <aside class="reading-sidebar" aria-label="Menú lateral de secciones">
        <div class="sidebar-sticky">
          <p class="sidebar-title">En esta página</p>
          <nav class="sidebar-nav">
            <a href="#precio-vivienda" data-section="precio-vivienda"><span>01</span> Precio vivienda</a>
            <a href="#desahucios-causa" data-section="desahucios-causa"><span>02</span> Alquiler e hipoteca</a>
            <a href="#desahucios" data-section="desahucios"><span>03</span> Desahucios</a>
          </nav>
          <div class="sidebar-progress" aria-hidden="true"><div class="sidebar-progress-track"><div id="readingProgress" class="sidebar-progress-bar"></div></div><span id="readingProgressText">0 % leído</span></div>
        </div>
      </aside>

      <div class="reading-content">
        <section id="precio-vivienda" class="section section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><span class="chapter-pill">Sección 1 de 3</span><a href="#desahucios-causa" class="chapter-next">Siguiente: Alquiler e hipoteca →</a></div>
            <div class="section-heading"><span class="section-kicker">Mercado de la vivienda</span><h2>Evolución del precio de la vivienda los últimos 10 años</h2><p>Representación de la evolución reciente del precio de la vivienda en España a lo largo de la última década.</p></div>
            <div class="definition-card"><div class="definition-icon" aria-hidden="true">€</div><div><p>La evolución del precio de la vivienda permite analizar los cambios del mercado residencial y observar tendencias de crecimiento, estabilización o descenso a lo largo del tiempo.</p></div></div>
            <article class="chart-card">
              <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Evolución del precio de la vivienda</h3></div></div>
              <div class="iframe-shell"><iframe id="ep-chart-precio-vivienda" class="ep-chart" src="https://embed.epdata.es/representacion/9885ae47-3986-4e37-ba24-69071cc1fbf7-106/450" title="Evolución del precio de la vivienda los últimos 10 años" loading="lazy" scrolling="no" allowfullscreen></iframe></div>
            </article>
          </div>
        </section>

        <section id="desahucios-causa" class="section section-alt section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><a href="#precio-vivienda" class="chapter-prev">← Anterior</a><span class="chapter-pill">Sección 2 de 3</span><a href="#desahucios" class="chapter-next">Siguiente: Desahucios →</a></div>
            <div class="section-heading"><span class="section-kicker">Pérdida de la vivienda</span><h2>Desahucios por no pagar el alquiler o la hipoteca</h2><p>Indicador que permite comparar los procedimientos relacionados con el impago del alquiler y de la hipoteca.</p></div>
            <article class="text-card"><h3>Alquiler e hipoteca</h3><p>Los desahucios pueden estar relacionados con diferentes situaciones de impago. Esta representación permite consultar su evolución y distinguir entre los casos vinculados al alquiler y los asociados a la hipoteca.</p></article>
            <article class="chart-card">
              <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Desahucios por impago del alquiler o la hipoteca</h3></div></div>
              <div class="iframe-shell"><iframe id="ep-chart-desahucios-causa" class="ep-chart" src="https://embed.epdata.es/representacion/a64f5500-b74b-498b-b9f8-5a33745cfe59-106/450" title="Desahucios por no pagar el alquiler o la hipoteca" loading="lazy" scrolling="no" allowfullscreen></iframe></div>
            </article>
          </div>
        </section>

        <section id="desahucios" class="section section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><a href="#desahucios-causa" class="chapter-prev">← Anterior</a><span class="chapter-pill">Sección 3 de 3</span><a href="#inicio" class="chapter-next">Volver al inicio ↑</a></div>
            <div class="section-heading"><span class="section-kicker">Evolución</span><h2>Desahucios</h2><p>Evolución general de los desahucios a lo largo del tiempo según la serie representada.</p></div>
            <article class="highlight-card"><div class="highlight-number">⌂</div><div><h3>Evolución de los desahucios</h3><p>La serie permite observar cómo cambia este indicador y analizar sus periodos de aumento o descenso.</p></div></article>
            <article class="chart-card">
              <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Evolución de los desahucios</h3></div></div>
              <div class="iframe-shell"><iframe id="ep-chart-desahucios" class="ep-chart" src="https://embed.epdata.es/representacion/6120a357-0f89-40b7-b470-2dc0e8d02207-106/450" title="Evolución de los desahucios" loading="lazy" scrolling="no" allowfullscreen></iframe></div>
            </article>
          </div>
        </section>
      </div>
    </div>

    <section class="section sources-section">
      <div class="container content-narrow"><div class="info-panel"><span class="info-panel-icon" aria-hidden="true">i</span><div><h2>Sobre los datos</h2><p>Esta página organiza los gráficos suministrados sobre vivienda y desahucios. Las representaciones se cargan directamente desde EPData y pueden actualizarse de forma independiente. Para usos académicos, divulgativos o institucionales conviene comprobar siempre la fecha y las fuentes originales de cada serie.</p></div></div></div>
    </section>
  </main>

  <script src="<?php echo htmlspecialchars(js_url('public-estadisticas.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>