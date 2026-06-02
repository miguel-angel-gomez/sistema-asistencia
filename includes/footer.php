<?php
/**
 * footer.php
 * Footer HTML reutilizable para todas las vistas de TiendaPro.
 * 
 * Uso básico:
 *   require_once __DIR__ . '/../includes/footer.php';
 * 
 * Con scripts extra:
 *   $scriptsExtra = ['js/mi-script.js'];
 *   require_once __DIR__ . '/../includes/footer.php';
 */

// Scripts adicionales opcionales (definir antes de incluir este archivo)
$scriptsExtra = $scriptsExtra ?? [];
?>

<!-- ════════════════════════ FOOTER ════════════════════════ -->
<footer class="footer-tiendapro mt-auto py-4">
    <div class="container">
        <div class="row align-items-center text-center text-md-start">

            <!-- Marca -->
            <div class="col-md-4 mb-3 mb-md-0">
                <span class="fw-bold fs-5">
                    <i class="fas fa-cube me-1 text-primary"></i>TiendaPro
                </span>
                <p class="text-muted small mb-0 mt-1">Sistema de gestión y asistencia</p>
            </div>

            <!-- Links rápidos -->
            <div class="col-md-4 mb-3 mb-md-0 text-center">
                <a href="/compras/index.php" class="text-muted small text-decoration-none me-3">
                    <i class="fas fa-home me-1"></i>Inicio
                </a>
                <a href="/compras/admin/index_admin.php" class="text-muted small text-decoration-none me-3">
                    <i class="fas fa-tachometer-alt me-1"></i>Admin
                </a>
                <a href="/compras/logout.php" class="text-muted small text-decoration-none">
                    <i class="fas fa-sign-out-alt me-1"></i>Salir
                </a>
            </div>

            <!-- Copyright -->
            <div class="col-md-4 text-md-end">
                <p class="text-muted small mb-0">
                    &copy; <?= date('Y') ?> TiendaPro &mdash; Todos los derechos reservados.
                </p>
                <p class="text-muted" style="font-size: 0.7rem;">
                    PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?> &bull; Bootstrap 5
                </p>
            </div>

        </div>
    </div>
</footer>
<!-- ════════════════════════ FIN FOOTER ════════════════════════ -->

<!-- Bootstrap JS (siempre último) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php foreach ($scriptsExtra as $script): ?>
<script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>

</body>

</html>