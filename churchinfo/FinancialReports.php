<?php
/*******************************************************************************
 *
 *  filename    : FinancialReports.php
 *  last change : 2005-03-26
 *  description : form to invoke financial reports
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security
if (!$_SESSION['bFinance'] && !$_SESSION['bAdmin'])
{
    Redirect("Menu.php");
    exit;
}

$sReportType = "";

if (array_key_exists ("ReportType", $_POST))
    $sReportType = FilterInput($_POST["ReportType"]);
    
if ($sReportType == "" && array_key_exists ("ReportType", $_GET))
    $sReportType = FilterInput($_GET["ReportType"]);

// Set the page title and include HTML header
$sPageTitle = gettext("Financial Reports");
if ($sReportType)
    $sPageTitle .= ": $sReportType";
require "Include/Header.php";
?>
<div class="box box-body">

<?php 

// No Records Message if previous report returned no records.
if (array_key_exists ("ReturnMessage", $_GET) && $_GET["ReturnMessage"] == "NoRows")
    echo "<h3><font color=red>".gettext("No records were returned from the previous report.")."</font></h3>";

if ($sReportType == "") {
    // First Pass - Choose report type
    echo "<form method=post action='FinancialReports.php'>";
    echo "<table cellpadding=3 align=left>";
    echo "<tr><td class=LabelColumn>" . gettext("Report Type:") . "</td>";
    echo "<td class=TextColumn><select name=ReportType>";
    echo "<option value=0>" . gettext("Select Report Type") ."</option>";
    echo "<option value='Pledge Summary'>" . gettext("Pledge Summary") ."</option>";
    echo "<option value='Pledge Family Summary'>" . gettext("Pledge FamilySummary") ."</option>";
    echo "<option value='Pledge Reminders'>" . gettext("Pledge Reminders") ."</option>";
    echo "<option value='Voting Members'>" . gettext("Voting Members") ."</option>";
    echo "<option value='Giving Report'>" . gettext("Giving Report (Tax Statements)") ."</option>";
    echo "<option value='Zero Givers'>" . gettext("Zero Givers") ."</option>";
    echo "<option value='Individual Deposit Report'>" . gettext("Individual Deposit Report") ."</option>";
    echo "<option value='Advanced Deposit Report'>" . gettext("Advanced Deposit Report") ."</option>";
    echo "</select>";
    echo "</td></tr>";
    // First Pass Cancel, Next Buttons
      echo "<tr><td>&nbsp;</td>
        <td><input type=button class=btn name=Cancel value='".gettext("Cancel")."'
        onclick=\"javascript:document.location='ReportList.php';\">
        <input type=submit class=btn name=Submit1 value='" . gettext("Next") . "'>
        </td></tr>
        </table></form>";

} else {
    $iFYID = $_SESSION['idefaultFY'];
    $iCalYear = date ("Y");
    // 2nd Pass - Display filters and other settings
    // Set report destination, based on report type
    switch($sReportType) {
        case "Giving Report":
            $action = "Reports/TaxReport.php";
        break;
        case "Zero Givers":
            $action = "Reports/ZeroGivers.php";
        break;
        case "Pledge Summary":
            $action = "Reports/PledgeSummary.php";
        break;
        case "Pledge Family Summary":
            $action = "Reports/FamilyPledgeSummary.php";
        break;
        case "Pledge Reminders":
            $action = "Reports/ReminderReport.php";
        break;
        case "Voting Members":
            $action = "Reports/VotingMembers.php";
        break;
        case "Individual Deposit Report";
            $action = "Reports/PrintDeposit.php";
        break;
        case "Advanced Deposit Report";
            $action = "Reports/AdvancedDeposit.php";
        break;
    }
    echo "<form method=post action=\"$action\">";
    echo "<input type=hidden name=ReportType value='$sReportType'>";
    echo "<table cellpadding=3 align=left>";
    echo "<tr><td><h3>". gettext("Filters") . "</h3></td></tr>";
    
    // Filter by Classification and Families
    if ($sReportType == "Giving Report" || $sReportType == "Pledge Reminders" || $sReportType == "Pledge Family Summary" || $sReportType == "Advanced Deposit Report") {
    
        //Get Classifications for the drop-down
        $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
        $rsClassifications = RunQuery($sSQL);
        ?>
        <tr>
                <td class="LabelColumn"><?= gettext("Classification:") ?><br></td>
                <td class=TextColumnWithBottomBorder><div class=SmallText>
                    </div><select name="classList[]" style="width:100%" multiple id="classList">
                    <?php
                    while ($aRow = mysql_fetch_array($rsClassifications)) {
                        extract($aRow);
                        echo "<option value=\"" . $lst_OptionID . "\"";
                        echo ">" . $lst_OptionName . "&nbsp;";
                    }
                    ?>
                    </select>
                </td>
        </tr>
        <tr>
        <td></td>
        <td>
        <br/>
        <button type="button" id="addAllClasses" class="btn">Add All Classes</button>
        <button type="button" id="clearAllClasses" class="btn">Clear All Classes</button><br/><br/>
        </td></tr>
        <?php

        $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam ORDER BY fam_Name";
        $rsFamilies = RunQuery($sSQL); ?>
        <tr><td class=LabelColumn><?= gettext("Filter by Family:") ?><br></td>
        <td class=TextColumnWithBottomBorder>
            <select name="family[]" id="family" multiple style="width:100%">
        <?php
        // Build Criteria for Head of Household
        if (!$sDirRoleHead)
            $sDirRoleHead = "1";
        $head_criteria = " per_fmr_ID = " . $sDirRoleHead;
        // If more than one role assigned to Head of Household, add OR
        $head_criteria = str_replace(",", " OR per_fmr_ID = ", $head_criteria);
        // Add Spouse to criteria
        if (intval($sDirRoleSpouse) > 0)
            $head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
        // Build array of Head of Households and Spouses with fam_ID as the key
        $sSQL = "SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (" . $head_criteria . ") ORDER BY per_fam_ID";
        $rs_head = RunQuery($sSQL);
        $aHead = array();
        while (list ($head_firstname, $head_famid) = mysql_fetch_row($rs_head)){
            if ($head_firstname && array_key_exists ($head_famid, $aHead))
                $aHead[$head_famid] .= " & " . $head_firstname;
            elseif ($head_firstname)
                $aHead[$head_famid] = $head_firstname;
        }
        while ($aRow = mysql_fetch_array($rsFamilies))
        {
            extract($aRow);
            echo "<option value=$fam_ID>$fam_Name";
            if (array_key_exists ($fam_ID, $aHead))
                echo ", " . $aHead[$fam_ID];
            echo " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
        }

        echo "</select></td></tr>";
        ?>
        <tr>
        <td></td>
        <td>
        <br/>
        <button type="button" id="addAllFamilies" class="btn">Add All Families</button>
        <button type="button" id="clearAllFamilies" class="btn">Clear All Families</button><br/><br/>
        </td></tr>
        <?php
    }
    
    // Starting and Ending Dates for Report
    if ($sReportType == "Giving Report" || $sReportType == "Advanced Deposit Report" || $sReportType == "Zero Givers") {
        $today = date("Y-m-d");
        echo "<tr><td class=LabelColumn>".gettext("Report Start Date:")."</td>
            <td class=TextColumn><input type=text name=DateStart maxlength=10 id=DateStart size=11 value='$today'></td></tr>";
        echo "<tr><td class=LabelColumn>".gettext("Report End Date:")."</td>
            <td class=TextColumn><input type=text name=DateEnd maxlength=10 id=DateEnd size=11 value='$today'></td></tr>";
        if ($sReportType == "Giving Report" || $sReportType == "Advanced Deposit Report") {
            echo "<tr><td class=LabelColumn>".gettext("Apply Report Dates To:")."</td>";
            echo "<td class=TextColumnWithBottomBorder><input name=datetype type=radio checked value='Deposit'>".gettext("Deposit Date (Default)");
            echo " &nbsp; <input name=datetype type=radio value='Payment'>".gettext("Payment Date")."</tr>";
        }
    }    
    
    // Fiscal Year
    if ($sReportType == "Pledge Summary" || $sReportType == "Pledge Reminders" || $sReportType == "Pledge Family Summary" || $sReportType == "Voting Members") {
        echo "<tr><td class=LabelColumn>".gettext("Fiscal Year:")."</td>";
        echo "<td class=TextColumn>";
        PrintFYIDSelect ($iFYID, "FYID");
        echo "</td></tr>";
    }

    // Filter by Deposit
    if ($sReportType == "Giving Report" || $sReportType == "Individual Deposit Report" || $sReportType == "Advanced Deposit Report") {
        $sSQL = "SELECT dep_ID, dep_Date, dep_Type FROM deposit_dep ORDER BY dep_ID DESC LIMIT 0,200";
        $rsDeposits = RunQuery($sSQL);
        echo "<tr><td class=LabelColumn>".gettext("Filter by Deposit:")."<br></td>";
        echo "<td class=TextColumnWithBottomBorder><div class=SmallText>";
        if ($sReportType != "Individual Deposit Report")
            echo (gettext("If deposit is selected, date criteria will be ignored."));
        echo "</div><select name=deposit>";
        if ($sReportType != "Individual Deposit Report")
            echo "<option value=0 selected>" . gettext("All deposits within date range") . "</option>";
        while ($aRow = mysql_fetch_array($rsDeposits)) {
            extract($aRow);
            echo "<option value=$dep_ID>$dep_ID &nbsp;$dep_Date &nbsp;$dep_Type ";
        }
        echo "</select></td></tr>";
    }
    
    // Filter by Account
    if ($sReportType == "Pledge Summary" || $sReportType == "Pledge Family Summary" || $sReportType == "Giving Report" || $sReportType == "Advanced Deposit Report" || $sReportType == "Pledge Reminders") {
        $sSQL = "SELECT fun_ID, fun_Name, fun_Active FROM donationfund_fun ORDER BY fun_Active, fun_Name";
        $rsFunds = RunQuery($sSQL);
        ?>
        
        <tr><td class="LabelColumn"><?= gettext("Filter by Fund:") ?><br></td>
        <td><select name="funds[]" multiple id="fundsList" style="width:100%">
        <?php
        while ($aRow = mysql_fetch_array($rsFunds)) {
            extract($aRow);
            echo "<option value=$fun_ID>$fun_Name";
            if ($fun_Active == "false")
                echo " &nbsp; INACTIVE";
        }
        ?>
        </select></td></tr>
         <tr>
        <td></td>
        <td>
        <br/>
        <button type="button" id="addAllFunds" class="btn">Add All Funds</button>
        <button type="button" id="clearAllFunds" class="btn">Clear All Funds</button><br/><br/>
        </td></tr>
        
        <?php
    }
    
    // Filter by Payment Method
    if ($sReportType == "Advanced Deposit Report") {    
        echo "<tr><td class=LabelColumn>".gettext("Filter by Payment Type:")."<br></td>";
        echo "<td class=TextColumnWithBottomBorder><div class=SmallText>"
            .gettext("Use Ctrl Key to select multiple");
        echo "</div><select name=method[] size=5 multiple>";
        echo "<option value=0 selected>".gettext("All Methods");
        echo "<option value='CHECK'>".gettext("Check")
            ."<option value='CASH'>".gettext("Cash")
            ."<option value='CREDITCARD'>".gettext("Credit Card")
            ."<option value='BANKDRAFT'>".gettext("Bank Draft")
            ."<option value='EGIVE'>".gettext("eGive");
        echo "</select></td></tr>";
    }
    
    if ($sReportType == "Giving Report") {
        echo "<tr><td class=LabelColumn>".gettext("Minimun Total Amount:")."</td>"
            . "<td class=TextColumnWithBottomBorder><div class=SmallText>"
            . gettext("0 - No Minimum") . "</div>"
            . "<input name=minimum type=text value='0' size=8></td></tr>";
    }    
    
    // Other Settings    
    echo "<tr><td><h3>". gettext("Other Settings") . "</h3></td></tr>";
    
    if ($sReportType == "Pledge Reminders"){
        echo "<tr><td class=LabelColumn>".gettext("Include:")."</td>"
            . "<td class=TextColumnWithBottomBorder><input name=pledge_filter type=radio value='pledge' checked>".gettext("Only Payments with Pledges")
            ." &nbsp; <input name=pledge_filter type=radio value='all'>".gettext("All Payments")."</td></tr>";
        echo "<tr><td class=LabelColumn>".gettext("Generate:")."</td>"
            . "<td class=TextColumnWithBottomBorder><input name=only_owe type=radio value='yes' checked>".gettext("Only Families with unpaid pledges")
            ." &nbsp; <input name=only_owe type=radio value='no'>".gettext("All Families")."</td></tr>";
    }
    
    if ($sReportType == "Giving Report" || $sReportType == "Zero Givers"){
        echo "<tr><td class=LabelColumn>".gettext("Report Heading:")."</td>"
            ."<td class=TextColumnWithBottomBorder><input name=letterhead type=radio value='graphic'>".gettext("Graphic")
            ." <input name=letterhead type=radio value='address' checked>".gettext("Church Address")
            ." <input name=letterhead type=radio value='none'>".gettext("Blank")."</td></tr>";
        echo "<tr><td class=LabelColumn>".gettext("Remittance Slip:")."</td>"
            . "<td class=TextColumnWithBottomBorder><input name=remittance type=radio value='yes'>".gettext("Yes")
            ." <input name=remittance type=radio value='no' checked>".gettext("No")."</td></tr>";
    }
    
    if ($sReportType == "Advanced Deposit Report"){
        echo "<tr><td class=LabelColumn>".gettext("Sort Data by:")."</td>"
            ."<td class=TextColumnWithBottomBorder><input name=sort type=radio value='deposit' checked>".gettext("Deposit")
            ." &nbsp;<input name=sort type=radio value='fund'>".gettext("Fund")
            ." &nbsp;<input name=sort type=radio value='family'>".gettext("Family")."</td></tr>";
        echo "<tr><td class=LabelColumn>".gettext("Report Type:")."</td>"
            . "<td class=TextColumnWithBottomBorder><input name=detail_level type=radio value='detail' checked>".gettext("All Data")
            ." <input name=detail_level type=radio value='medium'>".gettext("Moderate Detail")
            ." <input name=detail_level type=radio value='summary'>".gettext("Summary Data")."</td></tr>";
    }
    
    if ($sReportType == "Voting Members"){
        echo "<tr><td class=LabelColumn>".gettext("Voting members must have made<br>
            a donation within this many years<br>
            (0 to not require a donation):")."</td>";
        echo "<td class=TextColumnWithBottomBorder><input name=RequireDonationYears type=text value=0 size=5></td></tr>";
    }
    
    if ($sReportType == "Individual Deposit Report"){
        echo "<tr><td class=LabelColumn>".gettext("Report Type:")."</td>"
            . "<td class=TextColumnWithBottomBorder><input name=report_type type=radio value='Bank'>".gettext("Deposit Slip")
            ." <input name=report_type type=radio value='' checked>".gettext("Deposit Report")."</td></tr>";        
    }
    
    if ((($_SESSION['bAdmin'] && $bCSVAdminOnly) || !$bCSVAdminOnly) 
        && ($sReportType == "Pledge Summary" || $sReportType == "Giving Report" || $sReportType == "Individual Deposit Report" || $sReportType == "Advanced Deposit Report" || $sReportType == "Zero Givers")){
        echo "<tr><td class=LabelColumn>".gettext("Output Method:")."</td>";
        echo "<td class=TextColumnWithBottomBorder><input name=output type=radio checked value='pdf'>".gettext("PDF");
        echo " <input name=output type=radio value='csv'>".gettext("CSV")."</tr>";
    } else {
        echo "<input name=output type=hidden value='pdf'>";
    }
    
    // Back, Next Buttons
    echo "<tr><td>&nbsp;</td>
        <td><input type=button class=btn name=Cancel value='" . gettext("Back") . "'
        onclick=\"javascript:document.location='FinancialReports.php';\">
        <input type=submit class=btn name=Submit2 value='" . gettext("Create Report") . "'>
        </td></tr></table></form>";
}
?>
<script>
$("#DateStart").datepicker({format:'yyyy-mm-dd'});
$("#DateEnd").datepicker({format:'yyyy-mm-dd'});
$("#family").select2();
$("#addAllFamilies").click(function () {
var all = [];
    $("#family > option").each(function () {
        all.push(this.value);
    });
     $("#family").val(all).trigger("change");
});
$("#clearAllFamilies").click(function () {
      $("#family").val(null).trigger("change");
});

$("#classList").select2();
$("#addAllClasses").click(function () {
var all = [];
    $("#classList > option").each(function () {
        all.push(this.value);
    });
     $("#classList").val(all).trigger("change");
});
$("#clearAllClasses").click(function () {
      $("#classList").val(null).trigger("change");
});

$("#fundsList").select2();
$("#addAllFunds").click(function () {
var all = [];
    $("#fundsList > option").each(function () {
        all.push(this.value);
    });
     $("#fundsList").val(all).trigger("change");
});
$("#clearAllFunds").click(function () {
      $("#fundsList").val(null).trigger("change");
});

</script>
</div>
<?php
require "Include/Footer.php";
?>
