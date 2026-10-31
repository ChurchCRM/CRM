<?php

use ChurchCRM\Service\MemberSignInListService;
use ChurchCRM\Utils\InputUtils;

/*
 * The filters and bulk tools above an "Email needs attention" table (member
 * sign-in design §11.2), on Admin → Member Portal → Members and on
 * /people/sign-in-email. Wired by webpack/sign-in-email/email-attention.ts.
 * Variable: $attentionBarHidden (bool), whether the bar starts hidden.
 */
?>
<div class="card-body border-bottom py-2<?= !empty($attentionBarHidden) ? ' d-none' : '' ?>" id="memberListProblemBar">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="<?= gettext('Email problem') ?>" id="memberListProblems">
            <button type="button" class="btn btn-sm btn-outline-secondary active" data-problem=""><?= gettext('All') ?></button>
            <?php foreach (MemberSignInListService::EMAIL_PROBLEMS as $problem) : ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-problem="<?= $problem->value ?>">
                    <?= InputUtils::escapeHTML($problem->label()) ?>
                    <span class="badge bg-secondary text-white ms-1" data-problem-count="<?= $problem->value ?>"></span>
                </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-primary ms-auto d-none" id="sharedEmailFixButton">
            <i class="fa-solid fa-people-arrows me-1"></i><?= gettext('Fix shared emails') ?>
            <span class="badge bg-white text-primary ms-1" id="sharedEmailFixCount"></span>
        </button>
    </div>
    <div class="d-none" id="unconfirmedKindBar">
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2" role="group" aria-label="<?= gettext('Kind of unconfirmed email') ?>" id="unconfirmedKinds">
            <button type="button" class="btn btn-sm btn-outline-secondary active" data-kind=""><?= gettext('Every kind') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-kind="<?= MemberSignInListService::UNCONFIRMED_MOVED ?>">
                <?= gettext('Moved from the family email') ?>
                <span class="badge bg-secondary text-white ms-1" data-kind-count="<?= MemberSignInListService::UNCONFIRMED_MOVED ?>"></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-kind="<?= MemberSignInListService::UNCONFIRMED_REPLACEMENT ?>">
                <?= gettext('Replaced after a sign-in') ?>
                <span class="badge bg-secondary text-white ms-1" data-kind-count="<?= MemberSignInListService::UNCONFIRMED_REPLACEMENT ?>"></span>
            </button>
        </div>
    </div>
    <div class="d-none" id="bulkConfirmBar">
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkConfirmSelectAll" disabled><?= gettext('Select all') ?></button>
            <button type="button" class="btn btn-sm btn-primary" id="bulkConfirmButton" disabled>
                <i class="fa-solid fa-check-double me-1"></i><?= gettext('Confirm selected') ?>
                <span class="badge bg-white text-primary ms-1" id="bulkConfirmCount">0</span>
            </button>
            <span class="text-secondary small"><?= gettext('Only addresses you may confirm can be selected.') ?></span>
        </div>
    </div>
</div>
<div class="card-body border-bottom py-2 d-none" id="emailAttentionResult" aria-live="polite"></div>
