<?php

/*******************************************************************************
*
*  filename    : Reports/VotingMembers.php
*  last change : 2005-03-26
*  description : Creates a PDF with names of voting members for a particular fiscal year

******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;

//Get the Fiscal Year ID out of the querystring
$iFYID = InputUtils::legacyFilterInput($_POST['FYID'], 'int');
if (!$iFYID) {
    $iFYID = CurrentFY();
}
$_SESSION['idefaultFY'] = $iFYID; // Remember the chosen FYID
$iRequireDonationYears = InputUtils::legacyFilterInput($_POST['RequireDonationYears'], 'int');
$output = InputUtils::legacyFilterInput($_POST['output']);

class PdfVotingMembers extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->SetFont('Times', '', 10);
        $this->SetMargins(20, 20);

        $this->SetAutoPageBreak(false);
        $this->addPage();
    }
}

$pdf = new PdfVotingMembers();

$topY = 10;
$curY = $topY;

$pdf->writeAt(
    SystemConfig::getValue('leftX'),
    $curY,
    gettext('Voting members ') . MakeFYString($iFYID)
);
$curY += 10;

$votingMemberCount = 0;

// Get all the families
$sSQL = 'SELECT fam_ID, fam_Name FROM family_fam WHERE 1 ORDER BY fam_Name';
$rsFamilies = RunQuery($sSQL);

// Loop through families
while ($aFam = mysqli_fetch_array($rsFamilies)) {
    extract($aFam);

    // Get pledge date ranges
    $donation = 'no';
    if ($iRequireDonationYears > 0) {
        $startdate = $iFYID + 1995 - $iRequireDonationYears;
        $startdate .= '-' . SystemConfig::getValue('iFYMonth') . '-' . '01';
        $enddate = $iFYID + 1995 + 1;
        $enddate .= '-' . SystemConfig::getValue('iFYMonth') . '-' . '01';

        // Get payments only
        $sSQL = 'SELECT COUNT(plg_plgID) AS count FROM pledge_plg
			WHERE plg_FamID = ' . $fam_ID . " AND plg_PledgeOrPayment = 'Payment' AND
				 plg_date >= '$startdate' AND plg_date < '$enddate'";
        $rsPledges = RunQuery($sSQL);
        [$count] = mysqli_fetch_row($rsPledges);
        if ($count > 0) {
            $donation = 'yes';
        }
    }

    if (($iRequireDonationYears == 0) || $donation === 'yes') {
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Name);

        //Get the family members for this family
        $sSQL = 'SELECT per_FirstName, per_LastName, cls.lst_OptionName AS sClassName
				FROM person_per
				INNER JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				WHERE per_fam_ID = ' . $fam_ID . " AND cls.lst_OptionName='" . gettext('Member') . "'";

        $rsFamilyMembers = RunQuery($sSQL);

        if (mysqli_num_rows($rsFamilyMembers) == 0) {
            $curY += 5;
        }

        while ($aMember = mysqli_fetch_array($rsFamilyMembers)) {
            extract($aMember);
            $pdf->writeAt(SystemConfig::getValue('leftX') + 30, $curY, $per_FirstName . ' ' . $per_LastName);
            $curY += 5;
            if ($curY > 245) {
                $pdf->addPage();
                $curY = $topY;
            }
            $votingMemberCount += 1;
        }
        if ($curY > 245) {
            $pdf->addPage();
            $curY = $topY;
        }
    }
}

$curY += 5;
$pdf->writeAt(SystemConfig::getValue('leftX'), $curY, 'Number of Voting Members: ' . $votingMemberCount);

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('VotingMembers' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
