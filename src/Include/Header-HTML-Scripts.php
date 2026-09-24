<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

?>
<title>ChurchCRM: <?= InputUtils::escapeHTML($sPageTitle) ?></title>

<link rel="icon" href="<?= SystemURLs::getRootPath() ?>/favicon.ico" type="image/x-icon">

<!-- Custom ChurchCRM styles (includes Tabler, DataTables BS5, icons, and bridge overrides) -->
<?php
// $localeInfo is always initialised by every including header
// (Header.php, HeaderNotLoggedIn.php).
// The isset() guard is a safety net for any direct or future unknown includer.
?>
<?php if (isset($localeInfo) && $localeInfo->isRTL()): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm-rtl.min.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
<?php endif; ?>

<!-- Core ChurchCRM bundle (includes jQuery) -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>

<!-- Card Widget Handler for Bootstrap 5 -->
<script src="<?= SystemURLs::assetVersioned('/skin/js/card-widgets.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>

<?php
// Header.php still references the removed CRM_50x50.png until that file is
// switched to BrandLogo.php. Replace it client-side so the sidebar shows
// the current brand mark in both themes.
$brandRoot = SystemURLs::getRootPath();
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  (function () {
    var root = <?= InputUtils::jsonEncodeForScript($brandRoot) ?>;
    function brandSrc() {
      var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      return root + (dark
        ? '/Images/churchcrm-symbol-paper-blue.svg'
        : '/Images/churchcrm-symbol-ink-blue.svg');
    }
    function swap() {
      document.querySelectorAll('img[src*="CRM_50x50.png"]').forEach(function (img) {
        img.src = brandSrc();
        img.classList.add('crm-brand-logo');
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', swap);
    } else {
      swap();
    }
    document.addEventListener('CRM.theme.changed', swap);
  }());
</script>
