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

            <!-- Copyright -->
            <div class="row justify-content-center">
                <p class="card-header text-center">
                    &copy; <?= date('Y') ?> controlsystem &mdash; Todos los derechos reservados.
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