<?php

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\SundaySchoolService;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Authentication\AuthenticationManager;

$sundaySchoolService = new SundaySchoolService();

$iGroupId = '-1';
$iGroupName = 'Unknown';
if (isset($_GET['groupId'])) {
    $iGroupId = InputUtils::LegacyFilterInput($_GET['groupId'], 'int');
}

$sSQL = 'select * from group_grp where grp_ID ='.$iGroupId;
$rsSundaySchoolClass = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($rsSundaySchoolClass)) {
    $iGroupName = $aRow['grp_Name'];
}

$birthDayMonthChartArray = [];
foreach ($sundaySchoolService->getKidsBirthdayMonth($iGroupId) as $birthDayMonth => $kidsCount) {
    array_push($birthDayMonthChartArray, "['".gettext($birthDayMonth)."', ".$kidsCount.' ]');
}
$birthDayMonthChartJSON = implode(',', $birthDayMonthChartArray);

$genderChartArray = [];
foreach ($sundaySchoolService->getKidsGender($iGroupId) as $gender => $kidsCount) {
    array_push($genderChartArray, "{label: '".gettext($gender)."', data: ".$kidsCount.'}');
}
$genderChartJSON = implode(',', $genderChartArray);

$rsTeachers = $sundaySchoolService->getClassByRole($iGroupId, 'Teacher');
$sPageTitle = gettext('Sunday School').': '.$iGroupName;

$TeachersEmails = [];
$KidsEmails = [];
$ParentsEmails = [];

$thisClassChildren = $sundaySchoolService->getKidsFullDetails($iGroupId);

foreach ($thisClassChildren as $child) {
    if ($child['dadEmail'] != '') {
        array_push($ParentsEmails, $child['dadEmail']);
    }
    if ($child['momEmail'] != '') {
        array_push($ParentsEmails, $child['momEmail']);
    }
    if ($child['kidEmail'] != '') {
        array_push($KidsEmails, $child['kidEmail']);
    }
}

foreach ($rsTeachers as $teacher) {
    array_push($TeachersEmails, $teacher['per_Email']);
}

require '../Include/Header.php';

?>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('Sunday School Class Functions') ?></h3>
  </div>
  <div class="box-body">
    <?php
    $sMailtoDelimiter = AuthenticationManager::GetCurrentUser()->getUserConfigString("sMailtoDelimiter");
    $allEmails = array_unique(array_merge($ParentsEmails, $KidsEmails, $TeachersEmails));
    $roleEmails->Parents = implode($sMailtoDelimiter, $ParentsEmails).',';
    $roleEmails->Teachers = implode($sMailtoDelimiter, $TeachersEmails).',';
    $roleEmails->Kids = implode($sMailtoDelimiter, $KidsEmails).',';
    $sEmailLink = implode($sMailtoDelimiter, $allEmails).',';
    // Add default email if default email has been set and is not already in string
    if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
        $sEmailLink .= $sMailtoDelimiter.SystemConfig::getValue('sToEmailAddress');
    }
    $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368

    if (AuthenticationManager::GetCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
      // Display link
      ?>
      <div class="btn-group">
        <a class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><i
            class="fa fa-send-o"></i><?= gettext('Email') ?></a>
        <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown">
          <span class="caret"></span>
          <span class="sr-only"><?= gettext('Toggle Dropdown') ?></span>
        </button>
        <ul class="dropdown-menu" role="menu">
          <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
        </ul>
      </div>

      <div class="btn-group">
        <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><i
            class="fa fa-send"></i><?= gettext('Email (BCC)') ?></a>
        <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown">
          <span class="caret"></span>
          <span class="sr-only"><?= gettext('Toggle Dropdown') ?></span>
        </button>
        <ul class="dropdown-menu" role="menu">
          <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
        </ul>
      </div>
      <?php
    }
    ?>
    <!-- <a class="btn btn-success" data-toggle="modal" data-target="#compose-modal"><i class="fa fa-pencil"></i> Compose Message</a>  This doesn't really work right now...-->
    <a class="btn btn-app" href="../GroupView.php?GroupID=<?= $iGroupId ?>"><i
        class="fa fa-user-plus"></i><?= gettext('Add Students') ?> </a>

	<a class="btn btn-app" href="../GroupEditor.php?GroupID=<?= $iGroupId?>"><i class="fa fa-pencil"></i><?= gettext("Edit this Class") ?></a>
  </div>
