<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("Not Found") . ": " . gettext($memberType);
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="error-page">
    <h2 class="headline text-yellow">404</h2>

    <div class="error-content">
        <h3><i class="fa fa-warning text-yellow"></i> <?= gettext("Oops!") . " " . strtoupper($memberType) . " " . $id . " " . gettext("Not Found") ?></h3>

        <p>
            <?= gettext("We could not find the person(s) you were looking for.") ?>
            <?= gettext("Meanwhile, you may")?> <a href="<?= SystemURLs::getRootPath() ?>../../index.php"> <?= gettext("return to People Dashboard") ?></a>
        </p>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php';  ?>
