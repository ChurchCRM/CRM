<?php
/*******************************************************************************
*
*  filename    : sundayschol/SundaySchoolClassListExport.php
*  last change : 2017-11-03 Philippe Logel
*  description : Creates a csv for a Sunday School Class List

******************************************************************************/
require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\Map\PersonTableMap;
use Propel\Runtime\ActiveQuery\Criteria;

header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Description: File Transfer');
header('Content-Type: text/csv;charset='.SystemConfig::getValue("sCSVExportCharset"));
header('Content-Disposition: attachment; filename=SundaySchool-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');
header('Content-Transfer-Encoding: binary');

$delimiter = SystemConfig::getValue("sCSVExportDelemiter");

$out = fopen('php://output', 'w');

//add BOM to fix UTF-8 in Excel 2016 but not under, so the problem is solved with the sCSVExportCharset variable
if (SystemConfig::getValue("sCSVExportCharset") == "UTF-8") {
    fputs($out, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
}


fputcsv($out, [InputUtils::translate_special_charset('Class'),
  InputUtils::translate_special_charset('Role'),
  InputUtils::translate_special_charset('First Name'),
  InputUtils::translate_special_charset('Last Name'),
  InputUtils::translate_special_charset('Birth Date'),
  InputUtils::translate_special_charset('Mobile'),
  InputUtils::translate_special_charset('Home Phone'),
  InputUtils::translate_special_charset('Home Address'),
  InputUtils::translate_special_charset('Dad Name'),
  InputUtils::translate_special_charset('Dad Mobile') ,
  InputUtils::translate_special_charset('Dad Email'),
  InputUtils::translate_special_charset('Mom Name'),
  InputUtils::translate_special_charset('Mom Mobile'),
  InputUtils::translate_special_charset('Mom Email'),
  InputUtils::translate_special_charset('Properties') ], $delimiter);

// only the unday groups
$groups = GroupQuery::create()
                    ->orderByName(Criteria::ASC)
                    ->filterByType(4)
                    ->find();
                    
                    
foreach ($groups as $group) {
    $iGroupID = $group->getID();
    $sundayschoolClass = $group->getName();
        
        
    $groupRoleMemberships = ChurchCRM\Person2group2roleP2g2rQuery::create()
                            ->joinWithPerson()
                            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME) // I've try to reproduce per_LastName, per_FirstName
                            ->findByGroupId($iGroupID);
                            
    foreach ($groupRoleMemberships as $groupRoleMembership) {
        $groupRole = ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($groupRoleMembership->getRoleId())->findOne();
            
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
        
        $Address1 = $Address2 = $city = $state = $zip = " ";
        $dadFirstName = $dadLastName = $dadCellPhone = $dadEmail = " ";
        $momFirstName = $momLastName = $momCellPhone = $momEmail = " ";
        
        if (!empty($family)) {
            $famID = $family->getID();
            $Address1 = $family->getAddress1();
            $Address2 = $family->getAddress2();
            $city = $family->getCity();
            $state = $family->getState();
            $zip = $family->getZip();
                
                
            if ($lst_OptionName == "Student") {
                // only for a student
                $FAmembers = FamilyQuery::create()->findOneByID($famID)->getAdults();
            
                // il faut encore chercher les membres de la famille
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
        $props = " ";
        if ($lst_OptionName == "Student" && !empty($assignedProperties)) {
            foreach ($assignedProperties as $property) {
                $props.= $property->getProName().", ";
            }
                
            $props = chop($props, ", ");
        }
        
        $birthDate = '';
        if ($birthYear != '' && !$birthDate && (!$member->getFlags() || $lst_OptionName == "Student")) {
            $publishDate = DateTime::createFromFormat('Y-m-d', $birthYear.'-'.$birthMonth.'-'.$birthDay);
            $birthDate = $publishDate->format(SystemConfig::getValue("sDateFormatLong"));
        }
        
        fputcsv($out, [
            InputUtils::translate_special_charset($sundayschoolClass),
            InputUtils::translate_special_charset($lst_OptionName),
            InputUtils::translate_special_charset($firstName),
            InputUtils::translate_special_charset($lastname),
            $birthDate, $mobilePhone, $homePhone,
            InputUtils::translate_special_charset($Address1).' '.InputUtils::translate_special_charset($Address2).' '.InputUtils::translate_special_charset($city).' '.InputUtils::translate_special_charset($state).' '.$zip,
            InputUtils::translate_special_charset($dadFirstName).' '.InputUtils::translate_special_charset($dadLastName), $dadCellPhone, $dadEmail,
            InputUtils::translate_special_charset($momFirstName).' '.InputUtils::translate_special_charset($momLastName), $momCellPhone, $momEmail, $props], $delimiter);
    }
}

fclose($out);