</div>

<div class="box box-success">
  <div class="box-header">
    <h3 class="box-title"><?= gettext('Teachers') ?></h3>
  </div>
  <!-- /.box-header -->
  <div class="box-body row">
    <?php foreach ($rsTeachers as $teacher) {
        ?>
      <div class="col-sm-2">
        <!-- Begin user profile -->
        <div class="box box-info text-center user-profile-2">
          <div class="user-profile-inner">
            <h4 class="white"><?= $teacher['per_FirstName'].' '.$teacher['per_LastName'] ?></h4>
            <img src="<?= SystemURLs::getRootPath(); ?>/api/person/<?= $teacher['per_ID'] ?>/thumbnail"
                  alt="User Image" class="user-image initials-image" width="85" height="85" />
            <a href="mailto:<?= $teacher['per_Email'] ?>" type="button" class="btn btn-primary btn-sm btn-block"><i
                class="fa fa-envelope"></i> <?= gettext('Send Message') ?></a>
            <a href="../PersonView.php?PersonID=<?= $teacher['per_ID'] ?>" type="button"
               class="btn btn-primary btn-info btn-block"><i class="fa fa-q"></i><?= gettext('View Profile') ?></a>
          </div>
        </div>
      </div>
    <?php
    } ?>
  </div>
</div>

<div class="box box-info">
  <div class="box-header">
    <h3 class="box-title"><?= gettext('Quick Status') ?></h3>

    <div class="box-tools pull-right">
      <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
    </div>
  </div>
  <!-- /.box-header -->
  <div class="box-body row">
    <div class="col-lg-8">
      <!-- Bar chart -->
      <div class="box box-primary">
        <div class="box-header">
          <i class="fa fa-bar-chart-o"></i>

          <h3 class="box-title"><?= gettext('Birthdays by Month') ?></h3>
        </div>
        <div class="box-body">
          <div class="disableSelection" id="bar-chart" style="width: 100%; height: 300px;"></div>
        </div>
        <!-- /.box-body-->
      </div>
      <!-- /.box -->
    </div>
    <div class="col-lg-4">
      <!-- Donut chart -->
      <div class="box box-primary">
        <div class="box-header">
          <i class="fa fa-bar-chart-o"></i>

          <h3 class="box-title"><?= gettext('Gender') ?></h3>
        </div>
        <div class="box-body">
          <div id="donut-chart" style="width: 100%; height: 300px;"></div>
        </div>
        <!-- /.box-body-->
      </div>
      <!-- /.box -->
    </div>
  </div>
</div>

