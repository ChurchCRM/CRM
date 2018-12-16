<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\SystemService;

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
                        <td><?= SystemConfig::hasValidMailServerSettings() ? "true" : "false" ?></td>
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
                    <tr>
                        <td>PHP Max Exec</td>
                        <td><?= ini_get('max_execution_time') ?></td>
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
                    <?php foreach (AppIntegrityService::getApplicationPrerequisites() as $prerequisite) { ?>
                        <tr>
                          <td><a href='<?=$prerequisite->GetWikiLink()?>'><?= $prerequisite->getName()?></a></td>
                          <td><?= $prerequisite->GetStatusText()?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
              <h4><?= gettext("Application Integrity Check") . ": " . AppIntegrityService::getIntegrityCheckStatus()?></h4>
            </div>
            <div class="box-body">
              <p><?= gettext('Details:')?> <?=  AppIntegrityService::getIntegrityCheckMessage() ?></p>
                <?php
                  if (count(AppIntegrityService::getFilesFailingIntegrityCheck()) > 0) {
                      ?>
                    <p><?= gettext('Files failing integrity check') ?>:
                    <table class="display responsive no-wrap" width="100%" id="fileIntegrityCheckResultsTable">
                      <thead>
                      <td>FileName</td>
                      <td>Expected Hash</td>
                      <td>Actual Hash</td>
                    </thead>
                      <?php
                      foreach (AppIntegrityService::getFilesFailingIntegrityCheck() as $file) {
                          ?>
                    <tr>
                      <td><?= $file->filename ?></td>
                      <td><?= $file->expectedhash ?></td>
                      <td>
                          <?php
                          if ($file->status == 'File Missing') {
                           echo gettext('File Missing');
                          }
                          else {
                          echo $file->actualhash;
                        }?>
                      </td>
                    </tr>
                        <?php
                      }
                      ?>
                    </table>
                    <?php
                  }   
                ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= gettext("WebServer Modules") ?></h4>
            </div>
            <div class="box-body" style="overflow: scroll-x">
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $(document).ready(function() { 
  $("#fileIntegrityCheckResultsTable").DataTable({
    responsive: true,
    paging:false,
    searching: false
  });
  
  });
  
</script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
