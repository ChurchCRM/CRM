<?php
require_once '../Include/Config.php';
require_once '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\Service\SundaySchoolService;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

$dashboardService = new DashboardService();
$sundaySchoolService = new SundaySchoolService();

$groupStats = $dashboardService->getGroupStats();

$kidsWithoutClasses = $sundaySchoolService->getKidsWithoutClasses();
$classStats = $sundaySchoolService->getClassStats();
$classes = $groupStats['sundaySchoolClasses'];
$teachers = 0;
$kids = 0;
$families = 0;
$maleKids = 0;
$femaleKids = 0;
$familyIds = [];
foreach ($classStats as $class) {
    $kids = $kids + $class['kids'];
    $teachers = $teachers + $class['teachers'];
    $classKids = $sundaySchoolService->getKidsFullDetails($class['id']);
    foreach ($classKids as $kid) {
        $familyIds[] = $kid['fam_id'];
        if ($kid['kidGender'] == '1') {
            $maleKids++;
        } elseif ($kid['kidGender'] == '2') {
            $femaleKids++;
        }
    }
}

$sPageTitle = gettext('Sunday School Dashboard');
require_once '../Include/Header.php';

?>
<div class="card card-info card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-church"></i> <?= gettext('Sunday School Overview') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-secondary">
                    <span class="info-box-icon"><i class="fa-solid fa-chalkboard"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Classes') ?></span>
                        <span class="info-box-number"><?= $classes ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-success">
                    <span class="info-box-icon"><i class="fa-solid fa-person-chalkboard"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Teachers') ?></span>
                        <span class="info-box-number"><?= $teachers ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-primary">
                    <span class="info-box-icon"><i class="fa-solid fa-children"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Students') ?></span>
                        <span class="info-box-number"><?= $kids ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-info">
                    <span class="info-box-icon"><i class="fa-solid fa-people-roof"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Families') ?></span>
                        <span class="info-box-number"><?= count(array_unique($familyIds)) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-primary">
                    <span class="info-box-icon"><i class="fa-solid fa-child"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Boys') ?></span>
                        <span class="info-box-number"><?= $maleKids ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-danger">
                    <span class="info-box-icon"><i class="fa-solid fa-child-dress"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Girls') ?></span>
                        <span class="info-box-number"><?= $femaleKids ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                    ?>
                  <button class="btn btn-app bg-success" data-toggle="modal" data-target="#add-class">
                      <i class="fa-solid fa-plus-square fa-3x"></i><br>
                      <?= gettext('Add New Class') ?>
                  </button>
                    <?php
                } ?>
                <a href="SundaySchoolReports.php" class="btn btn-app bg-primary"
                   title="<?= gettext('Generate class lists and attendance sheets'); ?>">
                    <i class="fa-solid fa-file-pdf fa-3x"></i><br>
                    <?= gettext('Reports'); ?>
                </a>
                <a href="SundaySchoolClassListExport.php" class="btn btn-app bg-info"
                   title="<?= gettext('Export All Classes, Kids, and Parent to CSV file'); ?>">
                    <i class="fa-solid fa-file-csv fa-3x"></i><br>
                    <?= gettext('Export to CSV') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card card-primary card-outline">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-chalkboard-user"></i> <?= gettext('Sunday School Classes') ?></h3>
      <div class="card-tools pull-right">
          <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i>
          </button>
          <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i>
          </button>
      </div>
  </div>
  <div class="card-body">
    <table id="sundayschoolMissing" class="table table-striped table-bordered data-table w-100">
      <thead>
      <tr>
        <th><?= gettext('Class') ?></th>
        <th><?= gettext('Teachers') ?></th>
        <th><?= gettext('Students') ?></th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($classStats as $class) {
            ?>
        <tr>
          <td>
            <a href='<?= SystemURLs::getRootPath() ?>/GroupEditor.php?GroupID=<?= $class['id'] ?>' class="me-2" title="<?= gettext('Edit') ?>">
              <i class="fa-solid fa-pen"></i>
            </a>
            <a href='SundaySchoolClassView.php?groupId=<?= $class['id'] ?>'>
              <?= $class['name'] ?>
            </a>
          </td>
          <td><?= $class['teachers'] ?></td>
          <td><?= $class['kids'] ?></td>
        </tr>
            <?php
      } ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card card-warning card-outline">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-user-xmark"></i> <?= gettext('Students not in a Sunday School Class') ?></h3>
      <div class="card-tools pull-right">
          <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i>
          </button>
          <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i>
          </button>
      </div>
  </div>
  <div class="card-body table-responsive">
    <table id="sundayschoolMissing" class="table table-striped table-bordered data-table w-100">
      <thead>
      <tr>
        <th><?= gettext('First Name') ?></th>
        <th><?= gettext('Last Name') ?></th>
        <th><?= gettext('Birth Date') ?></th>
        <th><?= gettext('Age') ?></th>
        <th><?= gettext('Home Address') ?></th>
      </tr>
      </thead>
      <tbody>
      <?php

        foreach ($kidsWithoutClasses as $child) {
            extract($child);

            $birthDate = 'N/A';
            $age = 'N/A';
            $hideAge = $flags == 1 || empty($birthYear);
            try {
                if (!$hideAge) {
                    $birthDate = MiscUtils::formatBirthDate($birthYear, $birthMonth, $birthDay, $flags);
                    $age = MiscUtils::formatAge($birthMonth, $birthDay, $birthYear);
                }
            } catch (\Throwable $ex) {
                LoggerUtils::getAppLogger()->error("Failed to retrieve student's age", ['exception' => $ex]);
            }

            $html = <<<HTML
<tr>
<td>
  <a href="../PersonView.php?PersonID={$kidId}">
    $firstName
  </a>
</td>
<td>$LastName</td>
<td>$birthDate</td>
<td>$age</td>
<td>$Address1 $Address2 $city $state $zip</td>
</tr>
HTML;
            echo $html;
        }

        ?>
      </tbody>
    </table>
  </div>
</div>
<?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    ?>
  <div class="modal fade" id="add-class" tabindex="-1" role="dialog" aria-labelledby="add-class-label"
       aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title"
              id="delete-Image-label"><?= gettext('Add') ?> <?= gettext('Sunday School') ?> <?= gettext('Class') ?> </h4>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <input type="text" id="new-class-name" class="form-control" placeholder="<?= gettext('Enter Name') ?>"
                   maxlength="20" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= gettext('Cancel') ?></button>
          <button type="button" id="addNewClassBtn" class="btn btn-primary"
                  data-dismiss="modal"><?= gettext('Add') ?></button>
        </div>
      </div>
    </div>
  </div>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
      $('.data-table').DataTable(window.CRM.plugin.dataTable);

      $("#addNewClassBtn").click(function (e) {
        var groupName = $("#new-class-name").val(); // get the name of the from the textbox
        if (groupName) // ensure that the user entered a name
        {
          $.ajax({
            method: "POST",
            dataType: "json",
            contentType: "application/json; charset=utf-8",
            url: window.CRM.root + "/api/groups/",
            data: JSON.stringify({
              'groupName': groupName,
              'isSundaySchool': true
            })
          }).done(function (data) {                               //yippie, we got something good back from the server
            window.location.href = window.CRM.root + "/sundayschool/SundaySchoolClassView.php?groupId=" + data.Id;
          });
        }
        else {

        }
      });

    });
  </script>

    <?php
}
require_once '../Include/Footer.php';
