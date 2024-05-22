<?php

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\RedirectUtils;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::redirect('v2/dashboard');
}

class PdfEnvelopeReport extends ChurchInfoReport
{
    // Private properties
    public $_Margin_Left = 12;
    public $_Margin_Top = 12;
    public $_Char_Size = 12;
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

    public function checkLines(int $numlines): void
    {
        // Temporarily store off the position
        $CurY = $this->GetY();

        // Need to determine if we will extend beyond 17mm from the bottom of
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
        // Put the position back
        $this->SetY($CurY);
    }

    // This function formats the string for a family
    public function sGetFamilyString(Family $family): string
    {
        return $family->getEnvelope() . ' ' . $this->makeSalutation($family->getId());
    }

    // Number of lines is only for the $text parameter
    public function addRecord($text, $numlines): void
    {
        // Add an extra blank line after record
        $numlines++;
        $this->checkLines($numlines);

        $_PosX = $this->_Margin_Left + ($this->_Column * 108);
        $_PosY = $this->_Margin_Top + ($this->_CurLine * 5);
        $this->SetXY($_PosX, $_PosY);
        // Set width to 0 prints to right margin
        $this->MultiCell(0, 5, $text);
        $this->_CurLine += $numlines;
    }
}

// Instantiate the directory class and build the report.
$pdf = new PdfEnvelopeReport();

$families = FamilyQuery::Create()->orderByEnvelope()->filterByEnvelope(0, 'Criteria::GREATER_THAN')->find();

foreach ($families as $family) {
    $OutStr = '';
    $OutStr = $pdf->sGetFamilyString($family);

    // Count the number of lines in the output string
    if (strlen($OutStr)) {
        $numlines = mb_substr_count($OutStr, "\n");
    } else {
        $numlines = 0;
    }

    $pdf->addRecord($OutStr, $numlines);
}

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output('EnvelopeAssignments-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
