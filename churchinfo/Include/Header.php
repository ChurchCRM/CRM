<?php
/*******************************************************************************
*
*  filename    : Include/Header.php
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

// Turn ON output buffering
ob_start();

// Top level menu index counter
$MenuFirst = 1;


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
	<meta http-equiv="pragma" content="no-cache">
	<title>ChurchInfo: <?php echo $sPageTitle; ?></title>
	<link rel="stylesheet" type="text/css" href="Include/<?php echo $_SESSION['sStyle']; ?>">

	<script language=javascript>
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

	<script type="text/javascript" src="Include/jscalendar/calendar.js"></script>
	<script type="text/javascript" src="Include/jscalendar/lang/calendar-<?php echo substr($sLanguage,0,2); ?>.js"></script>
	<link rel="stylesheet" type="text/css" media="all" href="Include/jscalendar/calendar-blue.css" title="cal-style" />

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
	<script type="text/javascript" src="Include/domLib.js"></script>
	<script type="text/javascript" src="Include/domTT.js"></script>
	<script>
		var domTT_mouseHeight = domLib_isIE ? 17 : 20;
		var domTT_offsetX = domLib_isIE ? -2 : 0;
		var domTT_classPrefix = 'domTTClassic';
	</script>
	<?php } ?>

<?php

	if ($iNavMethod != 2)
	{
		if ($bDefectiveBrowser)
			echo "<script language=\"javascript\" src=\"Include/domMenu-IE.js\" type=\"text/javascript\"></script>";
		else
			echo "<script language=\"javascript\" src=\"Include/domMenu.js\" type=\"text/javascript\"></script>";
		?>

		<script language="javascript" type="text/javascript">

		// ChurchInfo Menu Bar Items:

		domMenu_data.setItem('domMenu_BJ', new domMenu_Hash(

			<?php echo $MenuFirst++ ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Main") . "'"; ?>,
				'uri', '',
				'statusText', '',
				1, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Log Off") . "'"; ?>,
					'uri', 'Default.php?Logoff=True',
					'statusText', ''
				),
				2, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Change Password") . "'"; ?>,
					'uri', 'UserPasswordChange.php',
					'statusText', ''
				)
			),

			<?php if ($_SESSION['bAdmin']) {
			echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Admin") . "'"; ?>,
				'uri', '',
				'statusText', '',
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Users") . "'"; ?>,
					'uri', 'UserList.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Add New User") . "'"; ?>,
					'uri', 'UserEditor.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Custom Person Fields") . "'"; ?>,
					'uri', 'PersonCustomFieldsEditor.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Donation Funds") . "'"; ?>,
					'uri', 'DonationFundEditor.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Backup Database") . "'"; ?>,
					'uri', 'BackupDatabase.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("CSV Import") . "'"; ?>,
					'uri', 'CSVImport.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Access report") . "'"; ?>,
					'uri', 'AccessReport.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit General Settings") . "'"; ?>,
					'uri', 'SettingsGeneral.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Report Settings") . "'"; ?>,
					'uri', 'SettingsReport.php',
					'statusText', ''
				),
				<?php if ($bUseDonationEnvelopes) { ?>
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Envelope Manager") . "'"; ?>,
					'uri', 'ManageEnvelopes.php',
					'statusText', ''
				),
				<?php } ?>
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', 
					<?php 
						echo "'";
						if (! $bRegistered)
							echo gettext("Please select this option to register ChurchInfo after configuring.");
						else
							echo gettext("Update registration");
						echo "',"; 
					?>
					'uri', 'Register.php',
					'statusText', ''
				)
			),
			<?php } ?>

			<?php echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("People/Families") . "'"; ?>,
				'uri', '',
				'statusText', <?php echo "'" . gettext("People/Families") . "'"; ?>,

				<?php if ($_SESSION['bAddRecords']) {
				echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Add New Person") . "'"; ?>,
					'uri', 'PersonEditor.php',
					'statusText', ''
				),

				<?php } echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("View All Persons") . "'"; ?>,
					'uri', 'SelectList.php?mode=person',
					'statusText', ''
				),

				<?php if ($_SESSION['bMenuOptions']) {
				echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Classification Manager") . "'"; ?>,
					'uri', 'OptionManager.php?mode=classes',
					'statusText', ''
				),

				<?php } echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', '---------------------------',
					'uri', '',
					'statusText', ''
				),

				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit volunteer opportunities") . "'"; ?>,
					'uri', 'VolunteerOpportunityEditor.php',
					'statusText', ''
				),

				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', '---------------------------',
					'uri', '',
					'statusText', ''
				),

				<?php if ($_SESSION['bAddRecords']) {
				echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Add New Family") . "'"; ?>,
					'uri', 'FamilyEditor.php',
					'statusText', ''
				),

				<?php } echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("View All Families") . "'"; ?>,
					'uri', 'SelectList.php?mode=family',
					'statusText', ''
				),

				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Family Geographic Utilties") . "'"; ?>,
					'uri', 'GeoPage.php',
					'statusText', ''
				),

				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Family Map") . "'"; ?>,
					'uri', 'MapUsingGoogle.php',
					'statusText', ''
				)

				<?php if ($_SESSION['bMenuOptions']) {
				echo ',' . $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Family Roles Manager") . "'"; ?>,
					'uri', 'OptionManager.php?mode=famroles',
					'statusText', ''
				)
				<?php } ?>
			),

                        <?php echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
                                'contents', <?php echo "'" . gettext("Events") . "'"; ?>,
                                'uri', '',
                                'statusText', <?php echo "'" . gettext("Events") . "'"; ?>,

                                <?php echo $MenuSecond++; ?>, new domMenu_Hash(
                                        'contents', <?php echo "'" . gettext("List Church Events") . "'"; ?>,
                                        'uri', 'ListEvents.php',
                                        'statusText', 'Add Church Event'
                                ),

                                <?php echo $MenuSecond++; ?>, new domMenu_Hash(
                                        'contents', <?php echo "'" . gettext("Add Church Event") . "'"; ?>,
                                        'uri', 'AddEvent.php',
                                        'statusText', 'Add Church Event'
                                )
                                <?php if ($_SESSION['bAdmin']) {
                                echo ", ".$MenuSecond++; ?>, new domMenu_Hash(
                                        'contents', <?php echo "'" . gettext("Manage Event Names") . "'"; ?>,
                                        'uri', 'EventNames.php',
                                        'statusText', ''
                                )
                                <?php } ?>


                        ),
			
                        <?php if ($_SESSION['bFinance']) {
			echo $MenuFirst++; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Deposit") . "'"; ?>,
				'uri', '',
				'statusText', '',
				1, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Create New Deposit") . "'"; ?>,
					'uri', 'DepositSlipEditor.php?DepositType=Bank',
					'statusText', ''
				),
				2, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("View All Deposits") . "'"; ?>,
					'uri', 'FindDepositSlip.php',
					'statusText', ''
				),
				3, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Deposit Reports") . "'"; ?>,
					'uri', 'FinancialReports.php',
					'statusText', ''
				),
				4, new domMenu_Hash(
					'contents', '---------------------------',
					'uri', '',
					'statusText', ''
				),
				5, new domMenu_Hash(
 					'contents', <?php echo "'" . gettext("Edit Deposit Slip " . $_SESSION['iCurrentDeposit']) . "'"; ?>,
 					'uri', 'DepositSlipEditor.php?DepositSlipID=<?php echo $_SESSION['iCurrentDeposit'];?>',
 					'statusText', ''
 				)
			),
			<?php } ?>

			<?php echo $MenuFirst++; $MenuSecond = 3; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Cart") . "'"; ?>,
				'uri', '',
				'statusText', '',
				1, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("List Cart Items") . "'"; ?>,
					'uri', 'CartView.php',
					'statusText', ''
				),
				2, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Empty Cart") . "'"; ?>,
					'uri', 'CartView.php?Action=EmptyCart',
					'statusText', ''
				)
				<?php if ($_SESSION['bManageGroups']) { echo ',' . $MenuSecond++; ?>
				, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Empty Cart to Group") . "'"; ?>,
					'uri', 'CartToGroup.php',
					'statusText', ''
				)
				<?php } if ($_SESSION['bAddRecords']) { echo ',' . $MenuSecond++; ?>
				, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Empty Cart to Family") . "'"; ?>,
					'uri', 'CartToFamily.php',
					'statusText', ''
				)
				<?php } ?>
                                <?php echo ',' . $MenuSecond++; ?>
                                , new domMenu_Hash(
                                        'contents', <?php echo "'" . gettext("Empty Cart to Event") . "'"; ?>,
                                        'uri', 'CartToEvent.php',
                                        'statusText', 'Empty Cart contents to Event'
                                )
			),
			<?php echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Data/Reports") . "'"; ?>,
				'uri', '',
				'statusText', '',
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("CSV Export Records") . "'"; ?>,
					'uri', 'CSVExport.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Query Menu") . "'"; ?>,
					'uri', 'QueryList.php',
					'statusText', ''
				),
				<?php echo $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Reports Menu") . "'"; ?>,
					'uri', 'ReportList.php',
					'statusText', ''
				)
			),
			<?php echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Groups") . "'"; ?>,
				'uri', '',
				'statusText', '',
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("List Groups") . "'"; ?>,
					'uri', 'GroupList.php',
					'statusText', ''
				)
				<?php if ($_SESSION['bManageGroups']) { echo ','; ?>
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Add a New Group") . "'"; ?>,
					'uri', 'GroupEditor.php',
					'statusText', ''
				)
				<?php }
				if ($_SESSION['bMenuOptions']) { echo ','; ?>
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Group Types") . "'"; ?>,
					'uri', 'OptionManager.php?mode=grptypes',
					'statusText', ''
				)
				<?php } echo ',' . $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Group Assignment Helper") . "'"; ?>,
					'uri', 'SelectList.php?mode=groupassign',
					'statusText', ''
				)
			),
			<?php echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Properties") . "'"; ?>,
				'uri', '',
				'statusText', '',
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("People Properties") . "'"; ?>,
					'uri', 'PropertyList.php?Type=p',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Family Properties") . "'"; ?>,
					'uri', 'PropertyList.php?Type=f',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Group Properties") . "'"; ?>,
					'uri', 'PropertyList.php?Type=g',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Property Types") . "'"; ?>,
					'uri', 'PropertyTypeList.php',
					'statusText', ''
				)
			),
			<?php echo $MenuFirst++; $MenuSecond = 1; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Help") . "'"; ?>,
				'uri', '',
				'statusText', '',
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'- " . gettext("About ChurchInfo") . " -'"; ?>,
					'uri', 'Help.php?page=About',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("People") . "'"; ?>,
					'uri', 'Help.php?page=People',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Families") . "'"; ?>,
					'uri', 'Help.php?page=Family',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Geographic features") . "'"; ?>,
					'uri', 'Help.php?page=Geographic',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Groups") . "'"; ?>,
					'uri', 'Help.php?page=Groups',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Finances") . "'"; ?>,
					'uri', 'Help.php?page=Finances',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Reports") . "'"; ?>,
					'uri', 'Help.php?page=Reports',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Administration") . "'"; ?>,
					'uri', 'Help.php?page=Admin',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Cart") . "'"; ?>,
					'uri', 'Help.php?page=Cart',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Properties") . "'"; ?>,
					'uri', 'Help.php?page=Properties',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Notes") . "'"; ?>,
					'uri', 'Help.php?page=Notes',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Custom Fields") . "'"; ?>,
					'uri', 'Help.php?page=Custom',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Classifications") . "'"; ?>,
					'uri', 'Help.php?page=Class',
					'statusText', ''
				),
				<?php echo $MenuSecond++;?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Canvass Support") . "'"; ?>,
					'uri', 'Help.php?page=Canvass',
					'statusText', ''
				),
                                <?php echo $MenuSecond++;?>, new domMenu_Hash(
                                        'contents', <?php echo "'" . gettext("Events") . "'"; ?>,
                                        'uri', 'Help.php?page=Events',
                                        'statusText', ''
                                )
			)
		));

		// domMenu_BJ: settings

		domMenu_settings.setItem('domMenu_BJ', new domMenu_Hash(
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
		));
		</script>

		<script>
		// Top Menu Bar
		document.onmouseup = function()
		{
			domMenu_deactivate('domMenu_BJ');
		}
		</script>
	</head>
	<body onload="javascript:scrollToCoordinates()"> 

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

			<script language="javascript">
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
		if (!$bDefectiveBrowser) echo "</div><BR><BR><BR>";
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
else
{
?>

</head>

<body topmargin="0" bottommargin="0" rightmargin="0" leftmargin="0">

<table height="100%" width="100%" border="0" cellpadding="5" cellspacing="0" align="center">
	<tr>
		<td class="LeftNavColumn" valign="top" width="200">

			<p>
				<b><?php echo gettext("Current User"); ?></b>
				<br>
				<span class="SmallText"><?php echo $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName']; ?></span>
				<br>
				<a class="SmallText" href="Default.php?Logoff=True"><?php echo gettext("Log Off"); ?></a></span>
				<br>
				<a class="SmallText" href="UserPasswordChange.php?PersonID=<?php echo $_SESSION['iUserID']; ?>"><?php echo gettext("Change Password"); ?></a>
			</p>

			<?php if ($_SESSION['bAdmin']) { ?>
			<p>
				<b><?php echo gettext("Admin"); ?></b>
				<br>
				<a class="SmallText" href="UserList.php"><?php echo gettext("Edit Users"); ?></a>
				<br>
				<a class="SmallText" href="UserEditor.php"><?php echo gettext("Add New User"); ?></a>
				<br>
				<a class="SmallText" href="PersonCustomFieldsEditor.php"><?php echo gettext("Edit Custom Person Fields"); ?></a>
				<br>
				<a class="SmallText" href="DonationFundEditor.php"><?php echo gettext("Edit Donation Funds"); ?></a>
				<br>
				<a class="SmallText" href="BackupDatabase.php"><?php echo gettext("Backup Database"); ?></a>
				<br>
				<a class="SmallText" href="CSVImport.php"><?php echo gettext("CSV Import"); ?></a>
				<br>
				<a class="SmallText" href="AccessReport.php"><?php echo gettext("Access Report"); ?></a>
				<br>
				<a class="SmallText" href="SettingsGeneral.php"><?php echo gettext("Edit General Settings"); ?></a>
				<br>
				<a class="SmallText" href="SettingsReport.php"><?php echo gettext("Exit Report Settings"); ?></a>
				<br>
				<a class="SmallText" href="Register.php"><?php
						if (! $bRegistered)
							echo gettext("Please select this option to register ChurchInfo after configuring.");
						else
							echo gettext("Update registration");
						echo "',"; 
				?></a>
			</p>
			<?php } ?>

			<p>
				<form name="PersonFilter" method="get" action="SelectList.php">
				<b><?php echo gettext("People"); ?></b>

				<?php if ($_SESSION['bAddRecords']) { ?>
					<br><a href="PersonEditor.php" class="SmallText"><?php echo gettext("Add New Person"); ?></a>
				<?php } ?>

				<br><a href="SelectList.php?mode=person" class="SmallText"><?php echo gettext("View All Persons"); ?></a>
				<input type="hidden" value="person" name="mode">
				<input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="PersonSearch" value="Search" onFocus="ClearFieldOnce(this);">

				<?php if ($_SESSION['bMenuOptions']) { ?>
					<br><a href="OptionManager.php?mode=classes" class="SmallText"><?php echo gettext("Classification Manager"); ?></a>
				<?php } ?>

				</form>
			</p>

			<p>
				<a class="SmallText" href="VolunteerOpportunityEditor.php"><?php echo gettext("Edit volunteer opportunities"); ?></a><br>
			</p>

			<p>
				<form name="FamilyFilter" method="get" action="SelectList.php">
				<b><?php echo gettext("Families"); ?></b>

				<?php if ($_SESSION['bAddRecords']) { ?>
					<br><a href="FamilyEditor.php" class="SmallText"><?php echo gettext("Add New Family"); ?></a>
				<?php } ?>

				<br><a href="SelectList.php?mode=family" class="SmallText"><?php echo gettext("View All Families"); ?></a>
				<input type="hidden" value="family" name="mode">
				<input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="FamilySearch" value="Search" onFocus="ClearFieldOnce(this);">

				<a class="SmallText" href="GeoPage.php"><?php echo gettext("Family Geographic Utilties"); ?></a>
				<br>
				<a class="SmallText" href="MapUsingGoogle.php"><?php echo gettext("Family Map"); ?></a>
				<br>

				<?php if ($_SESSION['bMenuOptions']) { ?>
					<br><a href="OptionManager.php?mode=famroles" class="SmallText"><?php echo gettext("Family Roles Manager"); ?></a>
				<?php } ?>

				</form>
			</p>

			<p>
				<b><?php echo gettext("Events"); ?></b>
				<br>
				<a class="SmallText" href="ListEvents.php"><?php echo gettext("List Church Events"); ?></a>
				<br>
				<a class="SmallText" href="AddEvent.php"><?php echo gettext("Add Church Event"); ?></a>
				<br>
				<a class="SmallText" href="EventNames.php"><?php echo gettext("Manage Event Names"); ?></a>
			</p>

			<?php if ($_SESSION['bFinance']) { ?>
			<p>
				<b><?php echo gettext("Deposit"); ?></b>
				<br>
			<a class="SmallText" href="DepositSlipEditor.php?DepositType=Bank"><?php echo gettext("Create New Deposit"); ?></a>
				<br>
				<a class="SmallText" href="FindDepositSlip.php"><?php echo gettext("View All Deposits"); ?></a><br>
				<a class="SmallText" href="FinancialReports.php"><?php echo gettext("Deposit Reports"); ?></a><br>
				<br>
				<a class="SmallText" href="DepositSlipEditor.php?DepositSlipID=<?php echo $_SESSION['iCurrentDeposit'];?>"><?php echo gettext("Edit Deposit Slip") . " " . $_SESSION['iCurrentDeposit']; ?></a>
			</p>
			<?php } ?>


			<p>
				<b><?php echo gettext("Cart"); ?></b>
				<br>
				<span class="SmallText">
					<?php echo gettext("Items in Cart") . ": " . count($_SESSION['aPeopleCart']); ?></span>
				<br>
				<a href="CartView.php" class="SmallText"><?php echo gettext("List Cart Items"); ?></a>
				<br>
				<a href="CartView.php?Action=EmptyCart" class="SmallText"><?php echo gettext("Empty Cart"); ?></a>

				<?php if ($_SESSION["bManageGroups"]) { ?>
					<br>
					<a class="SmallText" href="CartToGroup.php"><?php echo gettext("Empty Cart to Group"); ?></a>
				<?php } ?>
				<?php if ($_SESSION["bAddRecords"]) { ?>
					<br>
					<a class="SmallText" href="CartToFamily.php"><?php echo gettext("Empty Cart to Family"); ?></a>
				<?php } ?>
					<br>
				<a class="SmallText" href="CartToEvent.php"><?php echo gettext("Empty Cart to Event"); ?></a>
			</p>

			<p>
				<b><?php echo gettext("Data/Reports"); ?></b>
				<br>
				<a href="CSVExport.php" class="SmallText"><?php echo gettext("CSV Export Records"); ?></a>
				<br>
				<a href="QueryList.php" class="SmallText"><?php echo gettext("Query Menu"); ?></a>
				<br>
				<a href="ReportList.php" class="SmallText"><?php echo gettext("Report Menu"); ?></a>
			</p>

			<p>
				<b><?php echo gettext("Groups"); ?></b>
				<br>
				<a href="GroupList.php" class="SmallText"><?php echo gettext("List Groups"); ?></a>

				<?php if ($_SESSION['bManageGroups']) { ?>
					<br>
					<a href="GroupEditor.php" class="SmallText"><?php echo gettext("Add a New Group"); ?></a>
					<br>s
					<a href="OptionManager.php?mode=grptypes" class="SmallText"><?php echo gettext("Edit Group Types"); ?></a>
					<br>
					<a href="SelectList.php?mode=groupassign" class="SmallText"><?php echo gettext("Group Assignment Helper"); ?></a>
				<?php } ?>

			</p>

			<p>
				<b><?php echo gettext("Properties"); ?></b>
				<br>
				<a class="SmallText" href="PropertyList.php?Type=p"><?php echo gettext("People Properties"); ?></a>
				<br>
				<a class="SmallText" href="PropertyList.php?Type=f"><?php echo gettext("Family Properties"); ?></a>
				<br>
				<a class="SmallText" href="PropertyList.php?Type=g"><?php echo gettext("Group Properties"); ?></a>
				<br>
				<a class="SmallText" href="PropertyTypeList.php"><?php echo gettext("Property Types"); ?></a>
			</p>

			<p>
				<b><?php echo gettext("Help"); ?></b>
				<br>
				<a class="SmallText" href="Help.php?page=About"><?php echo gettext("About ChurchInfo"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=People"><?php echo gettext("People"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Family"><?php echo gettext("Families"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Geographic"><?php echo gettext("Geographic features"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Groups"><?php echo gettext("Groups"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Finances"><?php echo gettext("Finances"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Reports"><?php echo gettext("Reports"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Admin"><?php echo gettext("Administration"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Cart"><?php echo gettext("Cart"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Properties"><?php echo gettext("Properties"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Notes"><?php echo gettext("Notes"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Custom"><?php echo gettext("Custom Fields"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Class"><?php echo gettext("Classifications"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Canvass"><?php echo gettext("Canvass Support"); ?></a>
				<br>
				<a class="SmallText" href="Help.php?page=Events"><?php echo gettext("Events"); ?></a>
			</p>

			<img src="Images/Spacer.gif" height="100" width="1">
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
