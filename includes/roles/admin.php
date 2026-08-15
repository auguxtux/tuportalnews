<?php
declare(strict_types=1);


/**
 * FUNCIONES ESPECÍFICAS PARA ADMIN
 */

function getAdminStats($pdo) {
    return [
        'total_usuarios' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario'")->fetchColumn(),
        'total_periodistas' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'periodista'")->fetchColumn(),
        'total_noticias' => $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn(),
        'total_comentarios' => $pdo->query("SELECT COUNT(*) FROM comentarios")->fetchColumn(),
    ];
}

function getAdminMenu() {
    return [
        ['url' => 'admin/dashboard', 'icono' => '📊', 'texto' => 'Dashboard'],
        ['url' => 'admin/perfil', 'icono' => '👤', 'texto' => 'Mi Perfil'],
        ['url' => 'admin/usuarios-logueados', 'icono' => '👥', 'texto' => 'Usuarios'],
        ['url' => 'admin/periodistas', 'icono' => '✍️', 'texto' => 'Periodistas'],
        ['url' => 'admin/noticias', 'icono' => '📰', 'texto' => 'Noticias'],
        ['url' => 'admin/categorias', 'icono' => '🏷️', 'texto' => 'Categorías'],
        ['url' => 'admin/comentarios', 'icono' => '💬', 'texto' => 'Comentarios'],
        ['url' => 'admin/configuracion', 'icono' => '⚙️', 'texto' => 'Configuración'],
    ];
}

function getAdminDashboardContent($pdo) {
    $stats = getAdminStats($pdo);
    ob_start();
    ?>
    <div class="grid-4">
        <div class="tarjeta estadistica-card">
            <div class="estadistica-icono">👥</div>
            <div class="estadistica-valor"><?php echo $stats['total_usuarios']; ?></div>

            <div class="estadistica-etiqueta">Usuarios</div>
        </div>
        <div class="tarjeta estadistica-card">
            <div class="estadistica-icono">✍️</div>
            <div class="estadistica-valor"><?php echo $stats['total_periodistas']; ?></div>

            <div class="estadistica-etiqueta">Periodistas</div>
        </div>
        <div class="tarjeta estadistica-card">
            <div class="estadistica-icono">📰</div>
            <div class="estadistica-valor"><?php echo $stats['total_noticias']; ?></div>

            <div class="estadistica-etiqueta">Noticias</div>
        </div>
        <div class="tarjeta estadistica-card">
            <div class="estadistica-icono">💬</div>
            <div class="estadistica-valor"><?php echo $stats['total_comentarios']; ?></div>

            <div class="estadistica-etiqueta">Comentarios</div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
