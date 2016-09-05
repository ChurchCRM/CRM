<?php

namespace ChurchCRM\Service;

use ChurchCRM\Family;
use ChurchCRM\Group;
use ChurchCRM\Person;

class DataSeedService
{
  private function getPerson($rs, &$personPointer)
  {
    $user = $rs[$personPointer];
    $personPointer += 1;
    return $user;
  }

  private function savePersonImage($person, $id)
  {
    $dest = "../Images/Person/thumbnails/" . $id . ".jpg";
    #echo "Saving image for: ".$person->name->first." from: ".$person->picture->thumbnail. " to: ". $dest;
    file_put_contents($dest, fopen($person->picture->thumbnail, 'r'));
  }

  function generateFamilies($families)
  {
    requireUserGroupMembership("bAdmin");
    $kidsPerFamily = 3;
    $kidsdev = 3;
    $personPointer = 1;
    $count = $families * ($kidsPerFamily + $kidsdev + 2);
    $response = file_get_contents("http://api.randomuser.me/?nat=US&results=" . $count);
    $data = json_decode($response);
    $rs = $data->results;
    $rTotalHoh = 0;
    $rTotalSpouse = 0;
    $rTotalChildren = 0;

    for ($i = 0; $i < $families; $i++) {
      
      $hoh = $this->getPerson($rs, $personPointer);
      $family = new Family();
      $family->setName($hoh->name->last);
      $family->setAddress1($hoh->location->street);
      $family->setCity($hoh->location->city);
      $family->setState($hoh->location->state);
      $family->setCountry("USA");
      $family->setZip($hoh->location->zip);
      $family->setHomephone($hoh->phone);
      $family->setDateEntered(date('Y-m-d h:i:s', $hoh->registered));
      $family->setEnteredBy($_SESSION['iUserID']);
      $family->save();
      $FamilyID = $family->getId();

      $familyName = $hoh->name->last;
      $hoh->famID = $FamilyID;
      $hoh->per_fmr_id = 1;

      $spouse = $this->getPerson($rs, $personPointer);
      $spouse->name->last = $familyName;
      $spouse->famID = $FamilyID;
      $spouse->per_fmr_id = 2;

      $hohID = $this->insertPerson($hoh);
      $this->savePersonImage($hoh, $hohID);
      $rTotalHoh += 1;
      $spouseID = $this->insertPerson($spouse);
      $this->savePersonImage($spouse, $spouseID);
      $rTotalSpouse += 1;

      $thisFamChildren = rand($kidsPerFamily - $kidsdev, $kidsPerFamily + $kidsdev);

      for ($y = 0; $y < $thisFamChildren; $y++) {
        $child = $this->getPerson($rs, $personPointer);
        $child->name->last = $familyName;
        $child->famID = $FamilyID;
        $child->per_fmr_id = 3;
        $childID = $this->insertPerson($child);
        $this->savePersonImage($child, $childID);
        $rTotalChildren += 1;
      }

    }
    echo '{"families created": ' . $families . ',"heads of household created": ' . $rTotalHoh . ', "spouses created":' . $rTotalSpouse . ', "children created":' . $rTotalChildren . ',"random.me response":' . $response . '}';

  }

  function generateSundaySchoolClasses()
  {
    requireUserGroupMembership("bAdmin");
    $classNames = array("Angels class", "Class 1-3", "Class 4-5", "Class 6-7", "High School Class", "Youth Meeting");
    foreach ($classNames as $className) {
      $class = new Group();
      $class->setName($className);
      $class->setType(4);
      $class->save();
    }
  }

  function generateEvents()
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Events Seed Data Not Implemented"}';
  }

  function generateDeposits()
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Deposits Seed Data Not Implemented"}';
  }

  function generateFundRaisers()
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Fundraisers Seed Data Not Implemented"}';
  }

  function insertPerson($user)
  {
    $person = new Person();
    $person->setTitle($user->name->title);
    $person->setFirstName($user->name->first);
    $person->setLastName($user->name->last);
    if (FilterInput($user->gender) == "male") {
      $person->setGender(1);
    } else {
      $person->setGender(2);
    }

    $person->setAddress1($user->location->street);
    $person->setCity($user->location->city);
    $person->setState($user->location->state);
    $person->setZip($user->location->zip);
    $person->setCountry("USA");
    $person->setHomePhone($user->phone);
    $person->setCellPhone($user->cell);
    $person->setEmail($user->email);
    $person->setBirthDay(date('d', $user->dob));
    $person->setBirthMonth(date('m', $user->dob));
    $person->setBirthYear(date('Y', $user->dob));
    $person->setFamId($user->famID);
    $person->setFmrId($user->per_fmr_id);
    $person->setEnteredBy($_SESSION['iUserID']);
    $person->setDateEntered(date('Y-m-d h:i:s', $user->registered));
    $person->save();
    return $person->getId();

  }


}
