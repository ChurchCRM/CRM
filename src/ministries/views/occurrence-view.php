<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * S4 — the occurrence / staffing view (#9709, design §5.5).
 *
 * Markup only. The route decided what may be shown and everything below the header
 * comes from `GET /api/ministries/occurrences/{id}/staffing` — no queries here
 * (groups-mvc-guidelines.md).
 *
 * Every string is `gettext()`. The JS strings live in
 * `webpack/ministries/occurrence.ts`, because an `i18next.t()` call inside a `.php` file
 * is scanned by neither the PHP nor the JS extractor and is silently never translated
 * (design §5.10, F31).
 *
 * The §5.8 states are all here and are driven from the TS by one `renderState()`: a
 * loading block re-shown on **every** attempt, a Tabler `.empty` block, an error block
 * with a Retry that genuinely re-runs the load, and toasts through `window.CRM.notify`.
 * There are two independent state machines on this page — the requirements and the swap
 * queue — because they are two fetches and one failing must not blank the other.
 *
 * Icons are Font Awesome only (§5.9); the requirement cards collapse from three per row
 * (`col-lg-4`) to one below `md`, and every dropdown trigger carries
 * `data-bs-display="static"` (emitted by `window.CRM.buildActionMenu()`) so it is not
 * clipped inside a scrolling container.
 */

/** @var string $sRootPath */
/** @var int $iOccurrenceId */
/** @var int $iMinistryId */
/** @var string $sMinistryName */
/** @var string $sTeamName */
/** @var string $sScheduleName */
/** @var string $sOccurrenceStatus */
/** @var string $sOccurrenceDate */
/** @var string $sStart */
/** @var string $sEnd */
/** @var int $iEventId */
/** @var string $sEventTitle */
/** @var string $sEventLocation */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="volunteer-occurrence">

  <!-- Header: when, where the time comes from, and what state the occurrence is in -->
  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-start justify-content-between">
      <div>
        <h3 class="card-title mb-1"><?= InputUtils::escapeHTML($sScheduleName) ?></h3>
        <div class="text-body-secondary" id="occurrence-when">
          <i class="fa-solid fa-clock me-1"></i>
          <?= InputUtils::escapeHTML($sStart !== '' ? $sStart : $sOccurrenceDate) ?>
          <?php if ($sEnd !== ''): ?>
            &ndash; <?= InputUtils::escapeHTML(substr($sEnd, 11)) ?>
          <?php endif; ?>
        </div>
        <div class="text-body-secondary" id="occurrence-ministry">
          <i class="fa-solid fa-people-group me-1"></i>
          <?= InputUtils::escapeHTML($sMinistryName) ?>
          <?php if ($sTeamName !== ''): ?>
            &middot; <?= InputUtils::escapeHTML($sTeamName) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="text-end">
        <span class="badge <?= $sOccurrenceStatus === 'cancelled' ? 'bg-red-lt text-red' : 'bg-green-lt text-green' ?>" id="occurrence-status">
          <?= $sOccurrenceStatus === 'cancelled' ? gettext('Cancelled') : gettext('Scheduled') ?>
        </span>
        <?php if ($iEventId > 0): ?>
          <!--
            D4 made visible: a linked occurrence keeps NO times of its own, so the
            coordinator is told where the time actually comes from and can go change it
            in the one place that owns it. #9713 names that event and, when it has one,
            says where it happens — several occurrences may share one event (UC3), so
            "this event" on its own is not enough to act on.
          -->
          <div class="mt-2 small" id="occurrence-event">
            <a href="<?= $sRootPath ?>/event/view/<?= (int) $iEventId ?>" id="occurrence-event-link">
              <i class="fa-solid fa-calendar-day me-1"></i><?= $sEventTitle !== '' ? InputUtils::escapeHTML($sEventTitle) : gettext('Times come from this event') ?>
            </a>
            <div class="text-body-secondary"><?= gettext('Times come from this event') ?></div>
            <?php if ($sEventLocation !== ''): ?>
              <div class="text-body-secondary">
                <i class="fa-solid fa-location-dot me-1"></i><?= InputUtils::escapeHTML($sEventLocation) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Requirements and their assignment rows -->
  <div class="card mb-3">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
      <h4 class="card-title mb-0"><i class="fa-solid fa-list-check me-2"></i><?= gettext('Staffing') ?></h4>
      <div class="d-flex flex-wrap gap-2">
        <!--
          The needs editor (§2.10). Same authorization as staffing the occurrence: the
          route already turned away anyone who may not manage this occurrence, so
          reaching this markup at all IS the permission, exactly as the Assign buttons
          below are gated.
        -->
        <button type="button" class="btn btn-sm btn-outline-primary" id="requirements-edit">
          <i class="fa-solid fa-sliders me-1"></i><?= gettext('Edit staffing needs') ?>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="requirements-refresh">
          <i class="fa-solid fa-rotate me-1"></i><?= gettext('Refresh') ?>
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="volunteer-loading text-center py-4" id="requirements-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="requirements-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <!--
        The empty plan. This used to read "Nobody is needed yet" and point at a screen
        that did not exist — staffing requirements were a separate entity with an API and
        no UI, so a schedule had none and every occurrence it generated reported itself
        fully staffed at 0/0. The wording now names the state and the button fixes it.
      -->
      <div class="empty d-none" id="requirements-empty">
        <div class="empty-icon"><i class="fa-solid fa-calendar-check fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('No staffing needs set') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('Nobody has said how many volunteers each position needs, so there is nothing to fill.') ?>
        </p>
        <div class="empty-action">
          <button type="button" class="btn btn-primary" id="requirements-empty-edit">
            <i class="fa-solid fa-sliders me-1"></i><?= gettext('Set staffing needs') ?>
          </button>
        </div>
      </div>
      <div class="row g-3 d-none" id="requirements-content"></div>
    </div>
  </div>

  <!-- Assignments whose position no longer has a requirement (vasg_vreq_ID is SET NULL) -->
  <div class="card mb-3 d-none" id="other-assignments-card">
    <div class="card-header">
      <h4 class="card-title mb-0"><i class="fa-solid fa-circle-question me-2"></i><?= gettext('Assigned outside the current plan') ?></h4>
    </div>
    <div class="card-body">
      <p class="text-body-secondary">
        <?= gettext('These people are still assigned, but the position they hold is no longer part of this occurrence\'s staffing plan.') ?>
      </p>
      <div style="overflow-x: clip; overflow-y: visible;" class="">
        <table class="table table-vcenter" id="other-assignments-table">
          <thead>
            <tr>
              <th><?= gettext('Position') ?></th>
              <th><?= gettext('Volunteer') ?></th>
              <th class="text-center"><?= gettext('Status') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- The swap queue for this occurrence -->
  <div class="card" id="swaps-card">
    <div class="card-header">
      <h4 class="card-title mb-0"><i class="fa-solid fa-right-left me-2"></i><?= gettext('Substitution requests') ?></h4>
    </div>
    <div class="card-body">
      <div class="volunteer-loading text-center py-4" id="swaps-loading">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        <?= gettext('Loading') ?>
      </div>
      <div class="alert alert-danger d-none" role="alert" id="swaps-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <span class="volunteer-error-text"></span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2 volunteer-retry"><?= gettext('Retry') ?></button>
      </div>
      <div class="empty d-none" id="swaps-empty">
        <div class="empty-icon"><i class="fa-solid fa-right-left fa-2x text-muted"></i></div>
        <p class="empty-title"><?= gettext('No substitution requests') ?></p>
        <p class="empty-subtitle text-body-secondary">
          <?= gettext('When a volunteer proposes someone to take their place, it appears here for you to approve or reject.') ?>
        </p>
      </div>
      <div style="overflow-x: clip; overflow-y: visible;" class=" d-none" id="swaps-table-wrapper">
        <table class="table table-vcenter" id="volunteerSwapsTable">
          <thead>
            <tr>
              <th><?= gettext('Position') ?></th>
              <th><?= gettext('Volunteer') ?></th>
              <th><?= gettext('Proposed substitute') ?></th>
              <th><?= gettext('Proposed') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!--
  The eligible picker (§5.5). The list is fetched whole when the modal opens rather
  than searched over AJAX: it is bounded (qualified people for one position), it is
  ORDERED — last served first, which is the rotation — and each entry carries
  annotations (in the pool or not, already serving another position here) that a
  generic person search cannot express. In-pool people come first under their own
  optgroup and out-of-pool qualified people under a "Not in the pool" one, never
  hidden, because assigning them is a supported act behind one confirm (I3).
-->
<div class="modal fade" id="volunteer-assign-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= gettext('Assign a volunteer') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" role="alert" id="assign-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i>
          <span class="volunteer-error-text"></span>
        </div>
        <div class="mb-2 text-body-secondary" id="assign-position-label"></div>
        <div class="mb-3">
          <label class="form-label" for="assign-person-select"><?= gettext('Volunteer') ?></label>
          <select class="form-select" id="assign-person-select"></select>
          <div class="form-hint"><?= gettext('Whoever served least recently is listed first.') ?></div>
        </div>
        <div class="empty d-none" id="assign-empty">
          <div class="empty-icon"><i class="fa-solid fa-user-slash fa-2x text-muted"></i></div>
          <p class="empty-title"><?= gettext('Nobody is qualified for this position yet') ?></p>
          <p class="empty-subtitle text-body-secondary">
            <?= gettext('Grant the qualification on the ministry page, then assign them here.') ?>
          </p>
        </div>
        <!--
          I7 / D16. Multi-position on one occurrence is a SUPPORTED arrangement — one
          person may lead singing and serve communion at the same service — so this is a
          non-blocking caution, not a bootbox gate and not a server error. It exists so a
          coordinator notices an accidental double-booking, never so the system can
          refuse an intentional one.
        -->
        <div class="alert alert-warning d-none" role="alert" id="assign-conflict-warning">
          <i class="fa-solid fa-triangle-exclamation me-1"></i>
          <span class="assign-conflict-text"></span>
        </div>
        <div class="alert alert-warning d-none" role="alert" id="assign-outside-pool-warning">
          <i class="fa-solid fa-user-plus me-1"></i>
          <span class="assign-outside-pool-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="assign-save"><?= gettext('Assign') ?></button>
      </div>
    </div>
  </div>
</div>

<!--
  The staffing-needs editor (§2.10). The same position/checkbox/Min/Max rows the schedule
  form draws — one shared module, `webpack/ministries/staffing-needs.ts`, so a Max-below-Min
  rule cannot be enforced on one screen and not the other.

  Saving writes occurrence-level OVERRIDE rows; "Use the schedule's needs" deletes them so
  the occurrence follows its schedule again. Nothing is copied from the schedule at
  generation time — the merge is derived on every read — so a schedule that gains needs
  today immediately fixes the occurrences it generated last month.
-->
<div class="modal fade" id="volunteer-needs-modal" tabindex="-1" aria-hidden="true" aria-labelledby="needsModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="needsModalTitle"><?= gettext('Edit staffing needs') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" role="alert" id="needs-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i>
          <span class="volunteer-error-text"></span>
        </div>
        <p class="text-body-secondary" id="needs-form-hint"></p>
        <div class="volunteer-loading text-center py-4" id="needs-loading">
          <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
          <?= gettext('Loading') ?>
        </div>
        <div id="needs-form-rows"></div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-link text-danger d-none" id="needs-form-reset">
          <i class="fa-solid fa-rotate-left me-1"></i><?= gettext('Use the schedule\'s needs') ?>
        </button>
        <div class="d-flex gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
          <button type="button" class="btn btn-primary" id="needs-form-save"><?= gettext('Save') ?></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.volunteerOccurrence = {
  occurrenceId: <?= (int) $iOccurrenceId ?>,
  ministryId: <?= (int) $iMinistryId ?>,
  eventId: <?= (int) $iEventId ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/ministries-occurrence.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
