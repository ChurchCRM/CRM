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

require_once 'Functions.php';


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
          <h4 class="modal-title"><?= gettext("ERROR!") ?></h4>
        </div>
        <div class="modal-body">
          <p><?= gettext("Error making API Call to") ?>: <span id="APIEndpoint"></span></p>

          <p><?= gettext("Error text") ?>: <span id="APIErrorText"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-dismiss="modal"><?= gettext("Close") ?></button>
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
            <h4 class="modal-title"><?= gettext("Issue Report!") ?></h4>
          </div>
          <div class="modal-body">
            <div class="container-fluid">
              <div class="row">
                <div class="col-xl-3">
                  <label for="issueTitle"><?= gettext("Enter a Title for your bug / feature report") ?>: </label>
                </div>
                <div class="col-xl-3">
                  <input type="text" name="issueTitle" style="width:100%">
                </div>
              </div>
              <div class="row">
                <div class="col-xl-3">
                  <label for="issueDescription"><?= gettext("What were you doing when you noticed the bug / feature opportunity?") ?></label>
                </div>
                <div class="col-xl-3">
                  <textarea rows="10" cols="50" name="issueDescription" style="width:100%"></textarea>
                </div>
              </div>
            </div>
            <ul>
              <li><?= gettext("When you click \"submit,\" an error report will be posted to the ChurchCRM GitHub Issue tracker.") ?></li>
              <li><?= gettext("Please do not include any confidential information.") ?></li>
              <li><?= gettext("Some general information about your system will be submitted along with the request such as Server version and browser headers.") ?></li>
              <li><?= gettext("No personally identifiable information will be submitted unless you purposefully include it.") ?>"</li>
            </ul>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="submitIssue"><?= gettext("Submit") ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- End Issue Report Modal -->

  <?php
}

function Header_body_scripts() {
  global $sRootPath, $localeInfo;

  checkAllowedURL();
  ?>
  <script src="<?= $sRootPath ?>/skin/js/IssueReporter.js"></script>

  <script>
    window.CRM = {
      root: "<?= $sRootPath ?>",
      lang: "<?= $localeInfo->getLanguageCode() ?>",
      locale: "<?= $localeInfo->getLocale() ?>"
    };

    window.CRM.DisplayErrorMessage = function(endpoint, message) {
      $(".modal").modal('hide');
      $("#APIError").modal('show');
      $("#APIEndpoint").text(endpoint);
      $("#APIErrorText").text(message);
    };

    window.CRM.VerifyThenLoadAPIContent = function(url) {
      var error = '<?=gettext("There was a problem retrieving the requested object") ?>';
      $.ajax({
        type: 'HEAD',
        url: url,
        async: false,
        statusCode: {
          200: function() {
            window.open(url);
          },
          404: function() {
            window.CRM.DisplayErrorMessage(url, error);
          },
          500: function() {
            window.CRM.DisplayErrorMessage(url, error);
          }
        }
      });
    };

    $(document).ajaxError(function(evt, xhr, settings) {
      var CRMResponse = JSON.parse(xhr.responseText);
      window.CRM.DisplayErrorMessage("[" + settings.type + "] " + settings.url, " " + CRMResponse.message);
    });

    function LimitTextSize(theTextArea, size) {
      if(theTextArea.value.length > size) {
        theTextArea.value = theTextArea.value.substr(0, size);
      }
    }

    function popUp(URL) {
      var day = new Date();
      var id = day.getTime();
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

  $sSQL = "SELECT DISTINCT ucfg_name 
           FROM userconfig_ucfg 
           WHERE ucfg_per_id = 0 AND ucfg_cat = 'SECURITY' 
           ORDER by ucfg_id";

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

  $sSQL = "SELECT name, ismenu, parent, content, uri, statustext, session_var, session_var_in_text, 
                  session_var_in_uri, url_parm_name, security_grp, icon 
           FROM menuconfig_mcf 
           WHERE parent = '$menu' AND active=1 " . $security_matrix . " 
           ORDER BY sortorder";

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
    $sSQL = "SELECT name 
             FROM menuconfig_mcf 
             WHERE parent = '" . $aMenu['name'] . "' AND active=1 " . $security_matrix . " 
             ORDER BY sortorder";

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
        $query = "SELECT name, ismenu, content, uri, statustext, session_var, session_var_in_text,
                         session_var_in_uri, url_parm_name, security_grp
                  FROM menuconfig_mcf
                  WHERE parent = '$menu' AND active=1 " . $security_matrix . "
                  ORDER BY sortorder";

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
