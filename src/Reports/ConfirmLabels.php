<?php

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Reports\PdfLabel;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

$sLabelFormat = InputUtils::legacyFilterInput($_GET['labeltype']);
$bRecipientNamingMethod = $_GET['recipientnamingmethod'];
setcookie('labeltype', $sLabelFormat, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

$pdf = new PdfLabel($sLabelFormat);

$sFontInfo = MiscUtils::fontFromName($_GET['labelfont']);
setcookie('labelfont', $_GET['labelfont'], ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);
$sFontSize = $_GET['labelfontsize'];
setcookie('labelfontsize', $sFontSize, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);
$pdf->SetFont($sFontInfo[0], $sFontInfo[1]);
if ($sFontSize != 'default') {
    $pdf->setCharSize($sFontSize);
}

// Get every family. The SQL sort is on the primary ZIP; sortByMailingZip() re-sorts
// on the ZIP actually printed, which only differs for families whose second address
// is flagged as the mailing address.
$families = Family::sortByMailingZip(
    FamilyQuery::create()
        ->orderByZip()
        ->find()
);

foreach ($families as $family) {
    if ($bRecipientNamingMethod === 'familyname') {
        $labelText = $family->getName();
    } else {
        $labelText = $pdf->makeSalutation($family->getID());
    }

    $addressBlock = $family->getMailingAddressLines();
    if ($addressBlock !== '') {
        $labelText .= "\n" . $addressBlock;
    }

    $pdf->addPdfLabel($labelText);
}

if (SystemConfig::getIntValue('iPDFOutputType') === 1) {
    $pdf->Output('ConfirmDataLabels' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
