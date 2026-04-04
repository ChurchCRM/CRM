<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/**
 * Variables injected by pledges.php route handler:
 *
 * @var string  $type               'Pledge' or 'Payment'
 * @var string  $groupKey           Empty string for create; existing key for edit
 * @var int     $familyId           Pre-selected family ID (0 if none)
 * @var string  $familyName         Family display name (empty string if none)
 * @var int     $depositId          Current deposit ID (0 if none)
 * @var \Propel\Runtime\Collection\ObjectCollection $funds  Active DonationFund objects
 * @var \Propel\Runtime\Collection\ObjectCollection $openDeposits  Open Deposit objects
 * @var array   $fiscalYears        [fyId => label]
 * @var int     $currentFyId        Current fiscal year ID
 * @var bool    $enableNonDeductible
 * @var bool    $isEdit             true when editing, false when creating
 * @var array|null $pledge          Pledge data array from FinancialService::getPledgesByGroupKey()
 */

$isPledge = ($type === 'Pledge');
$cardClass = $isPledge ? 'bg-warning' : 'bg-primary text-white';
$alertClass = $isPledge ? 'alert-warning' : 'alert-primary';
$iconClass = $isPledge ? 'fa-file-signature' : 'fa-hand-holding-dollar';

// Build a fund-amount map from existing pledge data (for edit mode)
$pledgeFundAmounts = [];
$pledgeFundNonDeductible = [];
$pledgeFundComments = [];
if ($isEdit && !empty($pledge['funds'])) {
    foreach ($pledge['funds'] as $f) {
        $pledgeFundAmounts[$f['fundId']]       = $f['amount'];
        $pledgeFundNonDeductible[$f['fundId']] = $f['nonDeductible'];
        $pledgeFundComments[$f['fundId']]      = $f['comment'];
    }
}

$pledgeDate    = $isEdit ? ($pledge['date'] ?? date('Y-m-d')) : date('Y-m-d');
$pledgeFyId    = $isEdit ? ($pledge['fyId'] ?? $currentFyId) : $currentFyId;
$pledgeMethod  = $isEdit ? ($pledge['method'] ?? 'CHECK') : 'CHECK';
$pledgeCheckNo = $isEdit ? ($pledge['checkNo'] ?? '') : '';
$pledgeSchedule = $isEdit ? ($pledge['schedule'] ?? 'Once') : 'Once';
$pledgeDepositId = $isEdit ? ($pledge['depositId'] ?? 0) : $depositId;

?>

