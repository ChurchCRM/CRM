<?php
/*******************************************************************************
 *
 *  filename    : Include/Header-functions.php
 *  website     : http://www.churchcrm.io
 *  description : page header used for most pages
 *
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 *  This file best viewed in a text editor with tabs stops set to 4 characters
 *
 ******************************************************************************/

require_once dirname(__FILE__) . '/../Service/PersonService.php';
require_once 'Functions.php';
require_once dirname(__FILE__) . "/../Service/TaskService.php";

function Header_head_metatag() {
  global $sLanguage, $bExportCSV, $sMetaRefresh, $sHeader, $sGlobalMessage;
  global $sPageTitle, $sRootPath;

  if (strlen($sMetaRefresh)) {
    echo $sMetaRefresh;
  }
  ?>
  <title>ChurchCRM: <?= $sPageTitle ?></title>
  <?php
}

function Header_modals() {
  ?>
  <!-- API Call Error Modal -->
  <div id="APIError" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <!-- Modal content-->
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">ERROR!</h4>
        </div>
        <div class="modal-body">
          <p>Error making API Call to: <span id="APIEndpoint"></span></p>

          <p>Error text: <span id="APIErrorText"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <!-- End API Call Error Modal -->

  <!-- Issue Report Modal -->
  <div id="IssueReportModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <!-- Modal content-->
      <div class="modal-content">
        <form name="issueReport">
          <input type="hidden" name="pageName" value="<?= $_SERVER['SCRIPT_NAME']?>"/>
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Issue Report!</h4>
          </div>
          <div class="modal-body">
            <div class="container-fluid">
              <div class="row">
                <div class="col-xl-3">
                  <label for="issueTitle">Enter a Title for your bug / feature report: </label>
                </div>
                <div class="col-xl-3">
                  <input type="text" name="issueTitle" style="width:100%"></input>
                </div>
              </div>
              <div class="row">
                <div class="col-xl-3">
                  <label for="issueDescription">What were you doing when you noticed the bug / feature opportunity?</label>
                </div>
                <div class="col-xl-3">
                  <textarea rows="10" cols="50" name="issueDescription" style="width:100%"></textarea>
                </div>
              </div>
            </div>
            <ul>
              <li>When you click "submit," an error report will be posted to the ChurchCRM GitHub Issue tracker.</li>
              <li>Please do not include any confidential information.</li>
              <li>Some general information about your system will be submitted along with the request such as Server version and browser headers.</li>
              <li>No personally identifiable information will be submitted unless you purposefully include it.</li>
            </ul>
          </div>
          <div class="modal-footer">

            <button type="button" class="btn btn-primary" id="submitIssue">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- End Issue Report Modal -->

  <?php
}

function Header_body_scripts() {
  global $sLanguage, $bExportCSV, $sMetaRefresh, $bRegistered, $sHeader, $sGlobalMessage;
  global $bLockURL, $URL, $sRootPath;

  checkAllowedURL();
  ?>
  <script type="text/javascript" src="<?= $sRootPath ?>/skin/js/IssueReporter.js" type="text/javascript"></script>
  <script type="text/javascript" src="<?= $sRootPath ?>/Include/jscalendar/calendar.js"></script>
  <script type="text/javascript" src="<?= $sRootPath ?>/Include/jscalendar/lang/calendar-<?= substr($sLanguage, 0, 2) ?>.js"></script>

  <script language="javascript" type="text/javascript">
    window.CRM = {root: "<?= $sRootPath ?>"};

    // Popup Calendar stuff
    function selected(cal, date) {
      cal.sel.value = date; // update the date in the input field.
      if(cal.dateClicked)
        cal.callCloseHandler();
    }

    function closeHandler(cal) {
      cal.hide(); // hide the calendar
    }

    function showCalendar(id, format) {
      var el = document.getElementById(id);
      if(calendar != null) {
        calendar.hide();
      }
      else {
        var cal = new Calendar(false, null, selected, closeHandler);
        cal.weekNumbers = false;
        calendar = cal;                  // remember it in the global var
        cal.setRange(1900, 2070);        // min/max year allowed.
        cal.create();
      }
      calendar.setDateFormat(format);    // set the specified date format
      calendar.parseDate(el.value);      // try to parse the text in field
      calendar.sel = el;                 // inform it what input field we use
      calendar.showAtElement(el);        // show the calendar below it
      return false;
    }

    var MINUTE = 60 * 1000;
    var HOUR = 60 * MINUTE;
    var DAY = 24 * HOUR;
    var WEEK = 7 * DAY;

    function isDisabled(date) {
      var today = new Date();
      return (Math.abs(date.getTime() - today.getTime()) / DAY) > 10;
    }

    // Clear a field on the first focus
    var priorSelect = new Array();
    function ClearFieldOnce(sField) {
      if(priorSelect[sField.id]) {
        sField.select();
      }
      else {
        sField.value = "";
        priorSelect[sField.id] = true;
      }
    }

    function LimitTextSize(theTextArea, size) {
      if(theTextArea.value.length > size) {
        theTextArea.value = theTextArea.value.substr(0, size);
      }
    }

    function popUp(URL) {
      day = new Date();
      id = day.getTime();
      eval("page" + id + " = window.open(URL, '" + id + "', 'toolbar=0,scrollbars=yes,location=0,statusbar=0,menubar=0,resizable=yes,width=600,height=400,left = 100,top = 50');");
    }

  </script>
  <?php
}

