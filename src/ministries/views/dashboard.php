<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S1 — the coordinator dashboard (#9711, design §5.2).
 *
 * "What needs my attention", answered top to bottom in the order §5.2 fixes:
 * gaps that need filling, assignments still awaiting a reply, proposed
 * substitutions, upcoming occurrences, and — for an administrator only — the
 * notification-health card (the settings themselves are on Admin → Ministry Settings).
 *
 * Markup only. Every panel is rendered from ONE `GET /api/ministries/dashboard`
 * (§5.2: "Do not fan out to five endpoints"), and the route ran no query beyond
 * the page header (groups-mvc-guidelines.md).
 *
 * Every string here is `gettext()`; the JS strings live in
 * `webpack/ministries/dashboard.ts`, because an `i18next.t()` call inside a .php
 * file is scanned by neither the PHP nor the JS extractor and is silently never
 * translated (§5.10, F31).
 *
 * Each panel carries the full §5.8 state set — a loading block re-shown on every
 * attempt, a retry-able error block, a first-class Tabler `.empty` block and the
 * content — driven by one `renderState()` in the bundle.
 */

/** @var string $sRootPath */
/** @var bool $bIsAdmin */
/** @var bool $bIsManager */
/** @var int $iDays */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
?>
<?php require SystemURLs::getDocumentRoot() . '/Include/Header.php'; ?>

<div id="volunteer-dashboard">

  <!-- Quick actions (§5.2). "My ministries and teams" used to sit here as a
       link to a list page; the sidebar's Ministries heading lists the same
       ministries now, and the card in the right-hand column below still names
       them and the teams under them. -->
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3" id="volunteer-quick-actions">
    <?php if ($bIsManager): ?>
      <button type="button" class="btn btn-primary" id="ministry-new-btn">
        <i class="fa-solid fa-plus me-1"></i><?= gettext('New ministry') ?>
      </button>
    <?php endif; ?>
    <div class="ms-auto d-flex align-items-center gap-2">
      <label class="form-label mb-0 text-body-secondary" for="volunteer-days">
        <?= gettext('Show the next') ?>
      </label>
      <select class="form-select form-select-sm w-auto" id="volunteer-days">
        <option value="7"><?= sprintf(ngettext('%d day', '%d days', 7), 7) ?></option>
        <option value="14"><?= sprintf(ngettext('%d day', '%d days', 14), 14) ?></option>
        <option value="28" selected><?= sprintf(ngettext('%d day', '%d days', 28), 28) ?></option>
        <option value="90"><?= sprintf(ngettext('%d day', '%d days', 90), 90) ?></option>
      </select>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="volunteer-refresh">
        <i class="fa-solid fa-rotate me-1"></i><?= gettext('Refresh') ?>
      </button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-8">

      <!-- 1. Gaps that need filling — the primary card (§5.2 item 1). -->
      <div class="card mb-3" id="volunteer-gaps-card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i><?= gettext('Needs filling') ?>
          </h3>
          <span class="badge bg-danger-lt text-danger d-none" id="volunteer-gap-badge">0</span>
        </div>
        <div class="card-body">
          <div class="volunteer-loading text-center py-4" id="volunteer-gaps-loading">
            <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
            <?= gettext('Loading') ?>
          </div>
          <div class="alert alert-danger d-none" role="alert" id="volunteer-gaps-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span class="volunteer-error-text"></span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
          </div>
          <div class="empty d-none" id="volunteer-gaps-empty">
            <div class="empty-icon"><i class="fa-solid fa-circle-check fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('Nothing is short') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?= gettext('Every position in this window has the people it needs.') ?>
            </p>
          </div>
          <div class="d-none" id="volunteer-gaps-content">
            <div class="list-group list-group-flush" id="volunteer-gaps-list"></div>
          </div>
        </div>
      </div>

      <!-- 2. Assignments still awaiting a reply (§5.2 item 2). -->
      <div class="card mb-3" id="volunteer-pending-card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-hourglass-half me-2 text-warning"></i><?= gettext('Waiting for a reply') ?>
          </h3>
          <span class="badge bg-yellow-lt text-yellow d-none" id="volunteer-pending-badge">0</span>
        </div>
        <div class="card-body">
          <div class="volunteer-loading text-center py-4" id="volunteer-pending-loading">
            <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
            <?= gettext('Loading') ?>
          </div>
          <div class="alert alert-danger d-none" role="alert" id="volunteer-pending-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span class="volunteer-error-text"></span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
          </div>
          <div class="empty d-none" id="volunteer-pending-empty">
            <div class="empty-icon"><i class="fa-solid fa-envelope-circle-check fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('Everyone has answered') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?= gettext('Nobody in this window is still deciding.') ?>
            </p>
          </div>
          <div class="d-none" id="volunteer-pending-content">
            <div class="list-group list-group-flush" id="volunteer-pending-list"></div>
          </div>
        </div>
      </div>

      <!-- 3. Proposed substitutions (§5.2 item 3). -->
      <div class="card mb-3" id="volunteer-swaps-card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-right-left me-2 text-azure"></i><?= gettext('Substitution requests') ?>
          </h3>
          <span class="badge bg-azure-lt text-azure d-none" id="volunteer-swaps-badge">0</span>
        </div>
        <div class="card-body">
          <div class="volunteer-loading text-center py-4" id="volunteer-swaps-loading">
            <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
            <?= gettext('Loading') ?>
          </div>
          <div class="alert alert-danger d-none" role="alert" id="volunteer-swaps-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span class="volunteer-error-text"></span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
          </div>
          <div class="empty d-none" id="volunteer-swaps-empty">
            <div class="empty-icon"><i class="fa-solid fa-right-left fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('No substitutions to decide') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?= gettext('When a volunteer proposes someone to take their place, it appears here for you to approve.') ?>
            </p>
          </div>
          <div class="d-none" id="volunteer-swaps-content">
            <div class="row g-2" id="volunteer-swaps-list"></div>
          </div>
        </div>
      </div>

      <!-- 4. Upcoming occurrences (§5.2 item 4). -->
      <div class="card mb-3" id="volunteer-upcoming-card">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-calendar-days me-2"></i><?= gettext('Upcoming') ?>
          </h3>
        </div>
        <div class="card-body">
          <div class="volunteer-loading text-center py-4" id="volunteer-upcoming-loading">
            <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
            <?= gettext('Loading') ?>
          </div>
          <div class="alert alert-danger d-none" role="alert" id="volunteer-upcoming-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span class="volunteer-error-text"></span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
          </div>
          <div class="empty d-none" id="volunteer-upcoming-empty">
            <div class="empty-icon"><i class="fa-solid fa-calendar-check fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('Nothing scheduled yet') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?= gettext('Open a ministry from the Ministries menu, create a schedule and generate its dates, and the weeks to staff appear here.') ?>
            </p>
          </div>
          <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="volunteer-upcoming-content">
            <table class="table table-hover table-vcenter" id="volunteer-upcoming-table">
              <thead>
                <tr>
                  <th><?= gettext('When') ?></th>
                  <th><?= gettext('Ministry') ?></th>
                  <th><?= gettext('Schedule') ?></th>
                  <th class="text-center"><?= gettext('Staffed') ?></th>
                  <th class="text-center"><?= gettext('Status') ?></th>
                  <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <p class="text-body-secondary small mt-2 d-none" id="volunteer-capped-note">
            <i class="fa-solid fa-circle-info me-1"></i>
            <?= gettext('Only the first part of a very long list is shown. Narrow the window to see the rest.') ?>
          </p>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">

      <!-- My ministries and teams. For a team leader this is the ONLY entry
           point into the module: /ministries/{id} is ministry-scoped
           (design §4.6), so the sidebar's Ministries heading offers them no
           ministry entry and this card is where their teams are named. -->
      <div class="card mb-3" id="volunteer-scope-card">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-sitemap me-2"></i><?= gettext('My ministries and teams') ?>
          </h3>
        </div>
        <div class="card-body">
          <div class="list-group list-group-flush" id="volunteer-scope-ministries"></div>
          <div class="list-group list-group-flush mt-2" id="volunteer-scope-teams"></div>
          <div class="empty d-none" id="volunteer-scope-empty">
            <div class="empty-icon"><i class="fa-solid fa-handshake-angle fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('Nothing set up yet') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?php if ($bIsManager): ?>
                <?= gettext('Start by creating a ministry, then add the teams and positions that serve in it.') ?>
              <?php else: ?>
                <?= gettext('Ask a volunteer manager to create a ministry and make you its coordinator.') ?>
              <?php endif; ?>
            </p>
            <?php if ($bIsManager): ?>
              <!-- The same modal as the quick action above; ministry-create.ts
                   wires both buttons. This one used to live on the retired
                   ministries list page, where it was the empty state's action. -->
              <div class="empty-action">
                <button type="button" class="btn btn-primary" id="ministry-new-empty-btn">
                  <i class="fa-solid fa-plus me-1"></i><?= gettext('New ministry') ?>
                </button>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($bIsManager): ?>
            <!--
              Where authority is granted (#9706, design §4.4). The card above
              lists the scopes the VIEWER holds; a manager also needs to know
              where other people's are made, and that is the ministry page, one
              ministry at a time. No link to a grants screen, because there
              isn't one and shouldn't be: a grant only means anything next to
              the ministry it is about.
            -->
            <p class="text-body-secondary small mt-3 mb-0" id="volunteer-scope-grant-hint">
              <i class="fa-solid fa-user-shield me-1"></i>
              <?= gettext('Open a ministry to make someone its coordinator, or a leader of one of its teams.') ?>
            </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- 5. Notification health (§5.2 item 5, amended 2026-09-18). Shown to every
           viewer: a coordinator should know reminders are not going out even though
           only an administrator can do anything about it. The settings themselves,
           the cron hint and the failure list live on Admin → Ministry Settings. -->
      <div class="card mb-3" id="volunteer-failed-card">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-envelope-circle-check me-2"></i><?= gettext('Notifications') ?>
          </h3>
        </div>
        <div class="card-body">
          <p class="mb-2">
            <span class="badge bg-secondary-lt me-1" id="volunteer-failed-count">0</span>
            <span id="volunteer-failed-label"><?= gettext('messages could not be delivered') ?></span>
          </p>
          <?php if ($bIsAdmin): ?>
            <a class="btn btn-sm btn-outline-secondary" id="volunteer-settings-link" href="<?= $sRootPath ?>/admin/ministry-settings">
              <i class="fa-solid fa-sliders me-1"></i><?= gettext('Ministry Settings') ?>
            </a>
          <?php else: ?>
            <p class="text-body-secondary small mb-0" id="volunteer-settings-hint">
              <?= gettext('If messages are failing, ask an administrator to check Ministry Settings.') ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($bIsManager): ?>
  <?php require __DIR__ . '/partials/ministry-create-modal.php'; ?>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.volunteerDashboard = {
  days: <?= (int) $iDays ?>,
  isAdmin: <?= $bIsAdmin ? 'true' : 'false' ?>,
  isManager: <?= $bIsManager ? 'true' : 'false' ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/ministries-dashboard.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
