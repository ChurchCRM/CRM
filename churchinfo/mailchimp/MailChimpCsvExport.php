<?php
require "../Include/Config.php";
require "../Include/Functions.php";

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=EmailExport-" . date("Ymd") . ".csv");
header("Pragma: no-cache");
header("Expires: 0");

$out = fopen('php://output', 'w');

$sSQL = "select per_FirstName, per_LastName, per_Email, per_ID from person_per where per_Email != '' order by per_id";
$rsPeopleWithEmails = RunQuery($sSQL);

fputcsv($out, array('FirstName', 'LastName', 'Email', "CRM ID"));
while ($row = mysql_fetch_array($rsPeopleWithEmails)) {
  fputcsv($out, array($row["per_FirstName"], $row["per_LastName"], $row["per_Email"], $row["per_ID"]));
}
fclose($out);
?>
