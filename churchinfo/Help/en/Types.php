<?php
	$sPageTitle = "Custom Types";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What are the types for Custom Fields?</div>
		<table class="LightShadedBox">
			<td class="HeaderRow" align="center"><b>Name</b></td><td class="HeaderRow" align="center"><b>Description</b></td>
			<tr>
				<td nowrap><b>True/False</b></td>
				<td>Simple yes/no question</td>
			</tr>
			<tr>
				<td nowrap><b>Date</b></td>
				<td>Standard date in Year-Month-Day [YYYY-MM-DD] format</td>
			</tr>
			<tr>
				<td nowrap><b>Text Field (50 Character)</b></td>
				<td>A text field with a maximum length of 50 characters</td>
			</tr>
			<tr>
				<td nowrap><b>Text Field (100 Character)&nbsp;&nbsp;</b></td>
				<td>A text field with a maximum length of 100 characters</td>
			</tr>
			<tr>
				<td nowrap><b>Text Field (Long)</b></td>
				<td>A paragraph-length text field holding a maximum of 65,535 characters</td>
			</tr>
			<tr>
				<td nowrap><b>Year</b></td>
				<td>Standard 4-digit year.  Allowable values are 1901 to 2155</td>
			</tr>
			<tr>
				<td nowrap><b>Season</b></td>
				<td>Select one of the 4 seasons</td>
			</tr>
			<tr>
				<td nowrap><b>Number</b></td>
				<td>A whole number (integer) between -2147483648 and 2147483647</td>
			</tr>
			<tr>
				<td nowrap><b>Person From Group</b></td>
				<td>Select a person from a specified group</td>
			</tr>
			<tr>
				<td nowrap><b>Money</b></td>
				<td>A number with 2 decimal places, maximum 999999999.99</td>
			</tr>
			<tr>
				<td nowrap><b>Phone Number</b></td>
				<td>Standard phone number.  Will be auto-formatted based on person's country</td>
			</tr>
			<tr>
				<td nowrap><b>Custom Drop-Down List</b></td>
				<td>This lets you create a drop-down selection list of any values you want.  You can edit this list after you add this type to a form</td>
			</tr>
  </table>
</div>

<?php
	require "Include/Footer.php";
?>
