<?php
/*******************************************************************************
 *
 *  filename    : Include/Header.php
 *  last change : 2003-07-08
 *  description : page header used for most pages
 *
 *  http://www.churchdb.org/
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
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
			echo $MenuFirst++; ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Admin") . "'"; ?>,
				'uri', '',
				'statusText', '',
				1, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Users") . "'"; ?>,
					'uri', 'UserList.php',
					'statusText', ''
				),
				2, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Add New User") . "'"; ?>,
					'uri', 'UserEditor.php',
					'statusText', ''
				),
				3, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Custom Person Fields") . "'"; ?>,
					'uri', 'PersonCustomFieldsEditor.php',
					'statusText', ''
				),
				4, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Donation Funds") . "'"; ?>,
					'uri', 'DonationFundEditor.php',
					'statusText', ''
				),
				5, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Backup Database") . "'"; ?>,
					'uri', 'BackupDatabase.php',
					'statusText', ''
				),
				6, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("CSV Import") . "'"; ?>,
					'uri', 'CSVImport.php',
					'statusText', ''
				),
				7, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Access report") . "'"; ?>,
					'uri', 'AccessReport.php',
					'statusText', ''
				),
				8, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit General Settings") . "'"; ?>,
					'uri', 'SettingsGeneral.php',
					'statusText', ''
				),
				9, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Edit Report Settings") . "'"; ?>,
					'uri', 'SettingsReport.php',
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
				)

				<?php if ($_SESSION['bMenuOptions']) {
				echo ',' . $MenuSecond++; ?>, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Family Roles Manager") . "'"; ?>,
					'uri', 'OptionManager.php?mode=famroles',
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
			<?php echo $MenuFirst++ ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Properties") . "'"; ?>,
				'uri', '',
				'statusText', '',
				1, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("People Properties") . "'"; ?>,
					'uri', 'PropertyList.php?Type=p',
					'statusText', ''
				),
				2, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Family Properties") . "'"; ?>,
					'uri', 'PropertyList.php?Type=f',
					'statusText', ''
				),
				3, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Group Properties") . "'"; ?>,
					'uri', 'PropertyList.php?Type=g',
					'statusText', ''
				),
				4, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Property Types") . "'"; ?>,
					'uri', 'PropertyTypeList.php',
					'statusText', ''
				)
			),
			<?php echo $MenuFirst++ ?>, new domMenu_Hash(
				'contents', <?php echo "'" . gettext("Help") . "'"; ?>,
				'uri', '',
				'statusText', '',
				1, new domMenu_Hash(
					'contents', <?php echo "'- " . gettext("About ChurchInfo") . " -'"; ?>,
					'uri', 'Help.php?page=About',
					'statusText', ''
				),
				2, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("People") . "'"; ?>,
					'uri', 'Help.php?page=People',
					'statusText', ''
				),
				3, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Families") . "'"; ?>,
					'uri', 'Help.php?page=Family',
					'statusText', ''
				),
				4, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Groups") . "'"; ?>,
					'uri', 'Help.php?page=Groups',
					'statusText', ''
				),
				5, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Finances") . "'"; ?>,
					'uri', 'Help.php?page=Finances',
					'statusText', ''
				),
				6, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Reports") . "'"; ?>,
					'uri', 'Help.php?page=Reports',
					'statusText', ''
				),
				7, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Administration") . "'"; ?>,
					'uri', 'Help.php?page=Admin',
					'statusText', ''
				),
				8, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Cart") . "'"; ?>,
					'uri', 'Help.php?page=Cart',
					'statusText', ''
				),
				9, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Properties") . "'"; ?>,
					'uri', 'Help.php?page=Properties',
					'statusText', ''
				),
				10, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Notes") . "'"; ?>,
					'uri', 'Help.php?page=Notes',
					'statusText', ''
				),
				11, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Custom Fields") . "'"; ?>,
					'uri', 'Help.php?page=Custom',
					'statusText', ''
				),
				12, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Classifications") . "'"; ?>,
					'uri', 'Help.php?page=Class',
					'statusText', ''
				),
				13, new domMenu_Hash(
					'contents', <?php echo "'" . gettext("Canvass Support") . "'"; ?>,
					'uri', 'Help.php?page=Canvass',
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
	<body>

	<?php
	if (!$bDefectiveBrowser)
		echo "<div style=\"position:fixed; top:0; left:0; width: 100%;\">";

	if (strlen($_SESSION['iUserID'])) {
	?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td colspan="6">
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
					<?php if($_SESSION['bFinance'])echo gettext("Current deposit slip: "); ?><span id="CartCounter"><?php echo $_SESSION['iCurrentDeposit']; ?></span>
				</td>
				<td class="Search" align="right">
					<?php echo gettext("Items in Cart:"); ?><span id="CartCounter"><?php echo count($_SESSION['aPeopleCart']); ?></span>
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
			</p>
			<?php } ?>

			<p>
				<form name="PersonFilter" method="get" action="SelectList.php">
				<b><?php echo gettext("People"); ?></b>

				<?php if ($_SESSION['bAddRecords']) { ?>
					<br><a href="PersonEditor.php" class="SmallText"><?php echo gettext("Add New Person"); ?></a>
				<?php } ?>

				<?php if ($_SESSION['bMenuOptions']) { ?>
					<br><a href="OptionManager.php?mode=classes" class="SmallText"><?php echo gettext("Classification Manager"); ?></a>
				<?php } ?>

				<br><a href="SelectList.php?mode=person" class="SmallText"><?php echo gettext("View All Persons"); ?></a>
				<input type="hidden" value="person" name="mode">
				<input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="PersonSearch" value="Search" onFocus="ClearFieldOnce(this);">
				</form>
			</p>

			<p>
				<form name="FamilyFilter" method="get" action="SelectList.php">
				<b><?php echo gettext("Families"); ?></b>

				<?php if ($_SESSION['bAddRecords']) { ?>
					<br><a href="FamilyEditor.php" class="SmallText"><?php echo gettext("Add New Family"); ?></a>
				<?php } ?>

				<?php if ($_SESSION['bMenuOptions']) { ?>
					<br><a href="OptionManager.php?mode=famroles" class="SmallText"><?php echo gettext("Family Roles Manager"); ?></a>
				<?php } ?>

				<br><a href="SelectList.php?mode=family" class="SmallText"><?php echo gettext("View All Families"); ?></a>
				<input type="hidden" value="family" name="mode">
				<input style="font-size: 8pt; margin-top: 5px; margin-bottom: 5px;" type="text" name="Filter" id="FamilySearch" value="Search" onFocus="ClearFieldOnce(this);">
				</form>
			</p>

			<?php if ($_SESSION['bFinance']) { ?>
			<p>
				<b><?php echo gettext("Deposit"); ?></b>
				<br>
			<a class="SmallText" href="DepositSlipEditor.php?DepositType=Bank"><?php echo gettext("Create New Deposit"); ?></a>
				<br>
				<a class="SmallText" href="FindDepositSlip.php"><?php echo gettext("View All Deposits"); ?></a><br>
				<a class="SmallText" href="DepositSlipEditor.php?DepositSlipID=<?php echo $_SESSION['iCurrentDeposit'];?>"><?php echo gettext("Edit Deposit Slip " . $_SESSION['iCurrentDeposit']); ?></a>
			</p>
			<?php } ?>


			<p>
				<b><?php echo gettext("Cart"); ?></b>
				<br>
				<span class="SmallText">
					<?php echo gettext("Items in Cart:") . ' '; ?><span id="CartCounter"><?php echo count($_SESSION['aPeopleCart']); ?></span>
				</span>
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
				<?php } ?>

				<?php if ($_SESSION['bMenuOptions']) { ?>
					<br>
					<a href="OptionManager.php?mode=grptypes" class="SmallText"><?php echo gettext("Edit Group Types"); ?></a>
				<?php } ?>
				<br>
				<a href="SelectList.php?mode=groupassign" class="SmallText"><?php echo gettext("Group Assignment Helper"); ?></a>
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