<div class="box box-primary">
  <div class="box-header">
    <h3 class="box-title"><?= gettext('Students') ?></h3>
  </div>
  <!-- /.box-header -->
  <div class="box-body table-responsive">
    <h4 class="birthday-filter" style="display:none;"><?= gettext('Showing students with birthdays in') ?><span class="month"></span> <i style="cursor:pointer; color:red;" class="icon fa fa-close"></i></h4>
    <table id="sundayschool" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
      <thead>
      <tr>
        <th></th>
        <th><?= gettext('Name') ?></th>
        <th><?= gettext('Birth Date') ?></th>
        <th><?= gettext('Age') ?></th>
        <th><?= gettext('Email') ?></th>
        <th><?= gettext('Mobile') ?></th>
        <th><?= gettext('Home Phone') ?></th>
        <th><?= gettext('Home Address') ?></th>
        <th><?= gettext('Dad Name') ?></th>
        <th><?= gettext('Dad Mobile') ?></th>
        <th><?= gettext('Dad Email') ?></th>
        <th><?= gettext('Mom Name') ?></th>
        <th><?= gettext('Mom Mobile') ?></th>
        <th><?= gettext('Mom Email') ?></th>
      </tr>
      </thead>
      <tbody>
      <?php

      foreach ($thisClassChildren as $child) {
          $hideAge = $child['flags'] == 1 || $child['birthYear'] == '' || $child['birthYear'] == '0';
          $birthDate = MiscUtils::FormatBirthDate($child['birthYear'], $child['birthMonth'], $child['birthDay'], '-', $child['flags']); ?>
          <tr>
          <td>
            <img src="<?= SystemURLs::getRootPath(); ?>/api/person/<?= $child['kidId'] ?>/thumbnail"
                alt="User Image" class="user-image initials-image" style="width: <?= SystemConfig::getValue('iProfilePictureListSize') ?>px !; height: <?= SystemConfig::getValue('iProfilePictureListSize') ?>px; max-width:none" />
          </td>
          <td><a href="<?= SystemURLs::getRootPath(); ?>/PersonView.php?PersonID=<?= $child['kidId'] ?>"><?= $child['LastName'].', '.$child['firstName'] ?></a></td>
          <td><?= $birthDate ?> </td>
          <td><?= MiscUtils::FormatAge($child['birthMonth'], $child['birthDay'], $child['birthYear'], $child['flags']) ?></td>
          <td><?= $child['kidEmail'] ?></td>
          <td><?= $child['mobilePhone'] ?></td>
          <td><?= $child['homePhone'] ?></td>
          <td><?= $child['Address1'].' '.$child['Address2'].' '.$child['city'].' '.$child['state'].' '.$child['zip'] ?></td>
          <td><a href='<?= SystemURLs::getRootPath(); ?>/PersonView.php?PersonID=<?= $child['dadId'] ?>'><?= $child['dadFirstName'].' '.$child['dadLastName'] ?></a></td>
          <td><?= $child['dadCellPhone'] ?></td>
          <td><?= $child['dadEmail'] ?></td>
          <td><a href='<?= SystemURLs::getRootPath(); ?>/PersonView.php?PersonID=<?= $child['momId'] ?>'><?= $child['momFirstName'].' '.$child['momLastName'] ?></td>
          <td><?= $child['momCellPhone'] ?></td>
          <td><?= $child['momEmail'] ?></td>
          </tr>

      <?php
      }

      ?>
      </tbody>
    </table>
  </div>
</div>

