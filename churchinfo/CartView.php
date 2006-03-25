<?php
/*******************************************************************************
 *
 *  filename    : CartView.php
 *  description : displays records stored in cart
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library

function FontSelect($fieldname)
{
    global $sFPDF_PATH;
    
    $d = dir($sFPDF_PATH."/font/");
    $fontnames = array();
    $family = " ";
    while (false !== ($entry = $d->read())) 
    {
        $len = strlen($entry);
        if($len > 3){
            if(strtoupper(substr($entry, $len-3))=='PHP')
            { // php files only
                $filename = substr($entry, 0, $len-4);
                if(substr($filename, 0, strlen($family)) != $family)  
                    $family = $filename;
                $fontnames[] = FilenameToFontname($filename, $family);                    
            }
        }
    }
    
	echo "<tr>";
	echo "<td class=\"LabelColumn\">" . gettext("Font:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<select name=\"$fieldname\">";
    foreach($fontnames as $n)
    {
        $sel = "";
        if($_COOKIE[$fieldname] == $n) 
            $sel = " selected";
		echo "<option value=\"".$n."\"".$sel.">".$n."</option>";
	}
	echo "</select>";
	echo "</td>";
	echo "</tr>";
}

function FontSizeSelect($fieldname)
{
    $sizes = array("default", 6, 7, 8, 9, 10, 11, 12, 14, 16, 18);
	echo "<tr>";
	echo "<td class=\"LabelColumn\"> " . gettext("Font Size:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<select name=\"$fieldname\">";
    foreach($sizes as $s)
    {
        $sel = "";
        if($_COOKIE[$fieldname] == $s) 
            $sel = " selected";
		echo "<option value=\"".$s."\"".$sel.">".$s."</option>";
    }
	echo "</select>";
	echo "</td>";
	echo "</tr>";
}

function LabelSelect($fieldname)
{
    $labels = array("Tractor", "5160", "5161", "5162", "5163", "5164", "8600", "L7163");
	echo "<tr>";
	echo "<td class=\"LabelColumn\">" . gettext("Label Type:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<select name=\"$fieldname\">";
    foreach($labels as $l)
    {
        $sel = "";
        if($_COOKIE[$fieldname] == $l) 
            $sel = " selected";
        echo "<option value=\"".$l."\"".$sel.">".$l."</option>";
    }
	echo "</select>";
	echo "</td>";
	echo "</tr>";
}

function LabelGroupSelect($fieldname)
{
	echo "<tr><td class=\"LabelColumn\">" . gettext("Label Grouping") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"indiv\" ";

	if ($_COOKIE[$fieldname] != "fam")
		echo "checked";
	
	echo ">" . gettext("All Individuals") . "<br>";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"fam\" ";

	if ($_COOKIE[$fieldname] == "fam")
		echo "checked";

	echo ">" . gettext("Grouped by Family") . "<br></td></tr>";
}

function BulkMailPresort($fieldname)
{
	echo "<tr><td class=\"LabelColumn\">" . gettext("Bulk Mail Presort") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"none\" ";

	if (($_COOKIE[$fieldname] != "with") && ($_COOKIE[$fieldname] != "without"))
		echo "checked";
	
	echo ">" . gettext("No") . "<br>";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"without\" ";

	if ($_COOKIE[$fieldname] == "without")
		echo "checked";

	echo ">" . gettext("Yes") . "<br>";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"with\" ";

	if ($_COOKIE[$fieldname] == "with")
		echo "checked";

	echo ">" . gettext("Yes, with codes") . "<br></td></tr>";

}


require "Include/Config.php";
require "Include/Functions.php";

// Set the page title and include HTML header
$sPageTitle = gettext("View Your Cart");
require "Include/Header.php";

// Confirmation message that people where added to Event from Cart
if (count($_SESSION['aPeopleCart']) == 0) {
        if (!$_GET["Message"])
        {
            echo "<p align=\"center\" class=\"LargeText\">" . gettext("You have no items in your cart.") . "</p>";
        } else {
            switch ($_GET["Message"])
            {
                case "aMessage":
                    echo '<p align="center" class="LargeText">'.$_GET["iCount"].' '.($_GET["iCount"] == 1 ? "Record":"Records").' Emptied into Event ID:'.$_GET["iEID"].'</p>'."\n";
                break;
            }
        }
        echo '<p align="center"><input type="button" name="Exit" class="icButton" value="'.gettext("Back to Menu").'" '."onclick=\"javascript:document.location='Menu.php';\"></p>\n";

} else {

        $sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY per_LastName";
        $rsCartItems = RunQuery($sSQL);

        echo "<p align=\"center\">" . gettext("There are") . " " . mysql_num_rows($rsCartItems) . " " . gettext("item(s) in your cart.") . "</p>";
        echo "<table align=\"center\" width=\"50%\" cellpadding=\"4\" cellspacing=\"0\">\n";
        echo "<tr class=\"TableHeader\">";
        echo "<td><b>" . gettext("Name") . "</b></td>";
        echo "<td align=\"center\"><b>" . gettext("Address?") . "</b></td>";
        echo "<td align=\"center\"><b>" . gettext("Email?") . "</b></td>";
        echo "<td><b>" . gettext("Remove") . "</b></td>";

        $sEmailLink = "";
        $iEmailNum = 0;

        while ($aRow = mysql_fetch_array($rsCartItems))
        {
                $sRowClass = AlternateRowStyle($sRowClass);

                extract($aRow);

                $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
                if (strlen($sEmail))
                {
                        $sValidEmail = gettext("Yes");
                        if (!stristr($sEmailLink, $sEmail))
                        {
                                $email_array[] = $sEmail;

                                if ($iEmailNum == 0)
                                {
                                        $sEmailLink .= $sEmail;
                                        $iEmailNum++;
                                }
                                else
                                        $sEmailLink .= "," . $sEmail;
                        }
                }
                else
                {
                        $sValidEmail = gettext("No");
                }

                $sAddress1 = SelectWhichInfo($per_Address1, $fam_Address1, False);
                $sAddress2 = SelectWhichInfo($per_Address2, $fam_Address2, False);

                if (strlen($sAddress1) > 0 || strlen($sAddress2) > 0)
                        $sValidAddy = gettext("Yes");
                else
                        $sValidAddy = gettext("No");

                echo "<tr class=\"" . $sRowClass . "\">";
                echo "<td><a href=\"PersonView.php?PersonID=" . $per_ID . "\">" . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . "</a></td>";

                echo "<td align=\"center\">" . $sValidAddy . "</td>";
                echo "<td align=\"center\">" . $sValidEmail . "</td>";
                echo "<td><a href=\"CartView.php?RemoveFromPeopleCart=" . $per_ID . "\">" . gettext("Remove") . "</a></td>";
                echo "</tr>";
        }

        echo "</table>";
}

if (count($_SESSION['aPeopleCart']) != 0)
{
        echo "<br><table align=\"center\" cellpadding=\"15\"><tr><td valign=\"top\">";
        echo "<p align=\"center\" class=\"MediumText\">";
        echo "<b>" . gettext("Cart Functions") . "</b><br>";
        echo "<br>";
        echo "<a href=\"CartView.php?Action=EmptyCart\">" . gettext("Empty Cart") . "</a>";

        if ($_SESSION['bManageGroups']) {
                echo "<br>";
                echo "<a href=\"CartToGroup.php\">" . gettext("Empty Cart to Group") . "</a>";
        }
        if ($_SESSION['bAddRecords']) {
                echo "<br>";
                echo "<a href=\"CartToFamily.php\">" . gettext("Empty Cart to Family") . "</a>";
        }
        echo "<br>";
        echo "<a href=\"CartToEvent.php\">" . gettext("Empty Cart to Event") . "</a>";
        /* Link to CSV export */
        echo "<br>";
        echo "<a href=\"CSVExport.php?Source=cart\">" . gettext("CSV Export") . "</a>";

        if ($iEmailNum > 0) {
                // Add default email if default email has been set and is not already in string
                if ($sToEmailAddress != "" && $sToEmailAddress != "myReceiveEmailAddress" && !stristr($sEmailLink, $sToEmailAddress))
                        $sEmailLink .= "," . $sToEmailAddress;
                echo "<br><a href=\"mailto:" . $sEmailLink ."\">". gettext("Email Cart") . "</a>";
                echo "<br><a href=\"mailto:?bcc=".$sEmailLink."\">".gettext("Email (BCC)")."</a>";
        }

        echo "</p></td>";
