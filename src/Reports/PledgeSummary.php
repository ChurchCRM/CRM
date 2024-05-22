<?php

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled());

// Filter Values
$output = InputUtils::legacyFilterInput($_POST['output']);
$iFYID = InputUtils::legacyFilterInput($_POST['FYID'], 'int');
if (!$iFYID) {
    $iFYID = CurrentFY();
}
// Remember the chosen Fiscal Year ID
$_SESSION['idefaultFY'] = $iFYID;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::redirect('v2/dashboard');
}

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true' ORDER BY fun_Active, fun_Name";
$rsFunds = RunQuery($sSQL);

$overpaid = [];
$underpaid = [];
$pledgeFundTotal = [];
$paymentFundTotal = [];

while ($row = mysqli_fetch_array($rsFunds)) {
    $fun_name = $row['fun_Name'];
    $overpaid[$fun_name] = 0;
    $underpaid[$fun_name] = 0;
    $paymentCnt[$fun_name] = 0;
    $pledgeCnt[$fun_name] = 0;
    $pledgeFundTotal[$fun_name] = 0;
    $paymentFundTotal[$fun_name] = 0;
}
$pledgeFundTotal['Unassigned'] = 0;
$paymentFundTotal['Unassigned'] = 0;
$paymentCnt['Unassigned'] = 0;
$pledgeCnt['Unassigned'] = 0;

// Get pledges and payments for this fiscal year
$sSQL = 'SELECT plg_plgID, plg_FYID, plg_amount, plg_PledgeOrPayment, plg_fundID, plg_famID, b.fun_Name AS fundName, b.fun_Active AS fundActive FROM pledge_plg
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		 WHERE plg_FYID = ' . $iFYID;

// Filter by Fund
if (!empty($_POST['funds'])) {
    $count = 0;
    foreach ($_POST['funds'] as $fundID) {
        $fund[$count++] = InputUtils::legacyFilterInput($fundID, 'int');
    }
    if ($count == 1) {
        if ($fund[0]) {
            $sSQL .= " AND plg_fundID='$fund[0]' ";
        }
    } else {
        $sSQL .= " AND (plg_fundID ='$fund[0]'";
        for ($i = 1; $i < $count; $i++) {
            $sSQL .= " OR plg_fundID='$fund[$i]'";
        }
        $sSQL .= ') ';
    }
}

// Order by Family so the related pledges and payments will be together
$sSQL .= ' ORDER BY plg_famID';

$rsPledges = RunQuery($sSQL);

