<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_pagina = 'Paro en España | Evolución y datos EPA';
$meta_descripcion = 'Evolución del paro en España: tasa de desempleo, número de parados, paro juvenil y hogares con todos sus miembros en paro.';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="preconnect" href="https://embed.epdata.es">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('news-cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('public-estadirticas.css'), ENT_QUOTES, 'UTF-8'); ?>">

  <main id="contenido">
    <section id="inicio" class="hero">
      <div class="container hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Empleo · desempleo · EPA</p>
          <h1>El paro en España</h1>
          <p class="hero-lead">Una presentación visual y organizada de algunos de los principales indicadores de desempleo en España a partir de datos de la Encuesta de Población Activa (EPA).</p>
          <div class="hero-actions"><a class="button button-primary" href="#tasa-paro">Comenzar</a><a class="button button-secondary" href="#indice">Ver índice</a></div>
        </div>
        <aside class="hero-card" aria-label="Resumen temático">
          <div class="hero-card-icon" aria-hidden="true">↘</div><p class="hero-card-label">Indicadores incluidos</p><p class="hero-number">4</p>
          <p class="hero-card-text">bloques temáticos sobre desempleo general, número de parados, paro juvenil y hogares con todos sus miembros activos en paro.</p>
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
          <a class="chapter-link" href="#tasa-paro" data-section="tasa-paro"><span class="chapter-number">01</span><span class="chapter-copy"><strong>Tasa de paro</strong><small>Evolución porcentual en España</small></span></a>
          <a class="chapter-link" href="#parados" data-section="parados"><span class="chapter-number">02</span><span class="chapter-copy"><strong>Número de parados</strong><small>Evolución según la EPA</small></span></a>
          <a class="chapter-link" href="#juvenil" data-section="juvenil"><span class="chapter-number">03</span><span class="chapter-copy"><strong>Paro juvenil</strong><small>Comparación por comunidades autónomas</small></span></a>
          <a class="chapter-link" href="#hogares" data-section="hogares"><span class="chapter-number">04</span><span class="chapter-copy"><strong>Hogares en paro</strong><small>Todos los activos desempleados</small></span></a>
        </nav>
      </div>
    </section>

    <div class="reading-layout container">
      <aside class="reading-sidebar" aria-label="Menú lateral de secciones">
        <div class="sidebar-sticky"><p class="sidebar-title">En esta página</p><nav class="sidebar-nav">
          <a href="#tasa-paro" data-section="tasa-paro"><span>01</span> Tasa de paro</a><a href="#parados" data-section="parados"><span>02</span> Parados</a><a href="#juvenil" data-section="juvenil"><span>03</span> Paro juvenil</a><a href="#hogares" data-section="hogares"><span>04</span> Hogares</a>
        </nav><div class="sidebar-progress" aria-hidden="true"><div class="sidebar-progress-track"><div id="readingProgress" class="sidebar-progress-bar"></div></div><span id="readingProgressText">0 % leído</span></div></div>
      </aside>

      <div class="reading-content">
        <section id="tasa-paro" class="section section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><span class="chapter-pill">Sección 1 de 4</span><a href="#parados" class="chapter-next">Siguiente: Número de parados →</a></div>
            <div class="section-heading"><span class="section-kicker">Desempleo general</span><h2>Evolución de la tasa de paro en España (%)</h2><p>Evolución histórica de la tasa de desempleo en España expresada en porcentaje.</p></div>
            <div class="definition-card"><div class="definition-icon" aria-hidden="true">%</div><div><p>La tasa de paro expresa el porcentaje de personas desempleadas respecto al conjunto de la población activa y permite seguir la evolución general del mercado laboral.</p></div></div>
            <article class="chart-card"><div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Evolución de la tasa de paro en España</h3></div></div><div class="iframe-shell"><iframe id="ep-chart-tasa-paro" class="ep-chart" src="https://embed.epdata.es/representacion/7c16eeb2-b201-450b-b56c-e00df71ee371-106/450" title="Evolución de la tasa de paro en España" loading="lazy" scrolling="no" allowfullscreen></iframe></div></article>
          </div>
        </section>

        <section id="parados" class="section section-alt section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><a href="#tasa-paro" class="chapter-prev">← Anterior</a><span class="chapter-pill">Sección 2 de 4</span><a href="#juvenil" class="chapter-next">Siguiente: Paro juvenil →</a></div>
            <div class="section-heading"><span class="section-kicker">EPA</span><h2>Evolución del número de parados en España</h2><p>Número de personas desempleadas en España de acuerdo con los datos de la Encuesta de Población Activa.</p></div>
            <article class="text-card"><h3>Datos de la Encuesta de Población Activa</h3><p class="source-line">Datos de la EPA</p><p>La EPA permite observar cómo evoluciona el número total de personas desempleadas y analizar los cambios del mercado laboral a lo largo del tiempo.</p></article>
            <article class="chart-card"><div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Número de parados en España</h3></div></div><div class="iframe-shell"><iframe id="ep-chart-numero-parados" class="ep-chart" src="https://embed.epdata.es/representacion/4de9666b-7e66-49aa-a9a8-f42b94966f4d-106/450" title="Evolución del número de parados en España" loading="lazy" scrolling="no" allowfullscreen></iframe></div></article>
          </div>
        </section>

        <section id="juvenil" class="section section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><a href="#parados" class="chapter-prev">← Anterior</a><span class="chapter-pill">Sección 3 de 4</span><a href="#hogares" class="chapter-next">Siguiente: Hogares →</a></div>
            <div class="section-heading"><span class="section-kicker">Jóvenes y empleo</span><h2>Tasa de paro juvenil por comunidades autónomas</h2><p>Tasa de paro juvenil según la Encuesta de Población Activa (EPA), comparada entre las diferentes comunidades autónomas.</p></div>
            <article class="highlight-card"><div class="highlight-number">CCAA</div><div><h3>Comparación territorial</h3><p>La representación permite observar las diferencias existentes entre comunidades autónomas y analizar la distribución territorial del desempleo juvenil.</p></div></article>
            <article class="chart-card"><div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Tasa de paro juvenil según la EPA</h3></div></div><div class="iframe-shell"><iframe id="ep-chart-paro-juvenil" class="ep-chart" src="https://embed.epdata.es/representacion/fb538e0a-0177-48c3-9613-391e90fb97ea/450" title="Tasa de paro juvenil por comunidades autónomas" loading="lazy" scrolling="no" allowfullscreen></iframe></div></article>
          </div>
        </section>

        <section id="hogares" class="section section-alt section-chapter">
          <div class="container">
            <div class="chapter-toolbar"><a href="#juvenil" class="chapter-prev">← Anterior</a><span class="chapter-pill">Sección 4 de 4</span><a href="#inicio" class="chapter-next">Volver al inicio ↑</a></div>
            <div class="section-heading"><span class="section-kicker">Impacto en los hogares</span><h2>Evolución de hogares con todos sus miembros en paro</h2><p>Porcentaje de hogares en los que todas las personas activas se encuentran en situación de desempleo.</p></div>
            <div class="definition-card"><div class="definition-icon" aria-hidden="true">⌂</div><div><p>Este indicador permite observar la incidencia del desempleo en el conjunto del hogar, especialmente cuando ninguna de las personas activas dispone de empleo.</p></div></div>
            <article class="chart-card"><div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Hogares con todos sus miembros activos en paro</h3></div></div><div class="iframe-shell"><iframe id="ep-chart-hogares-paro" class="ep-chart" src="https://embed.epdata.es/representacion/db7c4676-8a18-4588-880a-bd032728a61d-106/450" title="Evolución de hogares con todos sus miembros activos en paro" loading="lazy" scrolling="no" allowfullscreen></iframe></div></article>
          </div>
        </section>
      </div>
    </div>

    <section class="section sources-section">
      <div class="container content-narrow"><div class="info-panel"><span class="info-panel-icon" aria-hidden="true">i</span><div><h2>Sobre los datos</h2><p>Esta página organiza los gráficos y contenidos suministrados sobre el paro en España. Las representaciones se cargan directamente desde EPData y pueden actualizarse de forma independiente. Para usos académicos o divulgativos conviene comprobar siempre la edición más reciente de la Encuesta de Población Activa y de las fuentes estadísticas correspondientes.</p></div></div></div>
    </section>
  </main>

  <script src="<?php echo htmlspecialchars(js_url('public-estadisticas.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>