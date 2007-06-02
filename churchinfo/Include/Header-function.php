<?php
/*******************************************************************************
*
*  filename    : Include/Header-functions.php
*  website     : http://www.churchdb.org
*  description : page header used for most pages
*
*  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

function Header_head_metatag() {
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage;
global $sPageTitle, $sRootPath;
?>
	<meta http-equiv="pragma" content="no-cache">
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
    <?php if (strlen($sMetaRefresh)) echo $sMetaRefresh; ?>
	<title>ChurchInfo: <?php echo $sPageTitle; ?></title>
	<link rel="stylesheet" type="text/css" href="<?php echo $sRootPath."/"; ?>Include/<?php echo $_SESSION['sStyle']; ?>">
	<link rel="stylesheet" type="text/css" media="all" href="<?php echo $sRootPath."/"; ?>Include/jscalendar/calendar-blue.css" title="cal-style">
<?php
}

function Header_body_scripts() {
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage, $sRootPath;
?>
	<script language="javascript" type="text/javascript">
		function scrollToCoordinates() 
		{	// This function reads X and Y scroll coordinates from a cookie
			// If the cookie does not exist or if cookies are not supported
			// default values of zero are returned.
			// Next, the browser scroll bars are set to the X and Y values
			// Finally, the cookie is deleted
			var X_scroll_coordinate = 0;
			var Y_scroll_coordinate = 0;
			X_scroll_coordinate = getCookie('X_scroll_coordinate');
			Y_scroll_coordinate = getCookie('Y_scroll_coordinate');
			if(X_scroll_coordinate==null)
				{X_scroll_coordinate = "0";}
			if(Y_scroll_coordinate==null)
				{Y_scroll_coordinate = "0";}
            // Never scroll to 0,0 
            if(Y_scroll_coordinate != "0" || X_scroll_coordinate != "0")       
                {window.scrollTo(X_scroll_coordinate,Y_scroll_coordinate);}
			// Important! Delete the cookies or every page will load at these coordinates.
			delCookie('X_scroll_coordinate');
			delCookie('Y_scroll_coordinate');
		}
		function saveScrollCoordinates() 
		{	// This function reads the current X and Y coordinate values
			// and saves them to a cookie
			// Should work with most browsers 
			// (Only tested FireFox 1.0 and IE 6.0)
			var scrOfX = 0, scrOfY = 0;
			if( typeof( window.pageYOffset ) == 'number' ) 
			{	//Netscape compliant
				scrOfY = window.pageYOffset;
				scrOfX = window.pageXOffset;
			} else if( document.body && ( document.body.scrollLeft || 
											document.body.scrollTop ) ) 
			{	//DOM compliant
				scrOfY = document.body.scrollTop;
				scrOfX = document.body.scrollLeft;
			} else if( document.documentElement && ( document.documentElement.scrollLeft 
											|| document.documentElement.scrollTop ) ) 
			{	//IE6 standards compliant mode
				scrOfY = document.documentElement.scrollTop;
				scrOfX = document.documentElement.scrollLeft;
			}
			setCookie('X_scroll_coordinate', scrOfX, 1);
			setCookie('Y_scroll_coordinate', scrOfY, 1);
		}

		function getCookie(NameOfCookie)
		{	// First we check to see if there is a cookie stored.
			// Otherwise the length of document.cookie would be zero.

			if (document.cookie.length > 0)
			{	// Second we check to see if the cookie's name is stored in the
				// "document.cookie" object for the page.

				// Since more than one cookie can be set on a
				// single page it is possible that our cookie
				// is not present, even though the "document.cookie" object
				// is not just an empty text.
				// If our cookie name is not present the value -1 is stored
				// in the variable called "begin".

				begin = document.cookie.indexOf(NameOfCookie+"=");
				if (begin != -1) // Note: != means "is not equal to"
				{	// Our cookie was set.
					// The value stored in the cookie is returned from the function.

					begin += NameOfCookie.length+1;
					end = document.cookie.indexOf(";", begin);
					if (end == -1) end = document.cookie.length;
					return unescape(document.cookie.substring(begin, end)); 
				}
			}
			return null;

			// Our cookie was not set.
			// The value "null" is returned from the function.
		}
		function setCookie(NameOfCookie, value, expiredays)
		{	// Three variables are used to set the new cookie.
			// The name of the cookie, the value to be stored,
			// and finally the number of days until the cookie expires.
			// The first lines in the function convert
			// the number of days to a valid date.

			var ExpireDate = new Date ();
			ExpireDate.setTime(ExpireDate.getTime() + (expiredays * 24 * 3600 * 1000));

			// The next line stores the cookie, simply by assigning
			// the values to the "document.cookie" object.
			// Note the date is converted to Greenwich Mean time using
			// the "toGMTstring()" function.

			document.cookie = NameOfCookie + "=" + escape(value) +
			((expiredays == null) ? "" : "; expires=" + ExpireDate.toGMTString());
		}
		function delCookie (NameOfCookie)
		{
		// The function simply checks to see if the cookie is set.
		// If so, the expiration date is set to Jan. 1st 1970.

			if (getCookie(NameOfCookie)) {
				document.cookie = NameOfCookie + "=" +
				"; expires=Thu, 01-Jan-70 00:00:01 GMT";
			}
		}
	</script> 

	<script type="text/javascript" src="<?php echo $sRootPath."/"; ?>Include/jscalendar/calendar.js"></script>
	<script type="text/javascript" src="<?php echo $sRootPath."/"; ?>Include/jscalendar/lang/calendar-<?php echo substr($sLanguage,0,2); ?>.js"></script>

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

	<?php if ($bToolTipsOn) { ?>
	<script type="text/javascript" src="<?php echo $sRootPath."/"; ?>Include/domLib.js"></script>
	<script type="text/javascript" src="<?php echo $sRootPath."/"; ?>Include/domTT.js"></script>
	<script>
		var domTT_mouseHeight = domLib_isIE ? 17 : 20;
		var domTT_offsetX = domLib_isIE ? -2 : 0;
		var domTT_classPrefix = 'domTTClassic';
	</script>
	<?php } ?>

<?php
}

function create_menu($menu) {

	echo "domMenu_data.setItem('domMenu_BJ', new domMenu_Hash(";
	addMenu($menu);
	echo "));\n";
	menu_setting();
}
function addMenu($menu) {
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
	$idx = 1;
	$ptr = 1;
	while ($aRow = mysql_fetch_array($rsMenu)) {	
//		if ($aRow['admin_only'] & !$_SESSION['bAdmin']) {
		// hide admin menu
//		} else {
			addMenuItem($aRow, $idx);
			if ($ptr < $item_cnt) {
				echo ", ";
				$idx++;
			}
//		}
		$ptr++;
	}
}

function addMenuItem($aMenu,$mIdx) {
global $sRootPath;

	$link = ($aMenu['uri'] == "") ? "" : $sRootPath."/".$aMenu['uri'];
	$text = $aMenu['statustext'];
	if (!is_null($aMenu['session_var'])) {
		if (($link > "") & ($aMenu['session_var_in_uri'])) {
			$link .= "?".$aMenu['url_parm_name']."=".$_SESSION[$aMenu['session_var']];
		}
		if (($text > "") & ($aMenu['session_var_in_text'])) {
			$text .= " ".$_SESSION[$aMenu['session_var']];
		}
	}
	echo "$mIdx, new domMenu_Hash("
		 ."'contents', '".$aMenu['content']."', "
		 ."'uri', '".$link."', "
		 ."'statusText', '".$text."'";
	if ($aMenu['ismenu']) {
		echo ", ";
		addMenu($aMenu['name']);
	}
	echo ")\n";
}

function menu_setting() {

		// domMenu_BJ: settings
	echo "domMenu_settings.setItem('domMenu_BJ', new domMenu_Hash(
			'menuBarWidth', '0%',
			'menuBarClass', 'BJ_menuBar',
			'menuElementClass', 'BJ_menuElement',
			'menuElementHoverClass', 'BJ_menuElementHover',
			'menuElementActiveClass', 'BJ_menuElementActive',
			'subMenuBarClass', 'BJ_subMenuBar',
			'subMenuElementClass', 'BJ_subMenuElement',
			'subMenuElementHoverClass', 'BJ_subMenuElementHover',
			'subMenuElementActiveClass', 'BJ_subMenuElementHover',
			'subMenuMinWidth', 'auto',
			'distributeSpace', false,
			'openMouseoverMenuDelay', -1,
			'openMousedownMenuDelay', 0,
			'closeClickMenuDelay', 0,
			'closeMouseoutMenuDelay', -1
		));\n";
	// Top Menu Bar
	echo "document.onmouseup = function()
		{
			domMenu_deactivate('domMenu_BJ');
		}";
}

function Header_body_menu() {
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage;
global $MenuFirst, $sPageTitle, $sRootPath;

		$MenuFirst = 1;

		if ($bDefectiveBrowser)
			echo "<script language=\"javascript\" src=\"".$sRootPath."/Include/domMenu-IE.js\" type=\"text/javascript\"></script>";
		else
			echo "<script language=\"javascript\" src=\"".$sRootPath."/Include/domMenu.js\" type=\"text/javascript\"></script>";
		?>

		<script language="javascript" type="text/javascript">

		// ChurchInfo Menu Bar Items:
		<?php create_menu("root"); ?>

		</script>

	<?php
	if (!$bDefectiveBrowser)
		echo "<div style=\"position:fixed; top:0; left:0; width: 100%;\">";

	if ($sHeader) {
		// Optional Header Code (Entered on General Settings page - sHeader)
		// Must first set a table with a background color, or content scrolls across
		// the background of the custom code when using a non-defective browser
		echo "<table width=100% bgcolor=white cellpadding=0 cellspacing=0 border=0><tr><td width=100%>";
		echo html_entity_decode($sHeader,ENT_QUOTES);
		echo "</td></tr></table>";
	}
	
	if (strlen($_SESSION['iUserID'])) {
	?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td colspan="7" width="100%">
					<div class="p" id="domMenu_BJ" style="height:20px; margin-bottom:0px;"></div>

            <script language="javascript" type="text/javascript">

				domMenu_activate('domMenu_BJ');
			</script>

				</td>
			</tr>
			<tr>
				<td class="Search">&nbsp;</td>
				<td class="Search" width="50%">
					<form name="SelectFilter" method="get" action="SelectList.php">
						<input class="menuButton" style="font-size: 8pt; margin-top: 5px;" type="text" name="Filter" id="SearchText" <?php echo 'value="' . gettext("Search") . '"'; ?> onfocus="ClearFieldOnce(this);">
						<input name="mode" type="radio" value="person" <?php if (! $_SESSION['bSearchFamily']) echo "checked";?>><?php echo gettext("Person"); ?><input type="radio" name="mode" value="family" <?php if ($_SESSION['bSearchFamily']) echo "checked";?>><?php echo gettext("Family"); ?>
					</form>
				</td>
				<td class="Search" align="center">
					<?php if($_SESSION['bFinance']) echo gettext("Current deposit slip") .
				": " . $_SESSION['iCurrentDeposit']; ?>
				</td>
				<td class="Search" align="right">
					<?php echo gettext("Items in Cart") . ": " . count($_SESSION['aPeopleCart']); ?>
				</td>
				<td class="Search">&nbsp;&nbsp;&nbsp;</td>
				<td class="Search" align="right">
					<?php echo gettext("User:") . " " . $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName']; ?>
				</td>
				<td class="Search">&nbsp;</td>
			</tr>
		</table>

	<?php
	}
		if (!$bDefectiveBrowser)
        {
            echo "</div>";
            if ($sHeader) {
                // Optional Header Code (Entered on General Settings page - sHeader)
                // Must first set a table with a background color, or content scrolls across
                // the background of the custom code when using a non-defective browser
                echo "  <table width=100% bgcolor=white cellpadding=0 cellspacing=0 border=0>
                        <tr><td width=100%>";
                echo html_entity_decode($sHeader,ENT_QUOTES);
                echo "</td></tr></table>";
        	}
            echo "<BR><BR><BR>";
        }

	?>

	<table width="100%" border="0" cellpadding="5" cellspacing="0" align="left">
		<tr>
			<td valign="top" width="100%" align="center">
				<table width="95%" border="0">
					<tr>
						<td valign="top">
							<br>
							<p class="PageTitle"><?php echo $sPageTitle; ?></p>
							<p align="center" style="color: red; font-weight: bold;"><?php $sGlobalMessage; ?></p>
<?php
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
		if ($aRow['admin_only'] & !$_SESSION['bAdmin']) {
		// hide admin menu
		} else {
			addEntry($aRow);
		}
		$ptr++;
	}
}

function addEntry($aMenu) {
global $sRootPath;

	$link = ($aMenu['uri'] == "") ? "" : $sRootPath."/".$aMenu['uri'];
	$text = $aMenu['statustext'];
	$content = $aMenu['content'];
	if (!is_null($aMenu['session_var'])) {
		if (($link > "") & ($aMenu['session_var_in_uri'])) {
			$link .= "?".$aMenu['url_parm_name']."=".$_SESSION[$aMenu['session_var']];
		}
		if (($text > "") & ($aMenu['session_var_in_text'])) {
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
global $sLanguage, $bDefectiveBrowser, $bExportCSV, $sMetaRefresh, $bToolTipsOn, $iNavMethod, $bRegistered, $sHeader, $sGlobalMessage, $sRootPath;
?>

<table width="100%" border="0" cellpadding="5" cellspacing="0" align="center">
	<tr>
		<td class="LeftNavColumn" valign="top" width="200">
         <p>
			<form name="PersonFilter" method="get" action="<?php echo $sRootPath."/"; ?>SelectList.php">
				<b><?php echo gettext("People"); ?></b>
				<input type="hidden" value="person" name="mode">
				<input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="PersonSearch" value="Search" onFocus="ClearFieldOnce(this);">
			</form>
         </p>
         <p>
			<form name="FamilyFilter" method="get" action="<?php echo $sRootPath."/"; ?>SelectList.php">
				<b><?php echo gettext("Families"); ?></b>
				<input type="hidden" value="family" name="mode">
				<input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="FamilySearch" value="Search" onFocus="ClearFieldOnce(this);">
			</form>
         </p>
		
		<?php create_side_nav("root"); ?>
			<img src="<?php echo $sRootPath."/"; ?>Images/Spacer.gif" height="100" width="1" alt="<?php echo $sRootPath."/"; ?>Images/Spacer.gif">
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
