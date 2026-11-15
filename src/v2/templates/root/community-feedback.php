<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$discussionsUrl = $sDiscussionsUrl ?? 'https://github.com/ChurchCRM/CRM/discussions';

?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="row row-cards g-3" id="communityFeedbackList"
             data-discussions-url="<?= InputUtils::escapeAttribute($discussionsUrl) ?>"
             data-count-label="<?= InputUtils::escapeAttribute(gettext('Ideas waiting for your review')) ?>"
             data-vote-label="<?= InputUtils::escapeAttribute(gettext('Review and vote')) ?>"
             data-empty="<?= InputUtils::escapeAttribute(gettext('The list could not be loaded. You can still open the ideas on GitHub.')) ?>"
             data-view-all="<?= InputUtils::escapeAttribute(gettext('View all ideas')) ?>"
             data-still="">
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title"><?= gettext('How to respond') ?></h3>
                <p><?= gettext('Open an idea. Vote with 👍 if your church needs it, and add a short comment about how you work today.') ?></p>
                <p class="mb-0">
                    <a href="https://discord.gg/tuWyFzj3Nj" target="_blank" rel="noopener noreferrer"><?= gettext('No GitHub account? Tell us on Discord') ?></a>
                </p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title"><?= gettext('Register your church') ?></h3>
                <p class="text-body-secondary"><?= gettext('One minute. We do not share what you send.') ?></p>
                <a href="https://forms.gle/F1xgoBaWUD1Fy7Bn9" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
                    <?= gettext('Register Now') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
