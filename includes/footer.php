<?php
// includes/footer.php — shared layout chrome bottom.
?>
  </main>

  <footer class="srp-footer">
    <span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span>
    <span class="srp-footer-meta">v1.0 · <?= e($USER['company_name'] ?? '') ?></span>
  </footer>
</div><!-- /.srp-main -->

<!-- Toast container (used by app.js for success/error feedback instead of alert()) -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="srpToasts"></div>

<!-- Bootstrap 5 bundle (exact pinned CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- jQuery + DataTables + Buttons (DataTables requires jQuery) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Lucide icons (exclusive icon set) -->
<script src="https://unpkg.com/lucide@0.383.0/dist/umd/lucide.js"></script>

<!-- App scripts -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (!empty($PAGE_SCRIPTS)): foreach ((array)$PAGE_SCRIPTS as $s): ?>
  <script src="<?= APP_URL ?>/assets/js/modules/<?= e($s) ?>"></script>
<?php endforeach; endif; ?>

<script>
  lucide.createIcons();
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= APP_URL ?>/sw.js').catch(()=>{});
  }
</script>
</body>
</html>