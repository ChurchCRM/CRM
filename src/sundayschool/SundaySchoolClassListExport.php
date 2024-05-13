<?php

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Base\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Description: File Transfer');
header('Content-Type: text/csv;charset=' . SystemConfig::getValue('sCSVExportCharset'));
header('Content-Disposition: attachment; filename=SundaySchool-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv');
header('Content-Transfer-Encoding: binary');

$delimiter = SystemConfig::getValue('sCSVExportDelimiter');

$out = fopen('php://output', 'w');

// Add BOM to fix UTF-8 in Excel 2016 but not under, so the problem is solved with the sCSVExportCharset variable
if (SystemConfig::getValue('sCSVExportCharset') === 'UTF-8') {
    fputs($out, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
}

fputcsv($out, [InputUtils::translateSpecialCharset('Class'),
    InputUtils::translateSpecialCharset('Role'),
    InputUtils::translateSpecialCharset('First Name'),
    InputUtils::translateSpecialCharset('Last Name'),
    InputUtils::translateSpecialCharset('Birth Date'),
    InputUtils::translateSpecialCharset('Mobile'),
    InputUtils::translateSpecialCharset('Home Phone'),
    InputUtils::translateSpecialCharset('Home Address'),
    InputUtils::translateSpecialCharset('Dad Name'),
    InputUtils::translateSpecialCharset('Dad Mobile'),
    InputUtils::translateSpecialCharset('Dad Email'),
    InputUtils::translateSpecialCharset('Mom Name'),
    InputUtils::translateSpecialCharset('Mom Mobile'),
    InputUtils::translateSpecialCharset('Mom Email'),
    InputUtils::translateSpecialCharset('Properties')], $delimiter);

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
                $props .= $property->getProName() . ', ';
            }

            $props = chop($props, ', ');
        }

        $birthDate = '';
        if ($birthYear != '' && !$birthDate && (!$member->getFlags() || $lst_OptionName === 'Student')) {
            $publishDate = DateTime::createFromFormat('Y-m-d', $birthYear . '-' . $birthMonth . '-' . $birthDay);
            $birthDate = $publishDate->format(SystemConfig::getValue('sDateFormatLong'));
        }

        fputcsv($out, [
            InputUtils::translateSpecialCharset($sundayschoolClass),
            InputUtils::translateSpecialCharset($lst_OptionName),
            InputUtils::translateSpecialCharset($firstName),
            InputUtils::translateSpecialCharset($lastname),
            $birthDate, $mobilePhone, $homePhone,
            InputUtils::translateSpecialCharset($Address1) . ' ' . InputUtils::translateSpecialCharset($Address2) . ' ' . InputUtils::translateSpecialCharset($city) . ' ' . InputUtils::translateSpecialCharset($state) . ' ' . $zip,
            InputUtils::translateSpecialCharset($dadFirstName) . ' ' . InputUtils::translateSpecialCharset($dadLastName), $dadCellPhone, $dadEmail,
            InputUtils::translateSpecialCharset($momFirstName) . ' ' . InputUtils::translateSpecialCharset($momLastName), $momCellPhone, $momEmail, $props], $delimiter);
    }
}

fclose($out);
