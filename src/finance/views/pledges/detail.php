<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/**
 * Variables injected by pledges.php route handler:
 *
 * @var array $pledge  Result of FinancialService::getPledgesByGroupKey()
 *   {
 *     groupKey:        string,
 *     familyId:        int,
 *     familyName:      string,
 *     date:            string (Y-m-d),
 *     fyId:            int,
 *     method:          string,
 *     checkNo:         string|null,
 *     depositId:       int|null,
 *     pledgeOrPayment: string ('Pledge'|'Payment'),
 *     schedule:        string|null,
 *     total:           float,
 *     funds:           array of {fundId, fundName, amount, nonDeductible, comment}
 *   }
 */

$isPledge   = ($pledge['pledgeOrPayment'] === 'Pledge');
$cardClass  = $isPledge ? 'bg-warning' : 'bg-primary text-white';
$badgeClass = $isPledge ? 'bg-warning text-dark' : 'bg-primary';
$iconClass  = $isPledge ? 'fa-file-signature' : 'fa-hand-holding-dollar';

$fyLabel    = FinancialService::formatFiscalYear($pledge['fyId']);

$methodLabels = [
    'CHECK'      => gettext('Check'),
    'CASH'       => gettext('Cash'),
    'CREDITCARD' => gettext('Credit Card'),
    'BANKDRAFT'  => gettext('Bank Draft'),
];
$methodLabel = $methodLabels[$pledge['method']] ?? InputUtils::escapeHTML($pledge['method']);

?>

<div class="container-xl">

    <!-- Header Card -->
    <div class="card mb-3">
        <div class="card-header <?= $cardClass ?>">
            <h3 class="card-title">
                <i class="fa-solid <?= $iconClass ?> me-2"></i>
                <?= $isPledge ? gettext('Pledge') : gettext('Payment') ?>
                <span class="badge <?= $badgeClass ?> ms-2">
                    <?= InputUtils::escapeHTML($pledge['pledgeOrPayment']) ?>
                </span>
            </h3>
            <div class="card-options">
                <a href="<?= $sRootPath ?>/finance/pledge/<?= urlencode($pledge['groupKey']) ?>/edit"
                   class="btn btn-sm btn-light">
                    <i class="fa-solid fa-pencil me-1"></i><?= gettext('Edit') ?>
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Family') ?></div>
                    <div class="fw-bold">
                        <a href="<?= $sRootPath ?>/v2/family/<?= (int) $pledge['familyId'] ?>">
                            <?= InputUtils::escapeHTML($pledge['familyName']) ?>
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Date') ?></div>
                    <div class="fw-bold"><?= InputUtils::escapeHTML($pledge['date']) ?></div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Fiscal Year') ?></div>
                    <div class="fw-bold"><?= InputUtils::escapeHTML($fyLabel) ?></div>
                </div>

                <?php if (!$isPledge): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Payment Method') ?></div>
                    <div class="fw-bold"><?= $methodLabel ?></div>
                </div>

                <?php if (!empty($pledge['checkNo'])): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Check #') ?></div>
                    <div class="fw-bold"><?= InputUtils::escapeHTML((string) $pledge['checkNo']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($pledge['depositId']): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Deposit Slip') ?></div>
                    <div class="fw-bold">
                        <a href="<?= $sRootPath ?>/DepositSlipEditor.php?DepositSlipID=<?= (int) $pledge['depositId'] ?>">
                            <?= gettext('Deposit #') . (int) $pledge['depositId'] ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php else: ?>

                <?php if (!empty($pledge['schedule'])): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Schedule') ?></div>
                    <div class="fw-bold"><?= InputUtils::escapeHTML($pledge['schedule']) ?></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small mb-1"><?= gettext('Total Amount') ?></div>
                    <div class="fw-bold fs-3 text-success">
                        $<?= number_format((float) $pledge['total'], 2) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fund Breakdown Card -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-hand-holding-dollar me-2"></i>
                <?= gettext('Fund Allocation') ?>
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th><?= gettext('Fund') ?></th>
                            <th class="text-end"><?= gettext('Amount') ?></th>
                            <?php if (!empty($pledge['funds']) && array_sum(array_column($pledge['funds'], 'nonDeductible')) > 0): ?>
                            <th class="text-end"><?= gettext('Non-Deductible') ?></th>
                            <?php endif; ?>
                            <th><?= gettext('Comment') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasNonDeductible = !empty($pledge['funds'])
                            && array_sum(array_column($pledge['funds'], 'nonDeductible')) > 0;
                        foreach ($pledge['funds'] as $f):
                        ?>
                        <tr>
                            <td><?= InputUtils::escapeHTML($f['fundName']) ?></td>
                            <td class="text-end fw-bold">$<?= number_format((float) $f['amount'], 2) ?></td>
                            <?php if ($hasNonDeductible): ?>
                            <td class="text-end text-muted">
                                <?= (float) $f['nonDeductible'] > 0 ? '$' . number_format((float) $f['nonDeductible'], 2) : '—' ?>
                            </td>
                            <?php endif; ?>
                            <td class="text-muted"><?= InputUtils::escapeHTML($f['comment']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold border-top">
                            <td><?= gettext('Total') ?></td>
                            <td class="text-end text-success">$<?= number_format((float) $pledge['total'], 2) ?></td>
                            <?php if ($hasNonDeductible): ?>
                            <td></td>
                            <?php endif; ?>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="card mb-3">
        <div class="card-body d-flex gap-2">
            <a href="<?= $sRootPath ?>/finance/pledge/<?= urlencode($pledge['groupKey']) ?>/edit"
               class="btn btn-primary">
                <i class="fa-solid fa-pencil me-1"></i><?= gettext('Edit') ?>
            </a>
            <a href="<?= $sRootPath ?>/finance/" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Back to Finance') ?>
            </a>
            <button type="button" class="btn btn-danger ms-auto" id="deletePledgeBtn"
                data-group-key="<?= InputUtils::escapeAttribute($pledge['groupKey']) ?>">
                <i class="fa-solid fa-trash-alt me-1"></i><?= gettext('Delete') ?>
            </button>
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
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    'use strict';

    const ROOT = window.CRM.root;

    function showToast(message, isError) {
        const toastEl = document.getElementById('pledge-toast');
        const bodyEl  = document.getElementById('pledge-toast-body');
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');
        bodyEl.textContent = message;
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    }

    const deleteBtn = document.getElementById('deletePledgeBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function () {
            const groupKey = this.dataset.groupKey;
            if (!confirm(<?= json_encode(gettext('Are you sure you want to permanently delete this pledge record?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)) return;

            try {
                const res = await fetch(ROOT + '/api/payments/' + encodeURIComponent(groupKey), {
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
}());
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
