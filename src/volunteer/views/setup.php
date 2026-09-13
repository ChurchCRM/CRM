<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S2 — the guided setup flow (design §5.3).
 *
 * Markup only. Everything the page needs to decide was decided in
 * routes/setup.php and arrives as a page arg; there are no queries here
 * (groups-mvc-guidelines.md). Every string is `gettext()` — an `i18next.t()`
 * call in a .php file is extracted by neither toolchain and is silently never
 * translated (design §5.10, F31), so the JS strings all live in
 * webpack/volunteer/setup.ts instead.
 *
 * Each step is a card. A step that is not yet reachable keeps its controls
 * `disabled`; a step that is done collapses to `.setup-step-summary` with an
 * Edit link. #9707 inserted the volunteer-pool step between the team and
 * position cards; #9708 adds the schedule steps after them — see the markers.
 */

/** @var string $sRootPath */
/** @var bool $bIsManager */
/** @var array<int, array{id: int, name: string}> $aMinistries */
/** @var int $iResumeMinistry */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="volunteer-setup" class="row">
  <div class="col-12 col-xl-8">

    <!-- Step 1 — Ministry. Manager-only to create (design §4.6). -->
    <div class="card mb-3" id="setup-step-ministry">
      <div class="card-header">
        <h3 class="card-title">
          <span class="badge bg-primary-lt text-primary me-2">1</span><?= gettext('Ministry') ?>
        </h3>
      </div>
      <div class="card-body">
        <div class="setup-step-summary d-none d-flex align-items-center justify-content-between">
          <div>
            <i class="fa-solid fa-circle-check text-success me-2"></i>
            <span class="fw-bold setup-summary-name"></span>
          </div>
          <button type="button" class="btn btn-sm btn-ghost-secondary" id="setup-ministry-edit">
            <i class="fa-solid fa-pencil me-1"></i><?= gettext('Edit') ?>
          </button>
        </div>

        <div class="setup-step-form">
          <?php if ($bIsManager): ?>
            <p class="text-body-secondary">
              <?= gettext('A ministry is the area that owns teams, positions and schedules — Coffee Bar, Worship, Children\'s Ministry.') ?>
            </p>
            <div class="mb-3">
              <label class="form-label" for="setup-ministry-name"><?= gettext('Ministry name') ?></label>
              <input type="text" class="form-control" id="setup-ministry-name" maxlength="100"
                     placeholder="<?= InputUtils::escapeAttribute(gettext('Coffee Bar')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="setup-ministry-description"><?= gettext('Description') ?></label>
              <input type="text" class="form-control" id="setup-ministry-description" maxlength="255">
            </div>
            <button type="button" class="btn btn-primary" id="setup-ministry-save">
              <i class="fa-solid fa-plus me-1"></i><?= gettext('Create ministry') ?>
            </button>
          <?php else: ?>
            <p class="text-body-secondary">
              <?= gettext('Creating a ministry is reserved for volunteer managers. Choose one you already coordinate to carry on.') ?>
            </p>
          <?php endif; ?>

          <?php if (count($aMinistries) > 0): ?>
            <div class="mt-3">
              <label class="form-label" for="setup-ministry-existing">
                <?= $bIsManager ? gettext('Or continue with an existing ministry') : gettext('Your ministries') ?>
              </label>
              <div class="d-flex gap-2 flex-column flex-sm-row">
                <select class="form-select" id="setup-ministry-existing">
                  <option value=""><?= gettext('Choose a ministry') ?></option>
                  <?php foreach ($aMinistries as $aMinistry): ?>
                    <option value="<?= (int) $aMinistry['id'] ?>"<?= (int) $aMinistry['id'] === (int) $iResumeMinistry ? ' selected' : '' ?>>
                      <?= InputUtils::escapeHTML($aMinistry['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline-primary" id="setup-ministry-choose">
                  <?= gettext('Continue') ?>
                </button>
              </div>
            </div>
          <?php elseif (!$bIsManager): ?>
            <div class="empty">
              <div class="empty-icon"><i class="fa-solid fa-handshake-angle fa-2x text-muted"></i></div>
              <p class="empty-title"><?= gettext('No ministries yet') ?></p>
              <p class="empty-subtitle text-body-secondary">
                <?= gettext('Ask a volunteer manager to create a ministry and make you its coordinator.') ?>
              </p>
            </div>
          <?php endif; ?>

          <div class="alert alert-danger d-none mt-3" role="alert" id="setup-ministry-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i><span class="setup-error-text"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 2 — Team. -->
    <div class="card mb-3" id="setup-step-team">
      <div class="card-header">
        <h3 class="card-title">
          <span class="badge bg-primary-lt text-primary me-2">2</span><?= gettext('Teams') ?>
        </h3>
      </div>
      <div class="card-body">
        <p class="text-body-secondary">
          <?= gettext('Teams are the groups of people who actually serve. One is enough — add more only if different people run different services.') ?>
        </p>

        <div class="volunteer-loading text-center py-4 d-none" id="setup-team-loading">
          <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
          <?= gettext('Loading') ?>
        </div>

        <ul class="list-group mb-3 d-none" id="setup-team-list"></ul>

        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-5">
            <label class="form-label" for="setup-team-name"><?= gettext('Team name') ?></label>
            <input type="text" class="form-control" id="setup-team-name" maxlength="100" disabled
                   placeholder="<?= InputUtils::escapeAttribute(gettext('Coffee Bar Team')) ?>">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="setup-team-description"><?= gettext('Description') ?></label>
            <input type="text" class="form-control" id="setup-team-description" maxlength="255" disabled>
          </div>
          <div class="col-12 col-md-3 d-grid">
            <button type="button" class="btn btn-primary" id="setup-team-save" disabled>
              <i class="fa-solid fa-plus me-1"></i><?= gettext('Add team') ?>
            </button>
          </div>
        </div>

        <div class="alert alert-danger d-none mt-3" role="alert" id="setup-team-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="setup-error-text"></span>
        </div>
      </div>
    </div>

    <!--
      Step 3 — Volunteer pool (#9707, design §5.3).

      The wording is the design's own and is load-bearing: "Choose the Group
      whose members volunteer" — never "add volunteers" — because membership
      stays in Groups and V2 copies nobody (D1).
    -->
    <div class="card mb-3" id="setup-step-pool">
      <div class="card-header">
        <h3 class="card-title">
          <span class="badge bg-primary-lt text-primary me-2">3</span><?= gettext('Volunteer pool') ?>
        </h3>
      </div>
      <div class="card-body">
        <p class="text-body-secondary">
          <?= gettext('Choose the Group whose members volunteer for this ministry. Nobody is copied — the Group stays in charge of who belongs, so adding someone there adds them here.') ?>
        </p>

        <div class="volunteer-loading text-center py-4 d-none" id="setup-pool-loading">
          <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
          <?= gettext('Loading') ?>
        </div>

        <ul class="list-group mb-3 d-none" id="setup-pool-list"></ul>

        <div class="d-flex gap-2 flex-column flex-sm-row">
          <button type="button" class="btn btn-primary" id="setup-pool-link" disabled>
            <i class="fa-solid fa-link me-1"></i><?= gettext('Link a Group') ?>
          </button>
          <button type="button" class="btn btn-outline-secondary" id="setup-pool-new-group" disabled>
            <i class="fa-solid fa-plus me-1"></i><?= gettext('Create a new Group') ?>
          </button>
        </div>

        <div class="alert alert-danger d-none mt-3" role="alert" id="setup-pool-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="setup-error-text"></span>
        </div>
      </div>
    </div>

    <!-- Step 4 — Positions. -->
    <div class="card mb-3" id="setup-step-position">
      <div class="card-header">
        <h3 class="card-title">
          <span class="badge bg-primary-lt text-primary me-2">4</span><?= gettext('Positions') ?>
        </h3>
      </div>
      <div class="card-body">
        <p class="text-body-secondary">
          <?= gettext('A position is a role someone serves in — Espresso, Song Leader, Nursery Teacher. Add as many as you need.') ?>
        </p>

        <div class="volunteer-loading text-center py-4 d-none" id="setup-position-loading">
          <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
          <?= gettext('Loading') ?>
        </div>

        <ul class="list-group mb-3 d-none" id="setup-position-list"></ul>

        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label" for="setup-position-name"><?= gettext('Position name') ?></label>
            <input type="text" class="form-control" id="setup-position-name" maxlength="100" disabled
                   placeholder="<?= InputUtils::escapeAttribute(gettext('Espresso')) ?>">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="setup-position-description"><?= gettext('Description') ?></label>
            <input type="text" class="form-control" id="setup-position-description" maxlength="255" disabled>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label" for="setup-position-team"><?= gettext('Team') ?></label>
            <select class="form-select" id="setup-position-team" disabled></select>
          </div>
          <div class="col-12 col-md-2 d-grid">
            <button type="button" class="btn btn-primary" id="setup-position-save" disabled>
              <i class="fa-solid fa-plus me-1"></i><?= gettext('Add') ?>
            </button>
          </div>
        </div>

        <div class="alert alert-danger d-none mt-3" role="alert" id="setup-position-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="setup-error-text"></span>
        </div>
      </div>
    </div>

    <?php /*
      The qualification matrix (§5.3 step 5) is NOT repeated here. It is the
      screen a coordinator returns to most (§5.4), so it lives on the ministry
      page's Qualifications tab and the flow links there rather than shipping a
      second copy that would drift. #9708 adds the schedule, staffing and
      generate steps after this point.
    */ ?>

    <!-- What next. -->
    <div class="card mb-3 d-none" id="setup-step-next">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fa-solid fa-flag-checkered me-2"></i><?= gettext('What next') ?>
        </h3>
      </div>
      <div class="card-body">
        <p><?= gettext('The structure is in place. From the ministry page you can rename teams, reorder positions and keep the setup current.') ?></p>
        <div class="d-flex gap-2 flex-column flex-sm-row">
          <a href="#" class="btn btn-primary" id="setup-open-ministry">
            <i class="fa-solid fa-arrow-right me-1"></i><?= gettext('Open the ministry page') ?>
          </a>
          <a href="<?= $sRootPath ?>/volunteer/dashboard" class="btn btn-outline-secondary" id="setup-back-dashboard">
            <?= gettext('Back to the volunteer dashboard') ?>
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.volunteerSetup = {
  isManager: <?= $bIsManager ? 'true' : 'false' ?>,
  ministryId: <?= (int) $iResumeMinistry ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/volunteer-setup.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
