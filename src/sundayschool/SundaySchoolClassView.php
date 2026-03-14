<?php

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Service\SundaySchoolService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

$sundaySchoolService = new SundaySchoolService();

$iGroupId = '-1';
$iGroupName = 'Unknown';
if (isset($_GET['groupId'])) {
    $iGroupId = InputUtils::legacyFilterInput($_GET['groupId'], 'int');
}

$thisGroup = GroupQuery::create()->findPk((int) $iGroupId);
if ($thisGroup !== null) {
    $iGroupName = $thisGroup->getName();
}

$birthDayMonthChartArray = [];
$rsTeachers = [];
$thisClassChildren = [];

try {
    foreach ($sundaySchoolService->getKidsBirthdayMonth($iGroupId) as $birthDayMonth => $kidsCount) {
        $birthDayMonthChartArray[] = [
            gettext($birthDayMonth),
            $kidsCount
        ];
    }
} catch (Throwable $e) {
    LoggerUtils::getAppLogger()->error('SundaySchoolClassView: Error getting birthday months', ['exception' => $e->getMessage()]);
}

$birthDayMonthChartJSON = json_encode($birthDayMonthChartArray, JSON_THROW_ON_ERROR);

try {
    $rsTeachers = $sundaySchoolService->getClassByRole($iGroupId, 'Teacher');
} catch (Throwable $e) {
    LoggerUtils::getAppLogger()->error('SundaySchoolClassView: Error getting teachers', ['exception' => $e->getMessage()]);
    $rsTeachers = [];
}

$sPageTitle = gettext('Sunday School') . ': ' . $iGroupName;

$TeachersEmails = [];
$KidsEmails = [];
$ParentsEmails = [];

try {
    $thisClassChildren = $sundaySchoolService->getKidsFullDetails($iGroupId);
} catch (Throwable $e) {
    LoggerUtils::getAppLogger()->error('SundaySchoolClassView: Error getting kids full details', ['exception' => $e->getMessage()]);
    $thisClassChildren = [];
}

foreach ($thisClassChildren as $child) {
    if (!empty($child['dadEmail'])) {
        $ParentsEmails[] = $child['dadEmail'];
    }
    if (!empty($child['momEmail'])) {
        $ParentsEmails[] = $child['momEmail'];
    }
    if (!empty($child['kidEmail'])) {
        $KidsEmails[] = $child['kidEmail'];
    }
}

foreach ($rsTeachers as $teacher) {
    $TeachersEmails[] = $teacher->getEmail();
}

require_once __DIR__ . '/../Include/Header.php';

?>

<h1 class="page-header"><?= gettext('Sunday School') ?>: <strong><?= htmlspecialchars($iGroupName) ?></strong></h1>

<div class="card card-info card-outline">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-bars"></i> <?= gettext('Sunday School Class Functions') ?></h3>
  </div>
  <div class="card-body row">
    <?php
    $sMailtoDelimiter = AuthenticationManager::getCurrentUser()->getUserConfigString("sMailtoDelimiter");
    $allEmails = array_unique([...$ParentsEmails, ...$KidsEmails, ...$TeachersEmails]);
    $roleEmails = [];
    $roleEmails['Parents'] = implode($sMailtoDelimiter, $ParentsEmails) . ',';
    $roleEmails['Teachers'] = implode($sMailtoDelimiter, $TeachersEmails) . ',';
    $roleEmails['Kids'] = implode($sMailtoDelimiter, $KidsEmails) . ',';
    $sEmailLink = implode($sMailtoDelimiter, $allEmails) . ',';
    // Add default email if default email has been set and is not already in string
    if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, (string) SystemConfig::getValue('sToEmailAddress'))) {
        $sEmailLink .= $sMailtoDelimiter . SystemConfig::getValue('sToEmailAddress');
    }
    $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
    ?>

    <div class="col-12 col-md-3">
      <a class="btn btn-app bg-success btn-block" href="../GroupView.php?GroupID=<?= $iGroupId ?>">
          <i class="fa-solid fa-user-plus fa-3x"></i><br>
          <?= gettext('Add Students') ?>
      </a>
    </div>

    <div class="col-12 col-md-3">
      <a class="btn btn-app bg-primary btn-block" href="../GroupEditor.php?GroupID=<?= $iGroupId?>">
          <i class="fa-solid fa-pen fa-3x"></i><br>
          <?= gettext("Edit this Class") ?>
      </a>
    </div>

    <?php
    if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
      // Display link
        ?>
      <div class="col-12 col-md-3">
        <div class="dropdown">
          <button class="btn btn-app bg-teal btn-block dropdown-toggle" type="button" id="emailClassDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= gettext('Send email with recipients in To field') ?>">
              <i class="fa-solid fa-paper-plane fa-3x"></i><br>
              <?= gettext('Email (To)') ?>
          </button>
          <div class="dropdown-menu" aria-labelledby="emailClassDropdown">
            <a class="dropdown-item" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All Members') ?></a>
            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-3">
        <div class="dropdown">
          <button class="btn btn-app bg-navy btn-block dropdown-toggle" type="button" id="emailClassBccDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= gettext('Send email with recipients in BCC field (hidden from each other)') ?>">
              <i class="fa-solid fa-user-secret fa-3x"></i><br>
              <?= gettext('Email (BCC)') ?>
          </button>
          <div class="dropdown-menu" aria-labelledby="emailClassBccDropdown">
            <a class="dropdown-item" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All Members') ?></a>
            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
          </div>
        </div>
      </div>
        <?php
    }
    ?>
  </div>
