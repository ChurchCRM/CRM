<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$discussionsUrl = $sDiscussionsUrl ?? 'https://github.com/ChurchCRM/CRM/discussions';
$feedbackItems = $feedbackItems ?? [];

?>

<div class="row row-cards mb-3 g-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-comments me-2"></i><?= gettext('Why your feedback matters') ?></h3>
            </div>
            <div class="card-body">
                <p><?= gettext('ChurchCRM is built by a small team, and the churches using it every day know better than anyone what should be built next.') ?></p>
                <p><?= gettext('Every vote and comment on the topics below is read by the maintainers. The topics with the most support become the next work we take on.') ?></p>
                <p class="mb-0"><?= gettext('Votes are advisory: they show what churches need most, and the maintainers set the final schedule.') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row row-cards mb-3 g-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-hand-point-up me-2"></i><?= gettext('How to take part') ?></h3>
            </div>
            <div class="card-body">
                <ol class="mb-3">
                    <li><?= gettext('Open a topic below that matters to your church.') ?></li>
                    <li><?= gettext('React with 👍 on the Discussion to vote for it.') ?></li>
                    <li><?= gettext('Add a comment explaining how your church does this work today — that real-world detail shapes the design.') ?></li>
                </ol>
                <p class="mb-0">
                    <?= gettext('No GitHub account?') ?>
                    <a href="https://discord.gg/tuWyFzj3Nj" target="_blank" rel="noopener noreferrer"><?= gettext('Join us on Discord') ?></a>
                    <?= gettext('and share your feedback there — it is carried back to the maintainers.') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row row-cards g-2">
    <div class="col-12">
        <?php if (!empty($feedbackItems)) { ?>
        <h3 class="mb-3"><?= sprintf(gettext('%d open topics need your feedback'), count($feedbackItems)) ?></h3>
        <?php foreach ($feedbackItems as $feedbackItem) { ?>
        <div class="card mb-3">
            <div class="card-body">
                <?php if (!empty($feedbackItem['category'])) { ?>
                <span class="badge bg-primary mb-2"><?= InputUtils::escapeHTML($feedbackItem['category']) ?></span>
                <?php } ?>
                <h4 class="card-title"><?= InputUtils::escapeHTML($feedbackItem['title']) ?></h4>
                <p class="text-body-secondary"><?= InputUtils::escapeHTML($feedbackItem['summary']) ?></p>
                <a href="<?= InputUtils::escapeAttribute($feedbackItem['discussionUrl']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                    <i class="fa-solid fa-comments me-2"></i><?= gettext('Vote & discuss') ?>
                </a>
            </div>
        </div>
        <?php } ?>
        <?php } else { ?>
        <div class="card">
            <div class="card-body">
                <p><?= gettext('The current list of topics could not be loaded — this install may be offline, or a new list is still being published.') ?></p>
                <p class="mb-0"><?= gettext('You can still see and join every open topic on GitHub:') ?></p>
                <p class="mt-3 mb-0">
                    <a href="<?= InputUtils::escapeAttribute($discussionsUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                        <i class="fa-brands fa-github me-2"></i><?= gettext('View all topics') ?>
                    </a>
                </p>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
