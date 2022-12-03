<?php
/*******************************************************************************
*
*  filename    : Reports/EnvelopeReport.php
*  description : Creates a report showing all envelope assignments

******************************************************************************/
require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

class PDF_EnvelopeReport extends ChurchInfoReport
{
    // Private properties
    public $_Margin_Left = 0;         // Left Margin
    public $_Margin_Top = 0;         // Top margin
    public $_Char_Size = 12;        // Character size
    public $_CurLine = 0;
    public $_Column = 0;
    public $_Font = 'Times';
    public $sFamily;
    public $sLastName;

    // Sets the character size
    // This changes the line height too
    public function Set_Char_Size($pt)
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

        $this->_Column = 0;
        $this->_CurLine = 2;
        $this->_Font = 'Times';
        $this->SetMargins(0, 0);

        $this->Set_Char_Size(12);
        $this->AddPage();
        $this->SetAutoPageBreak(false);

        $this->_Margin_Left = 12;
        $this->_Margin_Top = 12;

        $this->incrementY = 10;

        $this->Set_Char_Size(20);
        $this->WriteAt(12, 12, 'Envelope Numbers for all Families');
        $this->Set_Char_Size(12);
    }

    public function Check_Lines($numlines)
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
                $this->AddPage();
            } else {
                $this->_Column = 1;
                $this->_CurLine = 2;
            }
        }
        $this->SetY($CurY); // Put the position back
    }

    // This function formats the string for a family
    public function sGetFamilyString($aRow)
    {
        extract($aRow); // Get a row from family_fam

        return $fam_Envelope.' '.$this->MakeSalutation($fam_ID);
    }

    // Number of lines is only for the $text parameter
    public function Add_Record($text, $numlines)
    {
        $numlines++; // add an extra blank line after record
        $this->Check_Lines($numlines);

        $_PosX = $this->_Margin_Left + ($this->_Column * 108);
        $_PosY = $this->_Margin_Top + ($this->_CurLine * 5);
        $this->SetXY($_PosX, $_PosY);
        $this->MultiCell(0, 5, $text); // set width to 0 prints to right margin
        $this->_CurLine += $numlines;
    }
}

// Instantiate the directory class and build the report.
$pdf = new PDF_EnvelopeReport();

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

    $pdf->Add_Record($OutStr, $numlines);
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('EnvelopeAssingments-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
