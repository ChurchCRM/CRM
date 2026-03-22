<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardService;

$sPageTitle = gettext('People Dashboard');

require_once __DIR__ . '/Include/Header.php';

$dashboardService = new DashboardService();
$familyCount = $dashboardService->getFamilyCount();
$groupStats = $dashboardService->getGroupStats();
$dashboardStats = $dashboardService->getDashboardStats();
$personCount = $dashboardStats['personCount'];
$classificationStats = $dashboardStats['classificationStats'];
$genderStats = $dashboardStats['genderStats'];
$simpleGenderStats = $dashboardStats['simpleGenderStats'];
$ageGroupStats = $dashboardStats['ageGroupStats'];
$familyRoleStats = $dashboardStats['familyRoleStats'];

$sSQL = "SELECT per_Email, fam_Email, lst_OptionName as virt_RoleName FROM person_per
          LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
          INNER JOIN list_lst on lst_ID=1 AND per_cls_ID = lst_OptionID
          WHERE fam_DateDeactivated is  null
             AND per_ID NOT IN
          (SELECT per_ID
              FROM person_per
              INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
              INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email')";

$rsEmailList = RunQuery($sSQL);
$sEmailLink = '';
$sMailtoDelimiter = AuthenticationManager::getCurrentUser()->getUserConfigString("sMailtoDelimiter");
$roleEmails = [];
while (list($per_Email, $fam_Email, $virt_RoleName) = mysqli_fetch_row($rsEmailList)) {
    $sEmail = $per_Email;
    if ($sEmail) {
        if (!stristr($sEmailLink, $sEmail)) {
            $sEmailLink .= $sEmail .= $sMailtoDelimiter;
            if (!array_key_exists($virt_RoleName, $roleEmails)) {
                $roleEmails[$virt_RoleName] = "";
            }
            $roleEmails[$virt_RoleName] .= $sEmail;
        }
    }
}

$selfRegColor = "bg-red";
$selfRegText = "Disabled";
if (SystemConfig::getBooleanValue("bEnableSelfRegistration")) {
    $selfRegColor = "bg-green";
    $selfRegText = "Enabled";
}
?>

<!-- Overview Card -->
<div class="card card-info card-outline mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-people-group"></i> <?= gettext('Overview') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-secondary">
                                <div class="stat-icon bg-secondary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-people-roof"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Families') ?></div>
                            <div class="h2 m-0"><?= $familyCount['familyCount'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-success">
                                <div class="stat-icon bg-success text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('People') ?></div>
                            <div class="h2 m-0"><?= $personCount ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (SystemConfig::getValue('bEnabledSundaySchool')) { ?>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-warning">
                                <div class="stat-icon bg-warning text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-children"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('SS Kids') ?></div>
                            <div class="h2 m-0"><?= $groupStats['sundaySchoolkids'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-primary">
                                <div class="stat-icon bg-primary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Groups') ?></div>
                            <div class="h2 m-0"><?= $groupStats['groups'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="btn-group" role="group">
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/people/verify" class="btn btn-outline-info" title="<?= gettext('Verify People') ?>">
                        <i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Verify') ?>
                    </a>
                    <div class="btn btn-outline-secondary disabled" style="pointer-events: none;">
                        <span class="badge <?= $selfRegColor ?> me-2"><?= $selfRegText ?></span>
                        <i class="fa-solid fa-user-plus me-2"></i><?= gettext('Self Register') ?>
                    </div>
                    <?php
                    if ($sEmailLink) {
                        // Add default email if default email has been set and is not already in string
                        if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
                            $sEmailLink .= $sMailtoDelimiter . SystemConfig::getValue('sToEmailAddress');
                        }
                        $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
                        if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
                            // Display link
                            ?>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="emailAllDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= gettext('Email to all people') ?>">
                                    <i class="fa-solid fa-mail-bulk me-2"></i><?= gettext('Email All') ?>
                                </button>
                                <div class="dropdown-menu" aria-labelledby="emailAllDropdown">
                                    <a class="dropdown-item" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All People') ?></a>
                                    <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
                                </div>
                            </div>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-info dropdown-toggle" type="button" id="emailAllBccDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= gettext('Email with hidden recipients') ?>">
                                    <i class="fa-solid fa-user-secret me-2"></i><?= gettext('Email (BCC)') ?>
                                </button>
                                <div class="dropdown-menu" aria-labelledby="emailAllBccDropdown">
                                    <a class="dropdown-item" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All People') ?></a>
                                    <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-info mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-file-lines"></i> <?= gettext('Reports') ?></h3>
                <div class="card-tools ms-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body">
                <p> <a class="MediumText" href="members/self-register.php"><?php echo gettext('Self Register') ?> <?= gettext('Reports') ?></a>
                    <br>
                    <?php echo gettext('List families that were created via self registration.') ?>
                </p>
                <a class="MediumText" href="GroupReports.php"><?php echo gettext('Reports on groups and roles'); ?></a>
                <br>
                <?php echo gettext('Report on group and roles selected (it may be a multi-page PDF).'); ?>
                </p>
                <?php if (AuthenticationManager::getCurrentUser()->isCreateDirectoryEnabled()) {
                    ?>
                    <p><a class="MediumText" href="DirectoryReports.php"><?= gettext('People Directory') ?></a><br><?= gettext('Printable directory of all people, grouped by family where assigned') ?>
                    </p>
                    <?php
                } ?>
                <a class="MediumText" href="LettersAndLabels.php"><?php echo gettext('Letters and Mailing Labels'); ?></a>
                <br><?php echo gettext('Generate letters and mailing labels.'); ?>
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-primary mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-chart-bar"></i> <?= gettext('People Classification') ?></h3>
                <div class="card-tools ms-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <tr>
                        <th><?= gettext('Classification') ?></th>
                        <th>% <?= gettext('of People') ?></th>
                        <th class="text-end" style="width: 40px;"><?= gettext('Count') ?></th>
                    </tr>
                    <?php foreach (array_keys($classificationStats) as $key) {
                        ?>
                        <tr>
                            <td><a href='v2/people?Classification=<?= $classificationStats[$key]['id'] ?>'><?= gettext($key) ?></a></td>
                            <td>
                                <div class="progress progress-xs">
                                    <div class="progress-bar bg-success" style="width: <?= round($classificationStats[$key]['count'] / $personCount * 100) ?>%"></div>
                                </div>
                            </td>
                            <td><span class="badge bg-green"><?= $classificationStats[$key]['count'] ?></span></td>
                        </tr>
                        <?php
                    } ?>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card card-primary mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"> <i class="fa-solid fa-people-group"></i> <?= gettext('Family Roles') ?></h3>
                <div class="card-tools ms-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <tr>
                        <th><?= gettext('Role / Gender') ?></th>
                        <th>% <?= gettext('of People') ?></th>
                        <th class="text-end" style="width: 40px;"><?= gettext('Count') ?></th>
                    </tr>
                    <?php
                    foreach (array_keys($familyRoleStats) as $key) {
                        $genderId = $familyRoleStats[$key]['genderId'];
                        $roleId = $familyRoleStats[$key]['roleId'];
                        $roldGenderName = $key;
                        $roleGenderCount = $familyRoleStats[$key]['count'];

                        if ($roleGenderCount !== 0) {
                            ?>
                            <tr>
                                <td><a href="v2/people?Gender=<?= $genderId ?>&FamilyRole=<?= $roleId ?>"><?= $roldGenderName ?></a></td>
                                <td>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-success" style="width: <?= round(($roleGenderCount / $personCount) * 100) ?>%" title="<?= round(($roleGenderCount / $personCount) * 100) ?>%"></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-green"><?= $roleGenderCount ?></span></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </table>
            </div>
        </div>
    </div>


    <div class="col-lg-6">
        <div class="card card-info mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-id-card-clip"></i> <?= gettext('Gender Demographics') ?></h3>
                <div class="card-tools ms-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><?= gettext('Gender') ?></th>
                            <th class="text-right"><?= gettext('Count') ?></th>
                            <th class="text-right"><?= gettext('Percentage') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalGender = array_sum($simpleGenderStats);
                        foreach ($simpleGenderStats as $gender => $count):
                            if ($count > 0):
                                $percentage = $totalGender > 0 ? round(($count / $totalGender) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= gettext($gender) ?></td>
                            <td class="text-right"><strong><?= $count ?></strong></td>
                            <td class="text-right"><?= $percentage ?>%</td>
                        </tr>
                        <?php
                            endif;
                        endforeach;
                        ?>
                        <tr class="table-light">
                            <td><strong><?= gettext('Total') ?></strong></td>
                            <td class="text-right"><strong><?= $totalGender ?></strong></td>
                            <td class="text-right"><strong>100%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card card-info mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-birthday-cake"></i> <?= gettext('Age Histogram') ?></h3>
                <div class="card-tools ms-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div id="age-stats-bar" style="min-height: 400px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- this page specific inline scripts -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        //Age Histogram with Age Groups
        var ageGroupLabels = <?= json_encode(array_keys($ageGroupStats)); ?>;
        var ageGroupValues = <?= json_encode(array_values($ageGroupStats)); ?>;

        var ageChartOptions = {
            chart: {
                type: 'bar',
                height: 400,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            series: [
                {
                    name: "<?= gettext('Count') ?>",
                    data: ageGroupValues
                }
            ],
            xaxis: {
                categories: ageGroupLabels,
            },
            yaxis: {
                title: {
                    text: "<?= gettext('Count') ?>"
                },
                forceNiceScale: true
            },
            colors: ['#3366ff'],
            dataLabels: {
                enabled: false
            }
        };

        var ageChartElement = document.getElementById("age-stats-bar");
        if (ageChartElement && window.ApexCharts) {
            var ageChart = new window.ApexCharts(ageChartElement, ageChartOptions);
            ageChart.render();
        }
    });
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';
