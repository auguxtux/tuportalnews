<?php
declare(strict_types=1);


/**
 * FUNCIONES ESPECÍFICAS PARA PERIODISTA
 */

function getPeriodistaStats($pdo, $id_usuario) {
    $total = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_autor = :id");
    $total->execute([':id' => $id_usuario]);
    
    $visitas = $pdo->prepare("SELECT SUM(visitas) FROM noticias WHERE id_autor = :id");
    $visitas->execute([':id' => $id_usuario]);
    
    return [
        'total_noticias' => $total->fetchColumn(),
        'total_visitas' => $visitas->fetchColumn() ?: 0,
    ];
}

function getPeriodistaMenu() {
    return [
        ['url' => 'periodista/dashboard', 'icono' => '📊', 'texto' => 'Dashboard'],
        ['url' => 'periodista/perfil', 'icono' => '👤', 'texto' => 'Mi Perfil'],
        ['url' => 'periodista/mis-noticias', 'icono' => '📰', 'texto' => 'Mis Noticias'],
        ['url' => 'periodista/nueva-noticia', 'icono' => '➕', 'texto' => 'Nueva Noticia'],
    ];
}

function getPeriodistaDashboardContent($pdo, $id_usuario) {
    $stats = getPeriodistaStats($pdo, $id_usuario);
    
    // Últimas 5 noticias
    $stmt = $pdo->prepare("
        SELECT * FROM noticias 
        WHERE id_autor = :id 
        ORDER BY fecha_publicacion DESC 
        LIMIT 5
    ");
    $stmt->execute([':id' => $id_usuario]);
    $noticias = $stmt->fetchAll();
    
    ob_start();
    ?>
    <div class="grid-3">
        <div class="tarjeta resumen-card">
            <div class="resumen-icono">📝</div>
            <div class="resumen-datos">
                <span class="resumen-numero"><?php echo $stats['total_noticias']; ?></span>

                <span class="resumen-etiqueta">Noticias</span>
            </div>
        </div>
        <div class="tarjeta resumen-card">
            <div class="resumen-icono">👁️</div>
            <div class="resumen-datos">
                <span class="resumen-numero"><?php echo number_format($stats['total_visitas']); ?></span>

                <span class="resumen-etiqueta">Visitas</span>
            </div>
        </div>
        <div class="tarjeta resumen-card">
            <div class="resumen-icono">📊</div>
            <div class="resumen-datos">
                <span class="resumen-numero"><?php echo date('d/m/Y'); ?></span>

                <span class="resumen-etiqueta">Hoy</span>
            </div>
        </div>
    </div>
    
    <?php if (!empty($noticias)): ?>

        <h2>Últimas noticias</h2>
        <div class="tabla-responsive"><table class="tabla">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Fecha</th>
                    <th>Visitas</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($noticias as $n): ?>

                <tr>
                    <td><?php echo htmlspecialchars($n['titulo']); ?></td>

                    <td><?php echo formatearFecha($n['fecha_publicacion']); ?></td>

                    <td><?php echo $n['visitas']; ?></td>

                    <td><?php echo $n['estado']; ?></td>

                </tr>
                <?php endforeach; ?>

            </tbody>
        </table></div></div>
    <?php endif; ?>

    <?php

    return ob_get_clean();
}
