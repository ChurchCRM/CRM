<?php
/*******************************************************************************
*
*  filename    : Reports/NameTags.php
*  last change : 2012-06-26
*  description : Creates a PDF with name tags

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PDF_Label;
use ChurchCRM\Utils\InputUtils;

$sLabelFormat = InputUtils::LegacyFilterInput($_GET['labeltype']);
setcookie('labeltype', $sLabelFormat, time() + 60 * 60 * 24 * 90, '/');

$pdf = new PDF_Label($sLabelFormat);

$sFontInfo = FontFromName($_GET['labelfont']);
setcookie('labelfont', $_GET['labelfont'], time() + 60 * 60 * 24 * 90, '/');
$sFontSize = $_GET['labelfontsize'];
setcookie('labelfontsize', $sFontSize, time() + 60 * 60 * 24 * 90, '/');
$pdf->SetFont($sFontInfo[0], $sFontInfo[1]);

if ($sFontSize != 'default') {
    $pdf->Set_Char_Size($sFontSize);
}
//if($sFontSize != "default")
//	$pdf->SetFontSize($sFontSize);

$sSQL = 'SELECT * FROM person_per WHERE per_ID IN ('.ConvertCartToString($_SESSION['aPeopleCart']).') ORDER BY per_LastName';
$rsPersons = RunQuery($sSQL);

while ($aPer = mysqli_fetch_array($rsPersons)) {
    extract($aPer);

    $PosX = $pdf->_Margin_Left + ($pdf->_COUNTX * ($pdf->_Width + $pdf->_X_Space));
    $PosY = $pdf->_Margin_Top + ($pdf->_COUNTY * ($pdf->_Height + $pdf->_Y_Space));

    $perimg = '../Images/Person/'.$per_ID.'.jpg';
    if (file_exists($perimg)) {
        $s = getimagesize($perimg);
        $h = ($pdf->_Width / $s[0]) * $s[1];
        if ($h > $pdf->_Height) {
            $useWidth = $pdf->_Width * $pdf->_Height / $h;
        } else {
            $useWidth = $pdf->_Width;
        }

        $pdf->Image($perimg, $PosX, $PosY, $useWidth);

        $labelStr = sprintf("%s\n%s\n\n%d", $per_FirstName, $per_LastName, $per_ID);

        $firstWid = $pdf->GetStringWidth($per_FirstName);
        $lastWid = $pdf->GetStringWidth($per_LastName);
        $maxWid = max($firstWid, $lastWid);
        $useWid = $pdf->_Width / 2 - 2;

        if ($maxWid > $useWid) {
            $useFontSize = (int) ($sFontSize * $useWid / $maxWid);
            $pdf->Set_Char_Size($useFontSize);
        }

        $pdf->SetXY($PosX + $pdf->_Width / 2, $PosY + 3);
        $pdf->MultiCell($pdf->_Width / 2, $pdf->_Line_Height, iconv('UTF-8', 'ISO-8859-1', $labelStr));
        $pdf->Set_Char_Size($sFontSize);
        $pdf->Add_PDF_Label('');
    } else {
        $labelStr = sprintf("%s %s\n\n%d", $per_FirstName, $per_LastName, $per_ID);
        $nameWid = $pdf->GetStringWidth($per_FirstName.' '.$per_LastName);
        $useWid = $pdf->_Width - 2;
        if ($nameWid > $useWid) {
            $useFontSize = (int) ($sFontSize * $useWid / $nameWid);
            $pdf->Set_Char_Size($useFontSize);
        }
        $pdf->Add_PDF_Label($labelStr);
        $pdf->Set_Char_Size($sFontSize);
    }
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('NameTags'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
