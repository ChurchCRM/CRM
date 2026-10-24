<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/*
 * Admin → Member Portal → Members (member sign-in design §11.2, A7, §10.1).
 * Variables: $memberLists (the list choices, built by the route).
 * Every list is read a page at a time by webpack/admin-member-portal/members-tab.ts.
 */
?>
<div class="tab-pane fade" id="portal-members" role="tabpanel">
    <p class="text-secondary" id="memberListsLead">
        <?= gettext('Who may sign in to the Member Portal, who has, and whose email stops them. The lists follow the rules on the Sign-in tab.') ?>
    </p>
    <div class="row g-3">
        <div class="col-12 col-lg-4 col-xl-3">
            <div class="list-group" id="memberListPicker" role="tablist" aria-orientation="vertical">
                <?php foreach ($memberLists as $index => $list) : ?>
                    <button type="button" role="tab"
                            class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2<?= $index === 0 ? ' active' : '' ?>"
                            id="memberList-<?= $list['id'] ?>" data-list="<?= $list['id'] ?>"
                            aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                        <span><i class="fa-solid <?= $list['icon'] ?> me-2"></i><?= InputUtils::escapeHTML($list['label']) ?></span>
                        <span class="badge bg-secondary text-white" data-count-for="<?= $list['id'] ?>">&hellip;</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-12 col-lg-8 col-xl-9">
            <div class="card" id="memberListCard">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0" id="memberListTitle"></h3>
                        <div class="text-secondary small mt-1" id="memberListDescription"></div>
                    </div>
                </div>
                <?php
                $attentionBarHidden = true;
                require SystemURLs::getDocumentRoot() . '/people/views/partials/email-attention-toolbar.php';
                ?>
                <div class="card-body border-bottom py-2 d-none" id="memberListMoveCounts">
                    <div class="d-flex flex-wrap gap-3 small">
                        <span><?= gettext('Moved to a head of household') ?>: <strong data-move-count="head">0</strong></span>
                        <span><?= gettext('Moved to a spouse') ?>: <strong data-move-count="spouse">0</strong></span>
                        <span><?= gettext('Already held by a family member') ?>: <strong data-move-count="held">0</strong></span>
                        <span><?= gettext('Noted in the family timeline') ?>: <strong data-move-count="noted">0</strong></span>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-vcenter w-100" id="memberListTable"></table>
                </div>
            </div>
        </div>
    </div>
</div>
