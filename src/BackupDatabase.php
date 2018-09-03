<?php
/*******************************************************************************
 *
 *  filename    : BackupDatabase.php
 *  last change : 2016-01-04
 *  description : Creates a backup file of the database.
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['user']->isAdmin()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}


// Set the page title and include HTML header
$sPageTitle = gettext('Backup Database');
require 'Include/Header.php';

?>
<div class="box">
    <div class="box-header">
        <h3 class="box-title"><?= gettext('This tool will assist you in manually backing up the ChurchCRM database.') ?></h3>
    </div>
    <div class="box-body">
        <ul>
        <li><?= gettext('You should make a manual backup at least once a week unless you already have a regular backup procedule for your systems.') ?></li><br>
        <li><?= gettext('After you download the backup file, you should make two copies. Put one of them in a fire-proof safe on-site and the other in a safe location off-site.') ?></li><br>
        <li><?= gettext('If you are concerned about confidentiality of data stored in the ChurchCRM database, you should encrypt the backup data if it will be stored somewhere potentially accessible to others') ?></li><br>
        <li><?= gettext('For added backup security, you can e-mail the backup to yourself at an e-mail account hosted off-site or to a trusted friend.  Be sure to use encryption if you do this, however.') ?></li>
        </ul>
        <BR><BR>
        <form method="post" action="<?= sRootPath ?>/api/database/backup" id="BackupDatabase">
        <?= gettext('Select archive type') ?>:
        <input type="radio" name="archiveType" value="2" checked><?= gettext('Database Only (.sql)') ?>
        <input type="radio" name="archiveType" value="3" checked><?= gettext('Database and Photos (.tar.gz)') ?>
        <BR><BR>
        <input type="checkbox" name="encryptBackup" value="1"><?= gettext('Encrypt backup file with a password?') ?>
        &nbsp;&nbsp;&nbsp;
        <?= gettext('Password') ?>:<input type="password" name="pw1">
        <?= gettext('Re-type Password') ?>:<input type="password" name="pw2">
        <BR><span id="passworderror" style="color: red"></span><BR><BR>

        <input type="button" class="btn btn-primary" id="doBackup" <?= 'value="'.gettext('Generate and Download Backup').'"' ?>>
        <input type="button" class="btn btn-primary" id="doRemoteBackup" <?= 'value="'.gettext('Generate and Ship Backup to External Storage').'"' ?>>

        </form>
    </div>
</div>
<div class="box">
    <div class="box-header">
        <h3 class="box-title"><?= gettext('Backup Status:') ?> </h3>&nbsp;<h3 class="box-title" id="backupstatus" style="color:red"> <?= gettext('No Backup Running') ?></h3>
    </div>
     <div class="box-body" id="resultFiles">
     </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/BackupDatabase.js"></script>

<?php require 'Include/Footer.php' ?>
