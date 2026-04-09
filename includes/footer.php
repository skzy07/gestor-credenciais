</div><!-- /container -->

<div class="toast-container" id="toast-container"></div>

<script src="<?= APP_URL ?>/assets/js/crypto.js?v=<?= filemtime(__DIR__ . '/../assets/js/crypto.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php if (isset($extraScripts)): foreach ($extraScripts as $s): ?>
<script src="<?= APP_URL . '/assets/js/' . $s . '?v=' . filemtime(__DIR__ . '/../assets/js/' . $s) ?>"></script>
<?php endforeach; endif; ?>
<?php if (isset($inlineScript)): ?>
<script>
<?= $inlineScript ?>
</script>
<?php endif; ?>
</div><!-- /app-wrapper -->
</body>
</html>
