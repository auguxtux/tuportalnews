<?php
declare(strict_types=1);


/**
 * Página para reportar un comentario - VERSIÓN MÍNIMA
 * URL: /usuario/reportar-comentario.php?id=123
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/privado.php';

// Iniciar sesión manualmente
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Si no está logueado, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . route('login'));
    exit;
}

$reportePrivado = defined('REPORTE_COMENTARIO_PRIVADO') && REPORTE_COMENTARIO_PRIVADO === true;

if ($reportePrivado && !usuarioEsPrivado()) {
    http_response_code(404);
    exit('Contenido no disponible');
}

$id_comentario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Si no hay ID válido, mostrar error
if ($id_comentario <= 0) {
    die("❌ ID de comentario no válido. URL debe ser: /usuario/reportar-comentario.php?id=123");
}

$pdo = db();
// Obtener datos del comentario
$stmt = $pdo->prepare("
    SELECT c.*, u.nombre as autor_nombre, n.titulo as noticia_titula, n.id_noticia
    FROM comentarios c
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    JOIN noticias n ON c.id_noticia = n.id_noticia
    WHERE c.id_comentario = ?
      AND c.estado = 'aprobado'
      AND n.estado IN ('publicada','destacada')
      AND n.privada = ?
");
$stmt->execute([$id_comentario, $reportePrivado ? 1 : 0]);
$comentario = $stmt->fetch();

if (!$comentario) {
    http_response_code(404);
    exit('Contenido no disponible');
}

// Verificar que no sea su propio comentario
if ($_SESSION['usuario_id'] == $comentario['id_usuario']) {
    die("❌ No puedes reportar tu propio comentario.");
}

$titulo_pagina = 'Reportar comentario';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(css_url('usuario-reportar.css'), ENT_QUOTES, 'UTF-8'); ?>">
<main class="usuario-reportar-container">
  <div class="usuario-reportar-card">
    <header class="usuario-reportar-header"><h1 class="usuario-reportar-titulo">🚩 Reportar comentario</h1></header>
    <div class="usuario-reportar-body">
    <p class="usuario-reportar-contexto">
        Vas a reportar un comentario de
        <strong><?php echo htmlspecialchars($comentario['autor_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
        en la noticia
        <strong><?php echo htmlspecialchars($comentario['noticia_titula'], ENT_QUOTES, 'UTF-8'); ?></strong>.
    </p>

    <div id="mensaje-reporte" class="usuario-reportar-mensaje" data-mensaje-reporte role="status" aria-live="polite"></div>

    <form id="form-reporte" class="js-form-reporte" method="POST" action="<?php echo $reportePrivado
        ? route('privado_procesar_reporte_comentario')
        : route('procesar_reporte_comentario'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="comentario_id" value="<?php echo (int)$id_comentario; ?>">

        <div class="usuario-reportar-campo">
            <label for="motivo" class="usuario-reportar-label">Motivo:</label>
            <select name="motivo" id="motivo" class="usuario-reportar-select" required>
                <option value="">-- Selecciona un motivo --</option>
                <?php foreach (motivosReporte() as $valor => $etiqueta): ?>
                    <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="usuario-reportar-campo">
            <label for="descripcion" class="usuario-reportar-label" id="etiqueta-descripcion" data-etiqueta-descripcion>Descripción opcional:</label>
            <textarea name="descripcion" id="descripcion" class="usuario-reportar-textarea" rows="4" maxlength="1000"></textarea>
            <small class="usuario-reportar-contador"><span id="contador-descripcion" data-contador-descripcion>0</span>/1000</small>
        </div>

        <div class="usuario-reportar-acciones"><button type="submit" class="usuario-reportar-btn usuario-reportar-btn-enviar">Enviar reporte</button>
        <a href="<?php echo route($reportePrivado ? 'privado_comentarios' : 'comentarios_noticia', ['id' => $comentario['id_noticia']]); ?>#comentario-<?php echo (int)$id_comentario; ?>" class="usuario-reportar-btn usuario-reportar-btn-cancelar">
            Cancelar
        </a></div>
    </form>
    </div>
  </div>
</main>

<script src="<?php echo htmlspecialchars(js_url('reportar.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
