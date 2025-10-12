<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

// Security: User must be an Admin to access this page.
AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Log File Cleanup');
require_once 'Include/Header.php';

?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Log File Management') ?></h3>
    </div>
    <div class="card-body">
        <p><?= gettext('This tool helps you manage and clean up old log files from the logs directory.') ?></p>
        <p><?= gettext('Log files can accumulate over time and consume disk space. You can safely delete old log files that are no longer needed.') ?></p>
        
        <div id="logFilesList">
            <i class="fas fa-spinner fa-spin"></i> <?= gettext('Loading log files...') ?>
        </div>
    </div>
    <div class="card-footer">
        <button type="button" class="btn btn-danger" id="deleteSelectedBtn" style="display:none;">
            <i class="fas fa-trash"></i> <?= gettext('Delete Selected Files') ?>
        </button>
        <button type="button" class="btn btn-warning" id="deleteOldBtn" style="display:none;">
            <i class="fas fa-broom"></i> <?= gettext('Delete Files Older Than 30 Days') ?>
        </button>
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/LogCleanup.js"></script>

<?php
require_once 'Include/Footer.php';
