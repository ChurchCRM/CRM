<?php

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
    $PersonService = new PersonService();
    $FamilyService = new FamilyService();
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
      
      $FamilyID = $FamilyService->insertFamily($hoh);
      $familyName = $hoh->name->last;
      $hoh->famID = $FamilyID;
      $hoh->per_fmr_id = 1;

      $spouse = $this->getPerson($rs, $personPointer);
      $spouse->name->last = $familyName;
      $spouse->famID = $FamilyID;
      $spouse->per_fmr_id = 2;

      $hohID = $PersonService->insertPerson($hoh);
      $this->savePersonImage($hoh, $hohID);
      $rTotalHoh += 1;
      $spouseID = $PersonService->insertPerson($spouse);
      $this->savePersonImage($spouse, $spouseID);
      $rTotalSpouse += 1;

      #$thisFamChildren = stats_rand_gen_normal ($kidsPerFamily, $stddev);
      $thisFamChildren = rand($kidsPerFamily - $kidsdev, $kidsPerFamily + $kidsdev);

      for ($y = 0; $y < $thisFamChildren; $y++) {
        $child = $this->getPerson($rs, $personPointer);
        $child->name->last = $familyName;
        $child->famID = $FamilyID;
        $child->per_fmr_id = 3;
        $childID = $PersonService->insertPerson($child);
        $this->savePersonImage($child, $childID);
        $rTotalChildren += 1;
      }

    }
    echo '{"families created": ' . $families . ',"heads of household created": ' . $rTotalHoh . ', "spouses created":' . $rTotalSpouse . ', "children created":' . $rTotalChildren . ',"random.me response":' . $response . '}';

  }

  function generateSundaySchoolClasses($classes, $childrenPerTeacher)
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Sunday School Seed Data Not Implemented"}';
  }

  function generateEvents($events, $averageAttendance)
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Events Seed Data Not Implemented"}';
  }

  function generateDeposits($deposits, $averagedepositvalue)
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Deposits Seed Data Not Implemented"}';
  }

  function generateFundRaisers($fundraisers, $averageItems, $averageItemPrice)
  {
    requireUserGroupMembership("bAdmin");
    echo '{"status":"Fundraisers Seed Data Not Implemented"}';
  }

}
