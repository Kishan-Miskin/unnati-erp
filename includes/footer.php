        </div><!-- /content-area -->
    </div><!-- /main-content -->
</div><!-- /wrapper -->

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= file_exists(APP_ROOT . '/assets/js/app.js') ? filemtime(APP_ROOT . '/assets/js/app.js') : '1' ?>"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
<script>
    // Init active nav
    document.addEventListener('DOMContentLoaded', function() {
        initSidebar();
        autoHideAlerts();
    });
</script>
</body>
</html>
