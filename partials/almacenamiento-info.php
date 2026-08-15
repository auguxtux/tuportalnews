<?php
declare(strict_types=1);


/**
 * COMPONENTE: Información de almacenamiento del usuario
 * Muestra el espacio usado, límite y barra de progreso
 * 
 * Variables esperadas:
 * - $id_usuario (int): ID del usuario logueado
 * - $mostrar_acciones (bool): Si mostrar botones de ayuda
 */

if (!isset($id_usuario)) {
    $id_usuario = $_SESSION['usuario_id'] ?? 0;
}

if ($id_usuario <= 0) return;

// Obtener datos de almacenamiento
$limite_mb = obtenerLimiteAlmacenamientoUsuario($id_usuario);
$usado_mb = calcularEspacioUsadoUsuario($id_usuario);
$porcentaje = ($limite_mb > 0) ? round(($usado_mb / $limite_mb) * 100, 1) : 0;
$restante_mb = ($limite_mb > 0) ? round($limite_mb - $usado_mb, 2) : 0;
$superado = ($limite_mb > 0 && $usado_mb >= $limite_mb);
$esPeriodistaAlmacenamiento = ($_SESSION['usuario_rol'] ?? '') === 'periodista';
$rutaGestionAlmacenamiento = $esPeriodistaAlmacenamiento
    ? route('mis_noticias')
    : route('usuario_perfil');
$textoGestionAlmacenamiento = $esPeriodistaAlmacenamiento
    ? 'Gestionar mis noticias'
    : 'Gestionar mi avatar';

// Determinar color de la barra según porcentaje
if ($porcentaje >= 100) {
    $color_barra = '#dc2626'; // Rojo - límite superado
    $texto_estado = '⚠️ Límite superado';
    $texto_color = '#dc2626';
} elseif ($porcentaje >= 80) {
    $color_barra = '#f59e0b'; // Naranja - cerca del límite
    $texto_estado = '⚠️ Cerca del límite';
    $texto_color = '#f59e0b';
} elseif ($porcentaje >= 50) {
    $color_barra = '#eab308'; // Amarillo - uso moderado
    $texto_estado = '📊 Uso moderado';
    $texto_color = '#eab308';
} else {
    $color_barra = '#10b981'; // Verde - uso bajo
    $texto_estado = '✅ Espacio disponible';
    $texto_color = '#10b981';
}

// Para administradores (sin límite)
if ($limite_mb == 0) {
    $color_barra = '#3b82f6';
    $texto_estado = '👑 Sin límite (Admin)';
    $texto_color = '#3b82f6';
    $porcentaje = 0;
}
?>

<div class="almacenamiento-card" style="background: white; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <span style="font-size: 1.1rem; font-weight: 600;">💾 Almacenamiento</span>
            <span style="font-size: 0.8rem; color: <?php echo $texto_color; ?>; margin-left: 0.5rem;">

                <?php echo $texto_estado; ?>

            </span>
        </div>
        <div style="font-size: 0.9rem;">
            <strong><?php echo $usado_mb; ?></strong> MB / 

            <?php if ($limite_mb > 0): ?>

                <strong><?php echo $limite_mb; ?></strong> MB

            <?php else: ?>

                <strong>∞</strong> (Sin límite)
            <?php endif; ?>

        </div>
    </div>
    
    <!-- Barra de progreso -->
    <?php if ($limite_mb > 0): ?>

        <div style="background: #e5e7eb; border-radius: 10px; height: 10px; overflow: hidden;">
            <div style="width: <?php echo min($porcentaje, 100); ?>%; height: 100%; background: <?php echo $color_barra; ?>; border-radius: 10px; transition: width 0.3s;"></div>

        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.7rem; color: #6b7280;">
            <span>📊 <?php echo $porcentaje; ?>% usado</span>

            <span>📦 <?php echo $restante_mb; ?> MB libres</span>

        </div>
    <?php else: ?>

        <div style="background: #e5e7eb; border-radius: 10px; height: 10px; overflow: hidden;">
            <div style="width: 0%; height: 100%; background: #3b82f6; border-radius: 10px;"></div>
        </div>
        <div style="margin-top: 0.5rem; font-size: 0.7rem; color: #6b7280; text-align: center;">
            👑 Los administradores no tienen límite de almacenamiento
        </div>
    <?php endif; ?>

    
    <!-- Mensaje de advertencia si está cerca o supera el límite -->
    <?php if ($limite_mb > 0): ?>

        <?php if ($usado_mb >= $limite_mb): ?>

            <div style="margin-top: 1rem; padding: 0.75rem; background: #fee2e2; border-radius: 8px; color: #991b1b; border-left: 4px solid #dc2626;">
                <strong>⚠️ ¡Has superado tu límite de almacenamiento!</strong><br>
                No puedes subir nuevos archivos. 
                <a href="<?php echo htmlspecialchars($rutaGestionAlmacenamiento, ENT_QUOTES, 'UTF-8'); ?>" style="color: #dc2626; text-decoration: underline;">

                    <?php echo htmlspecialchars($textoGestionAlmacenamiento, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                para liberar espacio.
            </div>
        <?php elseif ($porcentaje >= 80): ?>

            <div style="margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border-radius: 8px; color: #92400e; border-left: 4px solid #f59e0b;">
                <strong>⚠️ Estás cerca de tu límite de almacenamiento</strong><br>
                Has usado el <strong><?php echo $porcentaje; ?>%</strong> de tu espacio. Considera 

                <a href="<?php echo htmlspecialchars($rutaGestionAlmacenamiento, ENT_QUOTES, 'UTF-8'); ?>" style="color: #92400e; text-decoration: underline;">

                    <?php echo htmlspecialchars($textoGestionAlmacenamiento, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                si necesitas más espacio.
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
