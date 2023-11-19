<?php

// Include the function library
require 'Include/Config.php';
$bSuppressSessionTests = true;
require 'Include/Functions.php';
require_once 'Include/Header-function.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Utils\RedirectUtils;

// Set the page title and include HTML header
$sPageTitle = gettext('Upgrade ChurchCRM');

if (!AuthenticationManager::validateUserSessionIsActive(false) || !AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::redirect('index.php');
    exit;
}
$expertMode = false;
if (isset($_GET['expertmode'])) {
    $expertMode = true;
}

require 'Include/HeaderNotLoggedIn.php';
Header_modals();
Header_body_scripts();

?>
<div class="col-lg-8 col-lg-offset-2" style="margin-top: 10px">
  <div class="timeline">
      <div class="time-label">
          <span class="bg-red"><?= gettext('Upgrade ChurchCRM') ?></span>
      </div>

    <?php
     $taskService = new TaskService();
     $preUpgradeTasks = $taskService->getActivePreUpgradeTasks();
    if (count($preUpgradeTasks) > 0) {
        ?>
    <div>
      <i class="fa fa-bomb bg-red"></i>
      <div class="timeline-item" >
        <h3 class="timeline-header"><?= gettext('Warning: Pre-Upgrade Tasks Detected') ?> <span id="status1"></span></h3>
        <div class="timeline-body" id="preUpgradeCheckWarning">
          <p><?= gettext("Some conditions have been identified which may prevent a successful upgrade")?></b></p>
          <p><?= gettext("Please review and mitigate these tasks before continuing with the upgrade:")?></p>
          <div>
            <ul>
            <?php
            foreach ($preUpgradeTasks as $preUpgradeTask) {
                ?>
                    <li><?= $preUpgradeTask->getTitle() ?>: <?= $preUpgradeTask->getDesc()?></li>
                  <?php
            } ?>

            </ul>

          </div>
          <p></p>
          <input type="button" class="btn btn-primary" id="acceptUpgradeTaskWarking" <?= 'value="' . gettext('I Understand') . '"' ?>>
        </div>
      </div>
    </div>
        <?php
    }
    ?>
    <?php
    if (AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed")) {
        ?>
    <div>
      <i class="fa fa-bomb bg-red"></i>
      <div class="timeline-item" >
        <h3 class="timeline-header"><?= gettext('Warning: Signature mismatch') ?> <span id="status1"></span></h3>
        <div class="timeline-body" id="integrityCheckWarning" <?= count($preUpgradeTasks) > 0 ? 'style="display:none"' : '' ?>>
          <p><?= gettext("Some ChurchCRM system files may have been modified since the last installation.")?><b><?= gettext("This upgrade will completely destroy any customizations made to the following files by reverting the files to the official version.")?></b></p>
          <p><?= gettext("If you wish to maintain your changes to these files, please take a manual backup of these files before proceeding with this upgrade, and then manually restore the files after the upgrade is complete.")?></p>
          <div>
              <p><?= gettext('Integrity Check Details:')?> <?=  AppIntegrityService::getIntegrityCheckMessage() ?></p>
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
                            if ($file->status === 'File Missing') {
                                echo gettext('File Missing');
                            } else {
                                echo $file->actualhash;
                            } ?>
                      </td>
                    </tr>
                            <?php
                        } ?>
                    </table>
                    <?php
                } ?>
            </div>
          <input type="button" class="btn btn-primary" id="acceptIntegrityCheckWarking" <?= 'value="' . gettext('I Understand') . '"' ?>>
        </div>
      </div>
    </div>
        <?php
    }
    ?>
    <div>
      <i class="fa fa-database bg-blue"></i>
      <div class="timeline-item" >
        <h3 class="timeline-header"><?= gettext('Step 1: Backup Database') ?> <span id="status1"></span></h3>
        <div class="timeline-body" id="backupPhase" <?= (AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed") || count($preUpgradeTasks) > 0) ? 'style="display:none"' : '' ?>>
          <p><?= gettext('Please create a database backup before beginning the upgrade process.')?></p>
          <input type="button" class="btn btn-primary" id="doBackup" <?= 'value="' . gettext('Generate Database Backup') . '"' ?>>
          <span id="backupStatus"></span>
          <div id="resultFiles" style="margin-top:10px">
          </div>
        </div>
      </div>
    </div>
    <div>
      <i class="fa fa-cloud-download bg-blue"></i>
      <div class="timeline-item" >
        <h3 class="timeline-header"><?= gettext('Step 2: Fetch Update Package on Server') ?> <span id="status2"></span></h3>
        <div class="timeline-body" id="fetchPhase" <?= $expertMode ? '' : 'style="display: none"' ?>>
          <p><?= gettext('Fetch the latest files from the ChurchCRM GitHub release page')?></p>
          <input type="button" class="btn btn-primary" id="fetchUpdate" <?= 'value="' . gettext('Fetch Update Files') . '"' ?> >
        </div>
      </div>
    </div>
    <div>
      <i class="fa fa-cogs bg-blue"></i>
      <div class="timeline-item" >
        <h3 class="timeline-header"><?= gettext('Step 3: Apply Update Package on Server') ?> <span id="status3"></span></h3>
        <div class="timeline-body" id="updatePhase" <?= $expertMode ? '' : 'style="display: none"' ?>>
          <p><?= gettext('Extract the upgrade archive, and apply the new files')?></p>
          <h4><?= gettext('Release Notes') ?></h4>
          <pre id="releaseNotes"></pre>
          <ul>
            <li><?= gettext('File Name:')?> <span id="updateFileName"> </span></li>
            <li><?= gettext('Full Path:')?> <span id="updateFullPath"> </span></li>
            <li><?= gettext('SHA1:')?> <span id="updateSHA1"> </span></li>
          </ul>
          <br/>
          <input type="button" class="btn btn-warning" id="applyUpdate" value="<?= gettext('Upgrade System') ?>">
        </div>
      </div>
    </div>
    <div>
      <i class="fa fa-sign-in bg-blue"></i>
      <div class="timeline-item" >
        <h3 class="timeline-header"><?= gettext('Step 4: Login') ?></h3>
        <div class="timeline-body" id="finalPhase" <?= $expertMode ? '' : 'style="display: none"' ?>>
          <a href="Logoff.php" class="btn btn-primary"><?= gettext('Login to Upgraded System') ?> </a>
        </div>
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

    $("#acceptUpgradeTaskWarking").click(function() {
      $("#preUpgradeCheckWarning").slideUp();
      $("#<?= AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed") ? "integrityCheckWarning" : "backupPhase" ?>").show("slow");
    });

    $("#acceptIntegrityCheckWarking").click(function() {
      $("#integrityCheckWarning").slideUp();
      $("#backupPhase").show("slow");
    });
  });

 $("#doBackup").click(function(){
   $("#status1").html('<i class="fa fa-circle-notch fa-spin"></i>');
   window.CRM.APIRequest({
      method : 'POST',
      path : 'database/backup',
      data : JSON.stringify({
        'BackupType' : 3
      })
    })
    .done(function(data) {
      var downloadButton = "<button class=\"btn btn-primary\" id=\"downloadbutton\" role=\"button\" onclick=\"javascript:downloadbutton('"+data.BackupDownloadFileName+"')\"><i class='fa fa-download'></i>  "+data.BackupDownloadFileName+"</button>";
      $("#backupstatus").css("color","green");
      $("#backupstatus").html("<?= gettext('Backup Complete, Ready for Download.') ?>");
      $("#resultFiles").html(downloadButton);
      $("#status1").html('<i class="fa fa-check" style="color:orange"></i>');
      $("#downloadbutton").click(function(){
        $("#fetchPhase").show("slow");
        $("#backupPhase").slideUp();
        $("#status1").html('<i class="fa fa-check" style="color:green"></i>');
      });
    }).fail(function()  {
      $("#backupstatus").css("color","red");
      $("#backupstatus").html("<?= gettext('Backup Error.') ?>");
    });

 });

 $("#fetchUpdate").click(function(){
    $("#status2").html('<i class="fa fa-circle-notch fa-spin"></i>');
    window.CRM.APIRequest({
      type : 'GET',
      path  : 'systemupgrade/downloadlatestrelease',
    }).done(function(data){
      $("#status2").html('<i class="fa fa-check" style="color:green"></i>');
      window.CRM.updateFile=data;
      $("#updateFileName").text(data.fileName);
      $("#updateFullPath").text(data.fullPath);
      $("#releaseNotes").text(data.releaseNotes);
      $("#updateSHA1").text(data.sha1);
      $("#fetchPhase").slideUp();
      $("#updatePhase").show("slow");
    });

 });

 $("#applyUpdate").click(function(){
   $("#status3").html('<i class="fa fa-circle-notch fa-spin"></i>');
   window.CRM.APIRequest({
      method : 'POST',
      path : 'systemupgrade/doupgrade',
      data : JSON.stringify({
        fullPath: window.CRM.updateFile.fullPath,
        sha1: window.CRM.updateFile.sha1
      })
    }).done(function(data){
      $("#status3").html('<i class="fa fa-check" style="color:green"></i>');
      $("#updatePhase").slideUp();
      $("#finalPhase").show("slow");
    });
 });

function downloadbutton(filename) {
    window.location = window.CRM.root +"/api/database/download/"+filename;
    $("#backupstatus").css("color","green");
    $("#backupstatus").html("<?= gettext('Backup Downloaded, Copy on server removed') ?>");
    $("#downloadbutton").attr("disabled","true");
}
</script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/datatables/pdfmake.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/datatables/vfs_fonts.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/datatables/datatables.min.js"></script>

<?php
// Add the page footer
require 'Include/FooterNotLoggedIn.php';

// Turn OFF output buffering
ob_end_flush();
?>
