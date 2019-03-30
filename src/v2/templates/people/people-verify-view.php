<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="pull-right">
    <a class="btn btn-success" role="button" href="<?= SystemURLs::getRootPath()?>/FamilyEditor.php">
        <span class="fa fa-plus" aria-hidden="true"></span><?= gettext('Add Family') ?>
    </a>
</div>
<p><br/><br/></p>
<div class="col-lg-6">
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title"><?= gettext('Self Update') ?> <?= gettext('Reports') ?></h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
            </div>
        </div>
        <div class="box-body">
            <p>
                <a class="MediumText"
                   href="<?= SystemURLs::getRootPath()?>/members/self-verify-updates.php"><?= gettext('Self Verify Updates') ?></a><br><?= gettext('Families who commented via self verify links') ?>
            </p>
            <p>
                <a class="MediumText"
                   href="<?= SystemURLs::getRootPath()?>/members/online-pending-verify.php"><?= gettext('Pending Self Verify') ?></a><br><?= gettext('Families with valid self verify links') ?>
            </p>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
?>
