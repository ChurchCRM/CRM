<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('Email Functions') ?></h3>
    </div>
    <div class="box-body">
        <a href="<?= SystemURLs::getRootPath()?>/email/MemberEmailExport.php" class="btn btn-app"><i class="fa fa-table"></i><?= gettext('Email Export') ?></a>
        <a href="<?= SystemURLs::getRootPath()?>/v2/email/duplicate" class="btn btn-app"><i class="fa fa-exclamation-triangle"></i><?= gettext('Find Duplicate Emails') ?></a>
        <a href="<?= SystemURLs::getRootPath()?>/v2/email/missing" class="btn btn-app"><i class="fa fa-bell-slash"></i><?= gettext('Families Without Emails') ?></a>
        <?php if (AuthenticationManager::GetCurrentUser()->isAdmin()) { ?>
        <a href="<?= SystemURLs::getRootPath()?>/v2/email/debug" class="btn btn-app"><i class="fa fa-stethoscope"></i><?= gettext('Debug') ?></a>
        <?php } ?>
    </div>
</div>

<?php if ($isMailChimpActive) { ?>
    <div class="row">
        <?php foreach ($mailChimpLists as $list) {
            ?>
            <div class="col-lg-4 col-md-2 col-sm-2">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= gettext('List') ?>: <?= $list['name'] ?></h3>
                    </div>
                    <div class="box-body">
                        <?php
                        echo "<table width='300px'>";
                        echo '<tr><td><b>' . gettext('Members:') . '</b> </td><td>' . $list['stats']['member_count'] . '</td></tr>';
                        echo '<tr><td><b>' . gettext('Campaigns:') . '</b> </td><td>' . $list['stats']['campaign_count'] . '</td></tr>';
                        echo '<tr><td><b>' . gettext('Unsubscribed count:') . '</b> </td><td>' . $list['stats']['unsubscribe_count'] . '</td></tr>';
                        echo '<tr><td><b>' . gettext('Unsubscribed count since last send:') . '</b> </td><td>' . $list['stats']['unsubscribe_count_since_send'] . '</td></tr>';
                        echo '<tr><td><b>' . gettext('Cleaned count:') . '</b> </td><td>' . $list['stats']['cleaned_count'] . '</td></tr>';
                        echo '<tr><td><b>' . gettext('Cleaned count since last send:') . '</b> </td><td>' . $list['stats']['cleaned_count_since_send'] . '</td></tr>';
                        echo '</tr></table>'; ?>
                    </div>
                </div>
            </div>
            <?php
        } ?>
    </div>
    <div class="row">
        <div class="col-lg-4 col-md-2 col-sm-2">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">MailChimp</h3>
                </div>
                <div class="box-body">
                    <ul>
                        <li>
                            <a href="<?= SystemURLs::getRootPath()?>/v2/email/missingfrommailchimp"><?= gettext('Missing emails report') ?> </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="row">
        <div class="col-lg-12 col-md-7 col-sm-3">
            <div class="box box-body">
                <div class="alert alert-warning">
                    <h4><i class="fa fa-ban"></i> MailChimp <?= gettext('is not configured') ?></h4>
                    <?= gettext('Please update the') ?> MailChimp <?= gettext('API key in Setting->') ?><a
                        href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php"><?= gettext('Edit General Settings') ?></a>,
                    <?= gettext('then update') ?> sMailChimpApiKey. <?= gettext('For more info see our ') ?><a
                        href="<?= SystemURLs::getSupportURL() ?>"> MailChimp <?= gettext('support docs.') ?></a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
