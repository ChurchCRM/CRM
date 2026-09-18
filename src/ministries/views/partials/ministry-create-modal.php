<?php

use ChurchCRM\Utils\InputUtils;

/**
 * The "New ministry" modal, opened from the dashboard's quick actions. It was
 * shared with the ministries list page until that page was retired in favour of
 * the sidebar's Ministries heading; the dashboard is the one place it lives now.
 *
 * Included only inside a manager-only branch: creating a ministry is manager-only
 * (design §4.6), and `POST /api/ministries/ministries` enforces that independently
 * — the markup being absent is a courtesy, not the control (D5).
 *
 * Behaviour lives in webpack/ministries/ministry-create.ts, which the dashboard
 * bundle imports; every JS-side string is `i18next.t()` there, because an
 * `i18next.t()` call inside a .php file is scanned by no extractor (§5.10, F31).
 */
?>
<div class="modal fade" id="ministryCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="ministryCreateModalTitle">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ministryCreateModalTitle"><?= gettext('New ministry') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= gettext('A ministry is created with its first team and its volunteer pool, so the only things to decide here are its name and what it does.') ?>
        </p>
        <div class="mb-3">
          <label class="form-label" for="ministry-create-name"><?= gettext('Ministry name') ?></label>
          <input type="text" class="form-control" id="ministry-create-name" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label" for="ministry-create-description"><?= gettext('Description') ?></label>
          <input type="text" class="form-control" id="ministry-create-description" maxlength="255">
        </div>
        <div class="alert alert-danger d-none" role="alert" id="ministry-create-form-error">
          <i class="fa-solid fa-circle-exclamation me-1"></i><span class="volunteer-error-text"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" class="btn btn-primary" id="ministry-create-save"><?= gettext('Create ministry') ?></button>
      </div>
    </div>
  </div>
</div>
