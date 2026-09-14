<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S3 — ministry detail (design §5.4, as amended by the product owner).
 *
 * Six tabs, each loaded lazily on its first activation: **Overview · Positions ·
 * Volunteers · Schedules · Occurrences · Help Wanted**. Markup only: the route
 * decided what may be shown and the tab contents come from
 * `/api/volunteer/ministries/{id}` — no queries here
 * (groups-mvc-guidelines.md).
 *
 * What moved, and why:
 *
 *   - The **Teams tab is gone**. A ministry's teams are a fact about the ministry
 *     rather than a working surface, so the team list is a card on Overview, under
 *     the three counts it explains.
 *   - **Qualifications is now "Volunteers"** — the grid answers "who serves in this
 *     ministry, and at what", which is the question its name should ask.
 *   - **Help wanted is a tab.** It used to sit outside the tab content, which made
 *     it render underneath every tab at once.
 *   - The **volunteer pool panel is gone**. The V2 pool endpoints and the Groups
 *     module still own the roster; this page simply no longer shows the panel, and
 *     qualifying somebody still brings them into the pool.
 *
 * Every string is `gettext()`. The JS strings live in
 * webpack/volunteer/ministry.ts, because an `i18next.t()` call inside a .php file
 * is scanned by neither the PHP nor the JS extractor and is silently never
 * translated (design §5.10, F31).
 */

/** @var string $sRootPath */
/** @var int $iMinistryId */
/** @var string $sMinistryName */
/** @var bool $bMinistryActive */
/** @var bool $bIsManager */
/** @var bool $bIsMinistryCoordinator */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="volunteer-ministry" class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <div>
      <h3 class="card-title mb-0"><?= InputUtils::escapeHTML($sMinistryName) ?></h3>
      <?php if (!$bMinistryActive): ?>
        <span class="badge bg-secondary-lt"><?= gettext('Inactive') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <ul class="nav nav-tabs" id="volunteer-ministry-tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link active" id="nav-item-overview" href="#overview" data-bs-toggle="tab" role="tab" aria-controls="overview" aria-selected="true">
        <i class="fa-solid fa-circle-info me-1"></i><?= gettext('Overview') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-positions" href="#positions" data-bs-toggle="tab" role="tab" aria-controls="positions" aria-selected="false">
        <i class="fa-solid fa-list-check me-1"></i><?= gettext('Positions') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-volunteers" href="#volunteers" data-bs-toggle="tab" role="tab" aria-controls="volunteers" aria-selected="false">
        <i class="fa-solid fa-user-check me-1"></i><?= gettext('Volunteers') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-schedules" href="#schedules" data-bs-toggle="tab" role="tab" aria-controls="schedules" aria-selected="false">
        <i class="fa-solid fa-repeat me-1"></i><?= gettext('Schedules') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-occurrences" href="#occurrences" data-bs-toggle="tab" role="tab" aria-controls="occurrences" aria-selected="false">
        <i class="fa-solid fa-calendar-days me-1"></i><?= gettext('Occurrences') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-help-wanted" href="#help-wanted" data-bs-toggle="tab" role="tab" aria-controls="help-wanted" aria-selected="false">
        <i class="fa-solid fa-bullhorn me-1"></i><?= gettext('Help Wanted') ?>
      </a>
    </li>
  </ul>

  <div class="card-body tab-content">

    <!--
      Overview: three counts, the description, the teams of the ministry, and — for
      a volunteer manager — who coordinates it.
    -->
    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="nav-item-overview">
      <div class="volunteer-loading text-center py-4" id="overview-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="overview-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="row g-3 d-none" id="overview-content">
        <div class="col-12 col-md-4">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="h1 mb-0" id="overview-team-count">0</div>
              <div class="text-body-secondary"><?= gettext('Teams') ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="h1 mb-0" id="overview-volunteer-count">0</div>
              <div class="text-body-secondary"><?= gettext('Volunteers') ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="h1 mb-0" id="overview-unfilled-count">0</div>
              <div class="text-body-secondary"><?= gettext('Unfilled Positions') ?></div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <p class="text-body-secondary mb-0" id="overview-description"></p>
        </div>
      </div>

      <!--
        Teams. The list that used to be the first thing on the Teams tab, with the
        team's leader beside it — the one fact about a team that a coordinator
        cannot get anywhere else on this page.

        The overflow wrapper is mandatory on every table carrying a row action menu:
        without it the dropdown is clipped by the card (table-action-menu.md).
      -->
      <div class="card mt-3" id="volunteer-teams-card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-people-group me-2"></i><?= gettext('Teams') ?>
          </h3>
          <button type="button" class="btn btn-primary btn-sm" id="team-add-btn">
            <i class="fa-solid fa-plus me-1"></i><?= gettext('Add team') ?>
          </button>
        </div>
        <div class="card-body">
          <div class="volunteer-loading text-center py-4" id="teams-loading">
            <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
            <?= gettext('Loading') ?>
          </div>
          <div class="alert alert-danger d-none" role="alert" id="teams-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span class="volunteer-error-text"></span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
          </div>
          <div class="empty d-none" id="teams-empty">
            <div class="empty-icon"><i class="fa-solid fa-people-group fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('No teams yet') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?= gettext('Teams are the groups of people who serve in this ministry.') ?>
            </p>
          </div>
          <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="teams-table-wrapper">
            <table class="table table-hover table-vcenter" id="volunteerTeamsTable">
              <thead>
                <tr>
                  <th><?= gettext('Name') ?></th>
                  <th><?= gettext('Description') ?></th>
                  <th><?= gettext('Team Leader') ?></th>
                  <th class="text-center"><?= gettext('Positions') ?></th>
                  <th class="text-center"><?= gettext('Status') ?></th>
                  <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