$security_matrix = GetSecuritySettings();

function GetSecuritySettings() {
  $aSecurityList[] = "bAdmin";
  $aSecurityList[] = "bAddRecords";
  $aSecurityList[] = "bEditRecords";
  $aSecurityList[] = "bDeleteRecords";
  $aSecurityList[] = "bMenuOptions";
  $aSecurityList[] = "bManageGroups";
  $aSecurityList[] = "bFinance";
  $aSecurityList[] = "bNotes";
  $aSecurityList[] = "bCommunication";
  $aSecurityList[] = "bCanvasser";
  $aSecurityList[] = "bAddEvent";
  $aSecurityList[] = "bSeePrivacyData";

  $sSQL = "SELECT DISTINCT ucfg_name FROM userconfig_ucfg WHERE ucfg_per_id = 0 AND ucfg_cat = 'SECURITY' ORDER by ucfg_id";
  $rsSecGrpList = RunQuery($sSQL);

  while ($aRow = mysql_fetch_array($rsSecGrpList)) {
    $aSecurityList[] = $aRow['ucfg_name'];
  }

  asort($aSecurityList);

  $sSecurityCond = " AND (security_grp = 'bALL'";
  for ($i = 0; $i < count($aSecurityList); $i++) {
    if (array_key_exists($aSecurityList[$i], $_SESSION) && $_SESSION[$aSecurityList[$i]]) {
      $sSecurityCond .= " OR security_grp = '" . $aSecurityList[$i] . "'";
    }
  }
  $sSecurityCond .= ")";
  return $sSecurityCond;
}

function addMenu($menu) {
  global $security_matrix;

  $sSQL = "SELECT name, ismenu, parent, content, uri, statustext, session_var, session_var_in_text, session_var_in_uri, url_parm_name, security_grp, icon FROM menuconfig_mcf WHERE parent = '$menu' AND active=1 " . $security_matrix . " ORDER BY sortorder";

  $rsMenu = RunQuery($sSQL);
  $item_cnt = mysql_num_rows($rsMenu);
  $idx = 1;
  $ptr = 1;
  while ($aRow = mysql_fetch_array($rsMenu)) {
    if (addMenuItem($aRow, $idx)) {
      if ($ptr == $item_cnt) {
        $idx++;
      }
      $ptr++;
    }
    else {
      $item_cnt--;
    }
  }
}

