<?php

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/PersonFunctions.php";
require "../Service/SundaySchoolService.php";

$sundaySchoolService = new SundaySchoolService();

$iGroupId = "-1";
$iGroupName = "Unknown";
if (isset($_GET['groupId'])) {
  $iGroupId = FilterInput($_GET["groupId"], "int");
}

$sSQL = "select * from group_grp where grp_ID =" . $iGroupId;
$rsSundaySchoolClass = RunQuery($sSQL);
while ($aRow = mysql_fetch_array($rsSundaySchoolClass)) {
  $iGroupName = $aRow['grp_Name'];
}

$birthDayMonthChartArray = array();
foreach ($sundaySchoolService->getKidsBirthdayMonth($iGroupId) as $birthDayMonth => $kidsCount) {
  array_push($birthDayMonthChartArray, "['" . $birthDayMonth . "', " . $kidsCount . " ]");
}
$birthDayMonthChartJSON = implode(",", $birthDayMonthChartArray);

$genderChartArray = array();
foreach ($sundaySchoolService->getKidsGender($iGroupId) as $gender => $kidsCount) {
  array_push($genderChartArray, "{label: '" . $gender . "', data: " . $kidsCount . "}");
}
$genderChartJSON = implode(",", $genderChartArray);


$rsTeachers = $sundaySchoolService->getClassByRole($iGroupId, "Teacher");
$sPageTitle = gettext("Sunday School: " . $iGroupName);

$TeachersEmails = array();
$KidsEmails = array();
$ParentsEmails = array();

$thisClassChildren = $sundaySchoolService->getKidsFullDetails($iGroupId);

foreach ($thisClassChildren as $child) {
 if ($child['dadEmail'] != "")
   array_push($ParentsEmails,  $child['dadEmail']);
 if ($child['momEmail'] != "")
   array_push($ParentsEmails, $child['momEmail']);
 if ($child['kidEmail'] != "")
   array_push($KidsEmails,  $child['kidEmail']);
}

foreach ($rsTeachers as $teacher) {
  array_push($TeachersEmails, $teacher['per_Email']);
}

require "../Include/Header.php";

?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Sunday School Class Functions</h3>
    </div>
  <div class="box-body">
    <?php
      $allEmails = array_unique(array_merge($ParentsEmails, $KidsEmails,$TeachersEmails));
      $roleEmails->Parents = join($sMailtoDelimiter, $ParentsEmails).",";
      $roleEmails->Teachers = join($sMailtoDelimiter, $TeachersEmails).",";
      $roleEmails->Kids = join($sMailtoDelimiter, $KidsEmails).",";
      $sEmailLink = join($sMailtoDelimiter, $allEmails).",";
      // Add default email if default email has been set and is not already in string
      if ($sToEmailAddress != '' && $sToEmailAddress != 'myReceiveEmailAddress' && !stristr($sEmailLink, $sToEmailAddress))
          $sEmailLink .= $sMailtoDelimiter . $sToEmailAddress;
      $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368

      if ($bEmailMailto) { // Does user have permission to email groups
      // Display link
      ?>
        <div class="btn-group">
          <a  class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink,0,-3)?>"><i class="fa fa-send-o"></i><?= gettext('Email')?></a>
          <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
           <?php generateGroupRoleEmailDropdown($roleEmails,"mailto:") ?>
          </ul>
        </div>
    
        <div class="btn-group">
          <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink,0,-3) ?>"><i class="fa fa-send"></i><?=gettext('Email (BCC)') ?></a>
           <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
           <?php generateGroupRoleEmailDropdown($roleEmails,"mailto:?bcc=") ?>
          </ul>
        </div>
      <?php
      }
    ?>
    <!-- <a class="btn btn-success" data-toggle="modal" data-target="#compose-modal"><i class="fa fa-pencil"></i> Compose Message</a>  This doesn't really work right now...-->
    <a class="btn btn-app" href="../GroupView.php?GroupID=<?= $iGroupId ?>"><i class="fa fa-eye-slash"></i> LegacyView </a>
  </div>
</div>

