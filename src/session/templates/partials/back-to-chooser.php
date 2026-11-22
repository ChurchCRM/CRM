<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

if (SystemConfig::getBooleanValue('bMemberSignIn') || SystemConfig::getBooleanValue('bEnableSelfRegistration')) : ?>
<p class="form-footer justify-content-center">
  <a id="sessionChooserLink" href="<?= SystemURLs::getRootPath() ?>/session/"><?= gettext('Choose a different way to sign in') ?></a>
</p>
<?php endif; ?>
