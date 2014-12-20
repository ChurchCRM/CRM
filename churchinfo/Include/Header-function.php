<?php
/*******************************************************************************
*
*  filename    : Include/Header-functions.php
*  website     : http://www.churchdb.org
*  description : page header used for most pages
*
*  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
*
*
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchInfo is free software; you can redistribute it and/or modify
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link rel="stylesheet" type="text/css" href="<?php /*echo $sURLPath."/"; */?>css/Style.css">
    <!--<link rel="stylesheet" type="text/css" href="<?php /*echo $sURLPath."/"; */?>Include/<?php /*echo $_SESSION['sStyle']; */?>">
    <link rel="stylesheet" type="text/css" media="all" href="<?php echo $sURLPath."/"; ?>Include/jscalendar/calendar-blue.css" title="cal-style">
    -->

    <!-- jQuery -->
    <link rel="stylesheet" type="text/css" href="//code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css">


	<!-- libraries -->
    <link rel="stylesheet" type="text/css" href="<?php echo $sURLPath."/"; ?>css/libs/font-awesome.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $sURLPath."/"; ?>css/libs/nanoscroller.css" />

    <!-- global styles -->
    <link rel="stylesheet" type="text/css" href="<?php echo $sURLPath."/"; ?>css/compiled/theme_styles.css" />
    <!-- this page specific styles -->
    <link rel="stylesheet" type="text/css" href="<?php echo $sURLPath."/"; ?>css/libs/magnific-popup.css">

    <!-- google font libraries -->
    <link href='//fonts.googleapis.com/css?family=Open+Sans:400,600,700,300|Titillium+Web:200,300,400' rel='stylesheet' type='text/css'>

    <?php if (strlen($sMetaRefresh)) echo $sMetaRefresh; ?>
    <title>ChurchInfo: <?php echo $sPageTitle; ?></title>

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
    $url = 'http://www.gravatar.com/avatar/';
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
                echo "</li>";
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
    $separators = array("separator1", "separator2", "separator3", "separator4");
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
            echo "<li><a href='$link'>".$aMenu['content']."</a>";
        } else if (in_array($aMenu['name'] , $separators)){
            echo "<li class=\"divider\">\n";
        } else {
            echo "<li>\n";
            echo "    <a href=\"#\" class=\"dropdown-toggle\">\n";
            echo "<i class=\"fa ". $aMenu['icon'] ."\"></i>";
            echo "<span>".$aMenu['content']."</span> ";
            echo "<i class=\"fa fa-chevron-circle-right drop-icon\"></i>\n";
            if ($aMenu['name'] == "cart") {
                echo " (". count($_SESSION['aPeopleCart']).") ";
            } else if ($aMenu['name'] == "deposit") {
            echo " (". $_SESSION['iCurrentDeposit'].") ";
            }
            ?>  </a>
                <ul class="submenu">
            <?php
        }
        if (($aMenu['ismenu']) && ($numItems > 0)) {
            echo "\n";
            addMenu($aMenu['name']);
            echo "</ul>";
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
        ?>


    <?php
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

    if (strlen($_SESSION['iUserID'])) {
    ?>
    <header class="navbar" id="header-navbar">
        <div class="container">
            <a href="Menu.php" id="logo" class="navbar-brand">
                Church Info CRM
            </a>
            <div class="clearfix">
                <button class="navbar-toggle" data-target=".navbar-ex1-collapse" data-toggle="collapse" type="button">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="fa fa-bars"></span>
                </button>
                <div class="nav-no-collapse navbar-left pull-left hidden-sm hidden-xs">
                    <ul class="nav navbar-nav pull-left">
                        <li>
                            <a class="btn" id="make-small-nav">
                                <i class="fa fa-bars"></i>
                            </a>
                        </li>
                    </ul>
                </div>


                <div class="nav-no-collapse pull-right" id="header-nav">
                    <ul class="nav navbar-nav pull-right">
                        <li class="mobile-search">
                            <a class="btn">
                                <i class="fa fa-search"></i>
                            </a>

                            <div class="drowdown-search">
                                <form role="search">
                                    <div class="form-group">
                                        <input type="text" class="form-control" placeholder="Search..." onfocus="ClearFieldOnce(this);" id="SearchText" <?php echo 'value="' . gettext("Search") . '"'; ?>>
                                        <i class="fa fa-search nav-search-icon"></i>
                                    </div>
                                </form>
                            </div>
                        </li>
                        <?php if ($_SESSION['bAdmin']) { ?>
                        <li class="dropdown profile-dropdown">
                            <a class="btn" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-cog"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <?php addMenu("admin"); ?>
                            </ul>
                        </li>
                        <?php } ?>
                        <li class="dropdown profile-dropdown">
                            <a class="btn" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-question-circle"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <?php addMenu("help"); ?>
                            </ul>
                        </li>
                        <li class="dropdown profile-dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <img src="<?php echo get_gravatar($_SESSION['sEmailAddress']); ?>" class="img-circle" />
                                <span class="hidden-xs"><?php echo $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName']; ?> </span> <b class="caret"></b>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="PersonView.php?PersonID=<?php echo $_SESSION['iUserID'];?>"><i class="fa fa-user"></i>Profile</a></li>
                                <li class="divider"></li>
                                <li><a href="UserPasswordChange.php">Change My Password</a></li>
                                <li><a href="SettingsIndividual.php">Change My Settings</a></li>
                                <li class="divider"></li>
                                <li><a href="Default.php?Logoff=True"><i class="fa fa-power-off"></i>Log Off</a></li>
                            </ul>
                        </li>
                        <li class="hidden-xxs">
                            <a class="btn" href="Default.php?Logoff=True">
                                <i class="fa fa-power-off"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <div id="page-wrapper" class="container">
        <div class="row">
            <div id="nav-col">
                <section id="col-left" class="col-left-nano">
                    <div id="col-left-inner" class="col-left-nano-content">
                        <div id="user-left-box" class="clearfix hidden-sm hidden-xs">
                            <img src="<?php echo get_gravatar($_SESSION['sEmailAddress'],70); ?>" class="img-circle" />
                            <div class="user-box">
									<span class="name">Welcome<br/>
                                        <?php echo $_SESSION['UserFirstName']; ?>
                                    </span>
                            </div>
                        </div>
                        <div class="collapse navbar-collapse navbar-ex1-collapse" id="sidebar-nav">
                            <ul class="nav nav-pills nav-stacked">
                                <li>
                                    <a href="Menu.php">
                                        <i class="fa fa-dashboard"></i>
                                        <span>Dashboard</span>
                                    </a>
                                </li>
                                <?php addMenu("root"); ?>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>



    <?php
    }
        if (!$bDefectiveBrowser)
        {
            echo "</div>";
            if ($sHeader) {
                // Optional Header Code (Entered on General Settings page - sHeader)
                // Must first set a table with a background color, or content scrolls across
                // the background of the custom code when using a non-defective browser
                echo "  <table width='100%' bgcolor=white cellpadding=0 cellspacing=0 border=0>
                        <tr><td width='100%'>";
                echo html_entity_decode($sHeader,ENT_QUOTES);
                echo "</td></tr></table>";
            }
            echo "<BR><BR><BR>";
        }

    ?>
        <div id="content-wrapper">
            <div class="row">
                <div class="col-lg-12">
                    <h1><?php echo $sPageTitle; ?></h1>
                </div>
            </div>

            <?php if ($sGlobalMessage) { ?>
                <div class="main-box-body clearfix">
                    <div class="alert alert-success fade in">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="fa fa-check-circle fa-fw fa-lg"></i>
                        <?php $sGlobalMessage; ?>
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

function Header_body_nomenu() {
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, 
       $sHeader, $sGlobalMessage, $sURLPath, $sPageTitle;

	$sURLPath = $_SESSION['sURLPath'];
?>

<table width="100%" border="0" cellpadding="5" cellspacing="0" align="center">
    <tr>
        <td class="LeftNavColumn" valign="top" width="200">
         <p>
            <form name="PersonFilter" method="get" action="<?php echo $sURLPath."/"; ?>SelectList.php">
                <b><?php echo gettext("People"); ?></b>
                <input type="hidden" value="person" name="mode">
                <input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="PersonSearch" value="Search" onFocus="ClearFieldOnce(this);">
            </form>
         </p>
         <p>
            <form name="FamilyFilter" method="get" action="<?php echo $sURLPath."/"; ?>SelectList.php">
                <b><?php echo gettext("Families"); ?></b>
                <input type="hidden" value="family" name="mode">
                <input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="FamilySearch" value="Search" onFocus="ClearFieldOnce(this);">
            </form>
         </p>
        
        <?php create_side_nav("root"); ?>
            <img src="<?php echo $sURLPath."/"; ?>Images/Spacer.gif" height="100" width="1" alt="<?php echo $sURLPath."/"; ?>Images/Spacer.gif">
        </td>

        <td valign="top" width="100%" align="center">
            <table width="95%" border="0">
                <tr>
                    <td valign="top">

                        <br>
                        <p class="PageTitle"><?php echo $sPageTitle; ?></p>
<?php
}
?>