<?php if ($bIsManager): ?>
      <!--
        Ministry coordinators — the screen for #9706's scope API
        (design §2.15, §4.4; the API is `/api/volunteer/scopes`).

        A SELF-CONTAINED block on purpose: everything it needs lives between this
        comment and the closing endif below, and its behaviour lives in its own
        bundle module (webpack/volunteer/scopes.ts), which ministry.ts only imports
        and initialises.

        Team leaders are NOT here any more — a leader belongs to a team, so the
        grant lives on the team's own row in the Teams card above. This card is
        ministry-coordinator grants and nothing else.

        Rendered only for a global volunteer manager, because granting authority is
        the one thing §3.2 says a coordinator must not be able to do for themselves.
        `$bIsManager` is the same `isGlobalManager()` answer the API will give; the
        API is still the decision-maker, and scopes.ts hides the card if a request
        ever comes back 403.
      -->
      <div class="card mt-3 d-none" id="volunteer-scope-panel">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-user-shield me-2"></i><?= gettext('Ministry Coordinators') ?>
          </h3>
          <button type="button" class="btn btn-sm btn-primary" id="scope-add-coordinator" disabled>
            <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add coordinator') ?>
          </button>
        </div>
        <div class="card-body">
          <p class="text-body-secondary" id="volunteer-scope-help">
            <i class="fa-solid fa-circle-info me-1"></i>
            <?= gettext('A coordinator runs the whole ministry — its teams, positions, schedules and every assignment in it.') ?>
          </p>
          <p class="text-body-secondary small" id="volunteer-scope-login-note">
            <?= gettext('Authority is given to the person, not to a login, so it can be granted before they have one. A person whose login is self-service only keeps seeing just their own schedule until an administrator widens their account.') ?>
          </p>

          <div class="volunteer-loading text-center py-4" id="scopes-loading">
            <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
            <?= gettext('Loading') ?>
          </div>
          <div class="alert alert-danger d-none" role="alert" id="scopes-error">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span class="volunteer-error-text"></span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
          </div>

          <div class="d-none" id="scopes-content">
            <div class="empty d-none" id="scopes-coordinators-empty">
              <div class="empty-icon"><i class="fa-solid fa-user-tie fa-2x text-muted"></i></div>
              <p class="empty-title"><?= gettext('No coordinators yet') ?></p>
              <p class="empty-subtitle text-body-secondary">
                <?= gettext('A volunteer manager can run this ministry without a grant. Add a coordinator to let someone else run it without giving them every other ministry as well.') ?>
              </p>
            </div>
            <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="scopes-coordinators-wrapper">
              <table class="table table-hover table-vcenter" id="volunteerCoordinatorsTable">
                <thead>
                  <tr>
                    <th><?= gettext('Person') ?></th>
                    <th><?= gettext('Granted') ?></th>
                    <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
