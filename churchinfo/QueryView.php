<?php
/*******************************************************************************
 *
 *  filename    : QueryView.php
 *  last change : 2003-06-09
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002 Deane Barker
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
$sPageTitle = gettext("Query View");

//Get the QueryID from the querystring
$iQueryID = FilterInput($_GET["QueryID"],'int');

$aFinanceQueries = explode(',', $aFinanceQueries);

if (!$_SESSION['bFinance'] && in_array($iQueryID,$aFinanceQueries))
{
	Redirect("Menu.php");
	exit;
}

//Include the header
require "Include/Header.php";

//Get the query information
$sSQL = "SELECT * FROM query_qry WHERE qry_ID = " . $iQueryID;
$rsSQL = RunQuery($sSQL);
extract(mysql_fetch_array($rsSQL));

//Get the parameters for this query
$sSQL = "SELECT * FROM queryparameters_qrp WHERE qrp_qry_ID = " . $iQueryID;
$rsParameters = RunQuery($sSQL);

//If the form was submitted or there are no parameters, run the query
if (isset($_POST["Submit"]) || mysql_num_rows($rsParameters) == 0)
{
	//Check that all validation rules were followed
	ValidateInput();

	//Any errors?
	if (count($aErrorText) == 0)
	{
		//No errors; process the SQL, run the query, and display the results
		ProcessSQL();
		DoQuery();
	}
	else
	{
		//Yes, there were errors; re-display the parameter form (the DisplayParameterForm function will
		//pick up and display any error messages)
		DisplayQueryInfo();
		DisplayParameterForm();
	}
}
else
{
	//Display the parameter form
	DisplayQueryInfo();
	DisplayParameterForm();
}


//Loops through all the parameters and ensures validation rules have been followed
function ValidateInput()
{
	global $rsParameters;
	global $_POST;
	global $vPOST;
	global $aErrorText;

	//Initialize the validated post array, error text array, and the error flag
 	$vPOST = array();
	$aErrorText = array();
	$bError = false;

	//Are there any parameters to loop through?
	if (mysql_num_rows($rsParameters)) { mysql_data_seek($rsParameters,0); }
	while ($aRow = mysql_fetch_array($rsParameters))
	{
		extract($aRow);

		//Is the value required?
		if ($qrp_Required && strlen(trim($_POST[$qrp_Alias])) < 1)
		{
			$bError = true;
			$aErrorText[$qrp_Alias] = gettext("This value is required.");
		}

		//Assuming there was no error above...
		else
		{
			//Validate differently depending on the contents of the qrp_Validation field
			switch ($qrp_Validation)
			{
				//Numeric validation
				case "n":

					//Is it a number?
					if (!is_numeric($_POST[$qrp_Alias]))
					{
						$bError = true;
						$aErrorText[$qrp_Alias] = gettext("This value must be numeric.");
					}
					else
					{
						//Is it more than the minimum?
						if ($_POST[$qrp_Alias] < $qrp_NumericMin)
						{
							$bError = true;
							$aErrorText[$qrp_Alias] = gettext("This value must be at least ") . $qrp_NumericMin;
						}
						//Is it less than the maximum?
						elseif ($_POST[$qrp_Alias] > $qrp_NumericMax)
						{
							$bError = true;
							$aErrorText[$qrp_Alias] = gettext("This value cannot be more than ") . $qrp_NumericMax;
						}
					}

					$vPOST[$qrp_Alias] = FilterInput($_POST[$qrp_Alias],'int');
					break;

				//Alpha validation
				case "a":

					//Is the length less than the maximum?
					if (strlen($_POST[$qrp_Alias]) > $qrp_AlphaMaxLength)
					{
						$bError = true;
						$aErrorText[$qrp_Alias] = gettext("This value cannot be more than ") . $qrp_AlphaMaxLength . gettext(" characters long");
					}
					//is the length more than the minimum?
					elseif (strlen($_POST[$qrp_Alias]) < $qrp_AlphaMinLength)
					{
						$bError = true;
						$aErrorText[$qrp_Alias] = gettext("This value cannot be less than ") . $qrp_AlphaMinLength . gettext(" characters long");
					}

					$vPOST[$qrp_Alias] = FilterInput($_POST[$qrp_Alias]);
					break;

				default:
					$vPOST[$qrp_Alias] = FilterInput($_POST[$qrp_Alias]);
					break;
			}
		}
	}
}


//Loops through the list of parameters and replaces their alias in the SQL with the value given for the parameter
function ProcessSQL()
{
	global $vPOST;
	global $qry_SQL;
	global $rsParameters;

	//Loop through the list of parameters
	if (mysql_num_rows($rsParameters)) {mysql_data_seek($rsParameters,0); }
	while ($aRow = mysql_fetch_array($rsParameters))
	{
		extract($aRow);

		//Debugging code
		//echo "--" . $qry_SQL . "<br>--" . "~" . $qrp_Alias . "~" . "<br>--" . $vPOST[$qrp_Alias] . "<p>";

		//Replace the placeholder with the parameter value
		$qry_SQL = str_replace("~" . $qrp_Alias . "~",$vPOST[$qrp_Alias],$qry_SQL);
	}
}


//Checks if a count is to be displayed, and displays it if required
function DisplayRecordCount()
{
	global $qry_Count;
	global $rsQueryResults;

	//Are we supposed to display a count for this query?
	if ($qry_Count == 1)
	{
		//Display the count of the recordset
		echo "<p align=\"center\">";
		echo mysql_num_rows($rsQueryResults) . gettext(" record(s) returned");
		echo "</p>";
	}
}


//Runs the parameterized SQL and display the results
function DoQuery()
{
	global $cnInfoCentral;
	global $aRowClass;
	global $rsQueryResults;
	global $qry_SQL;
	global $iQueryID;

	//Run the SQL
	$rsQueryResults = RunQuery($qry_SQL);

	//Set the first row style
	$sRowClass = "RowColorA";

	//Check for a count display
	DisplayRecordCount();

	//Start the table and the header row
	echo "<table align=\"center\" cellpadding=\"5\" cellspacing=\"0\">";
	echo "<tr class=\"TableHeader\">";

	//Loop through the fields and write the header row
	for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
	{
		//If this field is called "AddToCart", don't display this field...
		if (mysql_field_name($rsQueryResults,$iCount) != "AddToCart")
		{
			echo "<td>" . mysql_field_name($rsQueryResults,$iCount) . "</td>";
		}
	}

	//Close the header row
	echo "</tr>";

	//Loop through the recordset
	while($aRow = mysql_fetch_array($rsQueryResults))
	{
		//Alternate the background color of the row
		$sRowClass = AlternateRowStyle($sRowClass);

		//Begin the row
		echo "<tr class=\"" . $sRowClass . "\">";

		//Loop through the fields and write each one
		for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
		{
			//If this field is called "AddToCart", add this to the hidden form field...
			if (mysql_field_name($rsQueryResults,$iCount) == "AddToCart")
			{
				$aHiddenFormField[] = $aRow[$iCount];
			}
			//...otherwise just render the field
			else
			{
				//Write the actual value of this row
				echo "<td>" . $aRow[$iCount] . "&nbsp;</td>";
			}
		}

		//Close the row
		echo "</tr>";
	}

	//Close the table and allow a link to run the query again
	echo "</table>";
	echo "<p align=\"center\">";

	if (is_array($aHiddenFormField) && count($aHiddenFormField) > 0)
	{
		?>
		<form method="post" action="CartView.php"><p align="center">
			<input type="hidden" value="<?php echo join(",",$aHiddenFormField); ?>" name="BulkAddToCart">
			<input type="submit" class="icButton" name="AddToCartSubmit" value="<?php echo gettext("Add Results To Cart");?>">&nbsp;
			<input type="submit" class="icButton" name="AndToCartSubmit" value="<?php echo gettext("Intersect Results With Cart");?>">&nbsp;
			<input type="submit" class="icButton" name="NotToCartSubmit" value="<?php echo gettext("Remove Results From Cart");?>">
		</p></form>
		<?php
	}

	echo "<p align=\"center\"><a href=\"QueryView.php?QueryID=" . $iQueryID . "\">" . gettext("Run Query Again") . "</a></p>";
	echo "<p align=\"center\"><a href=\"QueryList.php\">". gettext("Return to Query Menu") . "</a></p>";

	//Print the SQL to make debugging easier
	echo "<br><p class=\"ShadedBox\"><span class=\"SmallText\">" . str_replace(Chr(13),"<br>",htmlspecialchars($qry_SQL)) . "</span></p>";
}


//Displays the name and description of the query
function DisplayQueryInfo()
{

	global $rsSQL;
	global $qry_Name;
	global $qry_Description;

	//Display the information about this query
	echo "<p align=\"center\">";
	echo "<b>" . $qry_Name . "</b><br>" . $qry_Description;
	echo "</p>";

}


//Displays a form to enter values for each parameter, creating INPUT boxes and SELECT drop-downs as necessary
function DisplayParameterForm()
{
	global $rsParameters;
	global $iQueryID;
	global $aErrorText;
	global $cnInfoCentral;

	//Start the form and the table
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "?QueryID=" . $iQueryID . "\">";
	echo "<table align=\"center\" cellpadding=\"5\" cellspacing=\"1\" border=\"0\">";

	//Loop through the parameters and display an entry box for each one
	if (mysql_num_rows($rsParameters)) {mysql_data_seek($rsParameters,0); }
	while ($aRow = mysql_fetch_array($rsParameters))
	{
		extract($aRow);

		//Begin the row, giving the name of the parameter
		echo "<tr>";
		echo "<td class=\"LabelColumn\">" . $qrp_Name . ":</td>";

		//Determine the type of parameter we're dealing with
		switch ($qrp_Type)
		{
			//Standard INPUT box
			case 0:
				//Begin the table cell, disoplay the INPUT tag, close the table cell
				echo "<td class=\"TextColumn\">";
				echo "<input size=\"" . $qrp_InputBoxSize . "\" name=\"" . $qrp_Alias . "\" type=\"text\" value=\"" . $qrp_Default . "\">";
				echo "</td>";
				break;

			//SELECT box with OPTION tags supplied in the queryparameteroptions_qpo table
			case 1:
				//Get the query parameter options for this parameter
				$sSQL = "SELECT * FROM queryparameteroptions_qpo WHERE qpo_qrp_ID = " . $qrp_ID;
				$rsParameterOptions = RunQuery($sSQL);

				//Begin the table cell and the SELECT tag
				echo "<td class=\"TextColumn\">";
				echo "<select name=\"" . $qrp_Alias . "\">";

				//Loop through the parameter options
				while ($ThisRow = mysql_Fetch_array($rsParameterOptions))
				{
					extract($ThisRow);

					//Display the OPTION tag
					echo "<option value=\"" . $qpo_Value . "\">" . $qpo_Display . "</option>";
				}

				//Close the SELECT tag and table cell
				echo "</select>";
				echo "</td>";
				break;

			//SELECT box with OPTION tags provided via a SQL query
			case 2:
				//Run the SQL to get the options
				$rsParameterOptions = RunQuery($qrp_OptionSQL);

				echo "<td class=\"TextColumn\">";
				echo "<select name=\"" . $qrp_Alias . "\">";

				while ($ThisRow = mysql_Fetch_array($rsParameterOptions))
				{
					extract($ThisRow);
					echo "<option value=\"" . $Value . "\">" . $Display . "</option>";
				}

				//Close the SELECT tag and table cell
				echo "</select>";
				echo "</td>";
				break;
		}

		//Display the query description and close the row
		echo "<td  valign=\"top\" class=\"SmallText\">" . $qrp_Description . "</td>";
		echo "</tr>";

		//If we are re-rendering this form due to a validation error, display the error
		if (isset($aErrorText[$qrp_Alias]))
		{ 
			echo "<tr><td colspan=\"3\" style=\"color: red;\">" . $aErrorText[$qrp_Alias] . "</td></tr>";
		}
		
	}

	?>
	
	<td colspan="3" align="center">
		<br>
		<input class="icButton" type="Submit" value="<?php echo gettext("Execute Query"); ?>" name="Submit">
	</p>

	</table>

	</form>

	<?php

}

require "Include/Footer.php";

?>
