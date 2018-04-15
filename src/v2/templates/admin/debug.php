<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\SystemService;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= gettext("System Information") ?></h4>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <td>ChurchCRM <?= gettext("Software Version") ?></td>
                        <td><?= SystemService::getInstalledVersion() ?></td>
                    </tr>
                    <tr>
                        <td>RootPath</td>
                        <td><?= SystemURLs::getRootPath() ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext("Valid Mail Server Settings") ?></td>
                        <td><?= SystemConfig::hasValidMailServerSettings() ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= gettext("Database") ?></h4>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <td>ChurchCRM <?= gettext("Database Version") ?></td>
                        <td><?= SystemService::getDBVersion() ?></td>
                    </tr>
                    <tr>
                        <td>MySQL <?= gettext("Database Version") ?></td>
                        <td><?= SystemService::getDBServerVersion() ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4>PHP</h4>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <td>PHP Version</td>
                        <td><?= PHP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td>Max file upload size</td>
                        <td><?= ini_get('upload_max_filesize') ?></td>
                    </tr>
                    <tr>
                        <td>Max POST size</td>
                        <td><?= ini_get('post_max_size') ?></td>
                    </tr>
                    <tr>
                        <td>PHP Memory Limit</td>
                        <td><?= ini_get('memory_limit') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= gettext("Application Prerequisites") ?></h4>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <?php foreach (AppIntegrityService::getApplicationPrerequisites() as $prerequisite => $status) { ?>
                        <tr>
                            <td><?= $prerequisite ?></td>
                            <td><?= $status ? "true" : "false" ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= gettext("WebServer Modules") ?></h4>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <?php
                    if (function_exists('apache_get_modules')) {
                        foreach (apache_get_modules() as $item) { ?>
                            <tr>
                                <td><?= $item ?></td>
                            </tr>
                        <?php }
                    } ?>
                </table>
            </div>
        </div>
    </div>
</div>


<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
