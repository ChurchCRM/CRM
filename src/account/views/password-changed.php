<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Change Password');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-md-6">
        <div class="card border-top border-success border-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Password Change Successful') ?></h3>
            </div>
            <div class="card-body">
                <p><?= InputUtils::escapeHTML(sprintf(gettext('The password for %s has been updated.'), $user->getFullName())) ?></p>
                <a href="<?= SystemURLs::getRootPath() ?>/account" class="btn btn-outline-secondary me-2"><?= gettext('My Account') ?></a>
                <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="btn btn-success"><?= gettext('Go to Dashboard') ?></a>
            </div>
        </div>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
