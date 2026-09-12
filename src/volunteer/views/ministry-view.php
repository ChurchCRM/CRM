<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S3 — ministry detail (design §5.4).
 *
 * Tabbed, each tab loaded lazily on its first activation. Markup only: the route
 * decided what may be shown and the tab contents come from
 * `/api/volunteer/ministries/{id}` — no queries here
 * (groups-mvc-guidelines.md).
 *
 * Every string is `gettext()`. The JS strings live in
 * webpack/volunteer/ministry.ts, because an `i18next.t()` call inside a .php file
 * is scanned by neither the PHP nor the JS extractor and is silently never
 * translated (design §5.10, F31).
 *
 * **Extension points for the rest of the epic.** The tab strip and the tab
 * content are both marked below. #9707 put the pool list inside the Teams tab
 * (renamed "Teams & Pools" — a pool belongs beside the team it feeds) and added
 * a Qualifications tab; #9708/#9711 append a Schedules tab. Each is one `<li>`
 * plus one `.tab-pane`, and the lazy-load registry in ministry.ts takes one more
 * entry — nothing here has to move.
 */

/** @var string $sRootPath */
/** @var int $iMinistryId */
/** @var string $sMinistryName */
/** @var bool $bMinistryActive */
/** @var bool $bIsManager */

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
    <a href="<?= $sRootPath ?>/volunteer/setup?ministryId=<?= (int) $iMinistryId ?>" class="btn btn-sm btn-outline-primary">
      <i class="fa-solid fa-wand-magic-sparkles me-1"></i><?= gettext('Guided setup') ?>
    </a>
  </div>

  <ul class="nav nav-tabs" id="volunteer-ministry-tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link active" id="nav-item-overview" href="#overview" data-bs-toggle="tab" role="tab" aria-controls="overview" aria-selected="true">
        <i class="fa-solid fa-circle-info me-1"></i><?= gettext('Overview') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-teams" href="#teams" data-bs-toggle="tab" role="tab" aria-controls="teams" aria-selected="false">
        <i class="fa-solid fa-people-group me-1"></i><?= gettext('Teams & Pools') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-positions" href="#positions" data-bs-toggle="tab" role="tab" aria-controls="positions" aria-selected="false">
        <i class="fa-solid fa-list-check me-1"></i><?= gettext('Positions') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-qualifications" href="#qualifications" data-bs-toggle="tab" role="tab" aria-controls="qualifications" aria-selected="false">
        <i class="fa-solid fa-user-check me-1"></i><?= gettext('Qualifications') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-occurrences" href="#occurrences" data-bs-toggle="tab" role="tab" aria-controls="occurrences" aria-selected="false">
        <i class="fa-solid fa-calendar-days me-1"></i><?= gettext('Occurrences') ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" id="nav-item-schedules" href="#schedules" data-bs-toggle="tab" role="tab" aria-controls="schedules" aria-selected="false">
        <i class="fa-solid fa-repeat me-1"></i><?= gettext('Schedules') ?>
      </a>
    </li>
  </ul>

  <div class="card-body tab-content">

    <!-- Overview -->
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
              <div class="h1 mb-0" id="overview-position-count">0</div>
              <div class="text-body-secondary"><?= gettext('Positions') ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card card-sm">
            <div class="card-body text-center">
              <div class="h1 mb-0" id="overview-active-position-count">0</div>
              <div class="text-body-secondary"><?= gettext('Active positions') ?></div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <p class="text-body-secondary mb-0" id="overview-description"></p>
        </div>
      </div>
    </div>

    <!-- Teams & Pools -->
    <div class="tab-pane fade" id="teams" role="tabpanel" aria-labelledby="nav-item-teams">
      <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-primary btn-sm" id="team-add-btn">
          <i class="fa-solid fa-plus me-1"></i><?= gettext('Add team') ?>
        </button>
      </div>
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
      <div class="table-responsive d-none" id="teams-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerTeamsTable">
          <thead>
            <tr>
              <th><?= gettext('Name') ?></th>
              <th><?= gettext('Description') ?></th>
              <th class="text-center"><?= gettext('Positions') ?></th>
              <th class="text-center"><?= gettext('Status') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <!--
        Volunteer pools (#9707, design §2.5). A pool is a LINK to an existing
        Group — the Group stays the roster and V2 copies nobody (D1). The member
        count below is therefore read-only here and the row links into the
        Groups module, which is where membership is edited (Appendix D-1).
      -->
      <hr class="my-4">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
        <h4 class="mb-0"><i class="fa-solid fa-users me-2"></i><?= gettext('Volunteer pools') ?></h4>
        <button type="button" class="btn btn-primary btn-sm" id="pool-add-btn">
          <i class="fa-solid fa-link me-1"></i><?= gettext('Link a Group') ?>
        </button>
      </div>
      <p class="text-body-secondary" id="pools-membership-note">
        <i class="fa-solid fa-circle-info me-1"></i>
        <?= gettext('Who is in a pool is decided by the Group. Open the group to add or remove people — that needs the Manage Groups permission.') ?>
      </p>
      <div class="empty d-none" id="pools-empty">
        <div class="empty-icon"><i class="fa-solid fa-users fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('No volunteer pool yet') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('Choose the Group whose members volunteer for this ministry. Nobody is copied — the Group stays in charge of who belongs.') ?>
        </p>
      </div>
      <div class="table-responsive d-none" id="pools-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerPoolsTable">
          <thead>
            <tr>
              <th><?= gettext('Group') ?></th>
              <th><?= gettext('Serves') ?></th>
              <th><?= gettext('Label') ?></th>
              <th class="text-center"><?= gettext('Members') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
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
      <div class="table-responsive d-none" id="positions-table-wrapper">
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
      Qualifications (#9707, design §5.4). People (rows) × positions (columns),
      one checkbox per cell. The whole grid comes from ONE
      /qualification-matrix response — §5.4 is explicit that 15–200 pool members
      must render without a request per cell.

      Deliberately NOT a DataTable: the column set is data-driven and every cell
      is an input, so DataTables' row cache would fight the optimistic toggle.
      The filter box below is the one DataTables feature this grid actually
      needs.
    -->
    <div class="tab-pane fade" id="qualifications" role="tabpanel" aria-labelledby="nav-item-qualifications">
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

      <div class="volunteer-loading text-center py-4" id="qualifications-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="qualifications-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="qualifications-empty">
        <div class="empty-icon"><i class="fa-solid fa-user-check fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('Nothing to qualify yet') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('Link a Group as the volunteer pool and add at least one position, then tick who can serve where.') ?>
        </p>
      </div>
      <div class="table-responsive d-none" id="qualifications-table-wrapper">
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

    <!--
      Occurrences (#9709). A minimal upcoming list with gap counts, so the staffing
      view (S4) is reachable from the ministry page - #9708 and #9715 left no hook for
      it, and #9711's dashboard is the richer answer. Deliberately small: the counts
      come from GET /api/volunteer/occurrences, which serves them from the single gap
      implementation, and nothing is re-derived here.
    -->
    <div class="tab-pane fade" id="occurrences" role="tabpanel" aria-labelledby="nav-item-occurrences">
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
      <div class="table-responsive d-none" id="occurrences-table-wrapper">
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
      <div class="table-responsive d-none" id="schedules-table-wrapper">
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

  </div>
</div>

<!-- Team editor -->
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
            <select class="form-select" id="position-form-team"></select>
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
  eligibility rule — so a coordinator may qualify anyone. The picker is the
  shared person selector (CR1/#9819) pointed at the core person search, not a
  second widget.
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
          <?= gettext('Anyone can be qualified, whether or not they are in a pool group. Being in the pool is what makes someone a candidate; the qualification is what makes them assignable.') ?>
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
          <select class="form-select" id="schedule-form-team"></select>
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.volunteerMinistry = {
  ministryId: <?= (int) $iMinistryId ?>,
  isManager: <?= $bIsManager ? 'true' : 'false' ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/volunteer-ministry.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
