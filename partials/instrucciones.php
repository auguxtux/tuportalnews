<?php
declare(strict_types=1);

/**
 * Guía rápida adaptada al nombre visible de cada perfil.
 * Los valores internos de rol se mantienen: admin, periodista y usuario.
 */

$rol = $_SESSION['usuario_rol'] ?? 'usuario';
$es_privado = false;

if ($rol === 'periodista' && isset($_SESSION['usuario_id'])) {
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM usuarios_privados WHERE id_usuario = ? AND activo = 1'
        );
        $stmt->execute([(int) $_SESSION['usuario_id']]);
        $es_privado = $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        registrarErrorInterno('PARTIAL.INSTRUCCIONES.PERFIL', $e);
    }
}

$perfilVisible = match (true) {
    $rol === 'admin' => 'Admin',
    $rol === 'periodista' && $es_privado => 'Colaborador',
    $rol === 'periodista' => 'Articulista',
    default => 'Comentarista',
};
?>

<div class="instr-card-instrucciones">
    <div class="instr-card-header">
        <h3><span class="instr-icono">📖</span> Guía del perfil: <?php echo htmlspecialchars($perfilVisible, ENT_QUOTES, 'UTF-8'); ?></h3>
        <button class="instr-toggle-instrucciones" onclick="toggleInstrucciones()">📘 Mostrar / ocultar</button>
    </div>

    <div class="instr-card-body instr-instrucciones-contenido" style="display: none;">
        <div class="instr-grid-instrucciones">
            <?php if ($rol === 'admin'): ?>
                <div class="instr-bloque">
                    <h4>👥 Cuentas y permisos</h4>
                    <ul>
                        <li>🧭 Usa el navegador superior del panel para ir al resumen, actividad, gestión o herramientas.</li>
                        <li>✅ Gestiona Comentaristas, Articulistas y Admins.</li>
                        <li>🔒 Concede o retira el permiso de Colaborador.</li>
                        <li>📧 Asigna a cada Colaborador su correo corporativo <code>@erun.es</code>.</li>
                        <li>⏸️ Desactivar conserva el contenido; eliminar definitivamente borra su actividad.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>📰 Edición y moderación</h4>
                    <ul>
                        <li>📝 Gestiona noticias públicas y privadas, categorías, fuentes y relaciones.</li>
                        <li>📍 Comprueba que toda noticia tenga categoría, fuente, lugar e imagen principal antes de publicarla.</li>
                        <li>🏷️ Categorías y Fuentes comparten una tabla administrativa y navegación directa entre ambas.</li>
                        <li>⚠️ Una categoría o fuente con noticias asociadas no puede eliminarse; desactivarla conserva el contenido.</li>
                        <li>💬 Modera comentarios, reportes y mensajes de contacto.</li>
                        <li>🚩 Confirma reportes válidos para mostrar públicamente solo su número y motivo.</li>
                        <li>📡 Activa, desactiva y supervisa las fuentes RSS compartidas.</li>
                        <li>📍 Asigna una región (comunidad autónoma) a cada fuente RSS para clasificación automática.</li>
                        <li>🏷️ Revisa la clasificación temática automática de las noticias importadas.</li>
                        <li>🚀 Consulta NASA y usa su multimedia oficial en noticias públicas o privadas.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>🛡️ Administración técnica</h4>
                    <ul>
                        <li>⚙️ Configura registro, moderación de comentarios, cuotas, mantenimiento y minificación.</li>
                        <li>👤 “Permitir registro” habilita Comentaristas; el registro de Articulistas tiene su propio ajuste.</li>
                        <li>💾 Crea, descarga y restaura backups; se conservan los 5 últimos.</li>
                        <li>🟢 Consulta usuarios conectados y filtra su actividad por rol.</li>
                        <li>🚨 Revisa ataques, sesiones, conexiones, errores y diagnóstico.</li>
                        <li>🔐 Login protegido contra fuerza bruta con bloqueo automático por IP.</li>
                        <li>🛡️ Escape de salida en todos los formularios para prevenir XSS.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>🌐 Comprobación</h4>
                    <ul>
                        <li>🔍 Comprueba portada, categorías, fuentes, lugares, buscadores, RSS y meteorología.</li>
                        <li>📚 Consulta la documentación antes de migrar o desplegar.</li>
                        <li>🧪 Verifica los cambios en desarrollo antes de producción.</li>
                    </ul>
                </div>
            <?php elseif ($rol === 'periodista' && $es_privado): ?>
                <div class="instr-bloque">
                    <h4>🔒 Contenido privado</h4>
                    <ul>
                        <li>🧭 El navegador ámbar reúne creación, noticias, buscadores y regreso al panel público.</li>
                        <li>👁️ Busca y consulta noticias privadas de todos los Colaboradores.</li>
                        <li>➕ Crea, edita y elimina únicamente tus noticias privadas.</li>
                        <li>💬 Comenta, valora, relaciona y reporta contenido privado.</li>
                        <li>📍 Asigna región y tema a tus noticias RSS importadas.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>🌐 Contenido público</h4>
                    <ul>
                        <li>✍️ Conservas todas las funciones públicas de un Articulista.</li>
                        <li>📡 Administra tus fuentes RSS y usa las fuentes activas compartidas.</li>
                        <li>📍 Al importar varias noticias RSS, asigna una ubicación común y edita después las excepciones.</li>
                        <li>🖼️ Imágenes, vídeos y contenido del editor se validan al guardar.</li>
                        <li>🚀 Selecciona multimedia NASA y decide si la imagen o el vídeo aparece primero.</li>
                        <li>🏷️ El tema se detecta automáticamente al importar; revisa y ajusta si es necesario.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>💬 Comunidad</h4>
                    <ul>
                        <li>💬 Consulta y administra tus propios comentarios.</li>
                        <li>📥 Revisa los comentarios recibidos agrupados por cada noticia pública.</li>
                        <li>⭐ Guarda, valora y reporta noticias públicas.</li>
                        <li>🔐 El contenido privado nunca se muestra en páginas públicas.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>💾 Cuenta y espacio</h4>
                    <ul>
                        <li>📊 Consulta tu cuota y elimina hasta 10 noticias seleccionadas.</li>
                        <li>👤 Actualiza perfil, avatar y contraseña desde el área de Articulista.</li>
                        <li>📧 Consulta tu correo corporativo y abre el webmail desde el panel privado.</li>
                        <li>⚠️ Al eliminar la cuenta se borra tu contenido; tus fuentes RSS pasan al Admin.</li>
                    </ul>
                </div>
            <?php elseif ($rol === 'periodista'): ?>
                <div class="instr-bloque">
                    <h4>✍️ Noticias públicas</h4>
                    <ul>
                        <li>🧭 El navegador verde da acceso directo a noticias, creación, RSS, comentarios y perfil.</li>
                        <li>➕ Crea, edita y elimina únicamente tus propias noticias.</li>
                        <li>🧭 Asigna título, categoría, fuente, ubicación y contenido.</li>
                        <li>🖼️ La imagen principal es obligatoria y se optimiza automáticamente.</li>
                        <li>🚀 Añade imágenes o vídeos NASA sin consumir tu espacio y conserva su atribución.</li>
                        <li>📍 Selecciona la región (comunidad autónoma) de la noticia.</li>
                        <li>🏷️ El tema se asigna automáticamente según el contenido.</li>
                        <li>🎬 Puedes mostrar primero el vídeo; la imagen seguirá siendo la portada de las tarjetas.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>📡 Fuentes RSS</h4>
                    <ul>
                        <li>➕ Añade y administra tus fuentes.</li>
                        <li>🌐 Usa también las fuentes activas compartidas por otros Articulistas.</li>
                        <li>📥 Selecciona manualmente cada noticia y su categoría; no se importan duplicados.</li>
                        <li>📍 Asigna una ubicación común al lote importado y ajusta cada noticia posteriormente si lo necesita.</li>
                        <li>📍 Cada fuente RSS tiene una región asignada; las noticias heredan esa región automáticamente.</li>
                        <li>🏷️ El sistema detecta el tema de cada noticia por palabras clave del título y extracto.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>📊 Participación</h4>
                    <ul>
                        <li>👀 Consulta visitas, comentarios y valoraciones de tus noticias.</li>
                        <li>📥 Usa “Comentarios recibidos” para consultar los comentarios agrupados por noticia.</li>
                        <li>💬 Administra tus comentarios y reporta contenido inapropiado.</li>
                        <li>⭐ Guarda y valora noticias como cualquier usuario registrado.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>💾 Cuenta y espacio</h4>
                    <ul>
                        <li>📊 Consulta tu cuota y elimina hasta 10 noticias seleccionadas.</li>
                        <li>👤 Actualiza perfil, avatar y contraseña.</li>
                        <li>⚠️ Al eliminar la cuenta se borra tu contenido; tus fuentes RSS pasan al Admin.</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="instr-bloque">
                    <h4>💬 Participar</h4>
                    <ul>
                        <li>🧭 El navegador azul reúne tu actividad, comentarios, favoritas, perfil y buscador.</li>
                        <li>📝 Comenta noticias públicas y edita o elimina tus comentarios.</li>
                        <li>⭐ Guarda noticias en “Mis Favoritas”.</li>
                        <li>📊 Valora noticias y distingue votos registrados de visitantes.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>🔍 Consultar</h4>
                    <ul>
                        <li>📰 Busca por texto, categoría, fuente, ubicación y fecha.</li>
                        <li>📍 En “Noticias por lugares” puedes consultar provincias, destinos internacionales y otras ubicaciones.</li>
                        <li>🔗 Consulta noticias relacionadas, galerías y valoraciones.</li>
                        <li>🌦️ Utiliza la predicción meteorológica y tu ubicación si la autorizas.</li>
                        <li>🚀 Explora imágenes, vídeos, proyectos y misiones en el catálogo NASA.</li>
                        <li>📍 Filtra noticias por región (comunidad autónoma) y categoría.</li>
                        <li>🏷️ Las noticias muestran etiquetas visuales de región y tema.</li>
                    </ul>
                </div>
                <div class="instr-bloque">
                    <h4>🛡️ Convivencia y cuenta</h4>
                    <ul>
                        <li>🚩 Reporta noticias o comentarios inapropiados indicando el motivo.</li>
                        <li>👤 Gestiona perfil, avatar y contraseña; usa recuperación si la olvidas.</li>
                        <li>⚠️ Eliminar la cuenta borra definitivamente tus datos y actividad.</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo css_url('instrucciones.css'); ?>">

<script>
function toggleInstrucciones() {
    const contenido = document.querySelector('.instr-instrucciones-contenido');
    if (!contenido) {
        return;
    }

    const estaOculto = contenido.style.display === 'none'
        || contenido.style.display === '';
    contenido.style.display = estaOculto ? 'block' : 'none';
}
</script>
