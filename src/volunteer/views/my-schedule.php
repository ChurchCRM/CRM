<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S5 — My volunteer schedule (#9712, design §5.6).
 *
 * Markup only. Everything on the page comes from `GET /api/volunteer/me/assignments`,
 * where the acting person is the session and there is no id in the URL to tamper with
 * (groups-mvc-guidelines.md: no queries in views).
 *
 * §5.6 is emphatic that this screen is **plain**: a volunteer must never read the words
 * *ministry hierarchy*, *requirement*, *occurrence* or *schedule*. They read a day, a
 * time, who they are serving with, what they are doing, and three buttons. Every string
 * below is chosen on that basis — "I'll be there", not "Accept assignment".
 *
 * Mobile-first per §5.9, and mobile is a hard requirement for this issue, not a nicety:
 * one column at every width (no `col-md-*` on the card list), the three actions in a
 * `.d-grid.gap-2.d-sm-flex` so they are full-width stacked buttons below `sm` and a row
 * above it, and `.volunteer-touch-target` giving every one of them the 44px minimum.
 *
 * The §5.8 states are all here and driven from the TS by one `renderState()`: a loading
 * block re-shown on every attempt, a Tabler `.empty` block, an error block whose Retry
 * genuinely re-runs the load, and toasts through `window.CRM.notify`.
 *
 * JS strings live in `webpack/volunteer/my-schedule.ts`. An `i18next.t()` call inside a
 * `.php` file is scanned by neither the PHP nor the JS extractor and is silently never
 * translated (design §5.10, F31).
 */

/** @var string $sRootPath */
/** @var string $sOpportunitiesUrl */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<style>
  /*
    §5.9: S5/S6 are mobile-first and 44px is the minimum touch target on the
    accept/decline/sign-up controls. Declared here rather than in the skin because
    it is two rules used by two pages; a module shipping its own `.min.css` through
    a webpack entry is NOT RTL-flipped by `churchcrm-rtl.min.css`, so anything more
    than this would have to use logical properties anyway.
  */
  .volunteer-touch-target {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  /* Below `sm` the actions are a full-width stack; above it they sit in a row. */
  .volunteer-card-actions .volunteer-touch-target {
    inline-size: 100%;
  }
  @media (min-width: 576px) {
    .volunteer-card-actions .volunteer-touch-target {
      inline-size: auto;
    }
  }
</style>

<div id="volunteer-my-schedule">

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="show-past">
        <label class="form-check-label" for="show-past"><?= gettext('Show what I have already done') ?></label>
      </div>
      <a class="btn btn-outline-primary volunteer-touch-target" href="<?= InputUtils::escapeAttribute($sOpportunitiesUrl) ?>">
        <i class="fa-solid fa-hand-holding-heart me-1"></i><?= gettext('Find something to help with') ?>
      </a>
    </div>
  </div>

  <div class="volunteer-loading text-center py-4" id="assignments-loading">
    <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
    <?= gettext('Loading') ?>
  </div>

  <div class="alert alert-danger d-none" role="alert" id="assignments-error">
    <i class="fa-solid fa-circle-exclamation me-1"></i>
    <span class="volunteer-error-text"></span>
    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="assignments-retry"><?= gettext('Retry') ?></button>
  </div>

  <div class="empty d-none" id="assignments-empty">
    <div class="empty-icon"><i class="fa-solid fa-calendar-check fa-2x text-muted"></i></div>
    <p class="empty-title"><?= gettext('Nothing on your list yet') ?></p>
    <p class="empty-subtitle text-body-secondary">
      <?= gettext('When someone puts you on a team, or you sign up yourself, it will show up here.') ?>
    </p>
    <div class="empty-action">
      <a class="btn btn-primary volunteer-touch-target" href="<?= InputUtils::escapeAttribute($sOpportunitiesUrl) ?>">
        <i class="fa-solid fa-hand-holding-heart me-1"></i><?= gettext('See what needs filling') ?>
      </a>
    </div>
  </div>

  <!-- One column at every width (§5.9): no grid, just a stack of cards. -->
  <div class="d-none" id="assignments-content"></div>

  <!--
    "Find a sub" (§5.6). The list comes from
    `GET /api/volunteer/me/assignments/{id}/substitutes`, which has already removed the
    volunteer themselves and anyone already on that slot, so the picker cannot offer a
    name the server will then refuse.
  -->
  <div class="modal fade" id="substitute-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= gettext('Ask someone to cover for you') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
        </div>
        <div class="modal-body">
          <p class="text-body-secondary">
            <?= gettext('Pick someone who has already agreed to take your place. Your coordinator will confirm it.') ?>
          </p>
          <div class="mb-3">
            <label class="form-label" for="substitute-select"><?= gettext('Who is covering') ?></label>
            <select class="form-select" id="substitute-select"></select>
            <div class="form-text" id="substitute-hint"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="substitute-comment"><?= gettext('Anything your coordinator should know') ?></label>
            <textarea class="form-control" id="substitute-comment" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
          <button type="button" class="btn btn-primary volunteer-touch-target" id="substitute-save"><?= gettext('Ask them') ?></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/volunteer-my-schedule.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
