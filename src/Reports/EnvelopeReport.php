<?php

/*******************************************************************************
*
*  filename    : Reports/EnvelopeReport.php
*  description : Creates a report showing all envelope assignments

******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

class PdfEnvelopeReport extends ChurchInfoReport
{
    // Private properties
    public $_Margin_Left = 12;         // Left Margin
    public $_Margin_Top = 12;         // Top margin
    public $_Char_Size = 12;        // Character size
    public $_CurLine = 2;
    public $_Column = 0;
    public $_Font = 'Times';
    public $sFamily;
    public $sLastName;

    // Sets the character size
    // This changes the line height too
    public function setCharSize($pt): void
    {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->SetFont($this->_Font, '', $this->_Char_Size);
        }
    }

    // Constructor
    public function __construct()
    {
        global $paperFormat;
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetMargins(0, 0);

        $this->setCharSize(12);
        $this->addPage();
        $this->SetAutoPageBreak(false);

        $this->setCharSize(20);
        $this->writeAt(12, 12, 'Envelope Numbers for all Families');
        $this->setCharSize(12);
    }

    public function checkLines($numlines): void
    {
        $CurY = $this->GetY();  // Temporarily store off the position

        // Need to determine if we will extend beyoned 17mm from the bottom of
        // the page.
        $this->SetY(-17);
        if ($this->_Margin_Top + (($this->_CurLine + $numlines) * 5) > $this->GetY()) {
            // Next Column or Page
            if ($this->_Column == 1) {
                $this->_Column = 0;
                $this->_CurLine = 2;
                $this->addPage();
            } else {
                $this->_Column = 1;
                $this->_CurLine = 2;
            }
        }
        $this->SetY($CurY); // Put the position back
    }

    // This function formats the string for a family
    public function sGetFamilyString($aRow): string
    {
        extract($aRow); // Get a row from family_fam

        return $fam_Envelope . ' ' . $this->makeSalutation($fam_ID);
    }

    // Number of lines is only for the $text parameter
    public function addRecord($text, $numlines): void
    {
        $numlines++; // add an extra blank line after record
        $this->checkLines($numlines);

        $_PosX = $this->_Margin_Left + ($this->_Column * 108);
        $_PosY = $this->_Margin_Top + ($this->_CurLine * 5);
        $this->SetXY($_PosX, $_PosY);
        $this->MultiCell(0, 5, $text); // set width to 0 prints to right margin
        $this->_CurLine += $numlines;
    }
}

// Instantiate the directory class and build the report.
$pdf = new PdfEnvelopeReport();

$sSQL = 'SELECT fam_ID, fam_Envelope FROM family_fam WHERE fam_Envelope>0 ORDER BY fam_Envelope';
$rsRecords = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsRecords)) {
    $OutStr = '';
    extract($aRow);

    $OutStr = $pdf->sGetFamilyString($aRow);

    // Count the number of lines in the output string
    if (strlen($OutStr)) {
        $numlines = mb_substr_count($OutStr, "\n");
    } else {
        $numlines = 0;
    }

    $pdf->addRecord($OutStr, $numlines);
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('EnvelopeAssignments-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
