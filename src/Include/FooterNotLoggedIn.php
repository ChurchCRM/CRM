<?php

use ChurchCRM\Plugin\PluginManager;

?>

<div class="auth-footer">
  <div>
    <?php $copyrightAttribution = 'login_footer'; require __DIR__ . '/partials/copyright.php'; ?>
  </div>
  <div class="auth-footer-social">
    <?php $socialLinkSeparator = ''; require __DIR__ . '/partials/social-links.php'; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/toast-container.php'; ?>

  <?= PluginManager::getPluginFooterContent() ?>
</body>
</html>