<div class="box box-success">
  <div class="box-header">
    <h3 class="box-title">Teachers</h3>
  </div>
  <!-- /.box-header -->
  <div class="box-body row">
    <?php foreach ($rsTeachers as $teacher) {
      ?>
      <div class="col-sm-2">
        <!-- Begin user profile -->
        <div class="box box-info text-center user-profile-2">
          <div class="user-profile-inner">
            <h4 class="white"><?= $teacher['per_FirstName'] . " " . $teacher['per_LastName'] ?></h4>
            <img src="<?= $personService->getPhoto($teacher['per_ID']); ?>" class="img-circle profile-avatar" alt="User avatar" width="80" height="80">
            <a href="mailto:<?= $teacher['per_Email'] ?>" type="button" class="btn btn-primary btn-sm btn-block"><i class="fa fa-envelope"></i> Send Message</a>
            <a href="../PersonView.php?PersonID=<?= $teacher['per_ID'] ?>" type="button" class="btn btn-primary btn-info btn-block"><i class="fa fa-q"></i> View Profile</a>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
</div>

<div class="box box-info collapsed-box">
  <div class="box-header">
    <h3 class="box-title">Quick Status</h3>
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

          <h3 class="box-title">Birthdays by Month</h3>
        </div>
        <div class="box-body">
          <div id="bar-chart" style="height: 300px;"></div>
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

          <h3 class="box-title">Gender</h3>
        </div>
        <div class="box-body">
          <div id="donut-chart" style="height: 300px;"></div>
        </div>
        <!-- /.box-body-->
      </div>
      <!-- /.box -->
    </div>
  </div>
</div>

<div class="box box-primary">
  <div class="box-header">
    <h3 class="box-title">Kids</h3>
  </div>
  <!-- /.box-header -->
  <div class="box-body table-responsive">
    <table id="sundayschool" class="table table-striped table-bordered" cellspacing="0" width="100%">
      <thead>
      <tr>
        <th>Name</th>
        <th>Birth Date</th>
        <th>Age</th>
        <th>Email</th>
        <th>Mobile</th>
        <th>Home Phone</th>
        <th>Home Address</th>
        <th>Dad Name</th>
        <th>Dad Mobile</th>
        <th>Dad Email</th>
        <th>Mom Name</th>
        <th>Mom Mobile</th>
        <th>Mom Email</th>
      </tr>
      </thead>
      <tbody>
      <?php

      foreach ($thisClassChildren as $child)
      {
        $birthDate = "";
        if ($child['birthYear'] != "") {
          $birthDate = $child['birthMonth'] . "/" . $child['birthDay'] . "/" . $child['birthYear'];
        }
        echo "<tr>";
        echo "<td><img src='" . $personService->getPhoto($child['kidId']) . "' hight='30' width='30' > <a href='../PersonView.php?PersonID=" . $child['kidId'] . "'>" . $child['firstName'] . ", " . $child['LastName'] . "</a></td>";
        echo "<td>" . $birthDate . "</td>";
        echo "<td>" . FormatAge($child['birthMonth'], $child['birthDay'], $child['birthYear'], "") . "</td>";
        echo "<td>" . $child['kidEmail'] . "</td>";
        echo "<td>" . $child['mobilePhone'] . "</td>";
        echo "<td>" . $child['homePhone'] . "</td>";
        echo "<td>" . $child['Address1'] . " " . $child['Address2'] . " " . $child['city'] . " " . $child['state'] . " " . $child['zip'] . "</td>";
        echo "<td><a href='../PersonView.php?PersonID=" . $child['dadId'] . "'>" . $child['dadFirstName'] . " " . $child['dadLastName'] . "</a></td>";
        echo "<td>" . $child['dadCellPhone'] . "</td>";
        echo "<td>" . $child['dadEmail'] . "</td>";
        echo "<td><a href='../PersonView.php?PersonID=" . $child['momId'] . "'>" . $child['momFirstName'] . " " . $child['momLastName'] . "</td>";
        echo "<td>" . $child['momCellPhone'] . "</td>";
        echo "<td>" . $child['momEmail'] . "</td>";
        echo "</tr>";
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
      return "'" . $string . "'";
    } else {
      return implode(",", $array);
    }
  }
  return "";
}

?>

<!-- COMPOSE MESSAGE MODAL -->
<div class="modal fade" id="compose-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content large">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title"><i class="fa fa-envelope-o"></i> Compose New Message</h4>
      </div>
      <form action="SendEmail.php" method="post">
        <div class="modal-body">
          <div class="form-group">
            <label>Kids Emails</label>
            <input name="email_to" class="form-control email-recepients-kids" value="<?= implodeUnique($KidsEmails, false) ?>">
          </div>
          <div class="form-group">
            <label>Parents Emails</label>
            <input name="email_to_2" class="form-control email-recepients-parents" value="<?= implodeUnique($ParentsEmails, false) ?>">
          </div>
          <div class="form-group">
            <label>Teachers Emails</label>
            <input name="email_cc" class="form-control email-recepients-teachers" value="<?= implodeUnique($TeachersEmails, false) ?>">
          </div>
          <div class="form-group">
            <textarea name="message" id="email_message" class="form-control" placeholder="Message" style="height: 120px;"></textarea>
          </div>
          <div class="form-group">
            <div class="btn btn-success btn-file">
              <i class="fa fa-paperclip"></i> Attachment
              <input type="file" name="attachment"/>
            </div>
            <p class="help-block">Max. 32MB</p>
          </div>

        </div>
        <div class="modal-footer clearfix">

          <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Discard</button>

          <button type="submit" class="btn btn-primary pull-left"><i class="fa fa-envelope"></i> Send Message</button>
        </div>
      </form>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- FLOT CHARTS -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.min.js" type="text/javascript"></script>
<!-- FLOT RESIZE PLUGIN - allows the chart to redraw when the window is resized -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.resize.min.js" type="text/javascript"></script>
<!-- FLOT PIE PLUGIN - also used to draw donut charts -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.pie.min.js" type="text/javascript"></script>
<!-- FLOT CATEGORIES PLUGIN - Used to draw bar charts -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.categories.min.js" type="text/javascript"></script>

<script type="text/javascript" charset="utf-8">
  $(document).ready(function () {
    $('#sundayschool').dataTable({
      "dom": 'T<"clear">lfrtip',
      "tableTools": {
        "sSwfPath": "//cdn.datatables.net/tabletools/2.2.3/swf/copy_csv_xls_pdf.swf"
      }
    });

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
  $.plot("#bar-chart", [bar_data], {
    grid: {
      borderWidth: 1,
      borderColor: "#f3f3f3",
      tickColor: "#f3f3f3"
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

require "../Include/Footer.php";

?>
