<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DonationFundService;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require __DIR__ . '/_charity-details-banner.php';

// $dashboardData is injected by the route controller (finance/routes/dashboard.php)
// It includes FY-scoped data for the selectedFyid passed in the GET ?fyid param.
$fyLabel = $dashboardData['fiscalYear']['label'];

$selectedFyid  = $dashboardData['selectedFyid'];
$availableYears = $dashboardData['availableYears'];
$currentFyid   = $dashboardData['currentFyid'];

$periodPayments = $selectedFyid === $currentFyid ? gettext('YTD Payments') : gettext('Payments');
$periodPledges = $selectedFyid === $currentFyid ? gettext('YTD Pledges') : gettext('Pledges');
$yearEnd = $dashboardData['yearEnd'];
$activeFunds = $dashboardData['activeFunds'];
$recentDeposits = $dashboardData['recentDeposits'];
$paymentsByFund = $dashboardData['paymentsByFund'];

$ytdPaymentTotal = $dashboardData['ytdPaymentTotal'];
$ytdPledgeTotal = $dashboardData['ytdPledgeTotal'];
$ytdPaymentCount = $dashboardData['ytdPaymentCount'];
$ytdDonorFamilies = $dashboardData['ytdDonorFamilies'];

$currentDeposit = $dashboardData['currentDeposit'];
$currentDepositId = $dashboardData['currentDepositId'];
$hasOpenDeposit = $currentDeposit && !$currentDeposit->getClosed();

$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();

$sRootPath = SystemURLs::getRootPath();
?>

