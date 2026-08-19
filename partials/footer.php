</main>


        <footer class="footer">
            <div class="footer-grid">
                <!-- Columna 1: Información legal -->
                <div class="footer-columna">
                    <h3 class="footer-titulo">📋 Información</h3>
                    <ul class="footer-lista">
                        <li><a href="<?php echo base_url('public/contacto'); ?>">📧 Contacto</a></li>

                        <li><a href="<?php echo base_url('public/terminos'); ?>">📜 Términos</a></li>

                        <li><a href="<?php echo base_url('public/privacidad'); ?>">🔒 Privacidad</a></li>

                        <li><a href="<?php echo base_url('public/cookies'); ?>">🍪 Cookies</a></li>

                    </ul>
                </div>

                <!-- Columna 2: accesos públicos -->
                <div class="footer-columna">
                    <h3 class="footer-titulo">📰 Explora</h3>
                    <ul class="footer-lista">
                        
                        <li><a href="<?php echo route('buscar_avanzado'); ?>">🔎 Busca News</a></li>
                        <li><a href="<?php echo route('tiempo'); ?>">🌤️ El tiempo</a></li>
                        <li><a href="<?php echo route('pobreza'); ?>">📊 Pobreza Data</a></li>
                        <li><a href="<?php echo route('nasa'); ?>">🚀 NASA News</a></li>
                    </ul>
                </div>
            </div>
        </footer>
    </div>

    <!-- MENÚ LATERAL Y BOTÓN VOLVER -->
    <script src="<?php echo htmlspecialchars(js_url('site-navigation.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

    <!-- AVISO DE COOKIES -->
    <?php require_once __DIR__ . '/cookie-consent.php'; ?>

</body>
</html>