?>
        <td>
    <form method="get" action="Reports/PDFLabel.php">
        <table cellpadding="4" align="center">
                <?php
				LabelGroupSelect("cartviewgroupbymode");
				BulkMailPresort("cartviewbulkmailpresort");
				LabelSelect("cartviewlabeltype");
				FontSelect("cartviewlabelfont");
				FontSizeSelect("cartviewlabelfontsize");
                ?> 	                           
                <tr>
                        <td class="LabelColumn"><?php echo gettext("Start Row:");?></td>
                        <td class="TextColumn"><input type="text" name="startrow" id="startrow" maxlength="2" size="3" value="1"></td>
                </tr>
                <tr>
                        <td class="LabelColumn"><?php echo gettext("Start Column:");?></td>
                        <td class="TextColumn"><input type="text" name="startcol" id="startcol" maxlength="2" size="3" value="1"></td>
                </tr>
                <tr>
                        <td class="LabelColumn"><?php echo gettext("Ignore Incomplete<br>Addresses:");?></td>
                        <td class="TextColumn"><input type="checkbox" name="onlyfull" id="onlyfull" value="1" checked></td>
                </tr>
    			<tr>
						<td></td>
						<td><input type="submit" class="icButton" value="<?php echo gettext("Generate Labels");?>" name="Submit"></td>
				</tr>
    </table></form></td></tr></table>

