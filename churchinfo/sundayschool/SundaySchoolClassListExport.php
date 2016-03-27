<?php
require "../Include/Config.php";
require "../Include/Functions.php";

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=SundaySchool-" . date("Ymd") . ".csv");
header("Pragma: no-cache");
header("Expires: 0");

$out = fopen('php://output', 'w');

// Get all the groups
$sSQL = "select grp.grp_Name sundayschoolClass, kid.per_ID kidId, kid.per_FirstName firstName, kid.per_LastName LastName, kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear, kid.per_CellPhone mobilePhone,
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
order by grp.grp_Name, fam.fam_Name";
$rsKids = RunQuery($sSQL);

fputcsv($out, array("Class",
  "First Name", "Last Name", "Birth Date", "Mobile",
  "Home Phone", "Home Address",
  "Dad Name", "Dad Mobile", "Dad Email",
  "Mom Name", "Mom Mobile", "Mom Email"));

while ($aRow = mysql_fetch_array($rsKids)) {
  extract($aRow);
  $birthDate = "";
  if ($birthYear != "") {
    $birthDate = $birthDay . "/" . $birthMonth . "/" . $birthYear;
  }
  fputcsv($out, array($sundayschoolClass, $firstName, $LastName, $birthDate, $mobilePhone, $homePhone, $Address1 . " " . $Address2 . " " . $city . " " . $state . " " . $zip,
    $dadFirstName . " " . $dadLastName, $dadCellPhone, $dadEmail,
    $momFirstName . " " . $momLastName, $momCellPhone, $momEmail));
}

fclose($out);
?>



