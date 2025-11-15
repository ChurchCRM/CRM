<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardService;

$sPageTitle = gettext('People Dashboard');

require_once 'Include/Header.php';

$dashboardService = new DashboardService();
$familyCount = $dashboardService->getFamilyCount();
$groupStats = $dashboardService->getGroupStats();
$dashboardStats = $dashboardService->getDashboardStats();
$personCount = $dashboardStats['personCount'];
$classificationStats = $dashboardStats['classificationStats'];
$genderStats = $dashboardStats['genderStats'];
$ageStats = $dashboardStats['ageStats'];
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
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
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

<!-- Default box -->
<div class="card">
    <div class="card-header with-border">
        <h3 class="card-title"><?= gettext('People Functions') ?></h3>
    </div>
    <div class="card-body">
        <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="btn btn-app bg-primary">
            <i class="fa-solid fa-user fa-3x"></i><br>
            <?= gettext('All People') ?>
        </a>
        <a href="<?= SystemURLs::getRootPath() ?>/v2/people/verify" class="btn btn-app bg-info">
            <i class="fa-solid fa-clipboard-check fa-3x"></i><br>
            <?= gettext('Verify People') ?>
        </a>
        <div class="btn btn-app bg-secondary">
            <span class="badge <?= $selfRegColor ?>"><?= $selfRegText ?></span>
            <i class="fa-solid fa-user-plus fa-3x"></i><br>
            <?= gettext('Self Register') ?>
        </div>
        <a href="<?= SystemURLs::getRootPath() ?>/v2/family" class="btn btn-app bg-success">
            <i class="fa-solid fa-people-roof fa-3x"></i><br>
            <?= gettext('All Families') ?>
        </a>
        <a href="MapUsingGoogle.php?GroupID=-1" class="btn btn-app bg-warning">
            <i class="fa-solid fa-map fa-3x"></i><br>
            <?= gettext('Family Map') ?>
        </a>
        <a href="GeoPage.php" class="btn btn-app bg-info">
            <i class="fa-solid fa-globe fa-3x"></i><br>
            <?= gettext('Family Geographic') ?>
        </a>
        <a href="UpdateAllLatLon.php" class="btn btn-app bg-purple">
            <i class="fa-solid fa-map-pin fa-3x"></i><br>
            <?= gettext('Update All Family Coordinates') ?>
        </a>

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
                    <button class="btn btn-app bg-primary dropdown-toggle" type="button" id="emailAllDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-mail-bulk fa-3x"></i><br>
                        <?= gettext('Email All') ?>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="emailAllDropdown">
                        <a class="dropdown-item" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All People') ?></a>
                        <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
                    </div>
                </div>
                <div class="dropdown d-inline-block">
                    <button class="btn btn-app bg-info dropdown-toggle" type="button" id="emailAllBccDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-user-secret fa-3x"></i><br>
                        <?= gettext('Email All (BCC)') ?>
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
<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-gray">
            <div class="inner">
                <h3>
                    <?= $familyCount['familyCount'] ?>
                </h3>

                <p>
                    <?= gettext('Families') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-people-roof"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/family" class="small-box-footer">
                <?= gettext('See all Families') ?> <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3>
                    <?= $personCount ?>
                </h3>

                <p>
                    <?= gettext('People') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-user"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="small-box-footer">
                <?= gettext('See All People') ?> <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <?php if (SystemConfig::getValue('bEnabledSundaySchool')) {
        ?>
        <!-- ./col -->
        <div class="col-lg-3 col-md-6 col-sm-6">
            <!-- small box -->
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>
                        <?= $groupStats['sundaySchoolkids'] ?>
                    </h3>

                    <p>
                        <?= gettext('Sunday School Kids') ?>
                    </p>
                </div>
                <div class="icon">
                    <i class="fa-solid fa-children"></i>
                </div>
                <a href="<?= SystemURLs::getRootPath() ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
                    <?= gettext('More info') ?> <i class="fa-solid fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <?php
    } ?>
    <!-- ./col -->
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3>
                    <?= $groupStats['groups'] ?>
                </h3>

                <p>
                    <?= gettext('Groups') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/GroupList.php" class="small-box-footer">
                <?= gettext('More info') ?> <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <!-- ./col -->