<?php endif; ?>
    </div>

    <!--
      Volunteers (formerly Qualifications; #9707, design §5.4). People (rows) ×
      positions (columns), one checkbox per cell, one team at a time. The whole
      grid comes from ONE /qualification-matrix response — §5.4 is explicit that
      15–200 pool members must render without a request per cell.

      Deliberately NOT a DataTable: the column set is data-driven and every cell
      is an input, so DataTables' row cache would fight the optimistic toggle.
      The filter box below is the one DataTables feature this grid actually
      needs.

      The wrapper scrolls sideways (`.volunteer-scroll-x`) rather than carrying
      the usual `overflow-x: clip` pair: position names and counts are unlimited,
      so a ministry with very many positions has to be scrollable rather than
      wrapped. The row action menu that a horizontal scroller would clip is
      re-anchored with `position: fixed` by ministry.ts — see
      table-action-menu.md, "A menu inside a horizontally scrolling table".
    -->
    <div class="tab-pane fade" id="volunteers" role="tabpanel" aria-labelledby="nav-item-volunteers">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-4">
          <label class="form-label" for="qualification-team-filter"><?= gettext('Team') ?></label>
          <select class="form-select" id="qualification-team-filter"></select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="qualification-filter"><?= gettext('Find a volunteer') ?></label>
          <input type="search" class="form-control" id="qualification-filter"
                 placeholder="<?= InputUtils::escapeAttribute(gettext('Start typing a name')) ?>">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2 justify-content-md-end">
          <button type="button" class="btn btn-outline-primary" id="qualification-add-person">
            <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Qualify someone else') ?>
          </button>
          <button type="button" class="btn btn-outline-primary" id="qualification-cart-btn">
            <i class="fa-solid fa-cart-shopping me-1"></i><?= gettext('Qualify the cart') ?>
          </button>
        </div>
      </div>

      <div class="volunteer-loading text-center py-4" id="volunteers-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="volunteers-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="volunteers-empty">
        <div class="empty-icon"><i class="fa-solid fa-user-check fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('Nothing to qualify yet') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('Create at least one position for this team, then tick who can serve where.') ?>
        </p>
      </div>
      <!--
        There is deliberately no Save button: each tick is its own write, and each
        one confirms itself beside the box it was made in. The hint says so, because
        a grid of checkboxes with no Save button otherwise reads as unsaved work.
      -->
      <p class="text-body-secondary small mb-2 d-none" id="volunteers-save-hint">
        <i class="fa-solid fa-circle-info me-1"></i><?= gettext('Ticks save as you make them.') ?>
      </p>
      <div class="volunteer-scroll-x d-none" id="volunteers-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerQualificationsTable">
          <thead>
            <tr>
              <th><?= gettext('Volunteer') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Positions -->
    <div class="tab-pane fade" id="positions" role="tabpanel" aria-labelledby="nav-item-positions">
      <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-primary btn-sm" id="position-add-btn">
          <i class="fa-solid fa-plus me-1"></i><?= gettext('Add position') ?>
        </button>
      </div>
      <div class="volunteer-loading text-center py-4" id="positions-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="positions-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="positions-empty">
        <div class="empty-icon"><i class="fa-solid fa-list-check fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('No positions yet') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('A position is a role someone serves in, such as Espresso or Song Leader.') ?>
        </p>
      </div>
      <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="positions-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerPositionsTable">
          <thead>
            <tr>
              <th class="text-center w-1"><?= gettext('Order') ?></th>
              <th><?= gettext('Name') ?></th>
              <th><?= gettext('Description') ?></th>
              <th><?= gettext('Team') ?></th>
              <th class="text-center"><?= gettext('Status') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!--
      Schedules (#9711). The recurring patterns #9708 built, made reachable: create
      one, generate its dates, open the weeks it produced. Generation is idempotent
      server-side (§2.9), so the button is safe to press twice.
    -->
    <div class="tab-pane fade" id="schedules" role="tabpanel" aria-labelledby="nav-item-schedules">
      <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-primary" id="schedule-add-btn">
          <i class="fa-solid fa-plus me-1"></i><?= gettext('Add schedule') ?>
        </button>
      </div>
      <div class="volunteer-loading text-center py-4" id="schedules-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="schedules-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="schedules-empty">
        <div class="empty-icon"><i class="fa-solid fa-repeat fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('No schedules yet') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('A schedule is the recurring pattern this ministry staffs — a weekly service, a Wednesday class. Add one and generate its dates.') ?>
        </p>
      </div>
      <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="schedules-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerSchedulesTable">
          <thead>
            <tr>
              <th><?= gettext('Name') ?></th>
              <th><?= gettext('Pattern') ?></th>
              <th><?= gettext('Team') ?></th>
              <th class="text-center"><?= gettext('Dates generated') ?></th>
              <th class="text-center"><?= gettext('Status') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!--
      Occurrences (#9709). A minimal upcoming list with gap counts, so the staffing
      view (S4) is reachable from the ministry page. Deliberately small: the counts
      come from GET /api/volunteer/occurrences, which serves them from the single gap
      implementation, and nothing is re-derived here.
    -->
    <div class="tab-pane fade" id="occurrences" role="tabpanel" aria-labelledby="nav-item-occurrences">
      <!--
        The tab shows what is still to come. Everything that already happened is
        one dialog away rather than mixed into the default list, because a
        coordinator opening this tab is staffing the weeks ahead and a year of
        past weeks buries them.
      -->
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-primary" id="occurrences-filter-btn">
          <i class="fa-solid fa-calendar-day me-1"></i><?= gettext('Filter by Date') ?>
        </button>
      </div>
      <p class="text-body-secondary small d-none" id="occurrences-range-note">
        <span id="occurrences-range-text"></span>
        <button type="button" class="btn btn-link btn-sm p-0 ms-2 align-baseline" id="occurrences-range-reset">
          <?= gettext('Back to upcoming') ?>
        </button>
      </p>
      <div class="volunteer-loading text-center py-4" id="occurrences-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="occurrences-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="occurrences-empty">
        <div class="empty-icon"><i class="fa-solid fa-calendar-days fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('Nothing scheduled yet') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('Create a schedule and generate its dates, and the weeks to staff appear here.') ?>
        </p>
      </div>
      <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="occurrences-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerOccurrencesTable">
          <thead>
            <tr>
              <th><?= gettext('When') ?></th>
              <th><?= gettext('Schedule') ?></th>
              <th class="text-center"><?= gettext('Filled') ?></th>
              <th class="text-center"><?= gettext('Still needed') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!--
      Help wanted (D19, design §5.6) — a tab of its own.

      It used to be a card BELOW the tab strip, which meant it rendered under every
      tab at once. Two fields a coordinator sets once and revisits rarely deserve
      their own tab rather than a permanent footer on the other five.

      Shown to anyone who can reach this page as a coordinator, which is the same
      authority the API applies — coordinators and above (§4.6). The API is still
      the decision-maker; ministry.ts reports a 403 rather than pretending.
    -->
    <div class="tab-pane fade" id="help-wanted" role="tabpanel" aria-labelledby="nav-item-help-wanted">
      <div id="volunteer-help-wanted">
        <p class="text-body-secondary">
          <?= gettext('Put this ministry on the Open Opportunities page, where any volunteer can see it and offer to help. They join the volunteer pool and you are emailed.') ?>
        </p>
        <label class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="help-wanted-toggle">
          <span class="form-check-label"><?= gettext('Show this ministry on the Open Opportunities page') ?></span>
        </label>
        <div class="mb-3">
          <label class="form-label" for="help-wanted-text"><?= gettext('What you want to say') ?></label>
          <textarea class="form-control" id="help-wanted-text" rows="3" maxlength="2000"
                    placeholder="<?= InputUtils::escapeAttribute(gettext('We would love more help on Sunday mornings — no experience needed.')) ?>"></textarea>
          <div class="form-text"><?= gettext('Shown exactly as you type it. Line breaks are kept.') ?></div>
        </div>
        <div class="alert alert-danger d-none" role="alert" id="help-wanted-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
        <button type="button" class="btn btn-primary" id="help-wanted-save"><?= gettext('Save') ?></button>
      </div>
    </div>

  </div>
</div>

<?php if ($bIsManager): ?>
<!-- Add a ministry coordinator (#9706) -->
<div class="modal fade" id="scopeCoordinatorModal" tabindex="-1" aria-hidden="true" aria-labelledby="scopeCoordinatorModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scopeCoordinatorModalTitle"><?= gettext('Add coordinator') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= gettext('A coordinator can manage everything in this ministry, including making changes a volunteer manager would otherwise have to make.') ?>
        </p>
        <div class="mb-3">
          <label class="form-label" for="scope-coordinator-person"><?= gettext('Person') ?></label>
          <select class="form-select person-search" id="scope-coordinator-person"
                  data-placeholder="<?= InputUtils::escapeAttribute(gettext('Start typing a name')) ?>"></select>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="scope-coordinator-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="scope-coordinator-save"><?= gettext('Add coordinator') ?></button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<!--
  Team editor — name, description, active, and the team's leader.

  The leader used to be a modal of its own, opened from two menu items on the
  team's row. It is a property of the team, so it is now a field of the dialog
  that edits one: adding a team and giving it a leader is one dialog, and
  changing the leader is the same Edit dialog everything else about the team is
  changed in.

  Granting is manager-only (§3.2) — the `/api/volunteer/scopes` endpoints refuse
  anyone else — so only a manager gets the picker. Everybody else is shown the
  current leader as read-only text and told who may change it, which is honest
  about the permission rather than offering a control the API will refuse.
-->
<div class="modal fade" id="teamModal" tabindex="-1" aria-hidden="true" aria-labelledby="teamModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="teamModalTitle"><?= gettext('Team') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="team-form-name"><?= gettext('Team name') ?></label>
          <input type="text" class="form-control" id="team-form-name" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label" for="team-form-description"><?= gettext('Description') ?></label>
          <input type="text" class="form-control" id="team-form-description" maxlength="255">
        </div>
        <div class="mb-3">
          <label class="form-label" for="team-form-leader"><?= gettext('Team leader') ?></label>
<?php if ($bIsManager): ?>
          <div class="input-group">
            <select class="form-select person-search" id="team-form-leader"
                    data-placeholder="<?= InputUtils::escapeAttribute(gettext('Start typing a name')) ?>"></select>
            <!--
              The × clears the picker, which is what says "this team has no leader":
              an empty field on save revokes the grant.
            -->
            <button type="button" class="btn btn-outline-secondary" id="team-form-leader-clear"
                    title="<?= InputUtils::escapeAttribute(gettext('Remove the team leader')) ?>"
                    aria-label="<?= InputUtils::escapeAttribute(gettext('Remove the team leader')) ?>">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div class="form-text">
            <?= gettext('A team leader can manage their own team — its positions, its schedules and who serves in them — and nothing else in the ministry.') ?>
          </div>
<?php else: ?>
          <input type="text" class="form-control" id="team-form-leader-readonly" readonly>
          <div class="form-text" id="team-form-leader-note">
            <?= gettext('Only a volunteer manager can change the team leader') ?>
          </div>
<?php endif; ?>
        </div>
        <label class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="team-form-active" checked>
          <span class="form-check-label"><?= gettext('Active') ?></span>
        </label>
        <div class="alert alert-danger d-none mt-3" role="alert" id="team-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="team-form-save"><?= gettext('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Position editor -->
<div class="modal fade" id="positionModal" tabindex="-1" aria-hidden="true" aria-labelledby="positionModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="positionModalTitle"><?= gettext('Position') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="position-form-name"><?= gettext('Position name') ?></label>
          <input type="text" class="form-control" id="position-form-name" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label" for="position-form-description"><?= gettext('Description') ?></label>
          <input type="text" class="form-control" id="position-form-description" maxlength="255">
        </div>
        <div class="row g-2">
          <div class="col-12 col-sm-8">
            <label class="form-label" for="position-form-team"><?= gettext('Team') ?></label>
            <!-- Filled from the ministry's teams; a position always belongs to one. -->
            <select class="form-select" id="position-form-team" required></select>
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label" for="position-form-order"><?= gettext('Order') ?></label>
            <input type="number" class="form-control" id="position-form-order" min="0" value="0">
          </div>
        </div>
        <label class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" id="position-form-active" checked>
          <span class="form-check-label"><?= gettext('Active') ?></span>
        </label>
        <div class="alert alert-danger d-none mt-3" role="alert" id="position-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="position-form-save"><?= gettext('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  Qualify one person who is not in a pool (#9707).

  §4.6 puts no pool-membership condition on granting a qualification, and §2.5
  says in as many words that the pool is the candidate set rather than the
  eligibility rule — so a coordinator may qualify anyone. D19 adds the other half:
  qualifying somebody outside the pool puts them in it. The picker is the shared
  person selector (CR1/#9819) pointed at the core person search, not a second
  widget.
-->
<div class="modal fade" id="qualifyPersonModal" tabindex="-1" aria-hidden="true" aria-labelledby="qualifyPersonModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qualifyPersonModalTitle"><?= gettext('Qualify someone else') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= gettext('Anyone can be qualified, whether or not they are in the pool already — qualifying them adds them to it. Being in the pool makes someone a candidate; the qualification is what makes them assignable.') ?>
        </p>
        <div class="mb-3">
          <label class="form-label" for="qualify-person-select"><?= gettext('Person') ?></label>
          <select class="form-select person-search" id="qualify-person-select"
                  data-placeholder="<?= InputUtils::escapeAttribute(gettext('Start typing a name')) ?>"></select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="qualify-person-position"><?= gettext('Position') ?></label>
          <select class="form-select" id="qualify-person-position"></select>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="qualify-person-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="qualify-person-save"><?= gettext('Qualify') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  Qualify everyone in the cart (#9707). The Cart is the existing bulk-selection
  mechanism (P5); V2 adds a sink for it and no second selection UI.
-->
<div class="modal fade" id="qualifyCartModal" tabindex="-1" aria-hidden="true" aria-labelledby="qualifyCartModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qualifyCartModalTitle"><?= gettext('Qualify the cart') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= gettext('Everyone currently in your people cart is qualified for the position you choose. The cart is left as it is, so you can qualify the same people for a second position.') ?>
        </p>
        <div class="mb-3">
          <label class="form-label" for="qualify-cart-position"><?= gettext('Position') ?></label>
          <select class="form-select" id="qualify-cart-position"></select>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="qualify-cart-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="qualify-cart-save"><?= gettext('Qualify') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Schedule editor (#9711) -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true" aria-labelledby="scheduleModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scheduleModalTitle"><?= gettext('Schedule') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="schedule-form-name"><?= gettext('Schedule name') ?></label>
          <input type="text" class="form-control" id="schedule-form-name" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label" for="schedule-form-team"><?= gettext('Team') ?></label>
          <!-- Filled from the ministry's teams; a schedule always belongs to one. -->
          <select class="form-select" id="schedule-form-team" required></select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="schedule-form-link-mode"><?= gettext('Where the dates come from') ?></label>
          <select class="form-select" id="schedule-form-link-mode">
            <option value="event_type"><?= gettext('An existing calendar event type') ?></option>
            <option value="standalone"><?= gettext('A weekly pattern of its own') ?></option>
          </select>
        </div>
        <div class="mb-3 d-none" id="schedule-form-event-type-row">
          <label class="form-label" for="schedule-form-event-type"><?= gettext('Event type') ?></label>
          <select class="form-select" id="schedule-form-event-type"></select>
          <div class="form-text"><?= gettext('The date and time of every occurrence come from the calendar event, so moving the event moves the schedule.') ?></div>
        </div>
        <div class="mb-3 d-none" id="schedule-form-title-filter-row">
          <label class="form-label" for="schedule-form-title-filter"><?= gettext('Only events whose title contains') ?></label>
          <input type="text" class="form-control" id="schedule-form-title-filter" maxlength="100">
        </div>
        <div class="row g-2 d-none" id="schedule-form-standalone-rows">
          <div class="col-12 col-md-6 mb-3">
            <label class="form-label" for="schedule-form-dow"><?= gettext('Day of the week') ?></label>
            <select class="form-select" id="schedule-form-dow">
              <option value="Sunday"><?= gettext('Sunday') ?></option>
              <option value="Monday"><?= gettext('Monday') ?></option>
              <option value="Tuesday"><?= gettext('Tuesday') ?></option>
              <option value="Wednesday"><?= gettext('Wednesday') ?></option>
              <option value="Thursday"><?= gettext('Thursday') ?></option>
              <option value="Friday"><?= gettext('Friday') ?></option>
              <option value="Saturday"><?= gettext('Saturday') ?></option>
            </select>
          </div>
          <div class="col-6 col-md-3 mb-3">
            <label class="form-label" for="schedule-form-start-time"><?= gettext('Starts') ?></label>
            <input type="time" class="form-control" id="schedule-form-start-time">
          </div>
          <div class="col-6 col-md-3 mb-3">
            <label class="form-label" for="schedule-form-end-time"><?= gettext('Ends') ?></label>
            <input type="time" class="form-control" id="schedule-form-end-time">
          </div>
        </div>
        <div class="row g-2">
          <div class="col-12 col-md-6 mb-3">
            <label class="form-label" for="schedule-form-window-start"><?= gettext('First date') ?></label>
            <input type="date" class="form-control" id="schedule-form-window-start">
          </div>
          <div class="col-12 col-md-6 mb-3">
            <label class="form-label" for="schedule-form-window-end"><?= gettext('Last date') ?></label>
            <input type="date" class="form-control" id="schedule-form-window-end">
          </div>
        </div>
        <label class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="schedule-form-active" checked>
          <span class="form-check-label"><?= gettext('Active') ?></span>
        </label>

        <!--
          Staffing needs (§2.10). A requirement is a separate entity from a position and
          nothing used to create one, so a schedule had none: its occurrences needed
          nobody, had no gaps, and reported "Fully staffed" at 0/0. The rows are rendered
          by webpack/volunteer/staffing-needs.ts from the team's active positions; the
          empty-plan warning is created by that module as a sibling of the list, so a
          re-render on a team change cannot take it away.
        -->
        <hr class="my-3">
        <div class="mb-2">
          <h6 class="mb-1"><i class="fa-solid fa-list-check me-2"></i><?= gettext('Staffing needs') ?></h6>
          <div class="form-text" id="schedule-form-needs-hint">
            <?= gettext('How many volunteers each occurrence of this schedule needs. Uncheck a position this schedule never uses.') ?>
          </div>
        </div>
        <div id="schedule-form-needs"></div>

        <div class="alert alert-danger d-none mt-3" role="alert" id="schedule-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="schedule-form-save"><?= gettext('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  Filter the Occurrences tab by date.

  The tab defaults to what is still to come; this dialog is how the weeks that
  already happened are reached. The range defaults to the last 90 days up to
  yesterday — "what did we just do" — and the list endpoint already takes a
  mandatory `from`/`to` window (design M9), so nothing new is asked of the API.
-->
<div class="modal fade" id="occurrenceRangeModal" tabindex="-1" aria-hidden="true" aria-labelledby="occurrenceRangeModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="occurrenceRangeModalTitle"><?= gettext('Filter by Date') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= gettext('Show the occurrences between two dates, including ones that have already happened.') ?>
        </p>
        <div class="row g-2">
          <div class="col-12 col-sm-6 mb-3">
            <label class="form-label" for="occurrence-range-from"><?= gettext('From') ?></label>
            <input type="date" class="form-control" id="occurrence-range-from">
          </div>
          <div class="col-12 col-sm-6 mb-3">
            <label class="form-label" for="occurrence-range-to"><?= gettext('To') ?></label>
            <input type="date" class="form-control" id="occurrence-range-to">
          </div>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="occurrence-range-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="occurrence-range-show"><?= gettext('Show') ?></button>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.volunteerMinistry = {
  ministryId: <?= (int) $iMinistryId ?>,
  isManager: <?= $bIsManager ? 'true' : 'false' ?>,
  isMinistryCoordinator: <?= $bIsMinistryCoordinator ? 'true' : 'false' ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/volunteer-ministry.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