<div class="container-xl">

    <!-- Mode Banner -->
    <div class="alert <?= $alertClass ?> d-flex align-items-center mb-3" role="alert">
        <i class="fa-solid <?= $iconClass ?> fa-2x me-3"></i>
        <div>
            <strong>
                <?= $isPledge
                    ? ($isEdit ? gettext('Editing a Pledge') : gettext('Recording a Pledge'))
                    : ($isEdit ? gettext('Editing a Payment') : gettext('Recording a Payment')) ?>
            </strong>
            <p class="mb-0 small">
                <?= $isPledge
                    ? gettext('A pledge is a commitment to give. It is not tied to a deposit slip.')
                    : gettext('A payment is an actual donation received. It will be added to the selected deposit.') ?>
            </p>
        </div>
    </div>

    <!-- Toast feedback -->
    <div id="pledge-toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
        <div id="pledge-toast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="pledge-toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Main Form Card -->
    <div class="card mb-3">
        <div class="card-header <?= $cardClass ?>">
            <h3 class="card-title">
                <i class="fa-solid <?= $iconClass ?> me-2"></i>
                <?= $isEdit ? gettext('Edit') : gettext('New') ?>
                <?= $isPledge ? gettext('Pledge') : gettext('Payment') ?>
                <?= gettext('Details') ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3">

                <!-- Family Selector -->
                <div class="col-lg-6">
                    <label class="form-label" for="FamilyName"><?= gettext('Family') ?> <span class="text-danger">*</span></label>
                    <input type="hidden" id="FamilyID" name="FamilyID" value="<?= (int) $familyId ?>">
                    <select class="form-select" id="FamilyName" name="FamilyName" required>
                        <?php if ($familyId && $familyName): ?>
                            <option value="<?= (int) $familyId ?>" selected><?= InputUtils::escapeHTML($familyName) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Date -->
                <div class="col-lg-3">
                    <label class="form-label" for="Date"><?= gettext('Date') ?> <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" id="Date" name="Date" value="<?= InputUtils::escapeAttribute($pledgeDate) ?>" required>
                </div>

                <!-- Fiscal Year -->
                <div class="col-lg-3">
                    <label class="form-label" for="FYID"><?= gettext('Fiscal Year') ?></label>
                    <select class="form-select" id="FYID" name="FYID">
                        <?php foreach ($fiscalYears as $fyId => $fyLabel): ?>
                            <option value="<?= (int) $fyId ?>"
                                <?= ($fyId === $pledgeFyId) ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($fyLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$isPledge): ?>
                <!-- Payment Method -->
                <div class="col-lg-3">
                    <label class="form-label" for="Method"><?= gettext('Payment Method') ?></label>
                    <select class="form-select" id="Method" name="Method">
                        <option value="CHECK"      <?= $pledgeMethod === 'CHECK'      ? 'selected' : '' ?>><?= gettext('Check') ?></option>
                        <option value="CASH"       <?= $pledgeMethod === 'CASH'       ? 'selected' : '' ?>><?= gettext('Cash') ?></option>
                        <option value="CREDITCARD" <?= $pledgeMethod === 'CREDITCARD' ? 'selected' : '' ?>><?= gettext('Credit Card') ?></option>
                        <option value="BANKDRAFT"  <?= $pledgeMethod === 'BANKDRAFT'  ? 'selected' : '' ?>><?= gettext('Bank Draft') ?></option>
                    </select>
                </div>

                <!-- Check Number -->
                <div class="col-lg-3" id="checkNumberGroup">
                    <label class="form-label" for="CheckNo"><?= gettext('Check #') ?></label>
                    <input class="form-control" type="number" id="CheckNo" name="CheckNo" value="<?= InputUtils::escapeAttribute((string)$pledgeCheckNo) ?>">
                </div>

                <!-- Deposit -->
                <div class="col-lg-6">
                    <label class="form-label" for="DepositID"><?= gettext('Deposit Slip') ?></label>
                    <select class="form-select" id="DepositID" name="DepositID">
                        <option value="0"><?= gettext('No deposit / create new deposit') ?></option>
                        <?php foreach ($openDeposits as $deposit): ?>
                            <option value="<?= (int) $deposit->getId() ?>"
                                <?= ($deposit->getId() == $pledgeDepositId) ? 'selected' : '' ?>>
                                <?= gettext('Deposit #') . (int) $deposit->getId() ?>
                                (<?= InputUtils::escapeHTML($deposit->getDate('Y-m-d')) ?>
                                - <?= InputUtils::escapeHTML($deposit->getType() ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <!-- Schedule (Pledges only) -->
                <div class="col-lg-4">
                    <label class="form-label" for="Schedule"><?= gettext('Payment Schedule') ?></label>
                    <select class="form-select" id="Schedule" name="Schedule">
                        <option value="Once"      <?= $pledgeSchedule === 'Once'      ? 'selected' : '' ?>><?= gettext('Once') ?></option>
                        <option value="Weekly"    <?= $pledgeSchedule === 'Weekly'    ? 'selected' : '' ?>><?= gettext('Weekly') ?></option>
                        <option value="Monthly"   <?= $pledgeSchedule === 'Monthly'   ? 'selected' : '' ?>><?= gettext('Monthly') ?></option>
                        <option value="Quarterly" <?= $pledgeSchedule === 'Quarterly' ? 'selected' : '' ?>><?= gettext('Quarterly') ?></option>
                        <option value="Other"     <?= $pledgeSchedule === 'Other'     ? 'selected' : '' ?>><?= gettext('Other') ?></option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Fund Allocation Card -->
    <div class="card mb-3">
        <div class="card-header <?= $cardClass ?>">
            <h3 class="card-title">
                <i class="fa-solid fa-hand-holding-dollar me-2"></i>
                <?= gettext('Fund Allocation') ?>
            </h3>
            <div class="card-options">
                <button type="button" class="btn btn-sm btn-light" id="addFundRow">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Fund') ?>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-vcenter mb-0" id="fundTable">
                    <thead>
                        <tr>
                            <th><?= gettext('Fund') ?> <span class="text-danger">*</span></th>
                            <th><?= gettext('Amount ($)') ?> <span class="text-danger">*</span></th>
                            <?php if ($enableNonDeductible): ?>
                            <th><?= gettext('Non-Deductible ($)') ?></th>
                            <?php endif; ?>
                            <th><?= gettext('Comment') ?></th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody id="fundRows">
                        <?php
                        // In edit mode, render a row for each existing fund allocation;
                        // in create mode, render one blank row.
                        if ($isEdit && !empty($pledge['funds'])) {
                            foreach ($pledge['funds'] as $f):
                        ?>
                        <tr class="fund-row">
                            <td>
                                <select class="form-select fund-select" name="funds[][fundId]" required>
                                    <option value=""><?= gettext('Select a fund') ?></option>
                                    <?php foreach ($funds as $fund): ?>
                                        <option value="<?= (int) $fund->getId() ?>"
                                            <?= ($fund->getId() == $f['fundId']) ? 'selected' : '' ?>>
                                            <?= InputUtils::escapeHTML($fund->getName()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" class="form-control fund-amount" name="funds[][amount]"
                                    step="0.01" min="0" max="999999.99"
                                    value="<?= number_format((float) $f['amount'], 2, '.', '') ?>" required>
                            </td>
                            <?php if ($enableNonDeductible): ?>
                            <td>
                                <input type="number" class="form-control fund-nondeductible" name="funds[][nonDeductible]"
                                    step="0.01" min="0" max="999999.99"
                                    value="<?= number_format((float) $f['nonDeductible'], 2, '.', '') ?>">
                            </td>
                            <?php endif; ?>
                            <td>
                                <input type="text" class="form-control fund-comment" name="funds[][comment]"
                                    maxlength="255"
                                    value="<?= InputUtils::escapeAttribute($f['comment']) ?>">
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-fund-row"
                                    title="<?= gettext('Remove') ?>">
                                    <i class="fa-solid fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                        <?php
                            endforeach;
                        } else {
                        ?>
                        <tr class="fund-row">
                            <td>
                                <select class="form-select fund-select" name="funds[][fundId]" required>
                                    <option value=""><?= gettext('Select a fund') ?></option>
                                    <?php foreach ($funds as $fund): ?>
                                        <option value="<?= (int) $fund->getId() ?>">
                                            <?= InputUtils::escapeHTML($fund->getName()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" class="form-control fund-amount" name="funds[][amount]"
                                    step="0.01" min="0" max="999999.99" value="" required>
                            </td>
                            <?php if ($enableNonDeductible): ?>
                            <td>
                                <input type="number" class="form-control fund-nondeductible" name="funds[][nonDeductible]"
                                    step="0.01" min="0" max="999999.99" value="0.00">
                            </td>
                            <?php endif; ?>
                            <td>
                                <input type="text" class="form-control fund-comment" name="funds[][comment]"
                                    maxlength="255" value="">
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-fund-row"
                                    title="<?= gettext('Remove') ?>">
                                    <i class="fa-solid fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td><?= gettext('Total') ?></td>
                            <td><span id="fundTotal">0.00</span></td>
                            <?php if ($enableNonDeductible): ?>
                            <td></td>
                            <?php endif; ?>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="card mb-3">
        <div class="card-body d-flex gap-2">
            <button type="button" class="btn btn-primary" id="savePledgeBtn">
                <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Save') ?>
            </button>
            <?php if (!$isEdit): ?>
            <button type="button" class="btn btn-success" id="saveAndAddBtn">
                <i class="fa-solid fa-plus me-1"></i><?= gettext('Save and Add Another') ?>
            </button>
            <?php endif; ?>
            <a href="<?= $sRootPath ?>/finance/" class="btn btn-secondary">
                <i class="fa-solid fa-xmark me-1"></i><?= gettext('Cancel') ?>
            </a>
            <?php if ($isEdit): ?>
            <button type="button" class="btn btn-danger ms-auto" id="deletePledgeBtn">
                <i class="fa-solid fa-trash-alt me-1"></i><?= gettext('Delete') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Fund row template (hidden) -->
<template id="fundRowTemplate">
    <tr class="fund-row">
        <td>
            <select class="form-select fund-select" name="funds[][fundId]" required>
                <option value=""><?= gettext('Select a fund') ?></option>
                <?php foreach ($funds as $fund): ?>
                    <option value="<?= (int) $fund->getId() ?>">
                        <?= InputUtils::escapeHTML($fund->getName()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control fund-amount" name="funds[][amount]"
                step="0.01" min="0" max="999999.99" value="" required>
        </td>
        <?php if ($enableNonDeductible): ?>
        <td>
            <input type="number" class="form-control fund-nondeductible" name="funds[][nonDeductible]"
                step="0.01" min="0" max="999999.99" value="0.00">
        </td>
        <?php endif; ?>
        <td>
            <input type="text" class="form-control fund-comment" name="funds[][comment]" maxlength="255" value="">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger remove-fund-row"
                title="<?= gettext('Remove') ?>">
                <i class="fa-solid fa-trash-alt"></i>
            </button>
        </td>
    </tr>
</template>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    'use strict';

    const ROOT = window.CRM.root;
    const GROUP_KEY = <?= json_encode($groupKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const IS_EDIT   = <?= $isEdit ? 'true' : 'false' ?>;
    const PLEDGE_TYPE = <?= json_encode($type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const ENABLE_ND = <?= $enableNonDeductible ? 'true' : 'false' ?>;

    // ---- Toast helper ----
    function showToast(message, isError) {
        const toastEl = document.getElementById('pledge-toast');
        const bodyEl  = document.getElementById('pledge-toast-body');
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');
        bodyEl.textContent = message;
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    }

    // ---- Family TomSelect ----
    const familyNameEl = document.getElementById('FamilyName');
    if (familyNameEl && !familyNameEl.tomselect) {
        new TomSelect(familyNameEl, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            load: function (query, callback) {
                if (query.length < 2) return callback();
                fetch(ROOT + '/api/families/search/' + encodeURIComponent(query))
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        var families = data && data.Families ? data.Families : [];
                        callback(families.map(function (obj) {
                            return { id: String(obj.Id), text: obj.displayName };
                        }));
                    })
                    .catch(function () { callback(); });
            },
            onChange: function (value) {
                document.getElementById('FamilyID').value = value;
            }
        });
    }

    // ---- Fund total calculation ----
    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.fund-amount').forEach(function (input) {
            const v = parseFloat(input.value);
            if (!isNaN(v) && v > 0) total += v;
        });
        document.getElementById('fundTotal').textContent = total.toFixed(2);
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('fund-amount')) recalcTotal();
    });

    // ---- Add fund row ----
    document.getElementById('addFundRow').addEventListener('click', function () {
        const tmpl = document.getElementById('fundRowTemplate');
        const clone = tmpl.content.cloneNode(true);
        document.getElementById('fundRows').appendChild(clone);
        recalcTotal();
    });

    // ---- Remove fund row ----
    document.getElementById('fundRows').addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-fund-row');
        if (!btn) return;
        const rows = document.querySelectorAll('.fund-row');
        if (rows.length <= 1) {
            showToast(<?= json_encode(gettext('At least one fund allocation is required'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
            return;
        }
        btn.closest('tr').remove();
        recalcTotal();
    });

    // ---- Payment method toggle for check # field ----
    const methodEl = document.getElementById('Method');
    const checkGroup = document.getElementById('checkNumberGroup');
    if (methodEl && checkGroup) {
        function toggleCheckGroup() {
            if (methodEl.value === 'CHECK') {
                checkGroup.style.display = '';
            } else {
                checkGroup.style.display = 'none';
                document.getElementById('CheckNo').value = '';
            }
        }
        methodEl.addEventListener('change', toggleCheckGroup);
        toggleCheckGroup();
    }

    // ---- Date → auto-resolve FYID ----
    const dateEl = document.getElementById('Date');
    const fyEl   = document.getElementById('FYID');
    if (dateEl && fyEl) {
        dateEl.addEventListener('change', function () {
            const d = new Date(this.value);
            if (isNaN(d.getTime())) return;
            const year  = d.getFullYear();
            const month = d.getMonth() + 1; // 1-based
            // FY IDs: year - 1996, adjusted for FY start month (iFYMonth)
            // We resolve via API to respect server config
            fetch(ROOT + '/api/fiscalyear?date=' + encodeURIComponent(this.value))
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (data && data.fyId) {
                        fyEl.value = data.fyId;
                    }
                })
                .catch(function () { /* ignore — user can select manually */ });
        });
    }

    // ---- Collect form data for API submission ----
    function collectPayload() {
        const familyId = parseInt(document.getElementById('FamilyID').value, 10);
        const date     = document.getElementById('Date').value;
        const fyid     = parseInt(fyEl ? fyEl.value : '0', 10);
        const methodEl2 = document.getElementById('Method');
        const method   = methodEl2 ? methodEl2.value : 'CHECK';
        const checkEl  = document.getElementById('CheckNo');
        const checkNo  = checkEl ? checkEl.value : '';
        const depEl    = document.getElementById('DepositID');
        const depId    = depEl ? parseInt(depEl.value, 10) : 0;
        const schedEl  = document.getElementById('Schedule');
        const schedule = schedEl ? schedEl.value : 'Once';

        if (!familyId) {
            showToast(<?= json_encode(gettext('Please select a family'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
            return null;
        }
        if (!date) {
            showToast(<?= json_encode(gettext('Please enter a date'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
            return null;
        }

        // Build FundSplit array
        const fundRows = document.querySelectorAll('.fund-row');
        const fundSplit = [];
        let hasAmount = false;
        let validationError = null;

        fundRows.forEach(function (row) {
            const fundSelect = row.querySelector('.fund-select');
            const amountInput = row.querySelector('.fund-amount');
            const ndInput    = row.querySelector('.fund-nondeductible');
            const commentInput = row.querySelector('.fund-comment');

            const fundId = fundSelect ? fundSelect.value : '';
            const amount = parseFloat(amountInput ? amountInput.value : '0') || 0;
            const nd     = parseFloat(ndInput ? ndInput.value : '0') || 0;
            const comment = commentInput ? commentInput.value : '';

            if (!fundId || fundId === '') {
                validationError = <?= json_encode(gettext('Please select a fund for all rows'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                return;
            }
            if (amount > 999999.99) {
                validationError = <?= json_encode(gettext('Amount exceeds maximum allowed (999999.99)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                return;
            }
            if (nd > amount) {
                validationError = <?= json_encode(gettext("Non-deductible amount can't exceed fund amount"), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                return;
            }
            if (amount > 0) hasAmount = true;

            fundSplit.push({
                FundID: fundId,
                Amount: amount,
                NonDeductible: nd,
                Comment: comment
            });
        });

        if (validationError) {
            showToast(validationError, true);
            return null;
        }
        if (!hasAmount) {
            showToast(<?= json_encode(gettext('At least one fund must have a non-zero amount'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
            return null;
        }

        return {
            FamilyID:  familyId,
            Date:      date,
            FYID:      fyid,
            type:      PLEDGE_TYPE,
            iMethod:   method,
            iCheckNo:  checkNo || null,
            DepositID: depId || null,
            schedule:  schedule,
            FundSplit: JSON.stringify(fundSplit),
            tScanString: ''
        };
    }

    // ---- Save (POST /api/payments/pledges) ----
    async function savePledge(redirectAfter) {
        const payload = collectPayload();
        if (!payload) return;

        try {
            const res = await fetch(ROOT + '/api/payments/pledges', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(function () { return {}; });

            if (!res.ok) {
                const msg = (data && (data.error || data.message)) || <?= json_encode(gettext('Save failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                showToast(msg, true);
                return;
            }

            showToast(<?= json_encode(gettext('Saved successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, false);

            if (redirectAfter === 'new') {
                setTimeout(function () {
                    window.location.href = ROOT + '/finance/pledge/new?type=' + encodeURIComponent(PLEDGE_TYPE);
                }, 800);
            } else if (data && data.payment) {
                try {
                    const parsed = JSON.parse(data.payment);
                    // Try to extract group key from the returned payment object
                    const gk = parsed && parsed.GroupKey ? parsed.GroupKey : null;
                    if (gk) {
                        setTimeout(function () {
                            window.location.href = ROOT + '/finance/pledge/' + encodeURIComponent(gk);
                        }, 800);
                        return;
                    }
                } catch (e) { /* ignore */ }
                setTimeout(function () {
                    window.location.href = ROOT + '/finance/';
                }, 800);
            } else {
                setTimeout(function () {
                    window.location.href = ROOT + '/finance/';
                }, 800);
            }
        } catch (err) {
            showToast(<?= json_encode(gettext('Network error, please try again'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
        }
    }

    document.getElementById('savePledgeBtn').addEventListener('click', function () {
        savePledge('view');
    });

    const saveAndAddBtn = document.getElementById('saveAndAddBtn');
    if (saveAndAddBtn) {
        saveAndAddBtn.addEventListener('click', function () {
            savePledge('new');
        });
    }

    // ---- Delete ----
    const deleteBtn = document.getElementById('deletePledgeBtn');
    if (deleteBtn && GROUP_KEY) {
        deleteBtn.addEventListener('click', async function () {
            if (!confirm(<?= json_encode(gettext('Are you sure you want to permanently delete this pledge record?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)) return;

            try {
                const res = await fetch(ROOT + '/api/payments/' + encodeURIComponent(GROUP_KEY), {
                    method: 'DELETE'
                });
                if (res.ok) {
                    showToast(<?= json_encode(gettext('Deleted successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, false);
                    setTimeout(function () {
                        window.location.href = ROOT + '/finance/';
                    }, 800);
                } else {
                    const data = await res.json().catch(function () { return {}; });
                    const msg = (data && (data.error || data.message)) || <?= json_encode(gettext('Delete failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                    showToast(msg, true);
                }
            } catch (err) {
                showToast(<?= json_encode(gettext('Network error, please try again'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
            }
        });
    }

    // Initial total calculation
    recalcTotal();
}());
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
