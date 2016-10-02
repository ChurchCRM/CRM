<?php
require "../Include/Config.php";
require "../Include/Functions.php";

use ChurchCRM\Service\GroupService;
use ChurchCRM\Service\SundaySchoolService;

$groupService = new GroupService();
$sundaySchoolService = new SundaySchoolService();
$groups = $groupService->getGroups();

$colNames = array();
array_push($colNames, "CRM ID");
array_push($colNames, "FirstName");
array_push($colNames, "LastName");
array_push($colNames, "Email");
foreach ($groups as $group) {
  array_push($colNames, $group["groupName"]);
}

$sundaySchoolsParents = array();
foreach ($groups as $group) {
  if ($group["grp_Type"] == 4) {
    $sundaySchoolParents = array();
    $kids = $sundaySchoolService->getKidsFullDetails($group["id"]);
    $parentIds = array();
    foreach ($kids as $kid) {
      if ($kid["dadId"] != "") {
        array_push($parentIds, $kid["dadId"]);
      }
      if ($kid["momId"] != "") {
        array_push($parentIds, $kid["momId"]);
      }
    }
    $sundaySchoolsParents[$group["id"]] = $parentIds;
  }
}

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=EmailExport-" . date("Ymd") . ".csv");
header("Pragma: no-cache");
header("Expires: 0");

$out = fopen('php://output', 'w');
fputcsv($out, $colNames);
foreach ($personService->getPeopleEmailsAndGroups() as $person) {
  $row = array();
  array_push($row, $person["id"]);
  array_push($row, $person["firstName"]);
  array_push($row, $person["lastName"]);
  array_push($row, $person["email"]);
  foreach ($groups as $group) {
    $groupRole = $person[$group["groupName"]];
    if ($groupRole == "" && $group["grp_Type"] == 4) {
      if (in_array($person["id"], $sundaySchoolsParents[$group["id"]])) {
        $groupRole = "Parent";
      }
    }
    array_push($row, $groupRole);
  }
  fputcsv($out, $row);
}
fclose($out);
?>