</div>

<div class="card card-info card-outline">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-chart-line"></i> <?= gettext('Class Overview') ?></h3>
    <div class="card-tools float-right">
      <button type="button" class="btn btn-tool" data-card-widget="collapse" title="<?= gettext('Toggle overview') ?>">
        <i class="fa-solid fa-chevron-up"></i>
      </button>
    </div>
  </div>
  <div class="card-body row">
    <?php
    // Calculate statistics
    $totalStudents = count($thisClassChildren);
    $maleCount = 0;
    $femaleCount = 0;
    $otherCount = 0;

    foreach ($thisClassChildren as $child) {
      switch ($child['kidGender']) {
        case 1:
          $maleCount++;
          break;
        case 2:
          $femaleCount++;
          break;
        default:
          $otherCount++;
      }
    }
    ?>

    <!-- Birthday Chart -->
    <div class="col-12 col-lg-6">
      <div class="card card-primary card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-chart-bar"></i> <?= gettext('Birthdays by Month') ?></h3>
        </div>
        <div class="card-body">
          <div class="disableSelection">
            <canvas id="bar-chart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Class Stats -->
    <div class="col-12 col-lg-6">
      <div class="row">
        <div class="col-12 col-sm-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h3><?= $totalStudents ?></h3>
              <p><?= gettext('Total Enrolled') ?></p>
            </div>
            <div class="icon">
              <i class="fa-solid fa-users"></i>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6">
          <div class="small-box bg-warning">
            <div class="inner">
              <h3><?= $maleCount ?> / <?= $femaleCount ?></h3>
              <p><?= gettext('Male / Female') ?></p>
            </div>
            <div class="icon">
              <i class="fa-solid fa-person-half-dress"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card card-success card-outline">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-person-chalkboard"></i> <?= gettext('Teachers') ?></h3>
  </div>
  <div class="card-body row">
    <?php foreach ($rsTeachers as $teacher) {
        ?>
      <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <!-- Begin user profile -->
        <div class="card card-primary text-center user-profile-2 h-100">
          <div class="user-profile-inner">
            <h4 class="white mb-3"><?= $teacher->getFirstName() . ' ' . $teacher->getLastName() ?></h4>
            <img data-image-entity-type="person"
                 data-image-entity-id="<?= $teacher->getId() ?>"
                 class="photo-small" />
            <div class="btn-group btn-group d-flex flex-column gap-2 mt-3" role="group">
                <a href="mailto:<?= $teacher->getEmail() ?>" type="button" class="btn btn-success btn-sm py-2" title="<?= gettext('Email') . ' ' . htmlspecialchars($teacher->getFirstName()) ?>">
                    <i class="fa-solid fa-envelope"></i> <?= gettext('Email') ?>
                </a>
                <a href="../PersonView.php?PersonID=<?= $teacher->getId() ?>" type="button" class="btn btn-primary btn-sm py-2" title="<?= gettext('View Profile') ?>">
                    <i class="fa-solid fa-user"></i> <?= gettext('Profile') ?>
                </a>
            </div>
          </div>
        </div>
      </div>
        <?php
    } ?>
  </div>
</div>