<div class="container-fluid">

    <!-- The weekly task: keep working on the open deposit, or start one -->
    <div class="card mb-3" id="current-work">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($hasOpenDeposit): ?>
            <span class="avatar bg-azure-lt text-azure rounded-circle"><i class="fa-solid fa-file-invoice"></i></span>
            <div class="me-auto">
                <div class="text-body-secondary small">
                    <?= gettext('Open deposit') ?> #<?= (int) $currentDepositId ?> · <?= $currentDeposit->getDate('M j, Y') ?> · <?= InputUtils::escapeHTML($currentDeposit->getType()) ?>
                </div>
                <div class="h2 mb-0"><?= CurrencyFormatter::formatHtml($currentDeposit->getVirtualColumn('totalAmount') ?? 0) ?></div>
            </div>
            <a href="<?= $sRootPath ?>/finance/pledge/new?type=Payment&depositId=<?= (int) $currentDepositId ?>" class="btn btn-primary">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i><?= gettext('Add Payment') ?>
            </a>
            <a href="<?= $sRootPath ?>/finance/deposit/<?= (int) $currentDepositId ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-lock me-1"></i><?= gettext('Review and Close Deposit') ?>
            </a>
            <?php else: ?>
            <span class="avatar bg-secondary-lt rounded-circle"><i class="fa-solid fa-file-invoice"></i></span>
            <div class="me-auto">
                <div class="fw-bold"><?= gettext('No open deposit') ?></div>
                <div class="text-body-secondary small"><?= gettext('Start a deposit, then add each payment to it.') ?></div>
            </div>
            <?php endif; ?>
            <a href="<?= $sRootPath ?>/finance/deposit/search?new=1" class="btn <?= $hasOpenDeposit ? 'btn-ghost-secondary' : 'btn-success' ?>">
                <i class="fa-solid fa-circle-plus me-1"></i><?= gettext('Create Deposit') ?>
            </a>
            <a href="<?= $sRootPath ?>/finance/pledge/new?type=Pledge" class="btn btn-ghost-secondary">
                <i class="fa-solid fa-file-signature me-1"></i><?= gettext('Add Pledge') ?>
            </a>
        </div>
    </div>

    <!-- The fiscal year scopes the totals and the fund breakdown below -->
    <form method="GET" class="d-flex align-items-center justify-content-end gap-2 mb-2">
        <label for="deposit-fyid" class="form-label mb-0 small text-body-secondary fw-semibold"><?= gettext('Fiscal Year') ?>:</label>
        <select name="fyid" id="deposit-fyid" class="form-select form-select-sm w-auto" onchange="this.form.submit();">
            <option value="0" <?= $selectedFyid === 0 ? 'selected' : '' ?>><?= gettext('All Time') ?></option>
            <?php foreach ($availableYears as $year): ?>
            <option value="<?= (int) $year['id'] ?>" <?= (int) $year['id'] === $selectedFyid ? 'selected' : '' ?>>
                <?= InputUtils::escapeHTML($year['label']) ?>
                <?php if ((int) $year['id'] === $currentFyid): ?> (<?= gettext('Current') ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Stat Cards Row -->
    <div class="row mb-3">
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
                            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($ytdPaymentTotal ?? 0) ?></div>
                            <div class="text-body-secondary"><?= $periodPayments ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($ytdPledgeTotal ?? 0) ?></div>
                            <div class="text-body-secondary"><?= $periodPledges ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-secondary text-white avatar rounded-circle">
                                <i class="fa-solid fa-people-roof icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= number_format($ytdDonorFamilies ?? 0) ?></div>
                            <div class="text-body-secondary"><?= gettext('Donor Families') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-info text-white avatar rounded-circle">
                                <i class="fa-solid fa-receipt icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= number_format($ytdPaymentCount) ?></div>
                            <div class="text-body-secondary"><?= gettext('Number of Payments') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3" id="payments-by-fund">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Payments by Fund') ?></h3>
            <span class="ms-auto text-body-secondary small"><?= InputUtils::escapeHTML($fyLabel) ?></span>
        </div>
        <?php if ($paymentsByFund !== []): ?>
        <div class="row g-0">
            <div class="col-lg-7 card-body">
                <div id="payments-by-fund-chart" aria-hidden="true"></div>
            </div>
            <div class="col-lg-5">
                <table class="table table-vcenter mb-0">
                    <caption class="visually-hidden"><?= gettext('Payments by Fund') ?> — <?= InputUtils::escapeHTML($fyLabel) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= gettext('Fund') ?></th>
                            <th scope="col" class="text-end"><?= gettext('Amount') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentsByFund as $fund): ?>
                        <tr data-fund-id="<?= (int) $fund['fundId'] ?>">
                            <th scope="row" class="text-break">
                                <a href="<?= $sRootPath ?>/finance/fund/<?= (int) $fund['fundId'] ?>/contributors?fyid=<?= (int) $selectedFyid ?>"><?= InputUtils::escapeHTML($fund['name']) ?></a>
                            </th>
                            <td class="text-end text-nowrap"><?= CurrencyFormatter::formatHtml($fund['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row"><?= gettext('Total') ?></th>
                            <td class="text-end text-nowrap fw-bold"><?= CurrencyFormatter::formatHtml($ytdPaymentTotal ?? 0) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card-body text-body-secondary"><?= gettext('No payments found for this period.') ?></div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Recent Deposits -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left me-2"></i><?= gettext('Recent Deposits') ?></h3>
                    <a href="<?= $sRootPath ?>/finance/deposit/search" class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="fa-solid fa-list me-1"></i><?= gettext('View All') ?>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentDeposits->count() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter mb-0">
                            <thead>
                                <tr>
                                    <th><?= gettext('ID') ?></th>
                                    <th><?= gettext('Date') ?></th>
                                    <th><?= gettext('Type') ?></th>
                                    <th><?= gettext('Comment') ?></th>
                                    <th class="text-end"><?= gettext('Total') ?></th>
                                    <th><?= gettext('Status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDeposits as $deposit): ?>
                                <tr class="<?= ($deposit->getId() === $currentDepositId) ? 'table-active' : '' ?>">
                                    <td>
                                        <a href="<?= $sRootPath ?>/finance/deposit/<?= $deposit->getId() ?>">
                                            #<?= $deposit->getId() ?>
                                        </a>
                                    </td>
                                    <td><?= $deposit->getDate('M j, Y') ?></td>
                                    <td><span class="badge bg-blue-lt text-blue"><?= $deposit->getType() ?></span></td>
                                    <td class="text-truncate finance-truncate"><?= InputUtils::escapeHTML($deposit->getComment() ?? '') ?></td>
                                    <td class="text-end fw-bold"><?= CurrencyFormatter::formatHtml($deposit->getVirtualColumn('totalAmount') ?? 0) ?></td>
                                    <td>
                                        <?php if ($deposit->getClosed()): ?>
                                        <span class="badge bg-green-lt text-green"><?= gettext('Closed') ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= gettext('Open') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="fa-solid fa-inbox fa-3x text-body-secondary"></i></div>
                        <p class="empty-title"><?= gettext('No deposits found.') ?></p>
                        <a href="<?= $sRootPath ?>/finance/deposit/search?new=1" class="btn btn-primary">
                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Create First Deposit') ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Year-end: one entry point. Statements, wording and settings live there. -->
            <div class="card mb-3" id="year-end-entry">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-calendar-check me-2"></i><?= gettext('Year-End Giving') ?></h3>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        <?php if ($yearEnd['openDeposits'] > 0): ?>
                        <span class="badge bg-warning text-dark me-1"><i class="fa-solid fa-exclamation"></i></span>
                        <?= InputUtils::escapeHTML(sprintf(ngettext('%1$s: %2$d deposit is still open. Close it so every gift is final before statements go out.', '%1$s: %2$d deposits are still open. Close them so every gift is final before statements go out.', $yearEnd['openDeposits']), $yearEnd['label'], $yearEnd['openDeposits'])) ?>
                        <?php else: ?>
                        <span class="badge bg-green-lt text-green me-1"><i class="fa-solid fa-check"></i></span>
                        <?= InputUtils::escapeHTML(sprintf(gettext('%s: every deposit is closed. Statements are ready to prepare.'), $yearEnd['label'])) ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?= $sRootPath ?>/finance/reports/year-end?year=<?= (int) $yearEnd['year'] ?>" class="btn btn-primary w-100">
                        <i class="fa-solid fa-envelope-open-text me-1"></i><?= gettext('Prepare Giving Statements') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/finance/statements/settings" class="btn btn-ghost-secondary w-100 mt-2">
                        <i class="fa-solid fa-file-invoice me-1"></i><?= gettext('Statement Settings') ?>
                    </a>
                </div>
            </div>

            <!-- Donation Funds, grouped by category -->
            <div class="card mb-3" id="donation-funds">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fa-solid fa-piggy-bank me-2"></i><?= gettext('Donation Funds') ?></h3>
                    <span class="badge bg-blue-lt text-blue ms-auto"><?= (int) $activeFunds->count() ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($activeFunds->count() > 0): ?>
                    <?php foreach ((new DonationFundService())->groupByCategory($activeFunds) as $category => $funds): ?>
                    <?php if ($category !== ''): ?>
                    <div class="px-3 pt-2 pb-1">
                        <small class="text-body-secondary fw-bold text-uppercase"><?= InputUtils::escapeHTML($category) ?></small>
                    </div>
                    <?php endif; ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($funds as $fund): ?>
                        <li class="list-group-item py-2 <?= $category !== '' ? 'ps-4' : '' ?>">
                            <a href="<?= $sRootPath ?>/finance/fund/<?= (int) $fund->getId() ?>/contributors" class="text-decoration-none">
                                <?= InputUtils::escapeHTML($fund->getName()) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty py-3">
                        <p class="empty-title"><?= gettext('No active funds configured.') ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php if ($isAdmin): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">window.financeDashboardConfig = <?= InputUtils::jsonEncodeForScript([
    'funds' => $paymentsByFund,
    'isAdmin' => $isAdmin,
    'fiscalYearMonths' => $isAdmin ? SystemConfig::getChoices('iFYMonth') : [],
    'depositSlipTooltip' => $isAdmin ? SystemConfig::getTooltip('sDepositSlipType') : '',
    'depositSlipTypes' => $isAdmin ? SystemConfig::getChoices('sDepositSlipType') : [],
    'requireCheckNumberTooltip' => $isAdmin ? SystemConfig::getTooltip('bRequireCheckNumber') : '',
]) ?>;</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-dashboard.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
