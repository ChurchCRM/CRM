<?php

/**
 * Signup Sheets — shown when a share or manage link does not resolve.
 *
 * Deliberately vague: it never distinguishes "no such sheet" from "sheet is no
 * longer public", so the page cannot be used to probe for valid tokens.
 *
 * @var string $message
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>

<div class="container py-5 signup-unavailable">
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-clipboard-question fa-3x text-body-secondary mb-3"></i>
            <h1 class="h4"><?= gettext('This signup link is not available') ?></h1>
            <p class="text-body-secondary mb-0"><?= InputUtils::escapeHTML($message) ?></p>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
