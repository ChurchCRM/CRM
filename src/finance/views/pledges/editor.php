<?php

use ChurchCRM\dto\SystemConfig;
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
 * @var string  $linkBack           Root-relative redirect target after save/delete/cancel (empty string if none)
 */

$linkBackTarget = $linkBack !== '' ? $sRootPath . '/' . ltrim($linkBack, '/') : $sRootPath . '/finance/';

$isPledge = ($type === 'Pledge');
$cardClass = $isPledge ? 'bg-warning' : 'bg-primary text-white';
$alertClass = $isPledge ? 'alert-warning' : 'alert-primary';
$iconClass = $isPledge ? 'fa-file-signature' : 'fa-hand-holding-dollar';

$pledgeDate    = $isEdit ? ($pledge['date'] ?? date('Y-m-d')) : date('Y-m-d');
$pledgeFyId    = $isEdit ? ($pledge['fyId'] ?? $currentFyId) : $currentFyId;
$pledgeMethod  = $isEdit ? ($pledge['method'] ?? 'CHECK') : 'CHECK';
$pledgeCheckNo = $isEdit ? ($pledge['checkNo'] ?? '') : '';
$pledgeSchedule = $isEdit ? ($pledge['schedule'] ?? 'Once') : 'Once';
$pledgeDepositId = $isEdit ? ($pledge['depositId'] ?? 0) : $depositId;
$requireCheckNumber = SystemConfig::getBooleanValue('bRequireCheckNumber');
$familySearchHint = SystemConfig::getBooleanValue('bUseDonationEnvelopes')
    ? gettext('Search by family name or envelope #')
    : gettext('Search by family name');

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
                    <label class="form-label" for="FamilyName"><?= gettext('Family') ?></label>
                    <input type="hidden" id="FamilyID" name="FamilyID" value="<?= (int) $familyId ?>">
                    <select class="form-select" id="FamilyName" name="FamilyName" placeholder="<?= InputUtils::escapeAttribute($familySearchHint) ?>">
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
                    <label class="form-label" for="CheckNo"><?= gettext('Check #') ?><?php if ($requireCheckNumber): ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input class="form-control" type="text" inputmode="numeric" pattern="[0-9]*" id="CheckNo" name="CheckNo" value="<?= InputUtils::escapeAttribute((string)$pledgeCheckNo) ?>"<?php if ($requireCheckNumber): ?> required<?php endif; ?>>
                </div>

                <!-- Deposit -->
                <div class="col-lg-6">
                    <label class="form-label" for="DepositID"><?= gettext('Deposit Slip') ?></label>
                    <select class="form-select" id="DepositID" name="DepositID">
                        <option value="0"><?= gettext('No deposit / create new deposit') ?></option>
                        <?php foreach ($openDeposits as $deposit): ?>
                            <option value="<?= (int) $deposit->getId() ?>"
                                <?= ($deposit->getId() == $pledgeDepositId) ? 'selected' : '' ?>>
                                <?= sprintf(gettext('Deposit #%d'), (int) $deposit->getId()) ?>
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
                                    <i class="fa-solid fa-trash"></i>
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
                                    <i class="fa-solid fa-trash"></i>
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
            <button type="button" class="btn btn-primary" id="savePledgeBtn"<?= $isEdit ? ' aria-keyshortcuts="Control+Enter Meta+Enter"' : '' ?>>
                <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Save') ?>
            </button>
            <?php if (!$isEdit): ?>
            <button type="button" class="btn btn-success" id="saveAndAddBtn" aria-keyshortcuts="Control+Enter Meta+Enter">
                <i class="fa-solid fa-plus me-1"></i><?= gettext('Save and Add Another') ?>
            </button>
            <?php endif; ?>
            <small class="text-body-secondary align-self-center d-none" id="saveShortcutHint"><kbd class="shortcut-mod">Ctrl</kbd>+<kbd>Enter</kbd></small>
            <a href="<?= InputUtils::escapeAttribute($linkBackTarget) ?>" class="btn btn-secondary">
                <i class="fa-solid fa-xmark me-1"></i><?= gettext('Cancel') ?>
            </a>
            <?php if ($isEdit): ?>
            <button type="button" class="btn btn-danger ms-auto" id="deletePledgeBtn">
                <i class="fa-solid fa-trash me-1"></i><?= gettext('Delete') ?>
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
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">window.CRM.pledgeEditor = <?= InputUtils::jsonEncodeForScript(['groupKey' => $groupKey, 'type' => $type, 'linkBack' => $linkBack, 'linkBackTarget' => $linkBack !== '' ? $linkBackTarget : '', 'requireCheckNumber' => $requireCheckNumber]) ?>;</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-pledge-editor.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