function addMenuItem($aMenu, $mIdx) {
  global $security_matrix, $sRootPath;

  $link = ($aMenu['uri'] == "") ? "" : $sRootPath . "/" . $aMenu['uri'];
  $text = $aMenu['statustext'];
  if (!is_null($aMenu['session_var'])) {
    if (($link > "") && ($aMenu['session_var_in_uri']) && isset($_SESSION[$aMenu['session_var']])) {
      if (strstr($link, "?") && true) {
        $cConnector = "&";
      }
      else {
        $cConnector = "?";
      }
      $link .= $cConnector . $aMenu['url_parm_name'] . "=" . $_SESSION[$aMenu['session_var']];
    }
    if (($text > "") && ($aMenu['session_var_in_text']) && isset($_SESSION[$aMenu['session_var']])) {
      $text .= " " . $_SESSION[$aMenu['session_var']];
    }
  }
  if ($aMenu['ismenu']) {
    $sSQL = "SELECT name FROM menuconfig_mcf WHERE parent = '" . $aMenu['name'] . "' AND active=1 " . $security_matrix . " ORDER BY sortorder";
    $rsItemCnt = RunQuery($sSQL);
    $numItems = mysql_num_rows($rsItemCnt);
  }
  if (!($aMenu['ismenu']) || ($numItems > 0)) {
    if ($link) {
      if ($aMenu['name'] != "sundayschool-dash") { # HACK to remove the sunday school 2nd dashboard
        echo "<li><a href='$link'>";
        if ($aMenu['icon'] != "") {
          echo "<i class=\"fa " . $aMenu['icon'] . "\"></i>";
        }
        if ($aMenu['parent'] != "root") {
          echo "<i class=\"fa fa-angle-double-right\"></i> ";
        }
        if ($aMenu['parent'] == "root") {
          echo "<span>" . gettext($aMenu['content']) . "</span></a>";
        }
        else {
          echo gettext($aMenu['content']) . "</a>";
        }
      }
    }
    else {
      echo "<li class=\"treeview\">\n";
      echo "    <a href=\"#\">\n";
      if ($aMenu['icon'] != "") {
        echo "<i class=\"fa " . $aMenu['icon'] . "\"></i>\n";
      }
      echo "<span>" . gettext($aMenu['content']) . "</span>\n";
      echo "<i class=\"fa fa-angle-left pull-right\"></i>\n";
      if ($aMenu['name'] == "deposit") {
        echo "<small class=\"badge pull-right bg-green\">" . $_SESSION['iCurrentDeposit'] . "</small>\n";
      }
      ?>  </a>
      <ul class="treeview-menu">
        <?php
        if ($aMenu['name'] == "sundayschool") {
          echo "<li><a href='" . $sRootPath . "/sundayschool/SundaySchoolDashboard.php'><i class='fa fa-angle-double-right'></i>".gettext("Dashboard")."</a></li>";
          $sSQL = "select * from group_grp where grp_Type = 4 order by grp_name";
          $rsSundaySchoolClasses = RunQuery($sSQL);
          while ($aRow = mysql_fetch_array($rsSundaySchoolClasses)) {
            echo "<li><a href='" . $sRootPath . "/sundayschool/SundaySchoolClassView.php?groupId=" . $aRow[grp_ID] . "'><i class='fa fa-angle-double-right'></i> " . gettext($aRow[grp_Name]) . "</a></li>";
          }
        }
      }
      if (($aMenu['ismenu']) && ($numItems > 0)) {
        echo "\n";
        addMenu($aMenu['name']);
        echo "</ul>\n</li>\n";
      }
      else {
        echo "</li>\n";
      }

      return true;
    }
    else {
      return false;
    }
  }

  function Header_body_menu() {
    global $sLanguage, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $bRegistered, $sHeader, $sGlobalMessage, $sGlobalMessageClass;
    global $MenuFirst, $sPageTitle, $sPageTitleSub, $sRootPath;

    $loggedInUserPhoto = (new PersonService())->getPhoto($_SESSION['iUserID']);

    $MenuFirst = 1;

    $taskService = new TaskService();
    ?>

    <header class="main-header">
      <!-- Logo -->
      <a href="<?= $sRootPath ?>/Menu.php" class="logo">
        <!-- mini logo for sidebar mini 50x50 pixels -->
        <span class="logo-mini"><b>C</b>RM</span>
        <!-- logo for regular state and mobile devices -->
        <?php if ($sHeader) { ?>
          <span class="logo-lg"><?= html_entity_decode($sHeader, ENT_QUOTES) ?></span>
        <?php }
        Else {
          ?>
          <span class="logo-lg"><b>Church</b>CRM</span>
  <?php } ?>
      </a>
      <!-- Header Navbar: style can be found in header.less -->
      <nav class="navbar navbar-static-top" role="navigation">
        <!-- Sidebar toggle button-->
        <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </a>

        <div class="navbar-custom-menu">
          <ul class="nav navbar-nav">
            <li class="dropdown settings-dropdown">
              <a href="<?= $sRootPath . "/" ?>CartView.php">
                <i class="fa fa-shopping-cart"></i>
                <span class="label label-success"><?= count($_SESSION['aPeopleCart']) ?></span>
              </a>

            </li>
            <!-- User Account: style can be found in dropdown.less -->
            <li class="dropdown user user-menu">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                <img src="<?= $loggedInUserPhoto ?>" class="user-image" alt="User Image">
                <span class="hidden-xs"><?= $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName'] ?> </span>

              </a>
              <ul class="dropdown-menu">
                <!-- User image -->
                <li class="user-header">
                  <img src="<?= $loggedInUserPhoto ?>" class="img-circle" alt="User Image">

                  <p>
  										<?= $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName'] ?>
                      <!--<small>Member since Nov. 2012</small>-->
                  </p>
                </li>
                <!-- Menu Body
                <li class="user-body">
                    <div class="row">
                        <div class="col-xs-4 text-center">
                            <a href="#">Followers</a>
                        </div>
                        <div class="col-xs-4 text-center">
                            <a href="#">Sales</a>
                        </div>
                        <div class="col-xs-4 text-center">
                            <a href="#">Friends</a>
                        </div>
                    </div>
                <!-- /.row --
                </li>-->
                <!-- Menu Footer-->
                <li class="user-footer">
                  <div class="pull-left">
                    <a href="<?= $sRootPath . "/" ?>UserPasswordChange.php" class="btn btn-default btn-flat"><?= gettext("Change Password")?></a>
                  </div>
                  <div class="pull-right">
                    <a href="<?= $sRootPath . "/" ?>SettingsIndividual.php" class="btn btn-default btn-flat"><?= gettext("My Settings")?></a>
                  </div>
                </li>
              </ul>
            </li>
  						<?php if ($_SESSION['bAdmin']) { ?>
              <li class="hidden-xxs">
                <a class="js-gitter-toggle-chat-button">
                  <i class="fa fa-comments"></i>
                </a>
              </li>
              <li class="dropdown settings-dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                  <i class="fa fa-cog"></i>
                </a>
                <ul class="dropdown-menu">
                  <li class="user-body">
                    <?php addMenu("admin"); ?>
                  </li>
                </ul>
              </li>
              <?php
                  $tasks = $taskService->getCurrentUserTasks();
                  $taskSize = count($tasks);
              ?>
            <li class="dropdown tasks-menu">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                <i class="fa fa-flag-o"></i>
                <span class="label label-danger"><?= $taskSize ?></span>
              </a>
              <ul class="dropdown-menu">
                <li class="header">You have <?= $taskSize ?> task(s)</li>
                <li>
                  <!-- inner menu: contains the actual data -->
                  <div class="slimScrollDiv" style="position: relative; overflow: hidden; width: auto; height: 200px;">
                    <ul class="menu" style="overflow: hidden; width: 100%; height: 200px;">
                      <?php foreach ($tasks as $task) { ?>
                      <li><!-- Task item -->
                        <a href="<?= $task["link"] ?>">
                          <h3><?= $task["title"] ?>
                            <?php if ($task["admin"]) { ?>
                            <small class="pull-right"><i class="fa fa-fw fa-lock"></i></small>
                            <?php } ?>
                          </h3>
                        </a>
                      </li>
                      <!-- end task item -->
                      <?php } ?>
                    </ul><div class="slimScrollBar" style="width: 3px; position: absolute; top: 11px; opacity: 0.4; display: none; border-radius: 7px; z-index: 99; right: 1px; height: 188.679px; background: rgb(0, 0, 0);"></div><div class="slimScrollRail" style="width: 3px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; opacity: 0.2; z-index: 90; right: 1px; background: rgb(51, 51, 51);"></div></div>
                </li>
                <!-- li class="footer">
                  <a href="<?= $sRootPath ?>/Tasks.php">View all tasks</a>
                </li -->
              </ul>
            </li>
      <?php  } ?>
            <li class="hidden-xxs">
              <a href="http://docs.churchcrm.io" target="_blank">
                <i class="fa fa-support"></i>
              </a>
            </li>
            <li class="hidden-xxs">
              <a href="#" data-toggle="modal" data-target="#IssueReportModal">
                <i class="fa fa-bug"></i>
              </a>
            </li>
            <li class="hidden-xxs">
              <a href="<?= $sRootPath ?>/Login.php?Logoff=True">
                <i class="fa fa-power-off"></i>
              </a>
            </li>
          </ul>
        </div>
      </nav>
    </header>
    <!-- =============================================== -->

    <!-- Left side column. contains the sidebar -->
    <aside class="main-sidebar">
      <!-- sidebar: style can be found in sidebar.less -->
      <section class="sidebar">
        <!-- search form -->
        <form action="#" method="get" class="sidebar-form">

          <select class="form-control multiSearch" style="width:100%">
          </select>

        </form>
        <!-- /.search form -->
        <!-- sidebar menu: : style can be found in sidebar.less -->
        <ul class="sidebar-menu">
          <li>
            <a href="<?= $sRootPath . "/" ?>Menu.php">
              <i class="fa fa-dashboard"></i> <span><?= gettext("Dashboard") ?></span>
            </a>
          </li>
  				<?php addMenu("root"); ?>
        </ul>
      </section>
    </aside>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <section class="content-header">
        <h1>
          <?php
          echo $sPageTitle . "\n";
          if ($sPageTitleSub != "") {
            echo "<small>" . $sPageTitleSub . "</small>";
          }
          ?>
        </h1>
        <ol class="breadcrumb">
          <li><a href="<?= $sRootPath . "/Menu.php" ?>"><i class="fa fa-dashboard"></i><?= gettext("Home") ?></a></li>
          <li class="active"><?= $sPageTitle ?></li>
        </ol>
      </section>
      <!-- Main content -->
      <section class="content">
  <?php if ($sGlobalMessage) { ?>
          <div class="main-box-body clearfix">
            <div class="callout callout-<?= $sGlobalMessageClass ?> fade in">
              <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
              <i class="fa fa-exclamation-triangle fa-fw fa-lg"></i>
    <?= $sGlobalMessage ?>
            </div>
          </div>
          <?php
        }
      }

      function create_side_nav($menu) {

        echo "<p>";
        addSection($menu);
        echo "</p>\n";
      }

      function addSection($menu) {
        global $cnInfoCentral;

        $security_matrix = " AND (security_grp = 'bALL'";
        if ($_SESSION['bAdmin']) {
          $security_matrix .= " OR security_grp = 'bAdmin'";
        }
        if ($_SESSION['bAddRecords']) {
          $security_matrix .= " OR security_grp = 'bAddRecords'";
        }
        if ($_SESSION['bMenuOptions']) {
          $security_matrix .= " OR security_grp = 'bMenuOptions'";
        }
        if ($_SESSION['bFinance']) {
          $security_matrix .= " OR security_grp = 'bFinance'";
        }
        if ($_SESSION['bManageGroups']) {
          $security_matrix .= " OR security_grp = 'bManageGroups'";
        }
        $security_matrix .= ")";
        $query = "SELECT name, ismenu, content, uri, statustext, session_var, session_var_in_text, session_var_in_uri, url_parm_name, security_grp FROM menuconfig_mcf WHERE parent = '$menu' AND active=1 " . $security_matrix . " ORDER BY sortorder";

        $rsMenu = mysql_query($query, $cnInfoCentral);
        $item_cnt = mysql_num_rows($rsMenu);
        $ptr = 1;
        while ($aRow = mysql_fetch_array($rsMenu)) {
          if (isset($aRow['admin_only']) & !$_SESSION['bAdmin']) {
            // hide admin menu
          }
          else {
            addEntry($aRow);
          }
          $ptr++;
        }
      }

      function addEntry($aMenu) {
        global $sRootPath;

        $link = ($aMenu['uri'] == "") ? "" : $sRootPath . "/" . $aMenu['uri'];
        $text = $aMenu['statustext'];
        $content = $aMenu['content'];
        if (!is_null($aMenu['session_var'])) {
          if (($link > "") && ($aMenu['session_var_in_uri']) && isset($_SESSION[$aMenu['session_var']])) {
            $link .= "?" . $aMenu['url_parm_name'] . "=" . $_SESSION[$aMenu['session_var']];
          }
          if (($text > "") && ($aMenu['session_var_in_text']) && isset($_SESSION[$aMenu['session_var']])) {
            $text .= " " . $_SESSION[$aMenu['session_var']];
          }
        }
        if (substr($content, 1, 10) == '----------') {
          $content = "--------------------";
        }
        if ($aMenu['ismenu']) {
          echo "</p>\n<p>\n";
        }
        if ($link > "") {
          echo "<a class=\"SmallText\" href=\"" . $link . "\">" . $content . "</a>";
        }
        else {
          echo $content;
        }
        echo "<br>\n";
        if ($aMenu['ismenu']) {
          addSection($aMenu['name']);
        }
      }
      ?>
