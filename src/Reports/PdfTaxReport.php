<?php

namespace ChurchCRM\Reports;

use ChurchCRM\dto\SystemConfig;

/**
 * PDF report class for generating giving/tax statements.
 * Used by TaxReport.php (bulk run) and FamilyTaxReportEmail.php (single-family email).
 */
class PdfTaxReport extends ChurchInfoReport
{
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(20, 20);
        $this->SetAutoPageBreak(false);
    }

    public function startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country, string $fam_envelope): float
    {
        global $letterhead, $sDateStart, $sDateEnd, $iDepID;
        $curY = $this->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);
        if (SystemConfig::getValue('bUseDonationEnvelopes')) {
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Envelope') . ': ' . $fam_envelope);
            $curY += SystemConfig::getValue('incrementY');
        }
        $curY += 2 * SystemConfig::getValue('incrementY');
        if ($iDepID) {
            $sSQL = "SELECT dep_Date, dep_Date FROM deposit_dep WHERE dep_ID='$iDepID'";
            $rsDep = RunQuery($sSQL);
            [$sDateStart, $sDateEnd] = mysqli_fetch_row($rsDep);
        }
        if ($sDateStart == $sDateEnd) {
            $DateString = date('F j, Y', strtotime($sDateStart));
        } else {
            $DateString = date('M j, Y', strtotime($sDateStart)) . ' - ' . date('M j, Y', strtotime($sDateEnd));
        }
        $blurb = SystemConfig::getValue('sTaxReport1') . ' ' . $DateString . '.';
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);

        return $curY + 2 * SystemConfig::getValue('incrementY');
    }

    public function finishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country): void
    {
        global $remittance;
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxReport2'));
        $curY += 3 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxReport3'));
        $curY += 3 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
        $curY += 4 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxSigner'));

        if ($remittance === 'yes') {
            // Add remittance slip
            $curX = 60;
            $curY = 194;
            $this->writeAt($curX, $curY, gettext('Please detach this slip and mail with your next gift.'));
            $curY += (1.5 * SystemConfig::getValue('incrementY'));
            $church_mailing = gettext('Please mail your next gift to ') . SystemConfig::getValue('sChurchName') . ', '
                . SystemConfig::getValue('sChurchAddress') . ', ' . SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  '
                . SystemConfig::getValue('sChurchZip') . ', ' . gettext('Phone') . ': ' . SystemConfig::getValue('sChurchPhone');
            $this->SetFont('Times', 'I', 10);
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $church_mailing);
            $this->SetFont('Times', '', 10);
            $curY = 215;
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $this->makeSalutation($fam_ID));
            $curY += SystemConfig::getValue('incrementY');
            if ($fam_Address1 != '') {
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Address1);
                $curY += SystemConfig::getValue('incrementY');
            }
            if ($fam_Address2 != '') {
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Address2);
                $curY += SystemConfig::getValue('incrementY');
            }
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_City . ', ' . $fam_State . '  ' . $fam_Zip);
            $curY += SystemConfig::getValue('incrementY');
            if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Country);
                $curY += SystemConfig::getValue('incrementY');
            }
            $curY = 246;
            $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchName'));
            $curY += SystemConfig::getValue('incrementY');
            if (SystemConfig::getValue('sChurchAddress') != '') {
                $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchAddress'));
                $curY += SystemConfig::getValue('incrementY');
            }
            $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  ' . SystemConfig::getValue('sChurchZip'));
            $curY += SystemConfig::getValue('incrementY');
            if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
                $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, $fam_Country);
                $curY += SystemConfig::getValue('incrementY');
            }
            $curX = 100;
            $curY = 215;
            $this->writeAt($curX, $curY, gettext('Gift Amount') . ':');
            $this->writeAt($curX + 25, $curY, '_______________________________');
            $curY += (2 * SystemConfig::getValue('incrementY'));
            $this->writeAt($curX, $curY, gettext('Gift Designation') . ':');
            $this->writeAt($curX + 25, $curY, '_______________________________');
        }
    }
}
