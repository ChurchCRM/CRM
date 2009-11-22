<?php
/*******************************************************************************
 *
 *  filename    : PaddleNumList.php
 *  last change : 2009-04-15
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2009 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$linkBack = FilterInput($_GET["linkBack"]);

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

if ($iFundRaiserID) {
	//Get the paddlenum records for this fundraiser
	$sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
	                a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
	         FROM paddlenum_pn
	         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
	         WHERE pn_FR_ID = '" . $iFundRaiserID . "' ORDER BY pn_Num"; 
	 $rsPaddleNums = RunQuery($sSQL);
}

require "Include/Header.php";

?>

<form method="post" action="FundRaiserEditor.php?<?php echo "linkBack=" . $linkBack . "&FundRaiserID=".$iFundRaiserID?>" name="FundRaiserEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="FundRaiserCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
			<?php
				if ($iFundRaiserID)
					echo "<input type=button class=icButton value=\"".gettext("Add Buyer")."\" name=AddBuyer onclick=\"javascript:document.location='PaddleNumEditor.php?CurrentFundraiser=$iFundRaiserID&linkBack=FundRaiserEditor.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">";
			?>
		</td>
	</tr>
</table>
</form>

<br>
<b><?php echo gettext("Buyers for this fundraiser:"); ?></b>
<br>

<table cellpadding="5" cellspacing="0" width="100%">

<tr class="TableHeader">
	<td><?php echo gettext("Number"); ?></td>
	<td><?php echo gettext("Buyer"); ?></td>
	<td><?php echo gettext("Edit"); ?></td>
	<td><?php echo gettext("Delete"); ?></td>
</tr>

<?php
$tog = 0;

//Loop through all buyers
while ($aRow =mysql_fetch_array($rsPaddleNums))
{
	extract($aRow);

	$sRowClass = "RowColorA";
?>
	<tr class="<?php echo $sRowClass ?>">
		<td>
			<?php echo $pn_Num?>&nbsp;
		</td>
		<td>
			<?php echo $buyerFirstName . " " . $buyerLastName ?>&nbsp;
		</td>
		<td>
			<a href="PaddleNumEditor.php?PaddleNumID=<?php echo $pn_ID . "&linkBack=PaddleNumList.php";?>">Edit</a>
		</td>
		<td>
			<a href="PaddleNumDelete.php?PaddleNumID=<?php echo $pn_ID . "&linkBack=PaddleNumList.php?FundRaiserID=" . $iFundRaiserID;?>">Delete</a>
		</td>
	</tr>
<?php
} // while
?>

</table>

<?php
require "Include/Footer.php";
?>
