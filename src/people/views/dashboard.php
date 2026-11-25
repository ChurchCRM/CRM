<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$dashboardSwitchActive = 'people';
include SystemURLs::getDocumentRoot() . '/Include/partials/dashboard-switch.php';
require __DIR__ . '/_dashboard-overview.php';

?>

<div class="d-flex align-items-baseline gap-2 mt-4 mb-2">
    <h2 class="h3 mb-0"><?= gettext('Insights') ?></h2>
    <span class="text-body-secondary small"><?= gettext('How the congregation is made up') ?></span>
</div>
<div>

    <!-- Main Content Row -->
    <div class="row">

        <!-- Left column: Classification + Family Roles -->
        <div class="col-lg-6">

            <!-- People Classification -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-bar me-2"></i><?= gettext('People by Classification') ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-vcenter card-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Classification') ?></th>
                                <th class="share-col"><?= gettext('Share') ?></th>
                                <th class="text-end count-col"><?= gettext('Count') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_keys($classificationStats) as $key):
                                $pct = $personCount > 0 ? round($classificationStats[$key]['count'] / $personCount * 100) : 0;
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="<?= $sRootPath ?>/people/list?Classification=<?= $classificationStats[$key]['id'] ?>">
                                            <?= gettext($key) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress progress-thin flex-grow-1 me-2">
                                                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="text-body-secondary small pct-label"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-body fw-medium"><?= $classificationStats[$key]['count'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Family Roles -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-people-group me-2"></i><?= gettext('Family Roles') ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-vcenter card-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Role / Gender') ?></th>
                                <th class="share-col"><?= gettext('Share') ?></th>
                                <th class="text-end count-col"><?= gettext('Count') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_keys($familyRoleStats) as $key):
                                $genderId        = $familyRoleStats[$key]['genderId'];
                                $roleId          = $familyRoleStats[$key]['roleId'];
                                $roleGenderCount = $familyRoleStats[$key]['count'];
                                if ($roleGenderCount === 0) { continue; }
                                $pct = $personCount > 0 ? round(($roleGenderCount / $personCount) * 100) : 0;
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="<?= $sRootPath ?>/people/list?Gender=<?= $genderId ?>&FamilyRole=<?= $roleId ?>">
                                            <?= $key ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress progress-thin flex-grow-1 me-2">
                                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="text-body-secondary small pct-label"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-body fw-medium"><?= $roleGenderCount ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right column: Gender Demographics + Age Histogram -->
        <div class="col-lg-6">

            <!-- Gender Demographics -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-id-card-clip me-2"></i><?= gettext('Gender Demographics') ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-vcenter card-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Gender') ?></th>
                                <th><?= gettext('Share') ?></th>
                                <th class="text-end"><?= gettext('Count') ?></th>
                                <th class="text-end"><?= gettext('Percentage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalGender = array_sum($simpleGenderStats);
                            foreach ($simpleGenderStats as $gender => $count):
                                if ($count <= 0) { continue; }
                                $pct = $totalGender > 0 ? round(($count / $totalGender) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?= gettext($gender) ?></td>
                                    <td>
                                        <div class="progress progress-xs">
                                            <div class="progress-bar bg-info" style="width:<?= $pct ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="text-end"><strong><?= $count ?></strong></td>
                                    <td class="text-end text-body-secondary"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bg-surface-secondary">
                                <td colspan="2"><strong><?= gettext('Total') ?></strong></td>
                                <td class="text-end"><strong><?= $totalGender ?></strong></td>
                                <td class="text-end"><strong>100%</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Age Histogram -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-column me-2"></i><?= gettext('Age Distribution') ?></h3>
                </div>
                <div class="card-body">
                    <div id="age-stats-bar" class="age-stats-chart"></div>
                </div>
            </div>

        </div>

    </div>

</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        var ageGroupLabels = <?= InputUtils::jsonEncodeForScript(array_keys($ageGroupStats)) ?>;
        var ageGroupValues = <?= InputUtils::jsonEncodeForScript(array_values($ageGroupStats)) ?>;

        var ageChartElement = document.getElementById('age-stats-bar');
        if (ageChartElement && window.ApexCharts) {
            new window.ApexCharts(ageChartElement, {
                chart: { type: 'bar', height: 300, toolbar: { show: false } },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
                series: [{ name: "<?= gettext('People') ?>", data: ageGroupValues }],
                xaxis: { categories: ageGroupLabels, labels: { rotate: -45 } },
                yaxis: { title: { text: "<?= gettext('Count') ?>" }, forceNiceScale: true },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f0f0f0' }
            }).render();
        }
    });
</script>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-dashboard.min.js') ?>"></script>
<?php if ($canEmail): ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-composer.min.js') ?>" defer nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