<div align="center">
<form method="get" action="DirectoryReports.php">
<?php echo "<br><h2>" . gettext("Create Member Directory") . "</h2>"; ?>
<input type="submit" class="icButton" name="cartdir" value="<?php echo gettext("Member Directory");?>">
</form>
</div>

<div align="center"><table><tr><td align="center">

<?php
        if ((isset($email_array)) && ($bEmailSend))
        {
                echo "<br><h2>" . gettext("Send Email To People in Cart") . "</h2>";
                echo "<form action=\"EmailPreview.php\" method=\"POST\">";
                foreach ($email_array as $email_address)
                {
                        echo "<input type=\"hidden\" name=\"emaillist[]\" value=\"" . $email_address . "\">";
                }
                echo "<input type=\"hidden\" name=\"emaillist[]\" value=\"" . $sToEmailAddress . "\">";

                // If editing, get Title and Message
            $sEditSubject = $_POST['emailtitle'];
            if (isset($sEditSubject))
                        $subject = $sEditSubject;
                else
                        $subject = "";

            $sEditMessage = $_POST['emailmessage'];
            if (isset($sEditMessage))
                        $message = $sEditMessage;
                else
                        $message = "";

                echo gettext("Subject:");
                echo "<br><input type=\"text\" name=\"emailtitle\" size=\"80\" value=\"" . htmlspecialchars(stripslashes($subject)) . "\"></input>";
                echo "<br>" . gettext("Message:");
                echo "<br><textarea name=\"emailmessage\" rows=\"20\" cols=\"72\">" . htmlspecialchars(stripslashes($message)) . "</textarea>";
                echo "<br><input class=\"icButton\" type=\"submit\" name=\"submit\" value=\"" . gettext("Preview your Email") . "\"></form>";
        }
        echo "</td></tr></table></div>";
        echo "<a name=\"email\"></a>";
}

require "Include/Footer.php";
?>
