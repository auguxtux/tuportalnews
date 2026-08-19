<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_pagina = 'Violencia de género en España | Datos e información';
$meta_descripcion = 'Información y datos sobre violencia de género en España: víctimas mortales, denuncias, llamadas al 016, menores huérfanos y otros indicadores.';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="preconnect" href="https://embed.epdata.es">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('news-cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('public-estadirticas.css'), ENT_QUOTES, 'UTF-8'); ?>">

  <main id="contenido">
    <section id="inicio" class="hero">
      <div class="container hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Información · sensibilización · datos</p>
          <h1>Violencia de género en España</h1>
          <p class="hero-lead">
            Una presentación visual de conceptos, cifras e indicadores sobre la violencia
            de género, basada en los contenidos y gráficos estadísticos facilitados.
          </p>

          <div class="hero-actions">
            <a class="button button-primary" href="#que-es">Comenzar</a>
            <a class="button button-secondary" href="#victimas">Ver datos</a>
          </div>
        </div>

        <aside class="hero-card" aria-label="Resumen de datos">
          <div class="hero-card-icon" aria-hidden="true">●</div>
          <p class="hero-card-label">Dato destacado</p>
          <p class="hero-number">36</p>
          <p class="hero-card-text">
            víctimas mortales indicadas en el contenido facilitado hasta agosto de 2026.
          </p>
          <p class="hero-card-note">
            Las cifras pueden variar con las actualizaciones de las fuentes oficiales.
          </p>
        </aside>
      </div>
    </section>

    <section class="chapter-navigation" aria-label="Índice de contenidos">
      <div class="container">
        <div class="chapter-nav-head">
          <div>
            <span class="section-kicker">Navegación rápida</span>
            <h2>Índice de contenidos</h2>
            <p>Selecciona una sección para ir directamente al contenido que quieras consultar.</p>
          </div>

          <button
            id="chapterMenuToggle"
            class="chapter-menu-toggle"
            type="button"
            aria-expanded="true"
            aria-controls="chapterMenu"
          >
            <span aria-hidden="true">☰</span>
            <span>Mostrar / ocultar índice</span>
          </button>
        </div>

        <nav id="chapterMenu" class="chapter-menu" aria-label="Secciones del recurso">
          <a class="chapter-link" href="#que-es" data-section="que-es">
            <span class="chapter-number">01</span>
            <span class="chapter-copy">
              <strong>¿Qué es?</strong>
              <small>Definición y marco conceptual</small>
            </span>
          </a>

          <a class="chapter-link" href="#victimas" data-section="victimas">
            <span class="chapter-number">02</span>
            <span class="chapter-copy">
              <strong>Víctimas mortales</strong>
              <small>Cifras, evolución y denuncia previa</small>
            </span>
          </a>

          <a class="chapter-link" href="#menores" data-section="menores">
            <span class="chapter-number">03</span>
            <span class="chapter-copy">
              <strong>Menores huérfanos</strong>
              <small>Impacto de la violencia en menores</small>
            </span>
          </a>

          <a class="chapter-link" href="#llamadas" data-section="llamadas">
            <span class="chapter-number">04</span>
            <span class="chapter-copy">
              <strong>Atención 016</strong>
              <small>Evolución de las llamadas</small>
            </span>
          </a>

          <a class="chapter-link" href="#denuncias" data-section="denuncias">
            <span class="chapter-number">05</span>
            <span class="chapter-copy">
              <strong>Denuncias</strong>
              <small>Datos judiciales y denuncias falsas</small>
            </span>
          </a>
        </nav>
      </div>
    </section>

    <div class="reading-layout container">
      <aside class="reading-sidebar" aria-label="Menú lateral de secciones">
        <div class="sidebar-sticky">
          <p class="sidebar-title">En esta página</p>
          <nav class="sidebar-nav">
            <a href="#que-es" data-section="que-es"><span>01</span> ¿Qué es?</a>
            <a href="#victimas" data-section="victimas"><span>02</span> Víctimas</a>
            <a href="#menores" data-section="menores"><span>03</span> Menores</a>
            <a href="#llamadas" data-section="llamadas"><span>04</span> 016</a>
            <a href="#denuncias" data-section="denuncias"><span>05</span> Denuncias</a>
          </nav>

          <div class="sidebar-progress" aria-hidden="true">
            <div class="sidebar-progress-track">
              <div id="readingProgress" class="sidebar-progress-bar"></div>
            </div>
            <span id="readingProgressText">0 % leído</span>
          </div>
        </div>
      </aside>

      <div class="reading-content">

    <section id="que-es" class="section section-chapter">
      <div class="container content-narrow">
        <div class="chapter-toolbar">
          <span class="chapter-pill">Sección 1 de 5</span>
          <a href="#victimas" class="chapter-next">Siguiente: Víctimas →</a>
        </div>

        <div class="section-heading">
          <span class="section-kicker">Concepto</span>
          <h2>¿Qué es la violencia de género?</h2>
        </div>

        <div class="definition-card">
          <div class="definition-icon" aria-hidden="true">§</div>
          <div>
            <p>
              La Ley española de Medidas de Protección Integral contra la Violencia de
              Género, aprobada en 2004, define este tipo de violencia como aquella que,
              como manifestación de la discriminación, la situación de desigualdad y las
              relaciones de poder de los hombres sobre las mujeres, se ejerce sobre estas
              por parte de quienes sean o hayan sido sus cónyuges o de quienes estén o
              hayan estado ligados a ellas por relaciones similares de afectividad, aun
              sin convivencia.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section id="victimas" class="section section-alt section-chapter">
      <div class="container">
        <div class="chapter-toolbar">
          <a href="#que-es" class="chapter-prev">← Anterior</a>
          <span class="chapter-pill">Sección 2 de 5</span>
          <a href="#menores" class="chapter-next">Siguiente: Menores →</a>
        </div>

        <div class="section-heading">
          <span class="section-kicker">Víctimas mortales</span>
          <h2>Mujeres asesinadas por violencia de género en España</h2>
          <p>
            Evolución de las víctimas mortales por violencia de género en lo que va de año.
          </p>
        </div>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Mujeres asesinadas por violencia de género</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-victimas-principal"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/7820a811-618d-4109-97be-f1d2c31313a6-106/450"
              title="Mujeres asesinadas por violencia de género en España"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
          </div>
        </article>

        <div class="stat-grid">
          <article class="stat-card">
            <span class="stat-value">36</span>
            <h3>Víctimas mortales</h3>
            <p>Hasta agosto de 2026, según el contenido facilitado.</p>
          </article>

          <article class="stat-card">
            <span class="stat-value">2</span>
            <h3>Víctimas en agosto</h3>
            <p>Dato indicado para agosto de 2026.</p>
          </article>

          <article class="stat-card">
            <span class="stat-value">24</span>
            <h3>Sin denuncia previa</h3>
            <p>De las 36 víctimas contabilizadas en el periodo indicado.</p>
          </article>
        </div>

        <article class="text-card">
          <h3>Víctimas mortales por violencia de género en lo que va de año</h3>
          <p class="source-line">
            Cifras de la Delegación del Gobierno contra la Violencia de Género
          </p>
          <p>
            Las víctimas mortales a causa de la violencia de género en España en lo que
            va de año hasta agosto de 2026 ascienden a 36, según el último balance
            indicado en el contenido facilitado. En agosto de 2026, un total de 2 mujeres
            han sido asesinadas víctimas de la violencia machista, lo que representa una
            variación de 0 respecto al mismo mes del año anterior.
          </p>
        </article>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Evolución de víctimas mortales</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-victimas-evolucion"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/7820a811-618d-4109-97be-f1d2c31313a6-106/450"
              title="Evolución de las víctimas mortales por violencia de género"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
          </div>
        </article>

        <article class="highlight-card">
          <div class="highlight-number">24</div>
          <div>
            <h3>Víctimas sin denuncia previa</h3>
            <p>
              De las 36 víctimas por violencia de género hasta agosto de 2026,
              un total de 24 no había presentado denuncia.
            </p>
          </div>
        </article>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Denuncia previa de las víctimas</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-denuncia-previa"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/ca7a1bf4-213b-46a2-a586-dbeed547cf37/450"
              title="Denuncia previa de las víctimas de violencia de género"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
          </div>
        </article>

        <div class="section-subheading">
          <h3>Procedencia del agresor y de la víctima</h3>
          <p>
            En los desplegables de las gráficas puedes consultar la evolución de la
            procedencia del agresor y de la víctima.
          </p>
        </div>

        <div class="charts-grid">
          <article class="chart-card">
            <div class="chart-card-header">
              <h3>Procedencia: gráfico 1</h3>
            </div>
            <div class="iframe-shell">
              <iframe
                id="ep-chart-procedencia-1"
                class="ep-chart"
                src="https://embed.epdata.es/representacion/624040fb-26d3-42a1-908c-9e98100b5a92/450"
                title="Procedencia del agresor y la víctima, gráfico 1"
                loading="lazy"
                scrolling="no"
                allowfullscreen
              ></iframe>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-header">
              <h3>Procedencia: gráfico 2</h3>
            </div>
            <div class="iframe-shell">
              <iframe
                id="ep-chart-procedencia-2"
                class="ep-chart"
                src="https://embed.epdata.es/representacion/986aee7f-96b0-4bbf-9de2-1168eac6f24b/450"
                title="Procedencia del agresor y la víctima, gráfico 2"
                loading="lazy"
                scrolling="no"
                allowfullscreen
              ></iframe>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section id="menores" class="section section-chapter">
      <div class="container">
        <div class="chapter-toolbar">
          <a href="#victimas" class="chapter-prev">← Anterior</a>
          <span class="chapter-pill">Sección 3 de 5</span>
          <a href="#llamadas" class="chapter-next">Siguiente: 016 →</a>
        </div>

        <div class="section-heading">
          <span class="section-kicker">Impacto en menores</span>
          <h2>Menores huérfanos por violencia de género</h2>
          <p>
            Evolución del número de menores que han quedado huérfanos como consecuencia
            de la violencia de género en España.
          </p>
        </div>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Evolución de los menores huérfanos por violencia de género</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-menores-huerfanos"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/473c76a8-53a7-4ce0-8d8a-cd13ece5582b/450"
              title="Evolución de menores huérfanos por violencia de género en España"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
          </div>
        </article>
      </div>
    </section>

    <section id="llamadas" class="section section-alt section-chapter">
      <div class="container">
        <div class="chapter-toolbar">
          <a href="#menores" class="chapter-prev">← Anterior</a>
          <span class="chapter-pill">Sección 4 de 5</span>
          <a href="#denuncias" class="chapter-next">Siguiente: Denuncias →</a>
        </div>

        <div class="section-heading">
          <span class="section-kicker">Atención y asistencia</span>
          <h2>Llamadas al teléfono 016</h2>
          <p>
            Evolución del número de llamadas al servicio 016 de atención a víctimas de
            violencia de género.
          </p>
        </div>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Número de llamadas al 016</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-llamadas-016"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/0567f3f6-6a89-4c9c-b97c-873dcac8facf/450"
              title="Número de llamadas al teléfono 016"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
          </div>
        </article>
      </div>
    </section>

    <section id="denuncias" class="section section-chapter">
      <div class="container">
        <div class="chapter-toolbar">
          <a href="#llamadas" class="chapter-prev">← Anterior</a>
          <span class="chapter-pill">Sección 5 de 5</span>
          <a href="#inicio" class="chapter-next">Volver al inicio ↑</a>
        </div>

        <div class="section-heading">
          <span class="section-kicker">Datos judiciales</span>
          <h2>Denuncias por violencia de género</h2>
          <p>Datos trimestrales y cifras relativas a denuncias falsas.</p>
        </div>

        <article class="text-card">
          <h3>Denuncias por violencia de género, datos trimestrales</h3>
          <p class="source-line">Cifras del Consejo General del Poder Judicial</p>
          <p>
            Los juzgados españoles recibieron un 4,28 % más de denuncias por violencia
            de género en el primer trimestre de 2025 que en el mismo periodo del año
            anterior, según los datos incluidos en el contenido facilitado.
          </p>
        </article>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Denuncias por violencia de género</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-denuncias-trimestrales"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/11554f19-60c3-4bb3-8a67-77acb8edb258/450"
              title="Denuncias por violencia de género, datos trimestrales"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
          </div>
        </article>

        <article class="text-card false-reports-card">
          <h3>Denuncias falsas por violencia de género</h3>
          <p class="source-line">Cifras de la Fiscalía General del Estado</p>

          <div class="percentage-grid">
            <div class="percentage-item">
              <strong>0,0069 %</strong>
              <span>
                del total de denuncias por violencia de género entre 2009 y 2018,
                según el dato incluido en el contenido facilitado.
              </span>
            </div>

            <div class="percentage-item">
              <strong>0,03 %</strong>
              <span>
                del total de denuncias entre 2009 y 2020, según el dato incluido
                en el contenido facilitado.
              </span>
            </div>
          </div>
        </article>

        <article class="chart-card">
          <div class="chart-card-header">
            <div>
              <span class="source-badge">EPData</span>
              <h3>Número de condenas por denuncia falsa</h3>
            </div>
          </div>

          <div class="iframe-shell">
            <iframe
              id="ep-chart-denuncias-falsas"
              class="ep-chart"
              src="https://embed.epdata.es/representacion/ef50d85c-804c-4b30-bf1a-a76eff9b6c0b/450"
              title="Condenas por denuncia falsa en casos de violencia de género"
              loading="lazy"
              scrolling="no"
              allowfullscreen
            ></iframe>
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
              Esta página organiza los textos y gráficos suministrados en el contenido
              original. Las gráficas se cargan directamente desde EPData y pueden
              actualizarse de forma independiente. Para trabajos académicos, divulgativos
              o institucionales conviene comprobar siempre la fecha y la edición más
              reciente de las fuentes oficiales.
            </p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="<?php echo htmlspecialchars(js_url('public-estadisticas.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>