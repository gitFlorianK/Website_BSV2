    </main>
    <footer class="site-footer">
        <div class="footer-inner">
            <p><?= escape($settings['footer_text'] ?? '') ?></p>
            <nav class="footer-nav">
                <a href="/impressum">Impressum</a>
                <a href="/datenschutz">Datenschutzerklärung</a>
            </nav>
        </div>
    </footer>
    <script>
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            const nav = document.getElementById('main-nav');
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            nav.classList.toggle('open');
        });
    </script>
</body>
</html>