<div class="card card-primary card-outline">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-users"></i> <?= gettext('Students') ?> <span class="badge badge-primary"><?= count($thisClassChildren) ?></span></h3>
  </div>
  <div class="card-body">
    <h4 class="birthday-filter d-none alert alert-info mb-3">
      <?= gettext('Showing students with birthdays in') ?> <span class="month font-weight-bold"></span>
      <i class="icon fa-solid fa-times float-right birthday-filter-clear" title="<?= gettext('Clear filter') ?>"></i>
    </h4>
    <div class="table-responsive">
      <table id="sundayschool" class="table table-striped table-hover data-table w-100">
        <thead>
          <tr>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Age') ?></th>
            <th><?= gettext('Mobile') ?></th>
            <th><?= gettext('Email') ?></th>
            <th><?= gettext('Father') ?></th>
            <th><?= gettext('Mother') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php
          foreach ($thisClassChildren as $child) {
            $hideAge = $child['hideAge'];
            $age = MiscUtils::formatAge($child['birthMonth'], $child['birthDay'], $child['birthYear']);
            ?>
          <tr>
            <td>
              <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $child['kidId'] ?>">
                <strong><?= htmlspecialchars($child['LastName'] . ', ' . $child['firstName']) ?></strong>
              </a>
            </td>
            <td><?= $hideAge ? '—' : $age ?></td>
            <td>
              <?php if ($child['mobilePhone']) { ?>
                <a href="tel:<?= urlencode($child['mobilePhone']) ?>" title="Call">
                  <i class="fa-solid fa-phone text-primary"></i> <?= $child['mobilePhone'] ?>
                </a>
              <?php } else { ?>
                <span class="text-muted">—</span>
              <?php } ?>
            </td>
            <td>
              <?php if ($child['kidEmail']) { ?>
                <a href="mailto:<?= $child['kidEmail'] ?>" title="Email">
                  <i class="fa-solid fa-envelope text-primary"></i>
                </a>
              <?php } else { ?>
                <span class="text-muted">—</span>
              <?php } ?>
            </td>
            <td>
              <?php if ($child['dadFirstName']) { ?>
                <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $child['dadId'] ?>">
                  <?= htmlspecialchars($child['dadFirstName'] . ' ' . $child['dadLastName']) ?>
                </a>
                <?php if ($child['dadCellPhone']) { ?>
                  <br><small><a href="tel:<?= urlencode($child['dadCellPhone']) ?>"><?= $child['dadCellPhone'] ?></a></small>
                <?php } ?>
              <?php } else { ?>
                <span class="text-muted">—</span>
              <?php } ?>
            </td>
            <td>
              <?php if ($child['momFirstName']) { ?>
                <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $child['momId'] ?>">
                  <?= htmlspecialchars($child['momFirstName'] . ' ' . $child['momLastName']) ?>
                </a>
                <?php if ($child['momCellPhone']) { ?>
                  <br><small><a href="tel:<?= urlencode($child['momCellPhone']) ?>"><?= $child['momCellPhone'] ?></a></small>
                <?php } ?>
              <?php } else { ?>
                <span class="text-muted">—</span>
              <?php } ?>
              <button class="btn btn-xs btn-outline-primary float-right ml-2" data-toggle="modal" data-target="#studentModal-<?= $child['kidId'] ?>" title="<?= gettext('View Full Details') ?>">
                <i class="fa-solid fa-info-circle"></i>
              </button>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>

    <!-- Student Detail Modals -->
    <?php
      foreach ($thisClassChildren as $child) {
        $hideAge = $child['hideAge'];
        $birthDate = MiscUtils::formatBirthDate($child['birthYear'], $child['birthMonth'], $child['birthDay'], $hideAge);
        $address = trim($child['Address1'] . ' ' . $child['Address2'] . ' ' . $child['city'] . ' ' . $child['state'] . ' ' . $child['zip']);
        ?>
      <div class="modal fade" id="studentModal-<?= $child['kidId'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title">
                <i class="fa-solid fa-user"></i>
                <?= htmlspecialchars($child['firstName'] . ' ' . $child['LastName']) ?>
              </h4>
              <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <h6 class="font-weight-bold mb-3"><i class="fa-solid fa-circle-info"></i> <?= gettext('Student Information') ?></h6>
                  <dl class="row">
                    <dt class="col-sm-5"><?= gettext('Birth Date:') ?></dt>
                    <dd class="col-sm-7"><?= $birthDate ?></dd>
                    <dt class="col-sm-5"><?= gettext('Kids Email:') ?></dt>
                    <dd class="col-sm-7">
                      <?php if ($child['kidEmail']) { ?>
                        <a href="mailto:<?= $child['kidEmail'] ?>"><?= htmlspecialchars($child['kidEmail']) ?></a>
                      <?php } else { ?>
                        <span class="text-muted">—</span>
                      <?php } ?>
                    </dd>
                    <dt class="col-sm-5"><?= gettext('Mobile:') ?></dt>
                    <dd class="col-sm-7">
                      <?php if ($child['mobilePhone']) { ?>
                        <a href="tel:<?= urlencode($child['mobilePhone']) ?>"><?= $child['mobilePhone'] ?></a>
                      <?php } else { ?>
                        <span class="text-muted">—</span>
                      <?php } ?>
                    </dd>
                    <dt class="col-sm-5"><?= gettext('Home Phone:') ?></dt>
                    <dd class="col-sm-7">
                      <?php if ($child['homePhone']) { ?>
                        <a href="tel:<?= urlencode($child['homePhone']) ?>"><?= $child['homePhone'] ?></a>
                      <?php } else { ?>
                        <span class="text-muted">—</span>
                      <?php } ?>
                    </dd>
                  </dl>
                </div>
                <div class="col-md-6">
                  <h6 class="font-weight-bold mb-3"><i class="fa-solid fa-home"></i> <?= gettext('Address') ?></h6>
                  <address>
                    <?php if ($address) { ?>
                      <?= htmlspecialchars($address) ?>
                    <?php } else { ?>
                      <span class="text-muted">—</span>
                    <?php } ?>
                  </address>

                  <h6 class="font-weight-bold mb-3 mt-4"><i class="fa-solid fa-users"></i> <?= gettext('Parents/Guardians') ?></h6>
                  <?php if ($child['dadFirstName'] || $child['momFirstName']) { ?>
                    <dl class="row">
                      <?php if ($child['dadFirstName']) { ?>
                        <dt class="col-sm-5"><?= gettext('Father:') ?></dt>
                        <dd class="col-sm-7">
                          <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $child['dadId'] ?>">
                            <?= htmlspecialchars($child['dadFirstName'] . ' ' . $child['dadLastName']) ?>
                          </a>
                          <?php if ($child['dadCellPhone']) { ?>
                            <br><small><a href="tel:<?= urlencode($child['dadCellPhone']) ?>"><?= $child['dadCellPhone'] ?></a></small>
                          <?php } ?>
                          <?php if ($child['dadEmail']) { ?>
                            <br><small><a href="mailto:<?= $child['dadEmail'] ?>"><?= htmlspecialchars($child['dadEmail']) ?></a></small>
                          <?php } ?>
                        </dd>
                      <?php } ?>
                      <?php if ($child['momFirstName']) { ?>
                        <dt class="col-sm-5"><?= gettext('Mother:') ?></dt>
                        <dd class="col-sm-7">
                          <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $child['momId'] ?>">
                            <?= htmlspecialchars($child['momFirstName'] . ' ' . $child['momLastName']) ?>
                          </a>
                          <?php if ($child['momCellPhone']) { ?>
                            <br><small><a href="tel:<?= urlencode($child['momCellPhone']) ?>"><?= $child['momCellPhone'] ?></a></small>
                          <?php } ?>
                          <?php if ($child['momEmail']) { ?>
                            <br><small><a href="mailto:<?= $child['momEmail'] ?>"><?= htmlspecialchars($child['momEmail']) ?></a></small>
                          <?php } ?>
                        </dd>
                      <?php } ?>
                    </dl>
                  <?php } else { ?>
                    <span class="text-muted">—</span>
                  <?php } ?>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $child['kidId'] ?>" class="btn btn-primary">
                <i class="fa-solid fa-user"></i> <?= gettext('View Profile') ?>
              </a>
              <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
          </div>
        </div>
      </div>
      <?php } ?>
  </div>
</div>

<?php
function implodeUnique($array, $withQuotes): string
{
          array_unique($array);
          asort($array);
    if (count($array) > 0) {
        if ($withQuotes) {
            $string = implode("','", $array);

            return "'" . $string . "'";
        } else {
            return implode(',', $array);
        }
    }

          return '';
}

?>

<!-- COMPOSE MESSAGE MODAL -->
<div class="modal fade" id="compose-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content large">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title"><i class="fa-solid fa-envelope"></i><?= gettext('Compose New Message') ?></h4>
      </div>
      <form action="SendEmail.php" method="post">
        <div class="modal-body">
          <div class="form-group">
            <label><?= gettext('Kids Emails') ?></label>
            <input name="email_to" class="form-control email-recipients-kids"
                   value="<?= implodeUnique($KidsEmails, false) ?>">
          </div>
          <div class="form-group">
            <label><?= gettext('Parents Emails') ?></label>
            <input name="email_to_2" class="form-control email-recipients-parents"
                   value="<?= implodeUnique($ParentsEmails, false) ?>">
          </div>
          <div class="form-group">
            <label><?= gettext('Teachers Emails') ?></label>
            <input name="email_cc" class="form-control email-recipients-teachers"
                   value="<?= implodeUnique($TeachersEmails, false) ?>">
          </div>
          <div class="form-group">
            <textarea name="message" id="email_message" class="form-control" placeholder="Message"
                      style="height: 120px;"></textarea>
          </div>
          <div class="form-group">
            <div class="btn btn-success btn-file">
              <i class="fa-solid fa-paperclip"></i><?= gettext('Attachment') ?>
              <input type="file" name="attachment"/>
            </div>
            <p class="help-block"><?= gettext('Max. 32MB') ?></p>
          </div>

        </div>
        <div class="modal-footer clearfix">

          <button type="button" class="btn btn-danger" data-dismiss="modal"><i
              class="fa-solid fa-times"></i><?= gettext('Discard') ?></button>

          <button type="submit" class="btn btn-primary float-left"><i
              class="fa-solid fa-envelope"></i><?= gettext('Send Message') ?></button>
        </div>
      </form>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- chartjs -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/chartjs/chart.umd.js') ?>"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $(function () {

    var dataTable = $('.data-table').DataTable(window.CRM.plugin.dataTable);

    // turn the element to select2 select style
    $('.email-recipients-kids').select2({
      placeholder: 'Enter recipients',
      tags: [<?php implodeUnique($KidsEmails, true) ?>]
    });
    $('.email-recipients-teachers').select2({
      placeholder: 'Enter recipients',
      tags: [<?= implodeUnique($TeachersEmails, true) ?>]
    });
    $('.email-recipients-parents').select2({
      placeholder: 'Enter recipients',
      tags: [<?= implodeUnique($ParentsEmails, true) ?>]
    });

    var birthDateColumn = dataTable.column(':contains(Birth Date)');

    function hideBirthDayFilter() {
      birthDateColumn
        .search('')
        .draw();

      birthDayFilter.hide();
    }

    var birthDateColumn = dataTable.column(0);

    function hideBirthDayFilter() {
      birthDateColumn
        .search('')
        .draw();

      birthDayFilter.hide();
    }

    var birthDayFilter = $('.birthday-filter');
    var birthDayMonth = birthDayFilter.find('.month');
    birthDayFilter.find('i.fa-times')
      .bind('click', hideBirthDayFilter);

    // Birthday chart click filter
    if (window.barChart) {
      document.getElementById('bar-chart').onclick = function(event) {
        var activePoints = window.barChart.getElementsAtEvent(event);

        if (activePoints.length === 0) {
          hideBirthDayFilter();
          return;
        }

        var monthIndex = activePoints[0]._index;
        var month = window.barChart.data.labels[monthIndex];

        birthDayMonth.text(month);
        birthDayFilter.show();

        activePoints.forEach(function(point) {
          point.custom = point.custom || {};
          point.custom.backgroundColor = 'red';
        });

        window.barChart.update();
      };
    }
  });

  /*
   * BAR CHART - Birthdays by Month
   */
  var barData = <?= $birthDayMonthChartJSON ?>;
  var barLabels = barData.map(data => data[0]);
  var barValues = barData.map(data => data[1]);
  var maxBarValue = Math.max(...barValues);

  var barChartConfig = {
    type: 'bar',
    data: {
      labels: barLabels,
      datasets: [{
        label: 'Birthdays by Month',
        borderColor: '#3c8dbc',
        backgroundColor: '#9ec5de',
        borderWidth: 2,
        data: barValues
      }]
    },
    options: {
      scales: {
        y: {
          max: maxBarValue + 1,
          beginAtZero: true,
          ticks: {
            stepSize: 1,
          }
        }
      }
    }
  };

  var barChart = new Chart(document.getElementById('bar-chart'), barChartConfig);
  window.barChart = barChart;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
<?php
require_once __DIR__ . '/../Include/Footer.php';
