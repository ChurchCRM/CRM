<?php

require_once '../Include/Config.php';
require_once '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Base\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\CsvExporter;
use Propel\Runtime\ActiveQuery\Criteria;

// Set headers BEFORE any output is sent (required for file download)
$charset = SystemConfig::getValue('sCSVExportCharset');
$filename = 'SundaySchool-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv';

header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Description: File Transfer');
header('Content-Type: text/csv;charset=' . $charset);
header('Content-Disposition: attachment; filename=' . $filename);
header('Content-Transfer-Encoding: binary');

// Build headers for CSV export
$headers = [
    'Class',
    'Role',
    'First Name',
    'Last Name',
    'Birth Date',
    'Mobile',
    'Home Phone',
    'Home Address',
    'Dad Name',
    'Dad Mobile',
    'Dad Email',
    'Mom Name',
    'Mom Mobile',
    'Mom Email',
    'Properties'
];

// Build rows for CSV export
$rows = [];

// Only the Sunday groups
$groups = GroupQuery::create()
                    ->orderByName(Criteria::ASC)
                    ->filterByType(4)
                    ->find();

foreach ($groups as $group) {
    $iGroupID = $group->getID();
    $sundayschoolClass = $group->getName();

    $groupRoleMemberships = Person2group2roleP2g2rQuery::create()
                            ->joinWithPerson()
                            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME)
                            ->findByGroupId($iGroupID);

    foreach ($groupRoleMemberships as $groupRoleMembership) {
        $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($groupRoleMembership->getRoleId())->findOne();

        $lst_OptionName = $groupRole->getOptionName();
        $member = $groupRoleMembership->getPerson();

        $firstName = $member->getFirstName();
        $middlename = $member->getMiddleName();
        $lastname = $member->getLastName();
        $birthDay = $member->getBirthDay();
        $birthMonth = $member->getBirthMonth();
        $birthYear = $member->getBirthYear();
        $homePhone = $member->getHomePhone();
        $mobilePhone = $member->getCellPhone();
        $hideAge = $member->hideAge();

        $family = $member->getFamily();

        $Address1 = $Address2 = $city = $state = $zip = ' ';
        $dadFirstName = $dadLastName = $dadCellPhone = $dadEmail = ' ';
        $momFirstName = $momLastName = $momCellPhone = $momEmail = ' ';

        if (!empty($family)) {
            $famID = $family->getID();
            $Address1 = $family->getAddress1();
            $Address2 = $family->getAddress2();
            $city = $family->getCity();
            $state = $family->getState();
            $zip = $family->getZip();

            if ($lst_OptionName === 'Student') {
                // Only for a student
                $FAmembers = FamilyQuery::create()->findOneByID($famID)->getAdults();

                // We still have to look for family members
                foreach ($FAmembers as $maf) {
                    if ($maf->getGender() == 1) {
                        // Dad
                        $dadFirstName = $maf->getFirstName();
                        $dadLastName = $maf->getLastName();
                        $dadCellPhone = $maf->getCellPhone();
                        $dadEmail = $maf->getEmail();
                    } elseif ($maf->getGender() == 2) {
                        // Mom
                        $momFirstName = $maf->getFirstName();
                        $momLastName = $maf->getLastName();
                        $momCellPhone = $maf->getCellPhone();
                        $momEmail = $maf->getEmail();
                    }
                }
            }
        }

        $assignedProperties = $member->getProperties();
        $props = ' ';
        if ($lst_OptionName === 'Student' && !empty($assignedProperties)) {
            foreach ($assignedProperties as $property) {
                $props .= $property->getProperty()->getProName() . ', ';
            }

            $props = chop($props, ', ');
        }

        $birthDate = '';
        if ($birthYear != '' && !$birthDate && (!$member->getFlags() || $lst_OptionName === 'Student')) {
            $publishDate = DateTime::createFromFormat('Y-m-d', $birthYear . '-' . $birthMonth . '-' . $birthDay);
            $birthDate = $publishDate->format(SystemConfig::getValue('sDateFormatLong'));
        }

        // Add row to array collection - CsvExporter will handle charset translation and formula injection escaping
        $rows[] = [
            $sundayschoolClass,
            $lst_OptionName,
            $firstName,
            $lastname,
            $birthDate,
            $mobilePhone,
            $homePhone,
            $Address1 . ' ' . $Address2 . ' ' . $city . ' ' . $state . ' ' . $zip,
            $dadFirstName . ' ' . $dadLastName,
            $dadCellPhone,
            $dadEmail,
            $momFirstName . ' ' . $momLastName,
            $momCellPhone,
            $momEmail,
            $props
        ];
    }
}

// Use CsvExporter to generate CSV output with RFC 4180 compliance and formula injection prevention
// Headers were already sent above, so use outputOnly() to avoid duplicate headers
$exporter = new CsvExporter($charset);
$exporter->insertHeaders($headers);
$exporter->insertRows($rows);
$exporter->outputOnly();
