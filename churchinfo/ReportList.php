<?php
/*******************************************************************************
 *
 *  filename    : ReportList.php
 *  last change : 2003-03-20
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

//Set the page title
$sPageTitle = gettext("Report Menu");

$today = getdate();
$year = $today['year'];

require "Include/Header.php";

?>

<p>
<a class="MediumText" href="GroupReports.php"><?php echo gettext("Reports on groups and roles"); ?></a>
<br>
<?php echo gettext("Report on group and roles selected (it may be a multi-page PDF)."); ?>
</p>


<?php if ($_SESSION['bAdmin'] || !$bCSVAdminOnly) { ?>
	<p>
	<a class="MediumText" href="DirectoryReports.php"><?php echo gettext("Members Directory"); ?></a>
	<br>
	<?php echo gettext("Printable directory of all members, grouped by family where assigned"); ?>
	</p>
<?php } ?>

<?php /*
<p>
<a href=""><?php echo gettext("Members Directory w/Photos"); ?></a>
<br>
<?php echo gettext("Printable directory of all members. Family photos where available / Individual photos otherwise."); ?>
</p> */ ?>

<p>
<a class="MediumText" href="Reports/NewsLetterLabels.php"><?php echo gettext("Mailing labels for the newsletter"); ?></a>
<br>
<?php echo gettext("Mailing labels for tractor feed printer (multi-page PDF)."); ?>
</p>

<p>
<a class="MediumText" href="Reports/ConfirmReport.php"><?php echo gettext("Confirm data report"); ?></a>
<br>
<?php echo gettext("Generate letters requesting confirmation of information in the database (multi-page PDF)."); ?>
</p>

<p>
<a class="MediumText" href="SundaySchool.php"><?php echo gettext("Sunday School Reports"); ?></a>
<br>
<?php echo gettext("Generate class lists and attendance sheets"); ?>
</p>


<p>
<a class="MediumText" href="FinancialReports.php"><?php echo gettext("Financial Reports"); ?></a>
<br>
<?php echo gettext("Pledges and Payments"); ?>
</p>


<?php
require "Include/Footer.php";
?>
