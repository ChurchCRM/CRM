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

function Header_head_metatag() {
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage;
global $sPageTitle, $sURLPath;

$sURLPath = $_SESSION['sURLPath'];
?>
    <?php if (strlen($sMetaRefresh)) echo $sMetaRefresh; ?>
    <title>ChurchCRM: <?php echo $sPageTitle; ?></title>
<?php
}

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @param boole $img True to return a complete IMG tag False for just the URL
 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @source http://gravatar.com/site/implement/images/php/
 */
function get_gravatar( $email, $s = 18, $d = 'mm', $r = 'g', $img = false, $atts = array() ) {
    $url = '//www.gravatar.com/avatar/';
    $url .= md5( strtolower( trim( $email ) ) );
    $url .= "?s=$s&d=$d&r=$r";
    if ( $img ) {
        $url = '<img src="' . $url . '"';
        foreach ( $atts as $key => $val )
            $url .= ' ' . $key . '="' . $val . '"';
        $url .= ' />';
    }
    return $url;
}

function Header_body_scripts() {
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage, 
$bLockURL, $URL, $sURLPath;

$sURLPath = $_SESSION['sURLPath'];
//
// Basic sercurity checks:
//
// Check if https is required:
// Verify that page has an authorized URL in the browser address bar.
// Otherwise redirect to login page.
// An array of authorized URL's is specified in Config.php ... $URL
    if (isset($bLockURL) && ($bLockURL === TRUE)) {
        echo '
    <script language="javascript" type="text/javascript">
        v_test="FAIL"'; // Set "FAIL" to assume the URL is not allowed
                        // Set "PASS" if we learn it is allowed
        foreach ($URL as $value) { // Default.php is 11 characters
            $value = substr($value, 0, -11);
            echo '
        if(window.location.href.indexOf("'.$value.'") == 0) v_test="PASS";';
        }
        echo '
        if (v_test == "FAIL") window.location="'.$URL[0].'";
    </script>';
    }
// End of basic security checks
 ?>

    <script type="text/javascript" src="<?php echo $sURLPath."/"; ?>Include/jscalendar/calendar.js"></script>
    <script type="text/javascript" src="<?php echo $sURLPath."/"; ?>Include/jscalendar/lang/calendar-<?php echo substr($sLanguage,0,2); ?>.js"></script>

    <script language="javascript" type="text/javascript">

        // Popup Calendar stuff
        function selected(cal, date)
        {
            cal.sel.value = date; // update the date in the input field.
            if (cal.dateClicked)
                cal.callCloseHandler();
        }

        function closeHandler(cal)
        {
            cal.hide(); // hide the calendar
        }

        function showCalendar(id, format)
        {
            var el = document.getElementById(id);
            if (calendar != null)
            {
                calendar.hide();
            }
            else
            {
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

        function isDisabled(date)
        {
            var today = new Date();
            return (Math.abs(date.getTime() - today.getTime()) / DAY) > 10;
        }

        // Clear a field on the first focus
        var priorSelect = new Array();
        function ClearFieldOnce(sField) {
            if (priorSelect[sField.id]) {
                sField.select();
            } else {
                sField.value = "";
                priorSelect[sField.id] = true;
            }
        }

        function LimitTextSize(theTextArea,size) {
            if (theTextArea.value.length > size) {
                theTextArea.value = theTextArea.value.substr(0,size);
            }
        }

        function popUp(URL)
        {
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
            
    while ($aRow = mysql_fetch_array($rsSecGrpList))
    {
        $aSecurityList[] = $aRow['ucfg_name'];
    }

    asort($aSecurityList);

    $sSecurityCond = " AND (security_grp = 'bALL'";
    for ($i = 0; $i < count($aSecurityList); $i++) {
    	if (array_key_exists ($aSecurityList[$i], $_SESSION) && $_SESSION[$aSecurityList[$i]]) {
            $sSecurityCond .= " OR security_grp = '" . $aSecurityList[$i] . "'";
        }
    }
    $sSecurityCond .= ")";
    return $sSecurityCond;
}

function addMenu($menu) {
    global $security_matrix;
    
    $sSQL = "SELECT name, ismenu, parent, content, uri, statustext, session_var, session_var_in_text, session_var_in_uri, url_parm_name, security_grp, icon FROM menuconfig_mcf WHERE parent = '$menu' AND active=1 ".$security_matrix." ORDER BY sortorder";
    
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
        } else {
            $item_cnt--;
        }
    }
}

function addMenuItem($aMenu,$mIdx) {
global $security_matrix, $sURLPath;
	$sURLPath = $_SESSION['sURLPath'];

    $link = ($aMenu['uri'] == "") ? "" : $sURLPath."/".$aMenu['uri'];
    $text = $aMenu['statustext'];
    if (!is_null($aMenu['session_var'])) {
        if (($link > "") && ($aMenu['session_var_in_uri']) && isset($_SESSION[$aMenu['session_var']])) {
            if (strstr($link, "?")&&true) {
                $cConnector = "&";
            } else {
                $cConnector = "?"; 
            }
            $link .= $cConnector.$aMenu['url_parm_name']."=".$_SESSION[$aMenu['session_var']];
        }
        if (($text > "") && ($aMenu['session_var_in_text']) && isset($_SESSION[$aMenu['session_var']])) {
            $text .= " ".$_SESSION[$aMenu['session_var']];
        }
    }
    if ($aMenu['ismenu']) {
        $sSQL = "SELECT name FROM menuconfig_mcf WHERE parent = '" . $aMenu['name'] . "' AND active=1 " . $security_matrix." ORDER BY sortorder";
        $rsItemCnt = RunQuery($sSQL);
        $numItems = mysql_num_rows($rsItemCnt);
    }
    if (!($aMenu['ismenu']) || ($numItems > 0))
    {
        if($link){
            echo "<li><a href='$link'>";
            if ($aMenu['icon'] != "") {
                echo "<i class=\"fa ". $aMenu['icon'] ."\"></i>";
            }
            if ($aMenu['parent'] != "root") {
                echo "<i class=\"fa fa-angle-double-right\"></i> ";
            }
            echo $aMenu['content']."</a>";
        } else {
            echo "<li class=\"treeview\">\n";
            echo "    <a href=\"#\">\n";
            if ($aMenu['icon'] != "") {
                echo "<i class=\"fa ". $aMenu['icon'] ."\"></i>\n";
            }
            echo "<span>".$aMenu['content']."</span>\n";
            echo "<i class=\"fa fa-angle-left pull-right\"></i>\n";
            if ($aMenu['name'] == "cart") {
                echo "<small class=\"badge pull-right bg-green\">". count($_SESSION['aPeopleCart'])."</small>\n";
            } else if ($aMenu['name'] == "deposit") {
                echo "<small class=\"badge pull-right bg-green\">". $_SESSION['iCurrentDeposit']."</small>\n";
            }
            ?>  </a>
                <ul class="treeview-menu">
            <?php
                if ($aMenu['name'] == "sundayschool") {
                    $sSQL = "select * from group_grp where grp_Type = 4 order by grp_name";
                    $rsSundaySchoolClasses = RunQuery($sSQL);
                    while ($aRow = mysql_fetch_array($rsSundaySchoolClasses)) {
                        echo "<li><a href='".$sURLPath."/SundaySchoolClassView.php?groupId=" . $aRow[grp_ID] . "'><i class='fa fa-angle-double-right'></i> " . $aRow[grp_Name] . "</a></li>";
                    }
                }
        }
        if (($aMenu['ismenu']) && ($numItems > 0)) {
            echo "\n";
            addMenu($aMenu['name']);
            echo "</ul>\n</li>\n";
        } else {
			echo "</li>\n";
		}

        return true;
    } else {
        return false;
    }
}

function Header_body_menu() {
    global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage;
    global $MenuFirst, $sPageTitle, $sPageTitleSub, $sURLPath;

	$sURLPath = $_SESSION['sURLPath'];

    $MenuFirst = 1;

    if (!$bDefectiveBrowser)
        echo "<div style=\"position:fixed; top:0; left:0; width: 100%;\">";

    if ($sHeader) {
        // Optional Header Code (Entered on General Settings page - sHeader)
        // Must first set a table with a background color, or content scrolls across
        // the background of the custom code when using a non-defective browser
        echo "<table width=\"100%\" bgcolor=white cellpadding=0 cellspacing=0 border=0><tr><td width=\"100%\">";
        echo html_entity_decode($sHeader,ENT_QUOTES);
        echo "</td></tr></table>";
    }
?>

    <header class="main-header">
        <!-- Logo -->
        <a href="<?= $sURLPath?>/Menu.php" class="logo">
            <!-- mini logo for sidebar mini 50x50 pixels -->
            <span class="logo-mini"><b>C</b>RM</span>
            <!-- logo for regular state and mobile devices -->
            <span class="logo-lg"><b>Church</b>CRM</span>
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
                    <!-- User Account: style can be found in dropdown.less -->
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <img src="<?php echo get_gravatar($_SESSION['sEmailAddress']); ?>" class="user-image" alt="User Image">
                            <span class="hidden-xs"><?php echo $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName']; ?> </span>

                        </a>
                        <ul class="dropdown-menu">
                            <!-- User image -->
                            <li class="user-header">
                                <img src="<?php echo get_gravatar($_SESSION['sEmailAddress']); ?>" class="img-circle" alt="User Image">

                                <p>
                                    <?php echo $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName']; ?>
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
                                    <a href="<?php echo $sURLPath."/"; ?>UserPasswordChange.php" class="btn btn-default btn-flat">Change Password</a>
                                </div>
                                <div class="pull-right">
                                    <a href="<?php echo $sURLPath."/"; ?>SettingsIndividual.php" class="btn btn-default btn-flat">My Settings</a>
                                </div>
                            </li>
                        </ul>
                    </li>
                    <?php if ($_SESSION['bAdmin']) { ?>
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
                    <?php } ?>
                    <li class="hidden-xxs">
                        <a href="http://docs.churchcrm.io" target="_blank">
                            <i class="fa fa-support"></i>
                        </a>
                    </li>
                    <li class="hidden-xxs">
                        <a href="<?php echo $sURLPath."/"; ?>Default.php?Logoff=True">
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
                    <!-- Sidebar user panel -->
                    <div class="user-panel">
                        <div class="pull-left image">
                            <img src="<?php echo get_gravatar($_SESSION['sEmailAddress'],70); ?>" class="img-circle" />
                        </div>
                        <div class="pull-left info">
                            <p>Welcome, <?php echo $_SESSION['UserFirstName']; ?></p>
                        </div>
                    </div>
                    <!-- search form -->
                    <form action="#" method="get" class="sidebar-form">
                        
                            <select class="form-control multiSearch" style="width:100%">
                            </select>
                        
                    </form>
                    <!-- /.search form -->
                    <!-- sidebar menu: : style can be found in sidebar.less -->
                    <ul class="sidebar-menu">
                        <li>
                            <a href="<?php echo $sURLPath."/"; ?>Menu.php">
                                <i class="fa fa-dashboard"></i> <span>Dashboard</span>
                            </a>
                        </li>
                        <?php addMenu("root"); ?>
                    </ul>
                </section>
            </aside>
            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">
                <section class="content-header">
                    <h1>
                        <?php
                        echo $sPageTitle."\n";
                        if ($sPageTitleSub != "") {
                            echo "<small>".$sPageTitleSub."</small>";
                        }?>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo $sURLPath."/Menu.php"; ?>"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li class="active"><?php echo $sPageTitle; ?></li>
                    </ol>
                </section>
                <!-- Main content -->
                <section class="content">
                    <?php if ($sGlobalMessage) { ?>
                    <div class="main-box-body clearfix">
                        <div class="alert alert-info fade in">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <i class="fa fa-exclamation-triangle fa-fw fa-lg"></i>
                            <?php echo $sGlobalMessage; ?>
                        </div>
                    </div>
                    <?php }
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
    $query = "SELECT name, ismenu, content, uri, statustext, session_var, session_var_in_text, session_var_in_uri, url_parm_name, security_grp FROM menuconfig_mcf WHERE parent = '$menu' AND active=1 ".$security_matrix." ORDER BY sortorder";
    
    $rsMenu = mysql_query($query, $cnInfoCentral);
    $item_cnt = mysql_num_rows($rsMenu);
    $ptr = 1;
    while ($aRow = mysql_fetch_array($rsMenu)) {    
        if (isset($aRow['admin_only']) & !$_SESSION['bAdmin']) {
        // hide admin menu
        } else {
            addEntry($aRow);
        }
        $ptr++;
    }
}

function addEntry($aMenu) {

$sURLPath = $_SESSION['sURLPath'];

    $link = ($aMenu['uri'] == "") ? "" : $sURLPath."/".$aMenu['uri'];
    $text = $aMenu['statustext'];
    $content = $aMenu['content'];
    if (!is_null($aMenu['session_var'])) {
        if (($link > "") && ($aMenu['session_var_in_uri']) && isset($_SESSION[$aMenu['session_var']])) {
            $link .= "?".$aMenu['url_parm_name']."=".$_SESSION[$aMenu['session_var']];
        }
        if (($text > "") && ($aMenu['session_var_in_text']) && isset($_SESSION[$aMenu['session_var']])) {
            $text .= " ".$_SESSION[$aMenu['session_var']];
        }
    }
    if (substr($content,1,10) == '----------') {
        $content = "--------------------";
    }
    if ($aMenu['ismenu']) {
        echo "</p>\n<p>\n";
    }
    if ($link >"") {
        echo "<a class=\"SmallText\" href=\"".$link."\">".$content."</a>";
    } else {
        echo $content;
    }
    echo "<br>\n";
    if ($aMenu['ismenu']) {
        addSection($aMenu['name']);
    }
}
?>
