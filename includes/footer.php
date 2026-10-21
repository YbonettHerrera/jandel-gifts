</main>
<!-- FIN CONTENIDO PRINCIPAL -->

<!-- FOOTER -->
<footer class="py-4 mt-5" style="background-color: #1a1a2e; color: #fff;">
    <div class="container text-center">
        <p class="mb-1">
            <strong>🎁 <?= NOMBRE_NEGOCIO ?></strong>
        </p>
        <p class="mb-1 small">
            <i class="bi bi-geo-alt"></i> 
            Funza, Mosquera y Madrid — Cundinamarca
        </p>
        <p class="mb-1 small">
            <i class="bi bi-whatsapp"></i> WhatsApp &nbsp;|&nbsp;
            <i class="bi bi-instagram"></i> Instagram &nbsp;|&nbsp;
            <i class="bi bi-facebook"></i> Facebook
        </p>
        <hr style="border-color: #444;">
        <p class="mb-0 small text-secondary">
            © <?= date('Y') ?> <?= NOMBRE_NEGOCIO ?> — Todos los derechos reservados
        </p>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- JS propio -->
<script src="<?= $base_url ?? '/' ?>assets/js/main.js"></script>
</body>
</html>