// Create PDF Report
if ($output === 'pdf') {
    class PdfPledgeSummaryReport extends ChurchInfoReport
    {
        // Constructor
        public function __construct()
        {
            parent::__construct('P', 'mm', $this->paperFormat);

            $this->SetFont('Times', '', 10);
            $this->SetMargins(0, 0);

            $this->SetAutoPageBreak(false);
            $this->addPage();
        }
    }

    // Instantiate the directory class and build the report.
    $pdf = new PdfPledgeSummaryReport();

    // Total all the pledges and payments by fund.  Compute overpaid and underpaid for each family as
    // we go through them.

    // This algorithm is complicated for the sake of efficiency.  The query gets all the payments ordered
    // by family.  As the loop below goes through the payments, it collects pledges and payment for each
    // family, by fund.  It needs to go around one extra time so the last payment gets posted to underpaid/
    // overpaid.
    $curFam = 0;
    $paidThisFam = [];
    $pledgeThisFam = [];
    $totRows = mysqli_num_rows($rsPledges);
    $thisRow = 0;
    $fundName = '';
    $plg_famID = 0;

    // Go through the loop one extra time
    for ($thisRow = 0; $thisRow <= $totRows; $thisRow += 1) {
        if ($thisRow < $totRows) {
            $aRow = mysqli_fetch_array($rsPledges);
            extract($aRow);
        }

        if ($fundName === '') {
            $fundName = 'Unassigned';
        }

        if ($plg_famID != $curFam || $thisRow == $totRows) {
            // Switching families.  Post the results for the previous family and initialize for the new family
            mysqli_data_seek($rsFunds, 0);
            while ($row = mysqli_fetch_array($rsFunds)) {
                $fun_name = $row['fun_Name'];
                if (array_key_exists($fun_name, $pledgeThisFam) && $pledgeThisFam[$fun_name] > 0) {
                    $thisPledge = $pledgeThisFam[$fun_name];
                } else {
                    $thisPledge = 0.0;
                }
                if (array_key_exists($fun_name, $paidThisFam) && $paidThisFam[$fun_name] > 0) {
                    $thisPay = $paidThisFam[$fun_name];
                } else {
                    $thisPay = 0.0;
                }
                $pledgeDiff = $thisPay - $thisPledge;
                if ($pledgeDiff > 0) {
                    $overpaid[$fun_name] += $pledgeDiff;
                } else {
                    $underpaid[$fun_name] -= $pledgeDiff;
                }
            }
            $paidThisFam = [];
            $pledgeThisFam = [];
            $curFam = $plg_famID;
        }

        if ($thisRow < $totRows) {
            if ($plg_PledgeOrPayment === 'Pledge') {
                if (array_key_exists($fundName, $pledgeFundTotal)) {
                    $pledgeFundTotal[$fundName] += $plg_amount;
                    $pledgeCnt[$fundName] += 1;
                } else {
                    $pledgeFundTotal[$fundName] = $plg_amount;
                    $pledgeCnt[$fundName] = 1;
                }
                if (array_key_exists($fundName, $pledgeThisFam)) {
                    $pledgeThisFam[$fundName] += $plg_amount;
                } else {
                    $pledgeThisFam[$fundName] = $plg_amount;
                }
            } elseif ($plg_PledgeOrPayment === 'Payment') {
                if (array_key_exists($fundName, $paymentFundTotal)) {
                    $paymentFundTotal[$fundName] += $plg_amount;
                    $paymentCnt[$fundName] += 1;
                } else {
                    $paymentFundTotal[$fundName] = $plg_amount;
                    $paymentCnt[$fundName] = 1;
                }
                if (array_key_exists($fundName, $paidThisFam)) {
                    $paidThisFam[$fundName] += $plg_amount;
                } else {
                    $paidThisFam[$fundName] = $plg_amount;
                }
            }
        }
    }

    $nameX = 20;
    $pledgeX = 60;
    $paymentX = 80;
    $pledgeCountX = 100;
    $paymentCountX = 120;
    $underpaidX = 145;
    $overpaidX = 170;
    $curY = 20;

    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchName'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchAddress'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  ' . SystemConfig::getValue('sChurchZip'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchPhone') . '  ' . SystemConfig::getValue('sChurchEmail'));
    $curY += 2 * SystemConfig::getValue('incrementY');

    $blurb = SystemConfig::getValue('sPledgeSummary1') . ' ';
    $blurb .= MakeFYString($iFYID) . ' ';
    $blurb .= SystemConfig::getValue('sPledgeSummary2') . ' ' . date('Y-m-d') . '.';
    $pdf->writeAt($nameX, $curY, $blurb);

    $curY += 3 * SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAt($nameX, $curY, 'Fund');
    $pdf->printRightJustified($pledgeX, $curY, 'Pledges');
    $pdf->printRightJustified($paymentX, $curY, 'Payments');
    $pdf->printRightJustified($pledgeCountX, $curY, '# Pledges');
    $pdf->printRightJustified($paymentCountX, $curY, '# Payments');
    $pdf->printRightJustified($underpaidX, $curY, 'Overpaid');
    $pdf->printRightJustified($overpaidX, $curY, 'Underpaid');
    $pdf->SetFont('Times', '', 10);
    $curY += SystemConfig::getValue('incrementY');

    // Change this to print out funds in active / alpha order
    mysqli_data_seek($rsFunds, 0);
    while ($row = mysqli_fetch_array($rsFunds)) {
        $fun_name = $row['fun_Name'];
        if ($pledgeFundTotal[$fun_name] > 0 || $paymentFundTotal[$fun_name] > 0) {
            if (strlen($fun_name) > 30) {
                $short_fun_name = mb_substr($fun_name, 0, 30) . '...';
            } else {
                $short_fun_name = $fun_name;
            }
            $pdf->writeAt($nameX, $curY, $short_fun_name);
            $amountStr = sprintf('%.2f', $pledgeFundTotal[$fun_name]);
            $pdf->printRightJustified($pledgeX, $curY, $amountStr);
            $amountStr = sprintf('%.2f', $paymentFundTotal[$fun_name]);
            $pdf->printRightJustified($paymentX, $curY, $amountStr);
            $pdf->printRightJustified($pledgeCountX, $curY, $pledgeCnt[$fun_name]);
            $pdf->printRightJustified($paymentCountX, $curY, $paymentCnt[$fun_name]);

            $amountStr = sprintf('%.2f', $overpaid[$fun_name]);
            $pdf->printRightJustified($underpaidX, $curY, $amountStr);
            $amountStr = sprintf('%.2f', $underpaid[$fun_name]);
            $pdf->printRightJustified($overpaidX, $curY, $amountStr);
            $curY += SystemConfig::getValue('incrementY');
        }
    }

    if ($pledgeFundTotal['Unassigned'] > 0 || $paymentFundTotal['Unassigned'] > 0) {
        $pdf->writeAt($nameX, $curY, 'Unassigned');
        $amountStr = sprintf('%.2f', $pledgeFundTotal['Unassigned']);
        $pdf->printRightJustified($pledgeX, $curY, $amountStr);
        $amountStr = sprintf('%.2f', $paymentFundTotal['Unassigned']);
        $pdf->printRightJustified($paymentX, $curY, $amountStr);
        $pdf->printRightJustified($pledgeCountX, $curY, $pledgeCnt['Unassigned']);
        $pdf->printRightJustified($paymentCountX, $curY, $paymentCnt['Unassigned']);
        $curY += SystemConfig::getValue('incrementY');
    }

    if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
        $pdf->Output('PledgeSummaryReport' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
    } else {
        $pdf->Output();
    }

// Output a text file
} elseif ($output === 'csv') {
    // Settings
    $delimiter = ',';
    $eol = "\r\n";

    // Build headings row
    preg_match('/SELECT (.*) FROM /i', $sSQL, $result);
    $headings = explode(',', $result[1]);
    $buffer = '';
    foreach ($headings as $heading) {
        $buffer .= trim($heading) . $delimiter;
    }
    // Remove trailing delimiter and add eol
    $buffer = mb_substr($buffer, 0, -1) . $eol;

    while ($row = mysqli_fetch_row($rsPledges)) {
        foreach ($row as $field) {
            // Remove any delimiters from data
            $field = str_replace($delimiter, ' ', $field);
            $buffer .= $field . $delimiter;
        }
        // Remove trailing delimiter and add eol
        $buffer = mb_substr($buffer, 0, -1) . $eol;
    }

    // Export file
    header('Content-type: text/x-csv');
    header('Content-Disposition: attachment; filename=ChurchInfo-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv');
    echo $buffer;
}
