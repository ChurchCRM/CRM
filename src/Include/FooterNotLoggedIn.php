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

  <?= PluginManager::getPluginFooterContent() ?>
</body>
</html>
