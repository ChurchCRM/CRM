<?php

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\GroupQuery;
use ChurchCRM\Service\SundaySchoolService;

$sundaySchoolService = new SundaySchoolService();
$groups = GroupQuery::create()->filterByActive(true)->filterByIncludeInEmailExport(true)->find();

$colNames = [];
array_push($colNames, 'CRM ID');
array_push($colNames, 'FirstName');
array_push($colNames, 'LastName');
array_push($colNames, 'Email');
foreach ($groups as $group) {
    array_push($colNames, $group->getName());
}

$sundaySchoolsParents = [];
foreach ($groups as $group) {
    if ($group->isSundaySchool()) {
        $sundaySchoolParents = [];
        $kids = $sundaySchoolService->getKidsFullDetails($group->getId());
        $parentIds = [];
        foreach ($kids as $kid) {
            if ($kid['dadId'] != '') {
                array_push($parentIds, $kid['dadId']);
            }
            if ($kid['momId'] != '') {
                array_push($parentIds, $kid['momId']);
            }
        }
        $sundaySchoolsParents[$group->getId()] = $parentIds;
    }
}

header('Content-type: text/csv');
header('Content-Disposition: attachment; filename=EmailExport-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputcsv($out, $colNames);
foreach ($personService->getPeopleEmailsAndGroups() as $person) {
    $row = [];
    array_push($row, $person['id']);
    array_push($row, $person['firstName']);
    array_push($row, $person['lastName']);
    array_push($row, $person['email']);
    foreach ($groups as $group) {
        $groupRole = $person[$group->getName()];
        if ($groupRole == '' && $group->isSundaySchool()) {
            if (in_array($person['id'], $sundaySchoolsParents[$group->getId()])) {
                $groupRole = 'Parent';
            }
        }
        array_push($row, $groupRole);
    }
    fputcsv($out, $row);
}
fclose($out);
