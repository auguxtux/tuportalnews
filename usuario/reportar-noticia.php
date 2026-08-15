<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/privado.php';

if (!estaLogueado()) {
    redireccionar(route('login'));
}

$reportePrivado = defined('REPORTE_NOTICIA_PRIVADA') && REPORTE_NOTICIA_PRIVADA === true;

if ($reportePrivado && !usuarioEsPrivado()) {
    http_response_code(404);
    exit('Contenido no disponible');
}

$noticiaId = (int)($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare("SELECT id_noticia, id_autor, titulo FROM noticias
                       WHERE id_noticia = ?
                         AND estado IN ('publicada','destacada')
                         AND privada = ?");
$stmt->execute([$noticiaId, $reportePrivado ? 1 : 0]);
$noticia = $stmt->fetch();

if (!$noticia || (int)$noticia['id_autor'] === (int)$_SESSION['usuario_id']) {
    http_response_code(404);
    exit('Contenido no disponible');
}

$titulo_pagina = 'Reportar noticia';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('usuario-reportar.css'), ENT_QUOTES, 'UTF-8'); ?>">
<main class="usuario-reportar-container">
  <div class="usuario-reportar-card">
    <header class="usuario-reportar-header"><h1 class="usuario-reportar-titulo">🚩 Reportar noticia</h1></header>
    <div class="usuario-reportar-body">
    <p class="usuario-reportar-contexto"><strong><?php echo htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
    <div id="mensaje-reporte" class="usuario-reportar-mensaje" data-mensaje-reporte role="status" aria-live="polite"></div>
    <form id="form-reporte-noticia" class="js-form-reporte" data-redirigir-exito="1" method="POST" action="<?php echo $reportePrivado
        ? route('privado_procesar_reporte_noticia')
        : route('procesar_reporte_noticia'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="noticia_id" value="<?php echo (int)$noticia['id_noticia']; ?>">
        <div class="usuario-reportar-campo"><label for="motivo" class="usuario-reportar-label">Motivo:</label>
            <select name="motivo" id="motivo" class="usuario-reportar-select" required>
                <option value="">-- Selecciona un motivo --</option>
                <?php foreach (motivosReporte() as $valor => $etiqueta): ?>
                    <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="usuario-reportar-campo"><label for="descripcion" class="usuario-reportar-label" id="etiqueta-descripcion" data-etiqueta-descripcion>Descripción opcional:</label>
            <textarea name="descripcion" id="descripcion" class="usuario-reportar-textarea" rows="4" maxlength="1000"></textarea>
            <small class="usuario-reportar-contador"><span id="contador-descripcion" data-contador-descripcion>0</span>/1000</small>
        </div>
        <div class="usuario-reportar-acciones"><button type="submit" class="usuario-reportar-btn usuario-reportar-btn-enviar">Enviar reporte</button>
        <a href="<?php echo route($reportePrivado ? 'privado_noticia' : 'noticia', ['id' => $noticia['id_noticia']]); ?>" class="usuario-reportar-btn usuario-reportar-btn-cancelar">Cancelar</a></div>
    </form>
    </div>
  </div>
</main>
<script src="<?php echo htmlspecialchars(js_url('reportar.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
