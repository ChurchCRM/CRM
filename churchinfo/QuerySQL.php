<?php
/*******************************************************************************
 *
 *  filename    : QuerySQL.php
 *  last change : 2003-01-04
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Set the page title
$sPageTitle = gettext("Free-Text Query");

// Security: User must be an Admin to access this page.  It allows unrestricted database access!
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

if (isset($_POST["SQL"]))
{
	//Assign the value locally
	$sSQL = stripslashes(trim($_POST["SQL"]));
}
else
{
	$sSQL = "";
}

if (isset($_POST["CSV"]))
{

    ExportQueryResults();
    exit;
}

require "Include/Header.php";
?>

<form method="post">

<center><table><tr>
    <td class="LabelColumn"> <?php echo gettext("Export Results to CSV file") ?> </td>
    <td class="TextColumn"><input name="CSV" type="checkbox" id="CSV" value="1"></td>
</tr></table></center>

<p align="center">
	<textarea style="font-family:courier,fixed; font-size:9pt; padding:1;" cols="60" rows="10" name="SQL"><?php echo $sSQL; ?></textarea>
</p>
<p align="center">
	<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Execute SQL") . '"'; ?>>
</p>

</form>

<?php


if (isset($_POST["SQL"]))
{
	if (strtolower(substr($sSQL,0,6)) == "select")
	{
		RunFreeQuery();
	}
}


function ExportQueryResults()
{

	global $cnInfoCentral;
	global $aRowClass;
	global $rsQueryResults;
	global $sSQL;
	global $iQueryID;


    $sCSVstring = "";
	
	//Run the SQL
	$rsQueryResults = RunQuery($sSQL);

	if (mysql_error() != "")
	{
		$sCSVstring = gettext("An error occured: ") . mysql_errno() . "--" . mysql_error();
	}
	else
	{

		//Loop through the fields and write the header row
		for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
		{
            $sCSVstring .= mysql_field_name($rsQueryResults,$iCount) . ",";
		}

        $sCSVstring .= "\n";

		//Loop through the recordsert
		while($aRow =mysql_fetch_array($rsQueryResults))
		{
			//Loop through the fields and write each one
			for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
			{
				$sCSVstring .= $aRow[$iCount] . ",";
			}

			$sCSVstring .= "\n";
		}
	}

    header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=Query-" . date("Ymd-Gis") . ".csv");
	header("Content-Transfer-Encoding: binary");
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public'); 
	echo $sCSVstring;
    exit;

}


function RunFreeQuery()
{

	global $cnInfoCentral;
	global $aRowClass;
	global $rsQueryResults;
	global $sSQL;
	global $iQueryID;

	
	//Run the SQL
	$rsQueryResults = RunQuery($sSQL);

	if (mysql_error() != "")
	{
		echo gettext("An error occured: ") . mysql_errno() . "--" . mysql_error();
	}
	else
	{

		$sRowClass = "RowColorA";

		echo '<table align="center" cellpadding="5" cellspacing="0">';

		echo '<tr class="' . $sRowClass . '">';

		//Loop through the fields and write the header row
		for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
		{
            echo '  <td align="center">
                        <b>' . mysql_field_name($rsQueryResults,$iCount) . '</b>
                    </td>';
		}

		echo '</tr>';

		//Loop through the recordsert
		while($aRow =mysql_fetch_array($rsQueryResults))
		{

			$sRowClass = AlternateRowStyle($sRowClass);

			echo '<tr class="' . $sRowClass . '">';

			//Loop through the fields and write each one
			for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
			{
				echo '<td align="center">' . $aRow[$iCount] . '</td>';
			}

			echo '</tr>';

		}

		echo '</table>';

		echo '<br><p class="ShadedBox" style="border-style: solid; margin-left: 50px; margin-right: 50 px; border-width: 1px;"><span class="SmallText">' . str_replace(Chr(13),"<br>",htmlspecialchars($sSQL)) . '</span></p>';
	}
}

require "Include/Footer.php";

?>
