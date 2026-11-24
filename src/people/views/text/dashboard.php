<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Vonage Integration Status -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-plug me-2"></i><?= gettext('SMS Integration') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($vonageConfigured): ?>
        <div class="alert alert-success mb-0">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-check me-2 fs-3"></i>
                <div>
                    <h4 class="alert-title mb-0"><?= gettext('Vonage SMS Connected') ?></h4>
                    <div class="text-secondary"><?= gettext('SMS messages can be sent directly from ChurchCRM via the Vonage API.') ?></div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <a href="<?= SystemURLs::getRootPath() ?>/plugins/management#plugin-vonage" class="btn btn-outline-success ms-auto">
                    <i class="fa-solid fa-gear me-1"></i><?= gettext('Plugin Settings') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mb-0">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation me-2 fs-3"></i>
                <div>
                    <h4 class="alert-title mb-0"><?= gettext('Vonage SMS Not Configured') ?></h4>
                    <div class="text-secondary"><?= gettext('Without Vonage, text actions will open your device\'s native SMS app or copy phone numbers to clipboard.') ?></div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <a href="<?= SystemURLs::getRootPath() ?>/plugins/management#plugin-vonage" class="btn btn-outline-warning ms-auto">
                    <i class="fa-solid fa-gear me-1"></i><?= gettext('Configure Vonage') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Text Tools Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-comment-sms me-2"></i><?= gettext('Text Tools') ?></h3>
    </div>
    <div class="card-body">
        <div class="text-secondary">
            <?= gettext('Text messaging actions are available from Group and Sunday School class views. Use the "Text" dropdown to copy phone numbers or send SMS messages to group members.') ?>
        </div>
    </div>
</div>


<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
