<?php
declare(strict_types=1);


/**
 * FUNCIONES ESPECÍFICAS PARA USUARIO
 */

function getUsuarioStats($pdo, $id_usuario) {
    $comentarios = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario = :id");
    $comentarios->execute([':id' => $id_usuario]);
    
    return [
        'total_comentarios' => $comentarios->fetchColumn(),
    ];
}

function getUsuarioMenu() {
    return [
        ['url' => 'usuario/dashboard', 'icono' => '📊', 'texto' => 'Dashboard'],
        ['url' => 'usuario/perfil', 'icono' => '👤', 'texto' => 'Mi Perfil'],
        ['url' => 'usuario/mis-comentarios', 'icono' => '💬', 'texto' => 'Mis Comentarios'],
    ];
}

function getUsuarioDashboardContent($pdo, $id_usuario) {
    $stats = getUsuarioStats($pdo, $id_usuario);
    
    // Últimos comentarios
    $stmt = $pdo->prepare("
        SELECT c.*, n.titulo as noticia_titulo
        FROM comentarios c
        JOIN noticias n ON c.id_noticia = n.id_noticia
        WHERE c.id_usuario = :id
        ORDER BY c.fecha_comentario DESC
        LIMIT 5
    ");
    $stmt->execute([':id' => $id_usuario]);
    $comentarios = $stmt->fetchAll();
    
    ob_start();
    ?>
    <div class="grid-3">
        <div class="tarjeta resumen-card">
            <div class="resumen-icono">💬</div>
            <div class="resumen-datos">
                <span class="resumen-numero"><?php echo $stats['total_comentarios']; ?></span>

                <span class="resumen-etiqueta">Comentarios</span>
            </div>
        </div>
    </div>
    
    <?php if (!empty($comentarios)): ?>

        <h2>Mis últimos comentarios</h2>
        <div class="lista-comentarios">
            <?php foreach ($comentarios as $c): ?>

            <div class="tarjeta-comentario">
                <p><?php echo htmlspecialchars($c['contenido']); ?></p>

                <small>En: <?php echo htmlspecialchars($c['noticia_titulo']); ?></small>

            </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>

    <?php

    return ob_get_clean();
}