</div><!-- /.row -->
<div class="row">
    <div class="col-lg-6">
        <div class="card card-info">
            <div class="card-header with-border">
                <h3 class="card-title"><i class="fa-solid fa-file-lines"></i> <?= gettext('Reports') ?></h3>
                <div class="card-tools pull-right">
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
        <div class="card card-primary">
            <div class="card-header with-border">
                <h3 class="card-title"><i class="fa-solid fa-chart-bar"></i> <?= gettext('People Classification') ?></h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body no-padding">
                <table class="table table-condensed">
                    <tr>
                        <th><?= gettext('Classification') ?></th>
                        <th>% <?= gettext('of People') ?></th>
                        <th style="width: 40px"><?= gettext('Count') ?></th>
                    </tr>
                    <?php foreach (array_keys($classificationStats) as $key) {
                        ?>
                        <tr>
                            <td><a href='v2/people?Classification=<?= $classificationStats[$key]['id'] ?>'><?= gettext($key) ?></a></td>
                            <td>
                                <div class="progress progress-xs progress-striped active">
                                    <div class="progress-bar progress-bar-success" style="width: <?= round($classificationStats[$key]['count'] / $personCount * 100) ?>%"></div>
                                </div>
                            </td>
                            <td><span class="badge bg-green"><?= $classificationStats[$key]['count'] ?></span></td>
                        </tr>
                        <?php
                    } ?>
                </table>
                <!-- /.box-body-->
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card card-primary">
            <div class="card-header with-border">
                <h3 class="card-title"> <i class="fa-solid fa-people-group"></i> <?= gettext('Family Roles') ?></h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
            <div class="card-body no-padding">
                <table class="table table-condensed">
                    <tr>
                        <th><?= gettext('Role / Gender') ?></th>
                        <th>% <?= gettext('of People') ?></th>
                        <th style="width: 40px"><?= gettext('Count') ?></th>
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
                                    <div class="progress progress-xs progress-striped active">
                                        <div class="progress-bar progress-bar-success" style="width: <?= round(($roleGenderCount / $personCount) * 100) ?>%" title="<?= round(($roleGenderCount / $personCount) * 100) ?>%"></div>
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
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-id-card-clip"></i> <?= gettext('Gender Demographics') ?></h3>
            </div>
            <!-- /.box-header -->
            <div class="card-body" style="height: 300px">
                <canvas id="gender-donut" style="height:250px"></canvas>
            </div>
        </div>
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-birthday-cake"></i> <?= gettext('Age Histogram') ?></h3>
            </div>
            <!-- /.box-header -->
            <div class="card-body" style="height: 400px">
                <canvas id="age-stats-bar" style="height:250px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- this page specific inline scripts -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        //Gender Donut
        var pieData = {
            labels: [
                '<?= gettext('Unassigned') ?>',
                '<?= gettext('Men') ?>',
                '<?= gettext('Women') ?>',
                '<?= gettext('Boys') ?>',
                '<?= gettext('Girls') ?>'
            ],
            datasets: [{
                data: <?php echo json_encode($genderStats); ?>,
                backgroundColor: ["#d1a73a", "#003399", "#9900ff", "#3399ff", "#009933"],
                hoverBackgroundColor: ["#f6c444", "#3366ff", "#ff66cc", "#99ccff", "#99cc00"]
            }]
        };

        var pieOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateRotate: false
            },
            plugins: {
                legend: {
                    labels: {
                        filter: (legendItem, data) => data.datasets[0].data[legendItem.index] > 0
                    }
                }
            }
        };

        var ctx = document.getElementById("gender-donut").getContext('2d');
        var pieChart = new Chart(ctx, {
            type: 'doughnut',
            data: pieData,
            options: pieOptions
        });

        //Age Histogram
        var ageLabels = <?= json_encode(array_keys($ageStats)); ?>;
        var ageValues = <?= json_encode(array_values($ageStats)); ?>;

        var ctx = document.getElementById("age-stats-bar").getContext('2d');
        var AgeChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: "Ages",
                    data: ageValues,
                    backgroundColor: "#3366ff"
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        display: true,
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
</script>
<?php
require_once 'Include/Footer.php';
