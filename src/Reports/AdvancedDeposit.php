<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Service\FinancialService;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Filter values
$sort = InputUtils::legacyFilterInput($_POST['sort']);
$detail_level = InputUtils::legacyFilterInput($_POST['detail_level']);
$datetype = InputUtils::legacyFilterInput($_POST['datetype']);
$output = InputUtils::legacyFilterInput($_POST['output']);
$sDateStart = InputUtils::legacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::legacyFilterInput($_POST['DateEnd'], 'date');
$iDepID = InputUtils::legacyFilterInput($_POST['deposit'], 'int');

// Prepare filter arrays
$classList = [];
$fundIds = [];
$familyIds = [];
$methods = [];

if (!empty($_POST['classList'])) {
    $classList = array_map('intval', $_POST['classList']);
}

if (!empty($_POST['funds'])) {
    foreach ($_POST['funds'] as $fundID) {
        $fundIds[] = InputUtils::legacyFilterInput($fundID, 'int');
    }
}

if (!empty($_POST['family'])) {
    foreach ($_POST['family'] as $famID) {
        $familyIds[] = InputUtils::legacyFilterInput($famID, 'int');
    }
}

if (!empty($_POST['method'])) {
    foreach ($_POST['method'] as $methodItem) {
        $methods[] = InputUtils::legacyFilterInput($methodItem);
    }
}

if (!$sort) {
    $sort = 'deposit';
}
if (!$detail_level) {
    $detail_level = 'detail';
}
if (!$output) {
    $output = 'pdf';
}

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::securityRedirect('Admin');
}

// Normalize date range
$today = date('Y-m-d');
if (!$sDateEnd && $sDateStart) {
    $sDateEnd = $sDateStart;
}
if (!$sDateStart && $sDateEnd) {
    $sDateStart = $sDateEnd;
}
if (!$sDateStart && !$sDateEnd) {
    $sDateStart = $today;
    $sDateEnd = $today;
}
if ($sDateStart > $sDateEnd) {
    $temp = $sDateStart;
    $sDateStart = $sDateEnd;
    $sDateEnd = $temp;
}

// Use FinancialService to get data using ORM instead of raw SQL
$financialService = new FinancialService();
$pledgeObjects = $financialService->getAdvancedDepositReportData(
    $sort,
    $sDateStart,
    $sDateEnd,
    $iDepID > 0 ? $iDepID : null,
    $fundIds,
    $familyIds,
    $methods,
    $classList,
    $datetype
);

// Convert Propel objects to array format for backward compatibility with existing PDF/CSV code
$rsReport = [];
foreach ($pledgeObjects as $pledge) {
    $row = [
        'fam_ID' => $pledge['FamId'],
        'fam_Name' => $pledge['Family']['Name'] ?? 'Unassigned',
        'fam_Address1' => $pledge['Family']['Address1'] ?? '',
        'fam_Address2' => $pledge['Family']['Address2'] ?? '',
        'fam_City' => $pledge['Family']['City'] ?? '',
        'fam_State' => $pledge['Family']['State'] ?? '',
        'fam_Zip' => $pledge['Family']['Zip'] ?? '',
        'fam_Country' => $pledge['Family']['Country'] ?? '',
        'plg_date' => $pledge['Date'],
        'plg_amount' => $pledge['Amount'],
        'plg_method' => $pledge['Method'],
        'plg_comment' => $pledge['Comment'],
        'plg_depID' => $pledge['DepId'],
        'plg_CheckNo' => $pledge['CheckNo'] ?? '',
        'fun_ID' => $pledge['FundId'],
        'fun_Name' => $pledge['DonationFund']['Name'] ?? 'Undesignated',
        'dep_Date' => $pledge['Deposit']['Date'] ?? '',
    ];
    $rsReport[] = $row;
}

// Exit if no rows returned
$iCountRows = count($rsReport);
if ($iCountRows < 1) {
    $params = [
        'ReturnMessage' => 'NoRows',
        'ReportType' => 'Advanced Deposit Report',
        'DateStart' => $sDateStart,
        'DateEnd'   => $sDateEnd,
        'datetype'  => $datetype,
    ];
    header('Location: ../FinancialReports.php?' . http_build_query($params));
    exit;
}

