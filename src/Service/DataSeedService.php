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
      $family->setName(ucwords ($hoh->name->last));
      $family->setAddress1(ucwords($hoh->location->street));
      $family->setCity(ucwords($hoh->location->city));
      $family->setState($this->getStateAbb(ucwords($hoh->location->state)));
      $family->setCountry("United States");
      $family->setZip($hoh->location->zip);
      $family->setHomePhone($hoh->phone);
      $family->setDateEntered(\DateTime::createFromFormat('Y-m-d H:i:s', $hoh->registered));
      $family->setEnteredBy($_SESSION['iUserID']);
      $family->save();
      $FamilyID = $family->getId();

      $familyName = ucwords ($hoh->name->last);
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
    $this->generateSundaySchoolClasses();
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
    $person->setTitle(ucwords($user->name->title));
    $person->setFirstName(ucwords ($user->name->first));
    $person->setLastName(ucwords ($user->name->last));
    if (FilterInput($user->gender) == "male") {
      $person->setGender(1);
    } else {
      $person->setGender(2);
    }

    $person->setAddress1(ucwords ($user->location->street));
    $person->setCity(ucwords ($user->location->city));
    $person->setState($this->getStateAbb(ucwords ($user->location->state)));
    $person->setZip($user->location->zip);
    $person->setCountry("USA");
    $person->setHomePhone($user->phone);
    $person->setCellPhone($user->cell);
    $person->setEmail($user->email);
    $birthdayDate = \DateTime::createFromFormat('Y-m-d H:i:s', $user->dob);
    $person->setBirthDay($birthdayDate->format("d"));
    $person->setBirthMonth($birthdayDate->format('m'));
    $person->setBirthYear($birthdayDate->format('Y'));
    $person->setFamId($user->famID);
    $person->setFmrId($user->per_fmr_id);
    $person->setEnteredBy($_SESSION['iUserID']);
    $person->setDateEntered(\DateTime::createFromFormat('Y-m-d H:i:s', $user->registered));
    $person->save();
    return $person->getId();

  }

  function getStateAbb($state) {
    $states = array(
      'Alabama'=>'AL',
      'Alaska'=>'AK',
      'Arizona'=>'AZ',
      'Arkansas'=>'AR',
      'California'=>'CA',
      'Colorado'=>'CO',
      'Connecticut'=>'CT',
      'Delaware'=>'DE',
      'Florida'=>'FL',
      'Georgia'=>'GA',
      'Hawaii'=>'HI',
      'Idaho'=>'ID',
      'Illinois'=>'IL',
      'Indiana'=>'IN',
      'Iowa'=>'IA',
      'Kansas'=>'KS',
      'Kentucky'=>'KY',
      'Louisiana'=>'LA',
      'Maine'=>'ME',
      'Maryland'=>'MD',
      'Massachusetts'=>'MA',
      'Michigan'=>'MI',
      'Minnesota'=>'MN',
      'Mississippi'=>'MS',
      'Missouri'=>'MO',
      'Montana'=>'MT',
      'Nebraska'=>'NE',
      'Nevada'=>'NV',
      'New Hampshire'=>'NH',
      'New Jersey'=>'NJ',
      'New Mexico'=>'NM',
      'New York'=>'NY',
      'North Carolina'=>'NC',
      'North Dakota'=>'ND',
      'Ohio'=>'OH',
      'Oklahoma'=>'OK',
      'Oregon'=>'OR',
      'Pennsylvania'=>'PA',
      'Rhode Island'=>'RI',
      'South Carolina'=>'SC',
      'South Dakota'=>'SD',
      'Tennessee'=>'TN',
      'Texas'=>'TX',
      'Utah'=>'UT',
      'Vermont'=>'VT',
      'Virginia'=>'VA',
      'Washington'=>'WA',
      'West Virginia'=>'WV',
      'Wisconsin'=>'WI',
      'Wyoming'=>'WY'
    );
    return $states[$state];
  }
}
