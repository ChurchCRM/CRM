<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S6 — Open opportunities (#9712, design §5.6).
 *
 * The same card shape as S5 over `GET /api/volunteer/me/opportunities`, each with a
 * single **Sign up** button. The list is server-side eligibility: it only ever contains
 * slots this person is qualified for, in a pool they belong to, with capacity left — so
 * the button is never a trap. The server re-validates both at signup anyway (§3.3.3),
 * because UI filtering is not authorization (D5).
 *
 * An empty list is a **first-class state, not an error** (§5.6): a Tabler `.empty`
 * block saying nothing is open right now, not a spinner that never resolves and not an
 * alert.
 *
 * Mobile-first per §5.9 — one column at every width, 44px touch targets, the sign-up
 * button full-width below `sm`. JS strings live in
 * `webpack/volunteer/opportunities.ts` (§5.10, F31).
 */

/** @var string $sRootPath */
/** @var string $sMyScheduleUrl */

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

<div id="volunteer-opportunities">

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
      <div class="text-body-secondary">
        <i class="fa-solid fa-circle-info me-1"></i>
        <?= gettext('These are the places you are trained for that still need someone.') ?>
      </div>
      <a class="btn btn-outline-primary volunteer-touch-target" href="<?= InputUtils::escapeAttribute($sMyScheduleUrl) ?>">
        <i class="fa-solid fa-calendar-check me-1"></i><?= gettext('Back to my schedule') ?>
      </a>
    </div>
  </div>

  <div class="volunteer-loading text-center py-4" id="opportunities-loading">
    <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
    <?= gettext('Loading') ?>
  </div>

  <div class="alert alert-danger d-none" role="alert" id="opportunities-error">
    <i class="fa-solid fa-circle-exclamation me-1"></i>
    <span class="volunteer-error-text"></span>
    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="opportunities-retry"><?= gettext('Retry') ?></button>
  </div>

  <div class="empty d-none" id="opportunities-empty">
    <div class="empty-icon"><i class="fa-solid fa-calendar-check fa-2x text-muted"></i></div>
    <p class="empty-title"><?= gettext('Nothing open right now') ?></p>
    <p class="empty-subtitle text-body-secondary">
      <?= gettext('We will email you when something needs filling.') ?>
    </p>
    <div class="empty-action">
      <a class="btn btn-primary volunteer-touch-target" href="<?= InputUtils::escapeAttribute($sMyScheduleUrl) ?>">
        <i class="fa-solid fa-calendar-check me-1"></i><?= gettext('Back to my schedule') ?>
      </a>
    </div>
  </div>

  <div class="d-none" id="opportunities-content"></div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/volunteer-opportunities.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
