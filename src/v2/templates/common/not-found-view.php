<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Not Found") .":" . gettext($memberType);
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="error-page">
    <h2 class="headline text-yellow">404</h2>

    <div class="error-content">
        <h3>
            <i class="fa-solid fa-triangle-exclamation text-yellow"></i>
            <?= gettext("Oops!") . ' ' . strtoupper($memberType) . ' ' . $id . ' ' . gettext("Not Found") ?>
        </h3>

        <p>
            <?= gettext("We could not find the record you were looking for.") ?>
        </p>

        <?php
        // Choose an appropriate return URL based on member type
        $lowerType = strtolower($memberType);
        if ($lowerType === 'person' || $lowerType === 'people') {
            $returnUrl = SystemURLs::getRootPath() . '/v2/people';
            $returnText = gettext('Return to People Dashboard');
        } elseif ($lowerType === 'family' || $lowerType === 'families') {
            $returnUrl = SystemURLs::getRootPath() . '/v2/families';
            $returnText = gettext('Return to Families Dashboard');
        } else {
            $returnUrl = SystemURLs::getRootPath() . '/v2/dashboard';
            $returnText = gettext('Return to Dashboard');
        }
        ?>

        <p class="mt-3">
            <a href="<?= $returnUrl ?>" class="btn btn-outline-primary" role="button" aria-label="<?= htmlspecialchars($returnText) ?>">
                <?= htmlspecialchars($returnText) ?>
            </a>
        </p>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
