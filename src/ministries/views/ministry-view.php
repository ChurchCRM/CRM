<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S3 — ministry detail (design §5.4, as amended by the product owner).
 *
 * Seven tabs, each loaded lazily on its first activation: **Overview · Positions ·
 * Volunteers · Schedules · Occurrences · Calendar · Help Wanted**. Markup only: the route
 * decided what may be shown and the tab contents come from
 * `/api/ministries/ministries/{id}` — no queries here
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
 *   - **Adding a volunteer no longer qualifies them.** "Add Volunteer" and "Add from
 *     Cart" put people in the ministry's pool and grant nothing, so neither dialog
 *     has a position select; the ticks on the grid are the second step.
 *   - **The Occurrences tab has a search form**, not a "Filter by Date" dialog —
 *     Team · Event · From · To above the table, every field live.
 *
 * Every string is `gettext()`. The JS strings live in
 * webpack/ministries/ministry.ts, because an `i18next.t()` call inside a .php file
 * is scanned by neither the PHP nor the JS extractor and is silently never
 * translated (design §5.10, F31).
 */

/** @var string $sRootPath */
/** @var int $iMinistryId */
/** @var string $sMinistryName */
/** @var bool $bMinistryActive */
/** @var bool $bIsManager */
/** @var bool $bIsMinistryCoordinator */
/** @var bool $bCanPinAnyCalendar the viewer holds Add Events, so every calendar is offered */
/** @var string|null $sClassUrlBase where a linked class opens, or null when the viewer cannot open groups */

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
    <!--
      Lifecycle (product-owner decision, 2026-09-17). An active ministry offers
      Deactivate to anyone who may open the page — a coordinator may deactivate their
      own ministry (§4.6 "Edit / deactivate: scope"). A deactivated one offers
      Reactivate, and — manager-only, because deletion is (§4.6) — Delete, which the
      API refuses for an active ministry regardless of what is rendered (D5). The
      counts the Delete dialog quotes come from the ministry document's summary.
    -->
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-primary btn-sm" id="ministry-edit-btn">
        <i class="fa-solid fa-pen me-1"></i><?= gettext('Edit') ?>
      </button>
      <?php if ($bMinistryActive): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="ministry-deactivate-btn"
                data-ministry-name="<?= InputUtils::escapeHTML($sMinistryName) ?>">
          <i class="fa-solid fa-box-archive me-1"></i><?= gettext('Deactivate') ?>
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-outline-primary btn-sm" id="ministry-reactivate-btn"
                data-ministry-name="<?= InputUtils::escapeHTML($sMinistryName) ?>">
          <i class="fa-solid fa-rotate-left me-1"></i><?= gettext('Reactivate') ?>
        </button>
        <?php if ($bIsManager): ?>
          <button type="button" class="btn btn-outline-danger btn-sm" id="ministry-delete-btn"
                  data-ministry-name="<?= InputUtils::escapeHTML($sMinistryName) ?>">
            <i class="fa-solid fa-trash me-1"></i><?= gettext('Delete') ?>
          </button>
        <?php endif; ?>
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
      <a class="nav-link" id="nav-item-calendar" href="#calendar" data-bs-toggle="tab" role="tab" aria-controls="calendar" aria-selected="false">
        <i class="fa-solid fa-calendar-plus me-1"></i><?= gettext('Calendar') ?>
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
        (design §2.15, §4.4; the API is `/api/ministries/scopes`).

        A SELF-CONTAINED block on purpose: everything it needs lives between this
        comment and the closing endif below, and its behaviour lives in its own
        bundle module (webpack/ministries/scopes.ts), which ministry.ts only imports
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
        <!-- Hidden until the list is long enough to need it (more than five
             names): on a short list it reads as a way to ADD a volunteer. -->
        <div class="col-12 col-md-4 d-none" id="qualification-filter-wrap">
          <label class="form-label" for="qualification-filter"><?= gettext('Find a volunteer') ?></label>
          <input type="search" class="form-control" id="qualification-filter"
                 placeholder="<?= InputUtils::escapeAttribute(gettext('Start typing a name')) ?>">
        </div>
        <!--
          Adding a volunteer and saying what they can do are two steps now. Both
          buttons put people in the ministry's pool and grant nothing; the ticks on
          the grid below are the second step, which is why neither dialog carries a
          position selector any more.
        -->
        <div class="col-12 col-md-4 ms-md-auto d-flex gap-2 justify-content-md-end">
          <button type="button" class="btn btn-outline-primary" id="qualification-add-person">
            <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add Volunteer') ?>
          </button>
          <button type="button" class="btn btn-outline-primary" id="qualification-cart-btn">
            <i class="fa-solid fa-cart-shopping me-1"></i><?= gettext('Add from Cart') ?>
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
        There is deliberately no Save button: each tick is its own write, and each one
        confirms itself in the standard top-right notification. The hint says so,
        because a grid of checkboxes with no Save button otherwise reads as unsaved
        work.

        The confirmation used to be a badge in a status slot beside each box, which
        meant every save nudged the checkbox column sideways for a second and a half.
        Nothing in a cell moves now — the cell is the checkbox and nothing else.
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
              <!--
                "Recruit Volunteers" (round four). A green check or nothing — the
                empty cell IS the "no" state, because a column of red crosses reads
                as a column of problems. Sortable like every other column, so a
                coordinator can bring the advertised roles together.
              -->
              <th class="text-center"><?= gettext('Recruiting') ?></th>
              <th class="text-center"><?= gettext('Self sign-up') ?></th>
              <th class="text-center"><?= gettext('Status') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!--
      Schedules (#9711). Which calendar events each team staffs (D22): create one,
      generate its occurrences, open the dates it produced. Generation is idempotent
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
          <?= gettext('A schedule says which calendar events a team staffs: a type of church service, a class\'s meetings, or this ministry\'s own events. Add one and generate its occurrences.') ?>
        </p>
      </div>
      <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="schedules-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerSchedulesTable">
          <thead>
            <tr>
              <th><?= gettext('Name') ?></th>
              <th><?= gettext('Which events') ?></th>
              <th><?= gettext('Team') ?></th>
              <th class="text-center"><?= gettext('Occurrences') ?></th>
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
      come from GET /api/ministries/occurrences, which serves them from the single gap
      implementation, and nothing is re-derived here.
    -->
    <div class="tab-pane fade" id="occurrences" role="tabpanel" aria-labelledby="nav-item-occurrences">
      <!--
        A search form rather than a "Filter by Date" dialog.

        The dialog it replaces could only narrow by date, and everything else — which
        team, which service — had to be read off the table by eye. Four fields, laid
        out like the Volunteers tab's controls above, and every one of them live: a
        change re-runs the query rather than waiting for a Show button.

        From defaults to today, because the tab exists to staff the weeks ahead; the
        weeks that already happened are reached by moving From back, which is one
        field instead of a dialog. To is deliberately EMPTY by default and means "as
        far as it goes" — the list endpoint takes a mandatory from/to window (design
        M9), so ministry.ts sends From + one year when the box is blank.
      -->
      <!--
        Export CSV and Print, above the form rather than beside the table.

        DataTables would put them in its own top row INSIDE the table wrapper,
        which on this tab lands them under the search form and beside a "Search:"
        box that no longer exists — `searching` is off for this table, because the
        Team/Event/From/To form above already narrows the query server-side and a
        second box that filters only the drawn page answers the same question with
        a different answer. ministry.ts moves the buttons here instead; the
        container is emptied before each re-init so a re-run of the query cannot
        leave two toolbars behind.
      -->
      <!-- Delete sits left of the export buttons and wakes up when a row is ticked. -->
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-danger btn-sm" id="occurrences-delete-btn" disabled>
            <i class="fa-solid fa-trash me-1" aria-hidden="true"></i><?= gettext('Delete') ?>
          </button>
          <button type="button" class="btn btn-outline-primary btn-sm" id="occurrences-staff-event-btn">
            <i class="fa-solid fa-calendar-plus me-1" aria-hidden="true"></i><?= gettext('Staff an event') ?>
          </button>
        </div>
        <div class="d-flex justify-content-end" id="occurrences-toolbar"></div>
      </div>
      <div class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-3">
          <label class="form-label" for="occurrence-team-filter"><?= gettext('Team') ?></label>
          <select class="form-select" id="occurrence-team-filter"></select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label" for="occurrence-event-filter"><?= gettext('Event') ?></label>
          <input type="search" class="form-control" id="occurrence-event-filter" maxlength="100"
                 placeholder="<?= InputUtils::escapeAttribute(gettext('Any event')) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="occurrence-from"><?= gettext('From') ?></label>
          <input type="date" class="form-control" id="occurrence-from">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="occurrence-to"><?= gettext('To') ?></label>
          <input type="date" class="form-control" id="occurrence-to">
        </div>
      </div>
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
          <?= gettext('Create a schedule and generate its occurrences, and the weeks to staff appear here.') ?>
        </p>
      </div>
      <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="occurrences-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerOccurrencesTable">
          <thead>
            <tr>
              <th class="w-1 no-export">
                <input type="checkbox" class="form-check-input" id="occurrences-select-all" aria-label="<?= InputUtils::escapeAttribute(gettext('Select all')) ?>" disabled>
              </th>
              <th><?= gettext('When') ?></th>
              <th><?= gettext('Team') ?></th>
              <th><?= gettext('Schedule') ?></th>
              <th class="text-center"><?= gettext('Filled') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!--
      Calendar (D24): the events this ministry owns, created here through core's event
      code and edited in the core event editor. Upcoming by default; the switch shows the
      past year instead, with each event's headcount (D26).
    -->
    <div class="tab-pane fade" id="calendar" role="tabpanel" aria-labelledby="nav-item-calendar">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <label class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="ministry-events-past">
          <span class="form-check-label"><?= gettext('Show past events') ?></span>
        </label>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-danger btn-sm" id="ministry-events-delete-btn" disabled>
            <i class="fa-solid fa-trash me-1" aria-hidden="true"></i><?= gettext('Delete events') ?>
          </button>
          <button type="button" class="btn btn-primary btn-sm" id="ministry-event-add-btn">
            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i><?= gettext('New event') ?>
          </button>
          <button type="button" class="btn btn-outline-primary btn-sm" id="ministry-event-add-series-btn">
            <i class="fa-solid fa-repeat me-1" aria-hidden="true"></i><?= gettext('New recurring event') ?>
          </button>
        </div>
      </div>
      <div class="volunteer-loading text-center py-4" id="ministry-events-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="ministry-events-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="ministry-events-empty">
        <div class="empty-icon"><i class="fa-solid fa-calendar-check fa-2x text-muted"></i></div>
        <div id="ministry-events-empty-upcoming">
          <p class="empty-title"><?= gettext('No upcoming events') ?></p>
          <p class="empty-subtitle text-body-secondary">
            <?= gettext('Add this ministry\'s events here: a workday, a class that meets every Sunday. They go on the church calendar, and once they are created you are asked whether to staff them.') ?>
          </p>
        </div>
        <div class="d-none" id="ministry-events-empty-past">
          <p class="empty-title"><?= gettext('No past events') ?></p>
          <p class="empty-subtitle text-body-secondary"><?= gettext('Nothing this ministry owns happened in the last year.') ?></p>
        </div>
      </div>
      <div class="volunteer-scroll-x d-none" id="ministry-events-table-wrapper">
        <table class="table table-hover table-vcenter" id="volunteerMinistryEventsTable">
          <thead>
            <tr>
              <th class="w-1 no-export">
                <input type="checkbox" class="form-check-input" id="ministry-events-select-all" aria-label="<?= InputUtils::escapeAttribute(gettext('Select all')) ?>" disabled>
              </th>
              <th><?= gettext('Date and time') ?></th>
              <th><?= gettext('Event') ?></th>
              <th><?= gettext('Calendars') ?></th>
              <th><?= gettext('Class') ?></th>
              <th><?= gettext('Staffing') ?></th>
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
          <?= gettext('Put this ministry on the Member Portal Open Opportunities page, where any volunteer can see it and offer to help. They join the volunteer pool and you are emailed.') ?>
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

  Granting is manager-only (§3.2) — the `/api/ministries/scopes` endpoints refuse
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
        <!--
          D23: the Sunday School class this team staffs. Its Teacher role is then
          written from this team's qualifications, and linking qualifies the class's
          current teachers for the position chosen below. Filled by ministry.ts.
        -->
        <div class="mb-3" id="team-form-class-row">
          <label class="form-label" for="team-form-class"><?= gettext('Sunday School Class') ?></label>
          <select class="form-select" id="team-form-class"></select>
          <div class="form-text">
            <?= gettext('Optional. Everyone qualified for a position of this team becomes a teacher of the class, and the class page no longer changes its teachers.') ?>
          </div>
        </div>
        <div class="mb-3 d-none" id="team-form-import">
          <label class="form-label" for="team-form-import-position"><?= gettext('Qualify its current teachers for') ?></label>
          <select class="form-select" id="team-form-import-position"></select>
          <div class="form-text" id="team-form-import-count"></div>
        </div>
        <!--
          D28: the old class's events this ministry created, when the class changes. The
          team → class link and an event's Linked Group are different facts, so nothing
          happens to them unless the coordinator says so here. Filled by ministry.ts.
        -->
        <fieldset class="mb-3 d-none" id="team-form-class-events">
          <legend class="form-label mb-1" id="team-form-class-events-text"></legend>
          <label class="form-check">
            <input class="form-check-input" type="radio" name="team-form-class-events" value="keep" checked>
            <span class="form-check-label" id="team-form-class-events-keep"></span>
          </label>
          <label class="form-check">
            <input class="form-check-input" type="radio" name="team-form-class-events" value="remove">
            <span class="form-check-label" id="team-form-class-events-remove"></span>
          </label>
          <label class="form-check" id="team-form-class-events-move-row">
            <input class="form-check-input" type="radio" name="team-form-class-events" value="move">
            <span class="form-check-label" id="team-form-class-events-move"></span>
          </label>
        </fieldset>
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

