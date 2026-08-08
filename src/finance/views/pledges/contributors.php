<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Pre-escape $sRootPath once; it is a server-controlled value but project
// convention requires escapeAttribute on every echoed href attribute.
$sRootPathEsc = InputUtils::escapeAttribute($sRootPath);

/**
 * Variables injected by /finance/routes/fund.php:
 *
 * @var array  $fund            ['id' => int, 'name' => string]
 * @var array  $contributors    Per-family rows: family_id, family_name, envelope, pledged, paid, remaining, percent, status, group_key
 * @var array  $stats           ['total_pledged', 'total_paid', 'total_remaining', 'contributor_count', 'percent_paid']
 * @var array  $availableYears  [{id, label}, ...]
 * @var int    $selectedFyid
 * @var int    $currentFyid
 */

$statusClasses = [
    'complete'     => 'text-success fw-bold',
    'on-track'     => 'text-info',
    'behind'       => 'text-warning',
    'critical'     => 'text-danger',
    'payment-only' => 'text-success fw-bold',
];

?>

<div class="container-fluid">

    <!-- Fiscal Year Selector + Back link -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="mb-0">
                <label for="fyid" class="fw-bold"><?= gettext('Fiscal Year') ?></label>
                <form method="GET" class="d-inline">
                    <select name="fyid" id="fyid" class="form-select d-inline-block" style="width: auto;">
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?= (int) $year['id'] ?>" <?= $year['id'] == $selectedFyid ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($year['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <small class="form-text text-body-secondary">
                    <?= gettext('Current Fiscal Year') ?>: <strong><?= FinancialService::formatFiscalYear($currentFyid) ?></strong>
                </small>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <a href="<?= $sRootPathEsc ?>/finance/pledge/dashboard?fyid=<?= (int) $selectedFyid ?>"
               class="btn btn-secondary me-2">
                <i class="fa-solid fa-arrow-left me-1"></i>
                <?= gettext('Back to Pledge Dashboard') ?>
            </a>
            <?php if (\ChurchCRM\Authentication\AuthenticationManager::getCurrentUser()->isAdmin()): ?>
            <a href="<?= $sRootPathEsc ?>/DonationFundEditor.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-cog me-1"></i>
                <?= gettext('Manage Funds') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-3">
        <!-- Total Pledged -->
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="fa-solid fa-file-signature icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($stats['total_pledged']) ?></div>
                            <div class="text-body-secondary"><?= gettext('Total Pledged') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Paid -->
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar rounded-circle">
                                <i class="fa-solid fa-hand-holding-dollar icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($stats['total_paid']) ?></div>
                            <div class="text-body-secondary">
                                <?php if ($stats['total_pledged'] > 0): ?>
                                    <?= number_format($stats['percent_paid'], 1) ?>% <?= gettext('of pledges') ?>
                                <?php else: ?>
                                    <?= gettext('Payments only') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remaining -->
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar rounded-circle">
                                <i class="fa-solid fa-circle-half-stroke icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($stats['total_remaining']) ?></div>
                            <div class="text-body-secondary"><?= gettext('Remaining') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contributors -->
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-info text-white avatar rounded-circle">
                                <i class="fa-solid fa-users icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= (int) $stats['contributor_count'] ?></div>
                            <div class="text-body-secondary"><?= $stats['contributor_count'] == 1 ? gettext('Contributor') : gettext('Contributors') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contributor DataTable -->
    <?php if (empty($contributors)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i>
            <?= gettext('No contributors found for this fund in the selected fiscal year') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-status-top bg-info"></div>
            <div class="card-header py-2">
                <h3 class="card-title">
                    <i class="fa-solid fa-users me-1"></i>
                    <?= gettext('Contributors') ?> &mdash; <?= InputUtils::escapeHTML(FinancialService::formatFiscalYear($selectedFyid)) ?>
                </h3>
            </div>
            <div style="overflow: visible;">
                <table id="fundContributors" class="table table-hover table-vcenter mb-0 w-100">
                    <thead>
                        <tr>
                            <th><?= gettext('Family Name') ?></th>
                            <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                            <th><?= gettext('Envelope') ?></th>
                            <?php endif; ?>
                            <th class="text-end"><?= gettext('Pledged Amount') ?></th>
                            <th class="text-end"><?= gettext('Payments') ?></th>
                            <th class="text-end"><?= gettext('Remaining') ?></th>
                            <th class="text-end"><?= gettext('% Paid') ?></th>
                            <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contributors as $contributor): ?>
                            <?php
                            $statusClass = $statusClasses[$contributor['status']] ?? '';
                            $pledged     = $contributor['pledged'];
                            $paid        = $contributor['paid'];
                            $remaining   = $contributor['remaining'];
                            $percent     = $contributor['percent'];
                            $groupKey    = $contributor['group_key'];
                            ?>
                            <tr>
                                <td class="fw-bold">
                                    <a href="<?= $sRootPathEsc ?>/people/family/<?= (int) $contributor['family_id'] ?>">
                                        <?= InputUtils::escapeHTML($contributor['family_name']) ?>
                                    </a>
                                </td>
                                <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                <td class="text-body-secondary small">
                                    <?= InputUtils::escapeHTML($contributor['envelope']) ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-end fw-bold"
                                    data-order="<?= InputUtils::escapeAttribute($pledged) ?>">
                                    <?= $pledged > 0 ? CurrencyFormatter::formatHtml($pledged) : '<span class="text-body-secondary">—</span>' ?>
                                </td>
                                <td class="text-end"
                                    data-order="<?= InputUtils::escapeAttribute($paid) ?>">
                                    <?= CurrencyFormatter::formatHtml($paid) ?>
                                </td>
                                <td class="text-end <?= $statusClass ?>"
                                    data-order="<?= InputUtils::escapeAttribute($remaining ?? 0) ?>">
                                    <?= $remaining !== null ? CurrencyFormatter::formatHtml($remaining) : '<span class="text-body-secondary">—</span>' ?>
                                </td>
                                <td class="text-end <?= $statusClass ?>"
                                    data-order="<?= InputUtils::escapeAttribute($percent) ?>">
                                    <?= number_format($percent, 0) ?>%
                                </td>
                                <td class="text-center w-1">
                                    <?php if ($groupKey !== null && $groupKey !== ''): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-ghost-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item contributor-view-link"
                                               href="<?= $sRootPathEsc ?>/finance/pledge/<?= urlencode($groupKey) ?>">
                                                <i class="ti ti-eye me-2"></i><?= $contributor['status'] === 'payment-only' ? gettext('View Payment') : gettext('View Pledge') ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-body-secondary small"><?= gettext('N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    // Fiscal year selector: submit the form when the selection changes
    var fyidSelect = document.getElementById('fyid');
    if (fyidSelect) {
        fyidSelect.addEventListener('change', function () {
            this.closest('form').submit();
        });
    }

    if (document.getElementById('fundContributors')) {
        var cfg = $.extend({}, window.CRM.plugin.dataTable, {
            order: [[0, 'asc']],
            pageLength: 25,
        });
        $('#fundContributors').DataTable(cfg);
    }
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
