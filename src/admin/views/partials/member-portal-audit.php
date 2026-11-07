<?php

use ChurchCRM\Service\SignInAuditService;
use ChurchCRM\Utils\InputUtils;

/*
 * Admin → Member Portal → Audit (member sign-in design §11.2): the member
 * sign-in audit, newest first, filtered by person, dates and event.
 * Behaviour: webpack/admin-member-portal/audit-tab.ts.
 */
?>
<div class="tab-pane fade" id="portal-audit" role="tabpanel">
    <p class="text-secondary">
        <?= gettext('Every member sign-in, refusal and change to sign-in, newest first. It never holds a code, link or passkey.') ?>
    </p>
    <form class="card mb-3" id="portalAuditFilters" autocomplete="off">
        <div class="card-body">
            <div class="row g-2 align-items-start">
                <div class="col-12 col-md-6 col-xl-4">
                    <label class="form-label" for="portalAuditPerson"><?= gettext('Person') ?></label>
                    <select class="form-select" id="portalAuditPerson" data-placeholder="<?= InputUtils::escapeAttribute(gettext('Everyone')) ?>"></select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label" for="portalAuditFrom"><?= gettext('From') ?></label>
                    <input type="date" class="form-control" id="portalAuditFrom">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label" for="portalAuditTo"><?= gettext('To') ?></label>
                    <input type="date" class="form-control" id="portalAuditTo">
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="portalAuditEvent"><?= gettext('Event') ?></label>
                    <select class="form-select" id="portalAuditEvent">
                        <option value=""><?= gettext('All events') ?></option>
                        <?php foreach (SignInAuditService::eventChoices() as $event) : ?>
                            <option value="<?= InputUtils::escapeAttribute($event['value']) ?>"><?= InputUtils::escapeHTML($event['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-1">
                    <span class="form-label d-none d-md-block" aria-hidden="true">&nbsp;</span>
                    <button type="reset" class="btn btn-outline-secondary w-100" id="portalAuditClear"><?= gettext('Clear') ?></button>
                </div>
            </div>
            <div class="invalid-feedback d-block" id="portalAuditError"></div>
        </div>
    </form>
    <div class="card">
        <div class="card-body">
            <table class="table table-vcenter w-100" id="portalAuditTable"></table>
        </div>
    </div>
</div>