<!--
  Edit ministry: its name, description and whether it provides teachers for Sunday School
  (D29). Anyone who may open this page may rename the ministry (§4.6); the Sunday School
  switch is a volunteer manager's, so it is read-only for everybody else and the API refuses
  a change from them regardless (D5).
-->
<div class="modal fade" id="ministryEditModal" tabindex="-1" aria-hidden="true" aria-labelledby="ministryEditModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ministryEditModalTitle"><?= gettext('Edit ministry') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="ministry-edit-name"><?= gettext('Ministry name') ?></label>
          <input type="text" class="form-control" id="ministry-edit-name" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label" for="ministry-edit-description"><?= gettext('Description') ?></label>
          <input type="text" class="form-control" id="ministry-edit-description" maxlength="255">
        </div>
        <div class="mb-3">
          <label class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" id="ministry-edit-sunday-school"<?= $bIsManager ? '' : ' disabled' ?>>
            <span class="form-check-label"><?= gettext('Can this ministry provide teachers for Sunday School?') ?></span>
          </label>
          <div class="form-text" id="ministry-edit-sunday-school-note">
            <?= $bIsManager
                ? gettext('Turn this on for a ministry whose teams teach Sunday School classes. Its teams can then be linked to a class, and its schedules and events can use one.')
                : gettext('Only a volunteer manager can change this.') ?>
          </div>
        </div>
        <div class="alert alert-danger d-none" role="alert" id="ministry-edit-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="ministry-edit-save"><?= gettext('Save') ?></button>
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
        <!--
          Round four: a position can advertise itself. Off by default, and below
          Active on purpose — an inactive position is never advertised whatever
          this switch says, so the order on screen matches the order of the rules.
        -->
        <label class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" id="position-form-recruiting">
          <span class="form-check-label"><?= gettext('Recruit Volunteers') ?></span>
        </label>
        <div class="form-text" id="position-form-recruiting-hint">
          <?= gettext('Advertise this position on the Member Portal Open Opportunities page.') ?>
        </div>
        <!--
          Self-assignable (2026-09-18). Being qualified for a position does not mean a
          volunteer may claim every open date of it: the preacher is chosen, not signed
          up for. Off, the Member Portal never offers the position and self-signup is
          refused; a team leader or coordinator assigns it.
        -->
        <label class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" id="position-form-self-assignable" checked>
          <span class="form-check-label"><?= gettext('Self-assignable') ?></span>
        </label>
        <div class="form-text" id="position-form-self-assignable-hint">
          <?= gettext('Qualified volunteers may sign themselves up for open dates of this position. Turn off for positions only a team leader or coordinator should assign; the Member Portal will not offer them.') ?>
        </div>
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
  Add one volunteer to this ministry (#9707, as amended).

  The dialog used to grant a qualification and carried a position select to say
  which. It does not any more: it puts the person in the ministry's pool Group and
  stops there, because being in the pool is candidacy and the tick on the grid is
  eligibility (§2.5) — two separate statements that were being made in one click.
  The row appears in the grid with no ticks, which is the prompt to make the second.

  The picker is the shared person selector (CR1/#9819) pointed at the core person
  search, not a second widget. The title names the selected team and is written by
  ministry.ts, because only the browser knows which team the grid is showing.
-->
<div class="modal fade" id="addVolunteerModal" tabindex="-1" aria-hidden="true" aria-labelledby="addVolunteerModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addVolunteerModalTitle"><?= gettext('Add Volunteer') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="add-volunteer-person"><?= gettext('Person') ?></label>
          <select class="form-select person-search" id="add-volunteer-person"
                  data-placeholder="<?= InputUtils::escapeAttribute(gettext('Start typing a name')) ?>"></select>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="add-volunteer-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="add-volunteer-save"><?= gettext('Add') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  Add everyone in the cart (#9707, as amended). The Cart is the existing
  bulk-selection mechanism (P5); V2 adds a sink for it and no second selection UI.

  Same change as the dialog above: they join the ministry's volunteers, and nobody
  is qualified for anything — so there is nothing to choose and no position select.
  One request does the whole cart (`POST /ministries/{id}/pool/from-cart`).
-->
<div class="modal fade" id="addFromCartModal" tabindex="-1" aria-hidden="true" aria-labelledby="addFromCartModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addFromCartModalTitle"><?= gettext('Add Everyone in Cart') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary mb-0">
          <?= gettext('Everyone in the cart joins this ministry\'s volunteers. Tick their positions afterwards.') ?>
        </p>
        <div class="alert alert-danger d-none mt-3" role="alert" id="add-from-cart-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="add-from-cart-save"><?= gettext('Add All') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Staff an event (D22): one calendar event, one team, its staffing needs and one occurrence. -->
<div class="modal fade" id="staffEventModal" tabindex="-1" aria-hidden="true" aria-labelledby="staffEventModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="staffEventModalTitle"><?= gettext('Staff an event') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= gettext('Pick an upcoming calendar event and say who it needs. The event keeps its own date and time; to staff a whole series, add a schedule instead.') ?>
        </p>
        <div class="row g-2">
          <div class="col-12 col-md-8 mb-3">
            <label class="form-label" for="staff-event-form-search"><?= gettext('Find an event') ?></label>
            <input type="search" class="form-control" id="staff-event-form-search" maxlength="100"
                   placeholder="<?= InputUtils::escapeAttribute(gettext('Event title')) ?>">
          </div>
          <div class="col-12 col-md-4 mb-3">
            <label class="form-label" for="staff-event-form-date"><?= gettext('Date') ?></label>
            <input type="date" class="form-control" id="staff-event-form-date">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="staff-event-form-event"><?= gettext('Event') ?></label>
          <select class="form-select" id="staff-event-form-event"></select>
        </div>
        <div class="row g-2">
          <div class="col-12 col-md-6 mb-3">
            <label class="form-label" for="staff-event-form-team"><?= gettext('Team') ?></label>
            <select class="form-select" id="staff-event-form-team"></select>
          </div>
          <div class="col-12 col-md-6 mb-3">
            <label class="form-label" for="staff-event-form-name"><?= gettext('Name') ?></label>
            <input type="text" class="form-control" id="staff-event-form-name" maxlength="100"
                   placeholder="<?= InputUtils::escapeAttribute(gettext('The event title')) ?>">
          </div>
        </div>
        <div class="mb-3" id="staff-event-form-offsets"></div>
        <hr class="my-3">
        <div class="mb-2">
          <h6 class="mb-1"><i class="fa-solid fa-list-check me-2"></i><?= gettext('Staffing needs') ?></h6>
          <div class="form-text"><?= gettext('How many volunteers this event needs. Uncheck a position it does not use.') ?></div>
        </div>
        <div id="staff-event-form-needs"></div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="staff-event-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="staff-event-form-save"><?= gettext('Staff this event') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  Generate occurrences (review, 2026-09-18). One row per position the schedule's plan
  asks for, each with a "Fill by default with" picker over the same rotation-ordered
  list the Assign dialog uses. A chosen person is assigned on every occurrence THIS
  run creates; "Set as Accepted" records their acceptance too, so they are not asked.
-->
<div class="modal fade" id="generateOccurrencesModal" tabindex="-1" aria-hidden="true" aria-labelledby="generateOccurrencesModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="generateOccurrencesModalTitle"><?= gettext('Generate occurrences') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary" id="generate-form-intro"></p>
        <p class="text-body-secondary">
          <?= gettext('Choose who fills each position by default. The choice is saved on the schedule: they are assigned on every occurrence made now and on those the daily top-up makes later, while they stay qualified, and asked to respond unless you set them as accepted. Leave a position open to assign it week by week.') ?>
        </p>
        <div class="volunteer-loading text-center py-3" id="generate-form-loading">
          <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
          <?= gettext('Loading') ?>
        </div>
        <div class="empty py-3 d-none" id="generate-form-empty">
          <p class="empty-title"><?= gettext('This schedule has no staffing needs yet') ?></p>
          <p class="empty-subtitle text-body-secondary">
            <?= gettext('Its occurrences will be made without positions to fill. Edit the schedule to add its staffing needs.') ?>
          </p>
        </div>
        <div id="generate-form-rows"></div>
        <!-- D30: a run that found no events says why, instead of a success toast. -->
        <div class="alert alert-warning d-none mt-3" role="status" id="generate-form-warning">
          <div class="d-flex">
            <i class="fa-solid fa-triangle-exclamation me-2 mt-1" aria-hidden="true"></i>
            <div>
              <div class="fw-medium" id="generate-form-warning-text"></div>
              <div class="mt-1" id="generate-form-warning-hint"></div>
              <button type="button" class="btn btn-sm btn-warning mt-2 d-none" id="generate-form-add-events"></button>
            </div>
          </div>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="generate-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i>
          <span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="generate-form-save"><?= gettext('Generate') ?></button>
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
            <option value="event_type"><?= gettext('Church events of a type') ?></option>
            <option value="class"><?= gettext("A class's meetings") ?></option>
            <option value="ministry"><?= gettext("This ministry's events") ?></option>
          </select>
          <div class="form-text"><?= gettext('The date and time of every occurrence come from its calendar event, so moving the event moves the schedule.') ?></div>
        </div>
        <div class="mb-3 d-none" id="schedule-form-event-type-row">
          <label class="form-label" for="schedule-form-event-type"><?= gettext('Event type') ?></label>
          <select class="form-select" id="schedule-form-event-type"></select>
        </div>
        <div class="mb-3 d-none" id="schedule-form-group-row">
          <label class="form-label" for="schedule-form-group"><?= gettext('Class') ?></label>
          <select class="form-select" id="schedule-form-group"></select>
          <div class="form-text"><?= gettext('One occurrence is made for each calendar event whose Linked Group is this class.') ?></div>
          <div class="form-text d-none" id="schedule-form-group-hint"></div>
          <div class="alert alert-warning py-2 mt-2 mb-0 d-none" role="status" id="schedule-form-group-warning"></div>
        </div>
        <div class="mb-3 d-none" id="schedule-form-title-filter-row">
          <label class="form-label" for="schedule-form-title-filter"><?= gettext('Event') ?></label>
          <select class="form-select" id="schedule-form-title-filter"></select>
          <div class="form-text"><?= gettext('One occurrence is made for each date of this event. Only events already on the calendar can be chosen.') ?></div>
          <div class="alert alert-warning py-2 mt-2 mb-0 d-none" role="status" id="schedule-form-title-warning"></div>
        </div>
        <div class="d-none" id="schedule-form-details">
        <div class="mb-3" id="schedule-form-offsets"></div>
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

        <!--
          Staffing needs (§2.10). A requirement is a separate entity from a position and
          nothing used to create one, so a schedule had none: its occurrences needed
          nobody, had no gaps, and reported "Fully staffed" at 0/0. The rows are rendered
          by webpack/ministries/staffing-needs.ts from the team's active positions; the
          empty-plan warning is created by that module as a sibling of the list, so a
          re-render on a team change cannot take it away.
        -->
        <hr class="my-3">
        <div class="mb-2">
          <h6 class="mb-1"><i class="fa-solid fa-list-check me-2"></i><?= gettext('Staffing needs') ?></h6>
          <div class="form-text" id="schedule-form-needs-hint">
            <?= gettext('How many volunteers each occurrence of this schedule needs. Uncheck a position this schedule never uses. A default volunteer is assigned on every new occurrence while they stay qualified; changing one never changes occurrences that already exist.') ?>
          </div>
        </div>
        <div id="schedule-form-needs"></div>
        </div>

        <div class="alert alert-danger d-none mt-3" role="alert" id="schedule-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary d-none" id="schedule-form-save"><?= gettext('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  New event / New recurring event (D24). One dialog with a One-time / Recurring switch. The
  events are created through core with this ministry's id, and only created: staffing them is
  the question asked after Create (D33).
-->
<div class="modal fade" id="ministryEventModal" tabindex="-1" aria-hidden="true" aria-labelledby="ministryEventModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ministryEventModalTitle"><?= gettext('New event') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="btn-group w-100 mb-3" role="group" aria-label="<?= InputUtils::escapeAttribute(gettext('How often')) ?>">
          <input type="radio" class="btn-check" name="ministry-event-kind" id="ministry-event-form-once" value="once" checked>
          <label class="btn btn-outline-primary" for="ministry-event-form-once"><?= gettext('One-time') ?></label>
          <input type="radio" class="btn-check" name="ministry-event-kind" id="ministry-event-form-series" value="series">
          <label class="btn btn-outline-primary" for="ministry-event-form-series"><?= gettext('Recurring') ?></label>
        </div>
        <div class="row g-2">
          <div class="col-12 col-md-7 mb-3">
            <label class="form-label" for="ministry-event-form-title"><?= gettext('Title') ?></label>
            <input type="text" class="form-control" id="ministry-event-form-title" maxlength="255">
          </div>
          <div class="col-12 col-md-5 mb-3">
            <label class="form-label" for="ministry-event-form-type"><?= gettext('Event type') ?></label>
            <select class="form-select" id="ministry-event-form-type"></select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="ministry-event-form-description"><?= gettext('Description') ?></label>
          <textarea class="form-control" id="ministry-event-form-description" rows="2" maxlength="255"></textarea>
        </div>
        <div class="mb-3" id="ministry-event-form-class-row">
          <label class="form-label" for="ministry-event-form-class"><?= gettext('Class') ?></label>
          <select class="form-select" id="ministry-event-form-class"></select>
          <div class="form-text"><?= gettext('Optional. The class becomes the Linked Group: its roster is who checks in, and a schedule for the class finds these events.') ?></div>
        </div>
        <div class="row g-2" id="ministry-event-form-once-fields">
          <div class="col-12 col-md-4 mb-3">
            <label class="form-label" for="ministry-event-form-date"><?= gettext('Date') ?></label>
            <input type="date" class="form-control" id="ministry-event-form-date">
          </div>
        </div>
        <div class="d-none" id="ministry-event-form-series-fields">
          <div class="row g-2">
            <div class="col-12 col-md-4 mb-3">
              <label class="form-label" for="ministry-event-form-recur-type"><?= gettext('Repeats') ?></label>
              <select class="form-select" id="ministry-event-form-recur-type">
                <option value="weekly"><?= gettext('Weekly') ?></option>
                <option value="monthly"><?= gettext('Monthly') ?></option>
                <option value="yearly"><?= gettext('Yearly') ?></option>
              </select>
            </div>
            <div class="col-12 col-md-8 mb-3" id="ministry-event-form-dow-row">
              <label class="form-label" for="ministry-event-form-dow"><?= gettext('Day of the week') ?></label>
              <select class="form-select" id="ministry-event-form-dow"></select>
            </div>
            <div class="col-12 col-md-8 mb-3 d-none" id="ministry-event-form-dom-row">
              <label class="form-label" for="ministry-event-form-dom"><?= gettext('Day of the month') ?></label>
              <input type="number" class="form-control" id="ministry-event-form-dom" min="1" max="31" step="1">
              <div class="form-text"><?= gettext('A day a month does not have falls on its last day.') ?></div>
            </div>
            <div class="col-12 col-md-8 mb-3 d-none" id="ministry-event-form-doy-row">
              <label class="form-label" for="ministry-event-form-doy-month"><?= gettext('Date each year') ?></label>
              <div class="d-flex gap-2">
                <select class="form-select" id="ministry-event-form-doy-month" aria-label="<?= InputUtils::escapeAttribute(gettext('Month')) ?>"></select>
                <input type="number" class="form-control w-auto" id="ministry-event-form-doy-day" min="1" max="31" step="1" aria-label="<?= InputUtils::escapeAttribute(gettext('Day')) ?>">
              </div>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-12 col-md-6 mb-3">
              <label class="form-label" for="ministry-event-form-range-start"><?= gettext('First date') ?></label>
              <input type="date" class="form-control" id="ministry-event-form-range-start">
            </div>
            <div class="col-12 col-md-6 mb-3">
              <label class="form-label" for="ministry-event-form-range-end"><?= gettext('Last date') ?></label>
              <input type="date" class="form-control" id="ministry-event-form-range-end" required>
              <div class="form-text text-warning d-none" role="status" id="ministry-event-form-range-end-note"></div>
            </div>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-6 col-md-4 mb-3">
            <label class="form-label" for="ministry-event-form-start-time"><?= gettext('Start time') ?></label>
            <input type="time" class="form-control" id="ministry-event-form-start-time">
          </div>
          <div class="col-6 col-md-4 mb-3">
            <label class="form-label" for="ministry-event-form-end-time"><?= gettext('End time') ?></label>
            <input type="time" class="form-control" id="ministry-event-form-end-time">
          </div>
        </div>
        <p class="alert alert-info py-2 d-none" id="ministry-event-form-preview"></p>
        <div class="mb-3">
          <div class="form-label"><?= gettext('Calendars') ?></div>
          <div id="ministry-event-form-calendars"></div>
          <div class="form-text" id="ministry-event-form-calendars-hint"><?= $bCanPinAnyCalendar
              ? gettext('You may add events to every calendar, so all of them are listed. This ministry\'s own calendar is ticked to start with.')
              : gettext('Only the calendars this ministry may add events to are listed. Its own calendar is ticked to start with.') ?></div>
        </div>
        <div class="alert alert-danger d-none mt-3" role="alert" id="ministry-event-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="ministry-event-form-save"><?= gettext('Create') ?></button>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.volunteerMinistry = {
  ministryId: <?= (int) $iMinistryId ?>,
  isManager: <?= $bIsManager ? 'true' : 'false' ?>,
  isMinistryCoordinator: <?= $bIsMinistryCoordinator ? 'true' : 'false' ?>,
  classUrlBase: <?= InputUtils::jsonEncodeForScript($sClassUrlBase ?? null) ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/ministries-ministry.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
