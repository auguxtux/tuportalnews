<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_pagina = 'Población en España | Datos y evolución';
$meta_descripcion = 'Indicadores clave de población, envejecimiento, natalidad y población en España con gráficos estadísticos.';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="preconnect" href="https://embed.epdata.es">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('news-cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('public-estadirticas.css'), ENT_QUOTES, 'UTF-8'); ?>">

<main id="contenido">
  <section id="inicio" class="hero">
    <div class="container hero-grid">
      <div class="hero-copy">
        <p class="eyebrow">Población · envejecimiento · demografía</p>
        <h1>Población en España</h1>
        <p class="hero-lead">
          Un recorrido visual por los indicadores clave de población, envejecimiento, natalidad
          y evolución demográfica en España.
        </p>
        <div class="hero-actions">
          <a class="button button-primary" href="#temperatura">Comenzar</a>
          <a class="button button-secondary" href="#indice">Ver índice</a>
        </div>
      </div>

      <aside class="hero-card" aria-label="Resumen del recurso">
        <div class="hero-card-icon" aria-hidden="true">👥</div>
        <p class="hero-card-label">Bloques temáticos</p>
        <p class="hero-number">5</p>
        <p class="hero-card-text">
          evolución demográfica, población, natalidad, envejecimiento y estructura por edad.
        </p>
        <p class="hero-card-note">
          Las gráficas se cargan desde EPData y pueden actualizarse independientemente.
        </p>
      </aside>
    </div>
  </section>

  <section id="indice" class="chapter-navigation" aria-label="Índice de contenidos">
    <div class="container">
      <div class="chapter-nav-head">
        <div>
          <span class="section-kicker">Navegación rápida</span>
          <h2>Índice de contenidos</h2>
          <p>Selecciona una sección para ir directamente al indicador que quieras consultar.</p>
        </div>
        <button id="chapterMenuToggle" class="chapter-menu-toggle" type="button" aria-expanded="true" aria-controls="chapterMenu">
          <span aria-hidden="true">☰</span><span>Mostrar / ocultar índice</span>
        </button>
      </div>

      <nav id="chapterMenu" class="chapter-menu" aria-label="Secciones del recurso">
        <a class="chapter-link" href="#temperatura" data-section="temperatura">
          <span class="chapter-number">01</span>
          <span class="chapter-copy"><strong>Temperatura global</strong><small>Aumento respecto a 1850-1900</small></span>
        </a>
        <a class="chapter-link" href="#emisiones" data-section="emisiones">
          <span class="chapter-number">02</span>
          <span class="chapter-copy"><strong>Emisiones de CO₂</strong><small>Evolución y origen</small></span>
        </a>
        <a class="chapter-link" href="#concentracion" data-section="concentracion">
          <span class="chapter-number">03</span>
          <span class="chapter-copy"><strong>CO₂ atmosférico</strong><small>Concentración en Mauna Loa</small></span>
        </a>
        <a class="chapter-link" href="#gases" data-section="gases">
          <span class="chapter-number">04</span>
          <span class="chapter-copy"><strong>Gases de efecto invernadero</strong><small>Variación desde 1984</small></span>
        </a>
        <a class="chapter-link" href="#olas-calor" data-section="olas-calor">
          <span class="chapter-number">05</span>
          <span class="chapter-copy"><strong>Olas de calor</strong><small>Evolución en España</small></span>
        </a>
      </nav>
    </div>
  </section>

  <div class="reading-layout container">
    <aside class="reading-sidebar" aria-label="Menú lateral de secciones">
      <div class="sidebar-sticky">
        <p class="sidebar-title">En esta página</p>
        <nav class="sidebar-nav">
          <a href="#temperatura" data-section="temperatura"><span>01</span> Temperatura</a>
          <a href="#emisiones" data-section="emisiones"><span>02</span> Emisiones</a>
          <a href="#concentracion" data-section="concentracion"><span>03</span> CO₂ atmosférico</a>
          <a href="#gases" data-section="gases"><span>04</span> GEI</a>
          <a href="#olas-calor" data-section="olas-calor"><span>05</span> Olas de calor</a>
        </nav>
        <div class="sidebar-progress" aria-hidden="true">
          <div class="sidebar-progress-track"><div id="readingProgress" class="sidebar-progress-bar"></div></div>
          <span id="readingProgressText">0 % leído</span>
        </div>
      </div>
    </aside>

    <div class="reading-content">
      <section id="temperatura" class="section section-chapter">
        <div class="container">
          <div class="chapter-toolbar">
            <span class="chapter-pill">Sección 1 de 5</span>
            <a href="#emisiones" class="chapter-next">Siguiente: Emisiones →</a>
          </div>
          <div class="section-heading">
            <span class="section-kicker">Calentamiento global</span>
            <h2>Incremento de la temperatura global</h2>
            <p>Aumento estimado de la temperatura en superficie a escala mundial por encima de los niveles de 1850 a 1900.</p>
          </div>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Incremento de la temperatura global</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-temperatura-global" class="ep-chart"
                src="https://embed.epdata.es/representacion/6d25725c-8aa9-4186-a1e3-7c047c76f32c/450"
                title="Incremento de la temperatura global" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>
        </div>
      </section>

      <section id="emisiones" class="section section-alt section-chapter">
        <div class="container">
          <div class="chapter-toolbar">
            <a href="#temperatura" class="chapter-prev">← Anterior</a>
            <span class="chapter-pill">Sección 2 de 5</span>
            <a href="#concentracion" class="chapter-next">Siguiente: CO₂ atmosférico →</a>
          </div>
          <div class="section-heading">
            <span class="section-kicker">Emisiones</span>
            <h2>Evolución de las emisiones de CO₂ en el mundo</h2>
            <p>Toneladas de CO₂ expresadas en miles de millones y evolución de las emisiones procedentes de combustibles fósiles.</p>
          </div>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Evolución de las emisiones de CO₂ en el mundo</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-emisiones-mundiales" class="ep-chart"
                src="https://embed.epdata.es/representacion/8a003cc7-fe0b-4740-82e7-5aba738b8c09/450"
                title="Evolución de las emisiones de CO2 en el mundo" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>

          <article class="text-card">
            <h3>Emisiones procedentes de combustibles fósiles</h3>
            <p>Los datos recogen un fuerte aumento de las emisiones de CO₂ procedentes de combustibles fósiles desde la época preindustrial.</p>
          </article>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Emisiones globales de CO₂ procedentes de combustibles fósiles</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-combustibles-fosiles" class="ep-chart"
                src="https://embed.epdata.es/representacion/f305b9e6-e82c-4a5f-bcd4-540107f66ec8/450"
                title="Evolución de emisiones globales de CO2 procedentes de combustibles fósiles" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>

          <article class="text-card">
            <h3>Evolución por tipo de combustible</h3>
            <p>La siguiente representación permite comparar cómo han evolucionado las emisiones globales de CO₂ según su origen.</p>
          </article>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Emisiones globales de CO₂ por origen</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-emisiones-origen" class="ep-chart"
                src="https://embed.epdata.es/representacion/e0505233-2ae6-4de8-9c2f-be5c37660f99/450"
                title="Evolución de las emisiones globales de CO2 por origen" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>
        </div>
      </section>

      <section id="concentracion" class="section section-chapter">
        <div class="container">
          <div class="chapter-toolbar">
            <a href="#emisiones" class="chapter-prev">← Anterior</a>
            <span class="chapter-pill">Sección 3 de 5</span>
            <a href="#gases" class="chapter-next">Siguiente: GEI →</a>
          </div>
          <div class="section-heading">
            <span class="section-kicker">Atmósfera</span>
            <h2>Concentración de CO₂ en la atmósfera</h2>
            <p>Mediciones de concentración de dióxido de carbono registradas por la estación de Mauna Loa, en Hawai.</p>
          </div>

          <article class="text-card">
            <h3>Observación atmosférica</h3>
            <p>
              Muchos estudios sobre contaminación y calentamiento global emplean cifras procedentes de estaciones
              ubicadas en distintas partes del globo que monitorizan la presencia de gases en la atmósfera.
              El siguiente gráfico recoge la concentración de CO₂ medida por la estación de Mauna Loa en Hawai.
            </p>
          </article>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Concentración de CO₂ mes a mes en la atmósfera</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-concentracion-co2" class="ep-chart"
                src="https://embed.epdata.es/representacion/bb76c652-3d4e-46b3-b9f1-5f1f7b59e862/450"
                title="Concentración de CO2 mes a mes en la atmósfera" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>
        </div>
      </section>

      <section id="gases" class="section section-alt section-chapter">
        <div class="container">
          <div class="chapter-toolbar">
            <a href="#concentracion" class="chapter-prev">← Anterior</a>
            <span class="chapter-pill">Sección 4 de 5</span>
            <a href="#olas-calor" class="chapter-next">Siguiente: Olas de calor →</a>
          </div>
          <div class="section-heading">
            <span class="section-kicker">Gases de efecto invernadero</span>
            <h2>Variación de gases de efecto invernadero respecto a 1984</h2>
            <p>Evolución global del CO₂, metano y óxido nitroso a partir de observaciones atmosféricas.</p>
          </div>

          <article class="highlight-card">
            <div class="highlight-number">GEI</div>
            <div>
              <h3>Variación indicada en el contenido</h3>
              <p>CO₂: +18,4 % · Metano: +13 % · Óxido nitroso: +9 %.</p>
            </div>
          </article>

          <article class="text-card">
            <p>
              Las concentraciones de metano y óxido nitroso han ascendido durante los últimos diez años,
              según las observaciones de la red de Vigilancia de la Atmósfera Global de la Organización
              Meteorológica Mundial, que cuenta con estaciones en regiones remotas del Ártico, zonas
              montañosas e islas tropicales.
            </p>
          </article>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Variación de las emisiones de gases de efecto invernadero respecto a 1984</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-gei" class="ep-chart"
                src="https://embed.epdata.es/representacion/7088de4d-2e2b-4145-ba91-45169773868a/450"
                title="Variación de gases de efecto invernadero respecto a 1984" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>
        </div>
      </section>

      <section id="olas-calor" class="section section-chapter">
        <div class="container">
          <div class="chapter-toolbar">
            <a href="#gases" class="chapter-prev">← Anterior</a>
            <span class="chapter-pill">Sección 5 de 5</span>
            <a href="#inicio" class="chapter-next">Volver al inicio ↑</a>
          </div>
          <div class="section-heading">
            <span class="section-kicker">España</span>
            <h2>Olas de calor en España</h2>
            <p>Número de días al año en situación de ola de calor en España por décadas desde 1975.</p>
          </div>

          <article class="text-card">
            <h3>Una década especialmente intensa</h3>
            <p>
              La última década ha sido la que más olas de calor ha registrado en España.
              También ha sido la que ha concentrado episodios de mayor duración.
            </p>
          </article>

          <article class="chart-card">
            <div class="chart-card-header"><div><span class="source-badge">EPData</span><h3>Días al año en situación de ola de calor en España</h3></div></div>
            <div class="iframe-shell">
              <iframe id="ep-chart-olas-calor" class="ep-chart"
                src="https://embed.epdata.es/representacion/bf370d55-01a4-42ab-8e03-c01bf80162d2/450"
                title="Número de días al año en situación de ola de calor en España" loading="lazy" scrolling="no"></iframe>
            </div>
          </article>
        </div>
      </section>
    </div>
  </div>

  <section class="section sources-section">
    <div class="container content-narrow">
      <div class="info-panel">
        <span class="info-panel-icon" aria-hidden="true">i</span>
        <div>
          <h2>Sobre los datos</h2>
          <p>
            Esta página organiza los textos y gráficos facilitados sobre cambio climático.
            Las visualizaciones se cargan directamente desde EPData y pueden actualizarse
            de forma independiente. Para usos académicos o divulgativos conviene comprobar
            siempre la fecha y la versión más reciente de las fuentes originales.
          </p>
        </div>
      </div>
    </div>
  </section>
</main>

<script src="<?php echo htmlspecialchars(js_url('public-estadisticas.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>