<?php
function implodeUnique($array, $withQuotes)
      {
          array_unique($array);
          asort($array);
          if (count($array) > 0) {
              if ($withQuotes) {
                  $string = implode("','", $array);

                  return "'".$string."'";
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
        <h4 class="modal-title"><i class="fa fa-envelope-o"></i><?= gettext('Compose New Message') ?></h4>
      </div>
      <form action="SendEmail.php" method="post">
        <div class="modal-body">
          <div class="form-group">
            <label><?= gettext('Kids Emails') ?></label>
            <input name="email_to" class="form-control email-recepients-kids"
                   value="<?= implodeUnique($KidsEmails, false) ?>">
          </div>
          <div class="form-group">
            <label><?= gettext('Parents Emails') ?></label>
            <input name="email_to_2" class="form-control email-recepients-parents"
                   value="<?= implodeUnique($ParentsEmails, false) ?>">
          </div>
          <div class="form-group">
            <label><?= gettext('Teachers Emails') ?></label>
            <input name="email_cc" class="form-control email-recepients-teachers"
                   value="<?= implodeUnique($TeachersEmails, false) ?>">
          </div>
          <div class="form-group">
            <textarea name="message" id="email_message" class="form-control" placeholder="Message"
                      style="height: 120px;"></textarea>
          </div>
          <div class="form-group">
            <div class="btn btn-success btn-file">
              <i class="fa fa-paperclip"></i><?= gettext('Attachment') ?>
              <input type="file" name="attachment"/>
            </div>
            <p class="help-block"><?= gettext('Max. 32MB') ?></p>
          </div>

        </div>
        <div class="modal-footer clearfix">

          <button type="button" class="btn btn-danger" data-dismiss="modal"><i
              class="fa fa-times"></i><?= gettext('Discard') ?></button>

          <button type="submit" class="btn btn-primary pull-left"><i
              class="fa fa-envelope"></i><?= gettext('Send Message') ?></button>
        </div>
      </form>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- FLOT CHARTS -->
<script  src="<?= SystemURLs::getRootPath() ?>/skin/external/flot/jquery.flot.js"></script>
<!-- FLOT RESIZE PLUGIN - allows the chart to redraw when the window is resized -->
<script  src="<?= SystemURLs::getRootPath() ?>/skin/external/flot/jquery.flot.resize.js"></script>
<!-- FLOT PIE PLUGIN - also used to draw donut charts -->
<script  src="<?= SystemURLs::getRootPath() ?>/skin/external/flot/jquery.flot.pie.js"></script>
<!-- FLOT CATEGORIES PLUGIN - Used to draw bar charts -->
<script  src="<?= SystemURLs::getRootPath() ?>/skin/external/flot/jquery.flot.categories.js"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $(document).ready(function () {

    var dataTable = $('.data-table').DataTable(window.CRM.plugin.dataTable);

    // turn the element to select2 select style
    $('.email-recepients-kids').select2({
      placeholder: 'Enter recepients',
      tags: [<?php implodeUnique($KidsEmails, true) ?>]
    });
    $('.email-recepients-teachers').select2({
      placeholder: 'Enter recepients',
      tags: [<?= implodeUnique($TeachersEmails, true) ?>]
    });
    $('.email-recepients-parents').select2({
      placeholder: 'Enter recepients',
      tags: [<?= implodeUnique($ParentsEmails, true) ?>]
    });

    var birthDateColumn = dataTable.column(':contains(Birth Date)');

    var hideBirthDayFilter = function() {
      plot.unhighlight();
      birthDateColumn
        .search('')
        .draw();

      birthDayFilter.hide();
    };

    var birthDayFilter = $('.birthday-filter');
    var birthDayMonth = birthDayFilter.find('.month');
    birthDayFilter.find('i.fa-close')
      .bind('click', hideBirthDayFilter);

    $("#bar-chart").bind("plotclick", function (event, pos, item) {
      plot.unhighlight();

      if (!item) {
        hideBirthDayFilter();
        return;
      }

      var month = bar_data.data[item.dataIndex][0];

      birthDateColumn
        .search(month.substr(0, 3))
        .draw();

      birthDayMonth.text(month);
      birthDayFilter.show();

      plot.highlight(item.series, item.datapoint);
    });
  });

  /*
   * BAR CHART
   * ---------
   */

  var bar_data = {
    data: [
      <?= $birthDayMonthChartJSON ?>
    ],
    color: "#3c8dbc"
  };

 var plot = $.plot("#bar-chart", [bar_data], {
    grid: {
      borderWidth: 1,
      borderColor: "#f3f3f3",
      tickColor: "#f3f3f3",
      hoverable:true,
      clickable:true
    },
    series: {
      bars: {
        show: true,
        barWidth: 0.5,
        align: "center"
      }
    },
    xaxis: {
      mode: "categories",
      tickLength: 0
    },
    yaxis: {
      tickSize: 1
    }
  });

  /* END BAR CHART */

  /*
   * DONUT CHART
   * -----------
   */

  var donutData = [<?=$genderChartJSON ?>];

  $.plot("#donut-chart", donutData, {
    series: {
      pie: {
        show: true,
        radius: 1,
        innerRadius: 0.5,
        label: {
          show: true,
          radius: 2 / 3,
          formatter: labelFormatter,
          threshold: 0.1
        }

      }
    },
    legend: {
      show: false
    }
  });
  /*
   * END DONUT CHART
   */
  /*
   * Custom Label formatter
   * ----------------------
   */
  function labelFormatter(label, series) {
    return "<div style='font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;'>"
      + label
      + "<br/>"
      + Math.round(series.percent) + "%</div>";
  }

</script>
<?php
require '../Include/Footer.php';
?>
