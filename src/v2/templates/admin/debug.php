<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\dto\SystemConfig;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>System Info</h4>
            </div>
            <div class="box-body">
                <li>Software Version: <?= SystemService::getInstalledVersion() ?></li>
                <li>DB Version: <?= SystemService::getDBVersion() ?></li>
                <li>RootPath: <?= SystemURLs::getRootPath() ?></li>
                <li>Valid Mail Server Settings: <?= SystemConfig::hasValidMailServerSettings() ?></li>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>DB Info</h4>
            </div>
            <div class="box-body">
                <li>RootPath: <?= SystemURLs::getRootPath() ?></li>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>Application Prerequisites</h4>
            </div>
            <div class="box-body">
                <?= print_r(AppIntegrityService::getApplicationPrerequisites()) ?>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>PHP Settings</h4>
            </div>
            <div class="box-body">
                <table class="table">
                    <tr>
                        <td>Max file upload size</td>
                        <td><?php echo ini_get('upload_max_filesize') ?></td>
                    </tr>
                    <tr>
                        <td>Max POST size</td>
                        <td><?php echo ini_get('post_max_size') ?></td>
                    </tr>
                    <tr>
                        <td>PHP Memory Limit</td>
                        <td><?php echo ini_get('memory_limit') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>


<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