// Set up bottom border value
$bottom_border = 250;
$summaryIntervalY = 4;
$page = 1;

    class PdfAdvancedDepositReport extends ChurchInfoReport
    {
        // Constructor
        public function __construct()
        {
            parent::__construct('P', 'mm', $this->paperFormat);
            $this->SetFont('Times', '', 10);
            $this->SetMargins(20, 15);

            $this->SetAutoPageBreak(false);
        }

        public function printRightJustified($x, $y, $str): void
        {
            $iLen = strlen($str);
            $nMoveBy = 2 * $iLen;
            $this->SetXY($x - $nMoveBy, $y);
            $this->Write(8, $str);
        }

        public function startFirstPage()
        {
            global $sDateStart, $sDateEnd, $sort, $iDepID, $datetype;
            $this->addPage();
            $curY = 20;
            $curX = 60;
            $this->SetFont('Times', 'B', 14);
            $this->writeAt($curX, $curY, SystemConfig::getValue('sChurchName') . ' Deposit Report');
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->SetFont('Times', 'B', 10);
            $curX = SystemConfig::getValue('leftX');
            $this->writeAt($curX, $curY, 'Data sorted by ' . ucwords($sort));
            $curY += SystemConfig::getValue('incrementY');
            if (!$iDepID) {
                $this->writeAt($curX, $curY, "$datetype Dates: $sDateStart through $sDateEnd");
                $curY += SystemConfig::getValue('incrementY');
            }
            if ($iDepID || $_POST['family'][0] || $_POST['funds'][0] || $_POST['method'][0]) {
                $heading = 'Filtered by ';
                if ($iDepID) {
                    $heading .= "Deposit #$iDepID, ";
                }
                if ($_POST['family'][0]) {
                    $heading .= 'Selected Families, ';
                }
                if ($_POST['funds'][0]) {
                    $heading .= 'Selected Funds, ';
                }
                if ($_POST['method'][0]) {
                    $heading .= 'Selected Payment Methods, ';
                }
                $heading = mb_substr($heading, 0, -2);
            } else {
                $heading = 'Showing all records for report dates.';
            }
            $this->writeAt($curX, $curY, $heading);
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->SetFont('Times', '', 10);

            return $curY;
        }

        public function pageBreak($page)
        {
            // Finish footer of previous page if necessary and add new page
            global $curY, $bottom_border, $detail_level;
            if ($curY > $bottom_border) {
                $this->finishPage($page);
                $page++;
                $this->addPage();
                $curY = 20;
                if ($detail_level === 'detail') {
                    $curY = $this->headings($curY);
                }
            }

            return $page;
        }

        public function headings($curY)
        {
            global $sort, $summaryIntervalY;
            if ($sort === 'deposit') {
                $curX = SystemConfig::getValue('leftX');
                $this->SetFont('Times', 'BU', 10);
                $this->writeAt($curX, $curY, 'Chk No.');
                $this->writeAt(40, $curY, 'Fund');
                $this->writeAt(80, $curY, 'Received From');
                $this->writeAt(135, $curY, 'Memo');
                $this->writeAt(181, $curY, 'Amount');
                $curY += 2 * $summaryIntervalY;
            } elseif ($sort === 'fund') {
                $curX = SystemConfig::getValue('leftX');
                $this->SetFont('Times', 'BU', 10);
                $this->writeAt($curX, $curY, 'Chk No.');
                $this->writeAt(40, $curY, 'Deposit No./ Date');
                $this->writeAt(80, $curY, 'Received From');
                $this->writeAt(135, $curY, 'Memo');
                $this->writeAt(181, $curY, 'Amount');
                $curY += 2 * $summaryIntervalY;
            } elseif ($sort === 'family') {
                $curX = SystemConfig::getValue('leftX');
                $this->SetFont('Times', 'BU', 10);
                $this->writeAt($curX, $curY, 'Chk No.');
                $this->writeAt(40, $curY, 'Deposit No./Date');
                $this->writeAt(80, $curY, 'Fund');
                $this->writeAt(135, $curY, 'Memo');
                $this->writeAt(181, $curY, 'Amount');
                $curY += 2 * $summaryIntervalY;
            }

            return $curY;
        }

        public function finishPage($page): void
        {
            $footer = "Page $page   Generated on " . date(SystemConfig::getValue('sDateTimeFormat'));
            $this->SetFont('Times', 'I', 9);
            $this->writeAt(80, 258, $footer);
        }
    }

    // Instantiate the directory class and build the report.
    if ($output === 'pdf') {
        $pdf = new PdfAdvancedDepositReport();

    $curY = $pdf->startFirstPage();
    $curX = 0;

    $currentDepositID = 0;
    $currentFundID = 0;
    $totalAmount = 0;
    $totalFund = [];

    $countFund = 0;
    $countDeposit = 0;
    $countReport = 0;
    $currentFundAmount = 0;
    $currentDepositAmount = 0;
    $currentReportAmount = 0;

    // Sort by Deposit Report
    if ($sort === 'deposit') {
        if ($detail_level === 'detail') {
            $curY = $pdf->headings($curY);
        }

        foreach ($rsReport as $aRow) {
            $fun_ID = $aRow['fun_ID'];
            $fun_Name = $aRow['fun_Name'];
            $fam_ID = $aRow['fam_ID'];
            $fam_Name = $aRow['fam_Name'];
            $fam_Address1 = $aRow['fam_Address1'];
            $plg_depID = $aRow['plg_depID'];
            $plg_amount = $aRow['plg_amount'];
            $plg_method = $aRow['plg_method'];
            $plg_comment = $aRow['plg_comment'];
            $plg_CheckNo = $aRow['plg_CheckNo'];
            $dep_Date = $aRow['dep_Date'];
            
            if (!$fun_ID) {
                $fun_ID = -1;
                $fun_Name = 'Undesignated';
            }
            if (!$fam_ID) {
                $fam_ID = -1;
                $fam_Name = 'Unassigned';
            }
            // First Deposit Heading
            if (!$currentDepositID && $detail_level != 'summary') {
                $sDepositTitle = "Deposit #$plg_depID ($dep_Date)";
                $pdf->SetFont('Times', 'B', 10);
                $pdf->writeAt(20, $curY, $sDepositTitle);
                $curY += 1.5 * $summaryIntervalY;
            }
            // Check for new fund
            if (($currentFundID != $fun_ID || $currentDepositID != $plg_depID) && $currentFundID && $detail_level != 'summary') {
                // New Fund. Print Previous Fund Summary
                if ($countFund > 1) {
                    $item = gettext('items');
                } else {
                    $item = gettext('item');
                }
                $sFundSummary = "$currentFundName Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
                $curY += 2;
                $pdf->SetXY(20, $curY);
                $pdf->SetFont('Times', 'I', 10);
                $pdf->Cell(176, $summaryIntervalY, $sFundSummary, 0, 0, 'R');
                $curY += 1.75 * $summaryIntervalY;
                $countFund = 0;
                $currentFundAmount = 0;
                $page = $pdf->pageBreak($page);
            }
            // Check for new deposit
            if ($currentDepositID != $plg_depID && $currentDepositID) {
                // New Deposit ID.  Print Previous Deposit Summary
                if ($countDeposit > 1) {
                    $item = gettext('items');
                } else {
                    $item = gettext('item');
                }
                $sDepositSummary = "Deposit #$currentDepositID Total - $countDeposit $item:   $" . number_format($currentDepositAmount, 2, '.', ',');
                $pdf->SetXY(20, $curY);
                $pdf->SetFont('Times', 'B', 10);
                $pdf->Cell(176, $summaryIntervalY, $sDepositSummary, 0, 0, 'R');
                $curY += 2 * $summaryIntervalY;
                if ($detail_level != 'summary') {
                    $pdf->line(40, $curY - 2, 195, $curY - 2);
                }
                $page = $pdf->pageBreak($page);

                // New Deposit Title
                if ($detail_level != 'summary') {
                    $sDepositTitle = "Deposit #$plg_depID ($dep_Date)";
                    $pdf->SetFont('Times', 'B', 10);
                    $pdf->writeAt(20, $curY, $sDepositTitle);
                    $curY += 1.5 * $summaryIntervalY;
                }
                $countDeposit = 0;
                $currentDepositAmount = 0;
            }

            // Print Deposit Detail
            if ($detail_level === 'detail') {
                // Format Data
                if ($plg_method === 'CREDITCARD') {
                    $plg_method = 'CREDIT';
                }
                if ($plg_method === 'BANKDRAFT') {
                    $plg_method = 'DRAFT';
                }
                if ($plg_method != 'CHECK') {
                    $plg_CheckNo = $plg_method;
                }
                if (strlen($plg_CheckNo) > 8) {
                    $plg_CheckNo = '...' . mb_substr($plg_CheckNo, -8, 8);
                }
                if (strlen($fun_Name) > 22) {
                    $sfun_Name = mb_substr($fun_Name, 0, 21) . '...';
                } else {
                    $sfun_Name = $fun_Name;
                }
                if (strlen($plg_comment) > 29) {
                    $plg_comment = mb_substr($plg_comment, 0, 28) . '...';
                }
                $fam_Name_Display = $fam_Name . ' - ' . $fam_Address1;
                if (strlen($fam_Name_Display) > 31) {
                    $fam_Name_Display = mb_substr($fam_Name_Display, 0, 30) . '...';
                }

                // Print Data
                $pdf->SetFont('Times', '', 10);
                $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
                $pdf->Cell(16, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R');
                $pdf->Cell(40, $summaryIntervalY, $sfun_Name);
                $pdf->Cell(55, $summaryIntervalY, $fam_Name_Display);
                $pdf->Cell(40, $summaryIntervalY, $plg_comment);
                $pdf->SetFont('Courier', '', 9);
                $pdf->Cell(25, $summaryIntervalY, $plg_amount, 0, 0, 'R');
                $pdf->SetFont('Times', '', 10);
                $curY += $summaryIntervalY;
                $page = $pdf->pageBreak($page);
            }
            // Update running totals
            $totalAmount += $plg_amount;
            if (array_key_exists($fun_Name, $totalFund)) {
                $totalFund[$fun_Name] += $plg_amount;
            } else {
                $totalFund[$fun_Name] = $plg_amount;
            }
            $countFund++;
            $countDeposit++;
            $countReport++;
            $currentFundAmount += $plg_amount;
            $currentDepositAmount += $plg_amount;
            $currentReportAmount += $plg_amount;
            $currentDepositID = $plg_depID;
            $currentFundID = $fun_ID;
            $currentFundName = $fun_Name;
            $currentDepositDate = $dep_Date;
        }

        // Print Final Summary
        // Print Fund Summary
        if ($detail_level != 'summary') {
            if ($countFund > 1) {
                $item = gettext('items');
            } else {
                $item = gettext('item');
            }
            $sFundSummary = "$fun_Name Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
            $curY += 2;
            $pdf->SetXY(20, $curY);
            $pdf->SetFont('Times', 'I', 10);
            $pdf->Cell(176, $summaryIntervalY, $sFundSummary, 0, 0, 'R');
            $curY += 1.75 * $summaryIntervalY;
            $page = $pdf->pageBreak($page);
        }
        // Print Deposit Summary
        if ($countDeposit > 1) {
            $item = gettext('items');
        } else {
            $item = gettext('item');
        }
        $sDepositSummary = "Deposit #$currentDepositID Total - $countDeposit $item:   $" . number_format($currentDepositAmount, 2, '.', ',');
        $pdf->SetXY(20, $curY);
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(176, $summaryIntervalY, $sDepositSummary, 0, 0, 'R');
        $curY += 2 * $summaryIntervalY;
        $page = $pdf->pageBreak($page);
    } elseif ($sort === 'fund') {
        // Sort by Fund  Report
        if ($detail_level === 'detail') {
            $curY = $pdf->headings($curY);
        }

        foreach ($rsReport as $aRow) {
            $fun_ID = isset($aRow['fun_ID']) ? $aRow['fun_ID'] : null;
            $fun_Name = isset($aRow['fun_Name']) ? $aRow['fun_Name'] : null;
            $fam_ID = isset($aRow['fam_ID']) ? $aRow['fam_ID'] : null;
            $fam_Name = isset($aRow['fam_Name']) ? $aRow['fam_Name'] : null;
            $fam_Address1 = isset($aRow['fam_Address1']) ? $aRow['fam_Address1'] : null;
            $plg_depID = isset($aRow['plg_depID']) ? $aRow['plg_depID'] : null;
            $plg_amount = isset($aRow['plg_amount']) ? $aRow['plg_amount'] : null;
            $plg_method = isset($aRow['plg_method']) ? $aRow['plg_method'] : null;
            $plg_comment = isset($aRow['plg_comment']) ? $aRow['plg_comment'] : null;
            $plg_CheckNo = isset($aRow['plg_CheckNo']) ? $aRow['plg_CheckNo'] : null;
            $dep_Date = isset($aRow['dep_Date']) ? $aRow['dep_Date'] : null;
            
            if (!$fun_ID) {
                $fun_ID = -1;
                $fun_Name = 'Undesignated';
            }
            if (!$fam_ID) {
                $fam_ID = -1;
                $fam_Name = 'Unassigned';
            }
            // First Fund Heading
            if (!$currentFundName && $detail_level != 'summary') {
                $sFundTitle = "Fund: $fun_Name";
                $pdf->SetFont('Times', 'B', 10);
                $pdf->writeAt(20, $curY, $sFundTitle);
                $curY += 1.5 * $summaryIntervalY;
            }
            // Check for new Family
            if (($currentFamilyID != $fam_ID || $currentFundID != $fun_ID) && $currentFamilyID && $detail_level != 'summary') {
                // New Family. Print Previous Family Summary
                if ($countFamily > 1) {
                    $item = gettext('items');
                } else {
                    $item = gettext('item');
                }
                $sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ',');
                $curY += 2;
                $pdf->SetXY(20, $curY);
                $pdf->SetFont('Times', 'I', 10);
                $pdf->Cell(176, $summaryIntervalY, $sFamilySummary, 0, 0, 'R');
                $curY += 1.75 * $summaryIntervalY;
                $countFamily = 0;
                $currentFamilyAmount = 0;
                $page = $pdf->pageBreak($page);
            }
            // Check for new Fund
            if ($currentFundID != $fun_ID && $currentFundID) {
                // New Fund ID.  Print Previous Fund Summary
                if ($countFund > 1) {
                    $item = gettext('items');
                } else {
                    $item = gettext('item');
                }
                $sFundSummary = "$currentFundName Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
                $pdf->SetXY(20, $curY);
                $pdf->SetFont('Times', 'B', 10);
                $pdf->Cell(176, $summaryIntervalY, $sFundSummary, 0, 0, 'R');
                $curY += 2 * $summaryIntervalY;
                if ($detail_level != 'summary') {
                    $pdf->line(40, $curY - 2, 195, $curY - 2);
                }
                $page = $pdf->pageBreak($page);

                // New Fund Title
                if ($detail_level != 'summary') {
                    $sFundTitle = "Fund: $fun_Name";
                    $pdf->SetFont('Times', 'B', 10);
                    $pdf->writeAt(20, $curY, $sFundTitle);
                    $curY += 1.5 * $summaryIntervalY;
                }
                $countFund = 0;
                $currentFundAmount = 0;
            }

            // Print Deposit Detail
            if ($detail_level === 'detail') {
                // Format Data
                if ($plg_method === 'CREDITCARD') {
                    $plg_method = 'CREDIT';
                }
                if ($plg_method === 'BANKDRAFT') {
                    $plg_method = 'DRAFT';
                }
                if ($plg_method != 'CHECK') {
                    $plg_CheckNo = $plg_method;
                }
                if (strlen($plg_CheckNo) > 8) {
                    $plg_CheckNo = '...' . mb_substr($plg_CheckNo, -8, 8);
                }
                $sDeposit = "Dep #$plg_depID $dep_Date";
                if (strlen($sDeposit) > 22) {
                    $sDeposit = mb_substr($sDeposit, 0, 21) . '...';
                }
                if (strlen($plg_comment) > 29) {
                    $plg_comment = mb_substr($plg_comment, 0, 28) . '...';
                }
                $fam_Name = $fam_Name . ' - ' . $fam_Address1;
                if (strlen($fam_Name) > 31) {
                    $fam_Name = mb_substr($fam_Name, 0, 30) . '...';
                }

                // Print Data
                $pdf->SetFont('Times', '', 10);
                $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
                $pdf->Cell(16, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R');
                $pdf->Cell(40, $summaryIntervalY, $sDeposit);
                $pdf->Cell(55, $summaryIntervalY, $fam_Name);
                $pdf->Cell(40, $summaryIntervalY, $plg_comment);
                $pdf->SetFont('Courier', '', 9);
                $pdf->Cell(25, $summaryIntervalY, $plg_amount, 0, 0, 'R');
                $pdf->SetFont('Times', '', 10);
                $curY += $summaryIntervalY;
                $page = $pdf->pageBreak($page);
            }
            // Update running totals
            $totalAmount += $plg_amount;
            if (array_key_exists($fun_Name, $totalFund)) {
                $totalFund[$fun_Name] += $plg_amount;
            } else {
                $totalFund[$fun_Name] = $plg_amount;
            }
            $countFund++;
            $countFamily++;
            $countReport++;
            $currentFundAmount += $plg_amount;
            $currentFamilyAmount += $plg_amount;
            $currentReportAmount += $plg_amount;
            $currentFamilyID = $fam_ID;
            $currentFamilyName = $fam_Name;
            $currentFundID = $fun_ID;
            $currentFundName = $fun_Name;
            $currentFamilyAddress = $fam_Address1;
        }

        // Print Final Summary
        // Print Family Summary
        if ($detail_level != 'summary') {
            if ($countFamily > 1) {
                $item = gettext('items');
            } else {
                $item = gettext('item');
            }
            $sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ',');
            $curY += 2;
            $pdf->SetXY(20, $curY);
            $pdf->SetFont('Times', 'I', 10);
            $pdf->Cell(176, $summaryIntervalY, $sFamilySummary, 0, 0, 'R');
            $curY += 1.75 * $summaryIntervalY;
            $page = $pdf->pageBreak($page);
        }
        // Print Fund Summary
        if ($countFund > 1) {
            $item = gettext('items');
        } else {
            $item = gettext('item');
        }
        $sFundSummary = "$currentFundName Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
        $pdf->SetXY(20, $curY);
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(176, $summaryIntervalY, $sFundSummary, 0, 0, 'R');
        $curY += 2 * $summaryIntervalY;
        if ($detail_level != 'summary') {
            $pdf->line(40, $curY - 2, 195, $curY - 2);
        }
        $page = $pdf->pageBreak($page);
    } elseif ($sort === 'family') {
        // Sort by Family  Report
        foreach ($rsReport as $aRow) {
            $fun_ID = isset($aRow['fun_ID']) ? $aRow['fun_ID'] : null;
            $fun_Name = isset($aRow['fun_Name']) ? $aRow['fun_Name'] : null;
            $fam_ID = isset($aRow['fam_ID']) ? $aRow['fam_ID'] : null;
            $fam_Name = isset($aRow['fam_Name']) ? $aRow['fam_Name'] : null;
            $fam_Address1 = isset($aRow['fam_Address1']) ? $aRow['fam_Address1'] : null;
            $plg_depID = isset($aRow['plg_depID']) ? $aRow['plg_depID'] : null;
            $plg_amount = isset($aRow['plg_amount']) ? $aRow['plg_amount'] : null;
            $plg_method = isset($aRow['plg_method']) ? $aRow['plg_method'] : null;
            $plg_comment = isset($aRow['plg_comment']) ? $aRow['plg_comment'] : null;
            $plg_CheckNo = isset($aRow['plg_CheckNo']) ? $aRow['plg_CheckNo'] : null;
            $dep_Date = isset($aRow['dep_Date']) ? $aRow['dep_Date'] : null;
            
            if (!$fun_ID) {
                $fun_ID = -1;
                $fun_Name = 'Undesignated';
            }
            if (!$fam_ID) {
                $fam_ID = -1;
                $fam_Name = 'Unassigned';
                $fam_Address1 = '';
            }
            // First Family Heading
            if (!$currentFamilyID && $detail_level != 'summary') {
                $sFamilyTitle = "$fam_Name - $fam_Address1";
                $pdf->SetFont('Times', 'B', 10);
                $pdf->writeAt(20, $curY, $sFamilyTitle);
                $curY += 1.5 * $summaryIntervalY;
            }
            // Check for new Fund
            if (($currentFundID != $fun_ID || $currentFamilyID != $fam_ID) && $currentFundID && $detail_level != 'summary') {
                // New Fund. Print Previous Fund Summary
                if ($countFund > 1) {
                    $item = gettext('items');
                } else {
                    $item = gettext('item');
                }
                $sFundSummary = "$currentFundName - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
                $curY += 2;
                $pdf->SetXY(20, $curY);
                $pdf->SetFont('Times', 'I', 10);
                $pdf->Cell(176, $summaryIntervalY, $sFundSummary, 0, 0, 'R');
                $curY += 1.75 * $summaryIntervalY;
                $countFund = 0;
                $currentFundAmount = 0;
                $page = $pdf->pageBreak($page);
            }
            // Check for new Family
            if ($currentFamilyID != $fam_ID && $currentFamilyID) {
                // New Family.  Print Previous Family Summary
                if ($countFamily > 1) {
                    $item = gettext('items');
                } else {
                    $item = gettext('item');
                }
                $sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ',');
                $pdf->SetXY(20, $curY);
                $pdf->SetFont('Times', 'B', 10);
                $pdf->Cell(176, $summaryIntervalY, $sFamilySummary, 0, 0, 'R');
                $curY += 2 * $summaryIntervalY;
                if ($detail_level != 'summary') {
                    $pdf->line(40, $curY - 2, 195, $curY - 2);
                }
                $page = $pdf->pageBreak($page);

                // New Family Title
                if ($detail_level != 'summary') {
                    $sFamilyTitle = "$fam_Name - $fam_Address1";
                    $pdf->SetFont('Times', 'B', 10);
                    $pdf->writeAt(20, $curY, $sFamilyTitle);
                    $curY += 1.5 * $summaryIntervalY;
                }
                $countFamily = 0;
                $currentFamilyAmount = 0;
            }

            // Print Deposit Detail
            if ($detail_level === 'detail') {
                // Format Data
                if ($plg_method === 'CREDITCARD') {
                    $plg_method = 'CREDIT';
                }
                if ($plg_method === 'BANKDRAFT') {
                    $plg_method = 'DRAFT';
                }
                if ($plg_method != 'CHECK') {
                    $plg_CheckNo = $plg_method;
                }
                if (strlen($plg_CheckNo) > 8) {
                    $plg_CheckNo = '...' . mb_substr($plg_CheckNo, -8, 8);
                }
                $sDeposit = "Dep #$plg_depID $dep_Date";
                if (strlen($sDeposit) > 22) {
                    $sDeposit = mb_substr($sDeposit, 0, 21) . '...';
                }
                if (strlen($plg_comment) > 29) {
                    $plg_comment = mb_substr($plg_comment, 0, 28) . '...';
                }
                $sFundName = $fun_Name;
                if (strlen($sFundName) > 31) {
                    $sFundName = mb_substr($sFundName, 0, 30) . '...';
                }

                // Print Data
                $pdf->SetFont('Times', '', 10);
                $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
                $pdf->Cell(16, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R');
                $pdf->Cell(40, $summaryIntervalY, $sDeposit);
                $pdf->Cell(55, $summaryIntervalY, $sFundName);
                $pdf->Cell(40, $summaryIntervalY, $plg_comment);
                $pdf->SetFont('Courier', '', 9);
                $pdf->Cell(25, $summaryIntervalY, $plg_amount, 0, 0, 'R');
                $pdf->SetFont('Times', '', 10);
                $curY += $summaryIntervalY;
                $page = $pdf->pageBreak($page);
            }
            // Update running totals
            $totalAmount += $plg_amount;
            if (array_key_exists($fun_Name, $totalFund)) {
                $totalFund[$fun_Name] += $plg_amount;
            } else {
                $totalFund[$fun_Name] = $plg_amount;
            }
            $countFund++;
            $countFamily++;
            $countReport++;
            $currentFundAmount += $plg_amount;
            $currentFamilyAmount += $plg_amount;
            $currentReportAmount += $plg_amount;
            $currentFamilyID = $fam_ID;
            $currentFamilyName = $fam_Name;
            $currentFundID = $fun_ID;
            $currentFundName = $fun_Name;
            $currentFamilyAddress = $fam_Address1;
        }

        // Print Final Summary
        // Print Fund Summary
        if ($detail_level != 'summary') {
            if ($countFund > 1) {
                $item = gettext('items');
            } else {
                $item = gettext('item');
            }
            $sFundSummary = "$currentFundName - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
            $curY += 2;
            $pdf->SetXY(20, $curY);
            $pdf->SetFont('Times', 'I', 10);
            $pdf->Cell(176, $summaryIntervalY, $sFundSummary, 0, 0, 'R');
            $curY += 1.75 * $summaryIntervalY;
            $page = $pdf->pageBreak($page);
        }
        // Print Family Summary
        if ($countFamily > 1) {
            $item = gettext('items');
        } else {
            $item = gettext('item');
        }
        $sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ',');
        $pdf->SetXY(20, $curY);
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(176, $summaryIntervalY, $sFamilySummary, 0, 0, 'R');
        $curY += 2 * $summaryIntervalY;
        if ($detail_level != 'summary') {
            $pdf->line(40, $curY - 2, 195, $curY - 2);
        }
        $page = $pdf->pageBreak($page);
    }

    // Print Report Summary
    if ($countReport > 1) {
        $item = gettext('items');
    } else {
        $item = gettext('item');
    }
    $sReportSummary = "Report Total ($countReport $item):   $" . number_format($currentReportAmount, 2, '.', ',');
    $pdf->SetXY(20, $curY);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(176, $summaryIntervalY, $sReportSummary, 0, 0, 'R');
    $pdf->line(40, $curY - 2, 195, $curY - 2);
    $curY += 2.5 * $summaryIntervalY;
    $page = $pdf->pageBreak($page);

    // Print Fund Totals
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetXY($curX, $curY);
    $pdf->writeAt(20, $curY, 'Deposit totals by fund');
    $pdf->SetFont('Courier', '', 10);
    $curY += 1.5 * $summaryIntervalY;
    ksort($totalFund);
    reset($totalFund);
    while ($FundTotal = current($totalFund)) {
        if (strlen(key($totalFund) > 22)) {
            $sfun_Name = mb_substr(key($totalFund), 0, 21) . '...';
        } else {
            $sfun_Name = key($totalFund);
        }
        $pdf->SetXY(20, $curY);
        $pdf->Cell(45, $summaryIntervalY, $sfun_Name);
        $pdf->Cell(25, $summaryIntervalY, number_format($FundTotal, 2, '.', ','), 0, 0, 'R');
        $curY += $summaryIntervalY;
        $page = $pdf->pageBreak($page);
        next($totalFund);
    }

    $pdf->finishPage($page);
    $pdf->Output('DepositReport-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');

    // Output a text file
    // ##################
    } elseif ($output === 'csv') {
    // Extract headers from rsReport array
    $headers = [];
    if (!empty($rsReport) && is_array($rsReport[0])) {
        $headers = array_keys($rsReport[0]);
    }

    // Convert array to 2D row format
    $rows = [];
    foreach ($rsReport as $record) {
        $rows[] = array_values($record);
    }

    // Only export if we have headers and rows
    if (!empty($headers) && !empty($rows)) {
        // Export using CsvExporter
        // basename: 'AdvancedDepositReport', includeDateInFilename: true adds today's date, .csv is added automatically
        CsvExporter::create($headers, $rows, 'AdvancedDepositReport', 'UTF-8', true);
    } else {
        $params = [
            'ReturnMessage' => 'NoRows',
            'ReportType' => 'Advanced Deposit Report',
            'DateStart' => $sDateStart,
            'DateEnd'   => $sDateEnd,
            'datetype'  => $datetype,
        ];
        header('Location: ../FinancialReports.php?' . http_build_query($params));
    }
}
