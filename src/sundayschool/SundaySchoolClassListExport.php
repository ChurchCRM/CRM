<?php

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;

function translate_Win_1252($string)
{
    if ($string == "" || $string == null) {
        return "";
    }
        
    return iconv('UTF-8', 'Windows-1252', html_entity_decode(gettext($string), ENT_COMPAT, 'UTF-8'));
}


header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Description: File Transfer');
header('Content-Type: text/csv;charset=UTF-8');
header('Content-Disposition: attachment; filename=SundaySchool-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');
header('Content-Transfer-Encoding: binary');

$lang = substr($localeInfo->getLocale(), 0, 2);

if ($lang == "fr") {
    $delimitor = ";";
} else {
    $delimitor = ",";
}

$out = fopen('php://output', 'w');

//add BOM to fix UTF-8 in Excel
//fputs($out, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

// Get all the groups
$sSQL = 'select grp.grp_Name sundayschoolClass, kid.per_ID kidId, kid.per_FirstName firstName, kid.per_LastName LastName, kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear, kid.per_CellPhone mobilePhone,
fam.fam_HomePhone homePhone,
dad.per_FirstName dadFirstName, dad.per_LastName dadLastName, dad.per_CellPhone dadCellPhone, dad.per_Email dadEmail,
mom.per_FirstName momFirstName, mom.per_LastName momLastName, mom.per_CellPhone momCellPhone, mom.per_Email momEmail,
fam.fam_Email famEmail, fam.fam_Address1 Address1, fam.fam_Address2 Address2, fam.fam_City city, fam.fam_State state, fam.fam_Zip zip

from person_per kid, family_fam fam
left Join person_per dad on fam.fam_id = dad.per_fam_id and dad.per_Gender = 1 and dad.per_fmr_ID = 1
left join person_per mom on fam.fam_id = mom.per_fam_id and mom.per_Gender = 2 and mom.per_fmr_ID = 2
,`group_grp` grp, `person2group2role_p2g2r` person_grp  

where kid.per_fam_id = fam.fam_ID and person_grp.p2g2r_rle_ID = 2 and
grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
order by grp.grp_Name, fam.fam_Name';
$rsKids = RunQuery($sSQL);

/*fputcsv($out, [_('Class'),
  _('First Name'), _('Last Name'), _('Birth Date'), _('Mobile'),
  _('Home Phone'), _('Home Address'),
  _('Dad Name'), _('Dad Mobile'), _('Dad Email'),
  _('Mom Name'), _('Mom Mobile'), _('Mom Email'), ],";");*/
  
fputcsv($out, [translate_Win_1252('Class'),
  translate_Win_1252('First Name'),
  translate_Win_1252('Last Name'),
  translate_Win_1252('Birth Date'),
  translate_Win_1252('Mobile'),
  translate_Win_1252('Home Phone'),
  translate_Win_1252('Home Address'),
  translate_Win_1252('Dad Name'),
  translate_Win_1252('Dad Mobile') ,
  translate_Win_1252('Dad Email'),
  translate_Win_1252('Mom Name'),
  translate_Win_1252('Mom Mobile'),
  translate_Win_1252('Mom Email') ], $delimitor);


while ($aRow = mysqli_fetch_array($rsKids)) {
    extract($aRow);
    $birthDate = '';
    if ($birthYear != '') {
        $birthDate = $birthDay.'/'.$birthMonth.'/'.$birthYear;
    }
    fputcsv($out, [
    translate_Win_1252($sundayschoolClass),
    translate_Win_1252($firstName),
    translate_Win_1252($LastName),
     $birthDate, $mobilePhone, $homePhone,
    translate_Win_1252($Address1).' '.$Address2.' '.$city.' '.$state.' '.$zip,
    $dadFirstName.' '.$dadLastName, $dadCellPhone, $dadEmail,
    $momFirstName.' '.$momLastName, $momCellPhone, $momEmail, ], $delimitor);
}


fclose($out);
