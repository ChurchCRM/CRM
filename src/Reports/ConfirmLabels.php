<?php

/*******************************************************************************
*
*  filename    : Reports/ConfimLabels.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the mailing labels for the confirm data letter

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Reports\PdfLabel;
use ChurchCRM\Utils\InputUtils;

$sLabelFormat = InputUtils::legacyFilterInput($_GET['labeltype']);
$bRecipientNamingMethod = $_GET['recipientnamingmethod'];
setcookie('labeltype', $sLabelFormat, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

$pdf = new PdfLabel($sLabelFormat);

$sFontInfo = FontFromName($_GET['labelfont']);
setcookie('labelfont', $_GET['labelfont'], ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);
$sFontSize = $_GET['labelfontsize'];
setcookie('labelfontsize', $sFontSize, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);
$pdf->SetFont($sFontInfo[0], $sFontInfo[1]);
if ($sFontSize != 'default') {
    $pdf->setCharSize($sFontSize);
}

// Get all the families which receive the newsletter by mail
$families = FamilyQuery::create()
        ->orderByZip()
        ->find();

foreach ($families as $family) {
    if ($bRecipientNamingMethod === 'familyname') {
        $labelText = $family->getName();
    } else {
        $labelText = $pdf->makeSalutation($family->getID());
    }
    if ($family->getAddress1() != '') {
        $labelText .= "\n" . $family->getAddress1();
    }
    if ($family->getAddress2() != '') {
        $labelText .= "\n" . $family->getAddress2();
    }
    $labelText .= sprintf("\n%s, %s  %s", $family->getCity(), $family->getState(), $family->getZip());

    if ($family->getCountry() != '' && $family->getCountry() != 'USA' && $family->getCountry() != 'United States') {
        $labelText .= "\n" . $family->getCountry();
    }

    $pdf->addPdfLabel($labelText);
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ConfirmDataLabels' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
