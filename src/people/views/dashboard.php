<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">

    <!-- Stat Cards Row -->
    <div class="row mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-secondary text-white avatar rounded-circle">
                                <i class="fa-solid fa-people-roof icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $familyCount['familyCount'] ?></div>
                            <div class="text-muted"><?= gettext('Families') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar rounded-circle">
                                <i class="fa-solid fa-people-group icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $personCount ?></div>
                            <div class="text-muted"><?= gettext('People') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (SystemConfig::getValue('bEnabledSundaySchool')) { ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar rounded-circle">
                                <i class="fa-solid fa-children icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $groupStats['sundaySchoolkids'] ?></div>
                            <div class="text-muted"><?= gettext('Sunday School Kids') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="fa-solid fa-users icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $groupStats['groups'] ?></div>
                            <div class="text-muted"><?= gettext('Groups') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-bolt me-2"></i><?= gettext('Quick Actions') ?></h3>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap" style="gap: .5rem;">
                <a href="<?= $sRootPath ?>/PersonEditor.php" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add Person') ?>
                </a>
                <a href="<?= $sRootPath ?>/FamilyEditor.php" class="btn btn-secondary">
                    <i class="fa-solid fa-house-user me-1"></i><?= gettext('Add Family') ?>
                </a>
                <a href="<?= $sRootPath ?>/v2/people" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-list me-1"></i><?= gettext('People List') ?>
                </a>
                <a href="<?= $sRootPath ?>/v2/family" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-home me-1"></i><?= gettext('Family List') ?>
                </a>
                <a href="<?= $sRootPath ?>/v2/people/verify" class="btn btn-outline-info">
                    <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Verify People') ?>
                </a>
                <?php if ($sEmailLink && $canEmail):
                    $emailHref    = 'mailto:' . mb_substr($sEmailLink, 0, -3);
                    $emailBccHref = 'mailto:?bcc=' . mb_substr($sEmailLink, 0, -3);
                    ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-envelope me-1"></i><?= gettext('Email All') ?>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="<?= $emailHref ?>"><?= gettext('All People') ?></a>
                            <div class="dropdown-divider"></div>
                            <?php foreach ($roleEmails as $role => $roleEmail):
                                $defaultTo = SystemConfig::getValue('sToEmailAddress');
                                if ($defaultTo !== '' && !stristr($roleEmail, $defaultTo)) {
                                    $roleEmail .= $sMailtoDelimiter . $defaultTo;
                                }
                                $encoded = urlencode($roleEmail);
                                ?>
                                <a class="dropdown-item" href="mailto:<?= mb_substr($encoded, 0, -3) ?>"><?= $role ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-user-secret me-1"></i><?= gettext('Email BCC') ?>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="<?= $emailBccHref ?>"><?= gettext('All People') ?></a>
                            <div class="dropdown-divider"></div>
                            <?php foreach ($roleEmails as $role => $roleEmail):
                                $defaultTo = SystemConfig::getValue('sToEmailAddress');
                                if ($defaultTo !== '' && !stristr($roleEmail, $defaultTo)) {
                                    $roleEmail .= $sMailtoDelimiter . $defaultTo;
                                }
                                $encoded = urlencode($roleEmail);
                                ?>
                                <a class="dropdown-item" href="mailto:?bcc=<?= mb_substr($encoded, 0, -3) ?>"><?= $role ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
                                <th style="width:45%;"><?= gettext('Share') ?></th>
                                <th class="text-end" style="width:60px;"><?= gettext('Count') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_keys($classificationStats) as $key):
                                $pct = $personCount > 0 ? round($classificationStats[$key]['count'] / $personCount * 100) : 0;
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="<?= $sRootPath ?>/v2/people?Classification=<?= $classificationStats[$key]['id'] ?>">
                                            <?= gettext($key) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height:6px;">
                                                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="text-muted small" style="min-width:2.5rem;"><?= $pct ?>%</span>
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
                                <th style="width:45%;"><?= gettext('Share') ?></th>
                                <th class="text-end" style="width:60px;"><?= gettext('Count') ?></th>
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
                                        <a href="<?= $sRootPath ?>/v2/people?Gender=<?= $genderId ?>&FamilyRole=<?= $roleId ?>">
                                            <?= $key ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height:6px;">
                                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="text-muted small" style="min-width:2.5rem;"><?= $pct ?>%</span>
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

        <!-- Right column: Gender Demographics + Age Histogram + Reports -->
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
                                    <td class="text-end text-muted"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light">
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
                    <div id="age-stats-bar" style="min-height:300px;"></div>
                </div>
            </div>

            <!-- Reports -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-file-lines me-2"></i><?= gettext('Reports') ?></h3>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?= $sRootPath ?>/members/self-register.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="fa-solid fa-user-clock fa-fw text-muted me-3"></i>
                        <div>
                            <div class="fw-medium"><?= gettext('Self Registration Report') ?></div>
                            <div class="text-muted small"><?= gettext('List families created via self registration') ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>

                    <a href="<?= $sRootPath ?>/DirectoryReports.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="fa-solid fa-address-book fa-fw text-muted me-3"></i>
                        <div>
                            <div class="fw-medium"><?= gettext('People Directory') ?></div>
                            <div class="text-muted small"><?= gettext('Printable directory of all people, grouped by family') ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    <a href="<?= $sRootPath ?>/LettersAndLabels.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="fa-solid fa-envelope-open-text fa-fw text-muted me-3"></i>
                        <div>
                            <div class="fw-medium"><?= gettext('Letters & Mailing Labels') ?></div>
                            <div class="text-muted small"><?= gettext('Generate letters and mailing labels') ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                </div>
            </div>

        </div>

    </div>

</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        var ageGroupLabels = <?= json_encode(array_keys($ageGroupStats)) ?>;
        var ageGroupValues = <?= json_encode(array_values($ageGroupStats)) ?>;

        var ageChartElement = document.getElementById('age-stats-bar');
        if (ageChartElement && window.ApexCharts) {
            new window.ApexCharts(ageChartElement, {
                chart: { type: 'bar', height: 300, toolbar: { show: false } },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
                series: [{ name: "<?= gettext('People') ?>", data: ageGroupValues }],
                xaxis: { categories: ageGroupLabels, labels: { rotate: -45 } },
                yaxis: { title: { text: "<?= gettext('Count') ?>" }, forceNiceScale: true },
                colors: ['#3366ff'],
                dataLabels: { enabled: false },
                grid: { borderColor: '#f0f0f0' }
            }).render();
        }
    });
</script>

<?php if ($isAdmin): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    window.CRM.settingsPanel.init({
        container: '#peopleSettings',
        title: i18next.t('People Settings'),
        icon: 'fa-solid fa-sliders',
        settings: [
            {
                name: 'bEnableSelfRegistration',
                label: i18next.t('Self Registration'),
                type: 'boolean',
                tooltip: i18next.t('Allow visitors to self-register as new families.')
            }
        ],
        onSave: function () {
            setTimeout(function () { window.location.reload(); }, 1500);
        }
    });
});
</script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
