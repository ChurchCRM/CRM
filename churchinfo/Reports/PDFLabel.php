<?php
/*******************************************************************************
*
*  filename    : Reports/PDFLabel.php
*  website     : http://www.churchdb.org
*  description : Creates a PDF document containing the addresses of
*                The people in the Cart
*
*  Copyright 2003  Jason York
*
*  Portions based on code by LPA (lpasseb@numericable.fr)
*  and Steve Dillon (steved@mad.scientist.com) from www.fpdf.org
*
*  Additional Contributions by
*  2006 Ed Davis
*  2006 Stephen Shaffer
*
*  Copyright 2006 Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

require "../Include/class_fpdf_labels.php";


function GroupBySalutation($famID, $aAdultRole, $aChildRole) {
// Function to place the name(s) on a label when grouping multiple
// family members on the same label.
// Make it put the name if there is only one adult in the family.
// Make it put two first names and the last name when there are exactly
// two adults in the family (e.g. "Nathaniel & Jeanette Brooks")
// Make it put two whole names where there are exactly two adults with 
// different names (e.g. "Doug Philbrook & Karen Andrews")
// When there are zero adults or more than two adults in the family just 
// use the family name.  This is helpful for sending newsletters to places
// such as "All Souls Church"
// Similar logic is applied if mailing to Sunday School children.


	$sSQL = "SELECT * FROM family_fam WHERE fam_ID=" . $famID;
	$rsFamInfo = RunQuery($sSQL);

	if (mysql_num_rows ($rsFamInfo) == 0)
		return "Invalid Family" . $famID;

	$aFam = mysql_fetch_array($rsFamInfo);
	extract ($aFam);

	// Only get family members that are in the cart
	$sSQL = "SELECT * FROM person_per WHERE per_fam_ID=" . $famID . " AND per_ID IN (" 
	. ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY per_LastName, per_FirstName";

	$rsMembers = RunQuery($sSQL);
	$numMembers = mysql_num_rows ($rsMembers);

	// Initialize to "Nothing to return"  If this value is returned
	// the calling program knows to skip this mode and try the next

	$sNameAdult = "Nothing to return";
	$sNameChild = "Nothing to return";
	$sNameOther = "Nothing to return";

	$numAdult = 0;
	$numChild = 0;
	$numOther = 0;

	for ($ind = 0; $ind < $numMembers; $ind++) {
		$member = mysql_fetch_array($rsMembers);
		extract ($member);

		$bAdult = FALSE;
		$bChild = FALSE;

		// check if this person is adult
		foreach ($aAdultRole as $value) {
			if ($per_fmr_ID == $value) {
				$aAdult[$numAdult++] = $member;
				$bAdult = TRUE;
			}
		}

		// now check if this person is a child.  Note, if child and adult directory roles
		// overlap the person will only be listed once as an adult (can't be adult and 
		// child simultaneously ... even if directory roles suggest otherwise)
		if (!$bAdult) {
			foreach ($aChildRole as $value) {
				if ($per_fmr_ID == $value) {
					$aChild[$numChild++] = $member;
					$bChild = TRUE;
				}
			}
		}

		// If this is not an adult or a child it must be something else.  Maybe it's
		// another church or the landscape company that mows the lawn.
		if (!$bAdult && !$bChild) {
			$aOther[$numOther++] = $member;
		}

	}

	if ($numAdult == 1) { // Generate Salutation for Adults in family
		extract ($aAdult[0]);
		$sNameAdult = $per_FirstName . " " . $per_LastName;
	} else if ($numAdult == 2) {
		$firstMember = mysql_fetch_array($rsMembers);
		extract ($aAdult[0]);
		$firstFirstName = $per_FirstName;
		$firstLastName = $per_LastName;
		$secondMember = mysql_fetch_array($rsMembers);
		extract ($aAdult[1]);
		$secondFirstName = $per_FirstName;
		$secondLastName = $per_LastName;
		if ($firstLastName == $secondLastName) {
			$sNameAdult = $firstFirstName . " & " . $secondFirstName . " " . $firstLastName;
		} else {
			$sNameAdult = $firstFirstName . " " . $firstLastName . " & " . 
						$secondFirstName . " " . $secondLastName;
		}
	} else if ($numAdult > 2) {
		$sNameAdult = $fam_Name;
	} // end if ($numAdult ...)


	if ($numChild > 0) { // Salutation for children grouped together
		$firstMember = mysql_fetch_array($rsMembers);
		extract ($aChild[0]);
		$firstFirstName = $per_FirstName;
		$firstLastName  = $per_LastName;
	}
	if ($numChild > 1) {
		$secondMember = mysql_fetch_array($rsMembers);
		extract ($aChild[1]);
		$secondFirstName = $per_FirstName;
		$secondLastName = $per_LastName;
	}
	if ($numChild > 2) {
		$thirdMember = mysql_fetch_array($rsMembers);
		extract ($aChild[2]);
		$thirdFirstName = $per_FirstName;
		$thirdLastName = $per_LastName;
	}
	if ($numChild > 3) {
		$fourthMember = mysql_fetch_array($rsMembers);
		extract ($aChild[3]);
		$fourthFirstName = $per_FirstName;
		$fourthLastName = $per_LastName;
	}
	if ($numChild == 1) {
		$sNameChild = $per_FirstName . " " . $per_LastName;
	}
	if ($numChild == 2) {
		if ($firstLastName == $secondLastName) {
			$sNameChild = $firstFirstName . " & " . $secondFirstName . " " . $firstLastName;
		} else {
			$sNameChild = $firstFirstName . " " . $firstLastName . " & " . 
							$secondFirstName . " " . $secondLastName;
		}
	}
	if ($numChild == 3) {
		$sNameChild = $firstFirstName . ", " . $secondFirstName . " & " . 
									$thirdFirstName . " " . $fam_Name;
	}
	if ($numChild == 4) {
		$sNameChild = $firstFirstName . ", " . $secondFirstName . ", " . 
					$thirdFirstName . " & " . $fourthFirstName . " " . $fam_Name;
	}
	if ($numChild > 4) {
		$sNameChild = "The " . $fam_Name . " Family";
	}

	if ($numOther) 
		$sNameOther = $fam_Name;

	unset($aName);

	$aName['adult'] = substr($sNameAdult,0,33);
	$aName['child'] = substr($sNameChild,0,33);
	$aName['other'] = substr($sNameOther,0,33);

	return $aName;
}

function MakeADCArray($sADClist) {

unset($aReturnArray);

// The end of each row is marked with the pipe | symbol
// keep fetching rows until gone
while ( substr_count ( $sADClist, "|" )){

    // find end of current row
    $endOfRow = strpos($sADClist,"|");
    if($endOfRow){
        
        $currentRow = substr($sADClist,0,$endOfRow);
        $sADClist = substr($sADClist,($endOfRow+1));

        // find the current adc (hint, last item listed)
        $currentRow = trim($currentRow);
        $adc = substr($currentRow,strrpos ($currentRow, " "));
        $adc = trim($adc, " ,\t\n\r\0\x0B");

        // Now get a list of the three digit codes associated
        // with this adc.  They are all before the "_" character
 
        $currentRow = substr($currentRow,0,strpos($currentRow,"_"));
        $currentRow = trim($currentRow, " ,\t\n\r\0\x0B");
        while (strlen($currentRow)){
            if (strpos($currentRow, ",")) {
                $nugget = trim(substr($currentRow,0,strpos($currentRow, ",")));
                $currentRow = trim(substr($currentRow,strpos($currentRow, ",")+1));

            } 
            else  // parsing last element
            {
                $nugget = trim($currentRow, " ,\t\n\r\0\x0B");
                $currentRow = "";
            }

            $dash = strpos($nugget,"-");
            if ($dash)
            {   // range of 
                $start  = intval(substr($nugget,0,$dash));
                $end    = intval(substr($nugget,$dash+1));
                if ($end >= $start)
                    for ($i=$start; $i<=$end; $i++)
                        $aReturnArray[$i] = $adc;
            } 
            else
            {   
                $i = intval($nugget);
                $aReturnArray[$i] = $adc;
            }
        }
    }
}

return serialize($aReturnArray);

}


function ZipBundleSort($inLabels) {
//
// Description:
// sorts an input array $inLabels() for presort bundles
//
// Inputs:
// $inLabels() is a 2-d associative array which must have:
//	"Zip" as the location of the zipcode,
// 	the array is generally of the form 
//      $Labels[$i] = array('Name'=>$name, 'Address'=>$address,...'Zip'=>$zip) 
//	
// Bundles will be returned in the following order: 
//	Bundles where full 5 digit zip count > 10 
//	Bundles where 3 digit zip count > 10
//	Bundles where "ADC" count > 10
//      Mixed ADC bundle
//
// Return Values:
// (1) The function returns an associative array which matches the input array containing any 
// legal bundles of "type" sorted by zip
// (2) if no legal bundles are found for the requested "type" then the function returns "FALSE"
// (3) the output array will also contain an associative value of "Notes" which will contain a 
//     text string to be printed on the labels indicating the bundle the label is a member of
// 
// Notes:
// (1) The ADC data is hard coded in the variable $adc was composed march 2006
// (2) the definition of a "legal" bundle is one that contains at least 10 units
// (3) this function is not PAVE certified
//
// Stephen Shaffer 2006, stephen@shaffers4christ.com
//
//////////////////////////////////////////////////////////////////////////////////////////////
// 60
// initialize the adc data list
//
// The following website is the source for the adc
// http://pe.usps.com/text/dmm300/L004.htm
// This array for STD mail

$sADClist  =
"005, 115, 117-119                      _LONG ISLAND NY 117         |" .
"006-009                                _ADC SAN JUAN PR 006        |" .
"010-017                                _ADC SPRINGFIELD MA 010     |" .
"018, 019, 021, 022, 024, 055           _ADC BOSTON MA 021          |" .
"020, 023, 025-029                      _ADC PROVIDENCE RI 028      |" .
"030-034, 038, 039                      _ADC PORTSMOUTH NH 038      |" .
"035-037, 050-054, 056-059              _ADC WHITE RIV JCT VT 050   |" .
"040-049                                _ADC PORTLAND ME 040        |" .
"060-069                                _ADC SOUTHERN CT 064        |" .
"070-079, 085-089                       _ADC DV DANIELS NJ 07099    |" .
"080-084                                _ADC SOUTH JERSEY NJ 080    |" .
"090-099                                _MILITARY CENTER NY 090     |" .
"100-102, 104                           _ADC NEW YORK NY 100        |" .
"103, 110-114, 116                      _ADC QUEENS NY 110          |" .
"105-109                                _ADC WESTCHESTER NY 105     |" .
"120-129                                _ADC ALBANY NY 120          |" .
"130-139                                _ADC SYRACUSE NY 130        |" .
"140-149                                _ADC BUFFALO NY 140         |" .
"150-168, 260                           _ADC PITTSBURGH PA 150      |" .
"169-178                                _ADC HARRISBURG PA 170      |" .
"179, 189, 193-196                      _ADC SOUTHEASTERN PA 189    |" .
"180-188                                _ADC LEHIGH VALLEY PA 180   |" .
"190-192                                _ADC PHILADELPHIA PA 190    |" .
"197-199                                _ADC WILMINGTON DE 197      |" .
"200                                    _WASHINGTON DC 200          |" .
"202-205                                _ADC WASHINGTON DC 202      |" .
"201, 220-223, 226, 227                 _ADC NORTHERN VA VA 220     |" .
"206-209                                _ADC SOUTHERN MD MD 207     |" .
"210-212, 214-219, 254, 267             _ADC LINTHICUM MD 210       |" .
"224, 225, 228-239, 244                 _ADC RICHMOND VA 230        |" .
"240-243, 245                           _ADC ROANOKE VA 240         |" .
"246-253, 255-259                       _ADC CHARLESTON WV 250      |" .
"261-266, 268                           _ADC CLARKSBURG WV 263      |" .
"270-279, 285                           _ADC GREENSBORO NC 270      |" .
"280-284, 286-289, 297                  _ADC CHARLOTTE NC 280       |" .
"290-296                                _ADC COLUMBIA SC 290        |" .
"298, 300, 301, 305, 306, 308, 309      _ADC NORTH METRO GA 30197   |" .
"299, 304, 313-315, 320-324, 326, 344   _ADC JACKSONVILLE FL 32088  |" .
"302, 303, 311, 399                     _ADC ATLANTA GA 303         |" .
"307, 370-374, 376-379, 384, 385        _ADC NASHVILLE TN 37099     |" .
"310, 312, 316-319, 398                 _ADC MACON GA 31293         |" .
"325, 365, 366, 394, 395, 700, 701, 703-708
                                        _ADC NEW ORLEANS LA 70099   |" .
"327-329, 334, 347, 349                 _ADC MID FLORIDA FL 32799   |" .
"330-333, 340                           _ADC MIAMI FL 33298         |" .
"335-339, 341, 342, 346                 _ADC MANASOTA FL 34299      |" .
"350-352, 354-359, 362                  _ADC BIRMINGHAM AL 35099    |" .
"360, 361, 363, 364, 367, 368           _ADC MONTGOMERY AL 36099    |" .
"369, 390-393, 396, 397                 _ADC JACKSON MS 39099       |" .
"375, 380-383, 386-389, 723             _ADC MEMPHIS TN 38099       |" .
"400-409, 411-418, 420-427, 471, 476, 477
                                        _ADC LOUISVILLE KY 400      |" .
"410, 450-455, 458, 459, 470            _ADC CINCINNATI OH 450      |" .
"430-438, 456, 457                      _ADC COLUMBUS OH 430        |" .
"439-449                                _ADC CLEVELAND OH 440       |" .
"460-469, 472-475, 478, 479             _ADC INDIANAPOLIS IN 460    |" .
"480-489, 492                           _ADC DETROIT MI 481         |" .
"490, 491, 493-497                      _ADC GRAND RAPIDS MI 493    |" .
"498, 499, 530-532, 534, 535, 537-539, 541-545, 549
                                        _ADC MILWAUKEE WI 530       |" .
"500-509, 520-528, 612                  _ADC DES MOINES IA 50091    |" .
"510-516, 680, 681, 683-693             _ADC OMAHA NE 680           |" .
"540, 546-548, 550, 551, 556-559        _ADC ST PAUL MN 55233       |" .
"553-555, 560-564, 566                  _ADC MINNEAPOLIS MN 55533   |" .
"565, 567, 580-588                      _ADC FARGO ND 580           |" .
"570-577                                _ADC SIOUX FALLS SD 570     |" .
"590-599, 821                           _ADC BILLINGS MT 590        |" .
"600-603, 610, 611, 614-616             _ADC CAROL STREAM IL 601    |" .
"604, 605, 609, 613, 617-619            _ADC S SUBURBAN IL 604      |" .
"606-608                                _ADC CHICAGO IL 606         |" .
"620, 622-631, 633-639                  _ADC ST LOUIS MO 63203      |" .
"640, 641, 644-658, 660-662, 664-668    _ADC KANSAS CITY MO 66340   |" .
"669-679, 739                           _ADC WICHITA KS 67099       |" .
"710-714                                _ADC SHREVEPORT LA 71099    |" .
"716-722, 724-729                       _ADC LITTLE ROCK AR 72098   |" .
"730, 731, 734-738, 748                 _ADC OKLAHOMA CITY OK 730   |" .
"733, 779-789                           _ADC SAN ANTONIO TX 78099   |" .
"740, 741, 743-747, 749                 _ADC TULSA OK 740           |" .
"750-759                                _ADC NORTH TEXAS TX 750     |" .
"760-769, 790-797                       _ADC FT WORTH TX 760        |" .
"770-778                                _ADC NORTH HOUSTON TX 773   |" .
"798, 799, 880, 885                     _ADC EL PASO TX 798         |" .
"800-816                                _ADC DENVER CO 800          |" .
"820, 822-831                           _ADC CHEYENNE WY 820        |" .
"832-834, 836, 837, 979                 _ADC BOISE ID 836           |" .
"835, 838, 980-985, 988-994, 998, 999   _ADC SEATTLE WA 980         |" .
"840-847, 898                           _ADC SALT LAKE CTY UT 840   |" .
"850, 852, 853, 855, 859, 860, 863      _ADC PHOENIX AZ 852         |" .
"856, 857                               _ADC TUCSON AZ 856          |" .
"864, 889-891, 893-895, 897, 961        _ADC LAS VEGAS NV 890       |" .
"865, 870-875, 877-879, 881-884         _ADC ALBUQUERQUE NM 870     |" .
"900-904                                _ADC LOS ANGELES CA 900     |" .
"905-908, 917, 918                      _ADC INDUSTRY CA 917        |" .
"910-916, 930-935                       _ADC SANTA CLARITA CA 914   |" .
"919-921                                _ADC SAN DIEGO CA 920       |" .
"922-925                                _ADC SN BERNARDINO CA 923   |" .
"926-928                                _ADC SANTA ANA CA 926       |" .
"936-939, 945-948, 950, 951             _ADC OAKLAND CA 945         |" .
"940, 941, 943, 944, 949, 954, 955      _ADC PENINSULA CA 941       |" .
"942, 952, 953, 956-960                 _ADC SACRAMENTO CA 956      |" .
"962-966                                _AMF SFO APO/FPO CA 962     |" .
"967, 968                               _ADC HONOLULU HI 967        |" .
"969                                    _ADC OAKLAND CA 945         |" .
"970-978, 986                           _ADC PORTLAND OR 970        |" .
"995-997                                _ADC ANCHORAGE AK 995       |";

$adc = unserialize(MakeADCArray($sADClist));

//foreach ($adc as $key => $value)
//    echo "key = $key, value = $value <br>";

//$db=0;
//
// Step 1 - create an array of only the zipcodes of length 5
//
// 80

$n = count($inLabels);
$nTotalLabels = $n;
if($db) echo "{$n} Labels passed to bundle function....<br>";

for($i=0; $i < $n; $i++) $Zips[$i] = intval(substr($inLabels[$i]['Zip'],0,5));

//
// perform a count of the array values 
//	

$ZipCounts=array_count_values($Zips);

//	
// walk through the input array and pull all matching records where count > 10
//

$nz5=0;

while (list($z,$zc) = each($ZipCounts)){
	if($zc >= 10){
		$NoteText = array('Note'=>"******* Presort ZIP-5 ".$z);
		$NameText = array('Name'=>" ".$zc." Addresses in Bundle ".$z);
		$AddressText = array('Address'=>" ".$nTotalLabels." Total Addresses");
		$CityText = array('City'=>"******* Presort ZIP-5 ".$z."  ");
		$outList[]=array_merge($NoteText,$NameText,$AddressText,$CityText);
		for($i=0; $i<$n; $i++){
			if(intval(substr($inLabels[$i]['Zip'],0,5))==$z) {
				$outList[] = array_merge($inLabels[$i], $NoteText);
				$inLabels[$i]['Zip']= -1; // done
				$nz5++;
			} 
		}
	}
}
if($db) echo "<br>{$nz5} Labels moved to the output list<br>";

//
//  remove processed labels for inLabels array
//

for($i=0; $i<$n; $i++)	if($inLabels[$i]['Zip'] != -1) $inLabels2[] = $inLabels[$i];
unset($inLabels);
$inLabels = $inLabels2;

//
// Pass 2 looking for ZIP3 bundles
//

unset($Zips);
$n = count($inLabels);
if($db) echo "<br>...pass 2 ZIP3..{$n} labels to process<br>";

//print_r($inLabels);

for($i=0; $i < $n; $i++) 
    $Zips[$i] = intval(substr($inLabels[$i]['Zip'],0,3));

//
// perform a count of the array values 
//	

$ZipCounts=array_count_values($Zips);

//	
// walk through the input array and pull all matching records where count > 10
//

$nz3=0;
while (list($z,$zc) = each($ZipCounts)){
	if($zc >= 10){
		$NoteText = array('Note'=>"******* Presort ZIP-3 ".$z);
		$NameText = array('Name'=>" ".$zc." Addresses in Bundle ".$z);
		$AddressText = array('Address'=>" ".$nTotalLabels." Total Addresses");
		$CityText = array('City'=>"******* Presort ZIP-3 ".$z."  ");
		$outList[]=array_merge($NoteText,$NameText,$AddressText,$CityText);
		for($i=0; $i<$n; $i++){
			if(intval(substr($inLabels[$i]['Zip'],0,3))==$z) {
				$outList[] = array_merge($inLabels[$i], $NoteText);
				$inLabels[$i]['Zip']= -1;
				$nz3++;
			} 
		}
	}
}

if($db) echo "{$nz3} Labels moved to the output list...<br>";
unset($inLabels2);
for($i=0; $i<$n; $i++)	
    if($inLabels[$i]['Zip'] != -1) 
        $inLabels2[] = $inLabels[$i];
unset($inLabels);
$inLabels = $inLabels2;

//
// Pass 3 looking for ADC bundles
//

unset($Zips);
$n = count($inLabels);
if($db) echo "...pass 3 ADC..{$n} labels to process\r\n";

for($i=0; $i < $n; $i++)
    if (isset($adc[intval(substr($inLabels[$i]['Zip'],0,3))]))
        $Zips[$i] = $adc[intval(substr($inLabels[$i]['Zip'],0,3))];
//
// perform a count of the array values 
//
unset($ZipCounts);
if (isset($Zips))
	$ZipCounts=array_count_values($Zips);

//	
// walk through the input array and pull all matching records where count > 10
//

$ncounts = 0;
if (isset($ZipCounts))
	$ncounts = count($ZipCounts);
$nadc=0;
if ($ncounts) {
while (list($z,$zc) = each($ZipCounts)){
	if($zc >= 10){
		$NoteText = array('Note'=>"******* Presort ADC ".$z);
		$NameText = array('Name'=>" ".$zc." Addresses in Bundle ADC ".$z);
		$AddressText = array('Address'=>" ".$nTotalLabels." Total Addresses");
		$CityText = array('City'=>"******* Presort ADC ".$z."  ");
		$outList[]=array_merge($NoteText,$NameText,$AddressText,$CityText);
		for($i=0; $i<$n; $i++){
			if($adc[intval(substr($inLabels[$i]['Zip'],0,3))]==$z) {
				$outList[] = array_merge($inLabels[$i], $NoteText);
				$inLabels[$i]['Zip'] = -1; // done
				$nadc++;
			} 
		}
	}
}
}

if($db) echo "{$nadc} Labels moved to the output list<br>";
unset($inLabels2);
for($i=0; $i<$n; $i++)	if($inLabels[$i]['Zip'] != -1) $inLabels2[] = $inLabels[$i];
unset($inLabels);
$inLabels = $inLabels2;

//
// Pass 4 looking for remaining Mixed ADC bundles
//
$nmadc=0;
unset($Zips);
$n = count($inLabels);
$zc = $n;
if($db) echo "...pass 4 Mixed ADC..{$n} labels to process\r\n";
	if ($zc > 0) {
		$NoteText = array('Note'=>"******* Presort MIXED ADC ");
		$NameText = array('Name'=>" ".$zc." Addresses in Bundle");
		$AddressText = array('Address'=>" ".$nTotalLabels." Total Addresses");
		$CityText = array('City'=>"******* Presort MIXED ADC   ");
		$outList[]=array_merge($NoteText,$NameText,$AddressText,$CityText);
		for($i=0; $i<$n; $i++){
			if($db) echo "$i.";
			$outList[] = array_merge($inLabels[$i], $NoteText);
			$nmadc ++;
		}
	}

if($db) echo "{$nmadc} Labels moved to the output list <br>";


//
// return the results
//

if(count($outList) > 0) {
	return($outList);
} else {
	return("FALSE");
}
}


function GenerateLabels(&$pdf, $mode, $iBulkMailPresort, $bToParents, $bOnlyComplete)
{
	// $mode is "indiv" or "fam"

	unset($didFam);

	$sSQL  = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value ";
	$sSQL .= "FROM config_cfg WHERE cfg_section='General'";
	$rsConfig = RunQuery($sSQL);
	if ($rsConfig) {
		while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
			$$cfg_name = $cfg_value;
		}
	}

	$sAdultRole = $sDirRoleHead . "," . $sDirRoleSpouse;
	$sAdultRole = trim($sAdultRole, " ,\t\n\r\0\x0B");
	$aAdultRole = explode(",", $sAdultRole);
	$aAdultRole = array_unique($aAdultRole);		
	sort($aAdultRole);

	$sChildRole = trim($sDirRoleChild, " ,\t\n\r\0\x0B");
	$aChildRole = explode(",", $sChildRole);
	$aChildRole = array_unique($aChildRole);		
	sort($aChildRole);

	$sSQL  = "SELECT * FROM person_per LEFT JOIN family_fam ";
	$sSQL .= "ON person_per.per_fam_ID = family_fam.fam_ID ";
	$sSQL .= "WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ";
	$sSQL .= "ORDER BY fam_Zip, per_LastName, per_FirstName";
	$rsCartItems = RunQuery($sSQL);

	while ($aRow = mysql_fetch_array($rsCartItems))
	{

	// It's possible (but unlikely) that three labels can be generated for a
	// family even when they are grouped.
	// At most one label for all adults
	// At most one label for all children
	// At most one label for all others (for example, another church or a landscape 
	// company)

	$sRowClass = AlternateRowStyle($sRowClass);

	if (($aRow['per_fam_ID'] == 0) && ($mode == "fam")) { 
		// Skip people with no family ID
		continue;
	}

	// Skip if mode is fam and we have already printed labels
	if ($didFam[$aRow['per_fam_ID']] && ($mode == "fam"))
		continue;

	$didFam[$aRow['per_fam_ID']] = 1;

	unset($aName);

	if ($mode == "fam")
		$aName = GroupBySalutation($aRow['per_fam_ID'], $aAdultRole, $aChildRole);
	else {
		$sName = FormatFullName($aRow['per_Title'], $aRow['per_FirstName'], "",
				 $aRow['per_LastName'], $aRow['per_Suffix'], 1);

		$bChild = FALSE;
		foreach ($aChildRole as $value) {
			if ($aRow['per_fmr_ID'] == $value) {
				$bChild = TRUE;
			}
		}

		if ($bChild)
			$aName['child'] = substr($sName,0,33);
		else 
			$aName['indiv'] = substr($sName,0,33);
	}

	foreach($aName as $key => $sName){

		// Bail out if nothing to print
		if ($sName == "Nothing to return")
			continue;

		if ($bToParents && ($key == "child"))
			$sName = "To the parents of:\n" . $sName;

		SelectWhichAddress($sAddress1, $sAddress2, $aRow['per_Address1'], $aRow['per_Address2'], $aRow['fam_Address1'], $aRow['fam_Address2'], false);

		$sCity = SelectWhichInfo($aRow['per_City'], $aRow['fam_City'], False);
		$sState = SelectWhichInfo($aRow['per_State'], $aRow['fam_State'], False);
		$sZip = SelectWhichInfo($aRow['per_Zip'], $aRow['fam_Zip'], False);

		$sAddress = $sAddress1;
		if ($sAddress2 != "")
			$sAddress .= "\n" . $sAddress2;

		if (!$bOnlyComplete || ( (strlen($sAddress)) && strlen($sCity) && strlen($sState) && strlen($sZip) ) )
		{
			$sLabelList[]=array('Name'=>$sName, 'Address'=>$sAddress,'City'=>$sCity,'State'=>$sState,'Zip'=>$sZip); //,'fam_ID'=>$aRow['fam_ID']);
		}
	} // end of foreach loop
	} // end of while loop

	unset($zipLabels);
	if ($iBulkMailPresort) {
		//
		// now sort the label list by presort bundle definitions
		//
		$zipLabels = ZipBundleSort($sLabelList);
		if ($iBulkMailPresort == 2) {
		while(list($i,$sLT)=each($zipLabels)){		
			$pdf->Add_PDF_Label(sprintf("%s\n%s\n%s\n%s, %s %s", 
							$sLT['Note'],$sLT['Name'],$sLT['Address'],
							$sLT['City'], $sLT['State'],$sLT['Zip']));
		} // end while
		} else {
		while(list($i,$sLT)=each($zipLabels)){		
			$pdf->Add_PDF_Label(sprintf("%s\n%s\n%s, %s %s", 
							$sLT['Name'],$sLT['Address'],
							$sLT['City'], $sLT['State'],$sLT['Zip']));
		} // end while
		} // end of if ($BulkMailPresort == 2)		
	} else {
		while(list($i,$sLT)=each($sLabelList)){
			$pdf->Add_PDF_Label(sprintf("%s\n%s\n%s, %s %s",
							$sLT['Name'], $sLT['Address'],
							$sLT['City'], $sLT['State'], $sLT['Zip']));
		} // end while
	} // end of if($iBulkMailPresort)

	if (isset($zipLabels))
		return serialize($zipLabels);
	else
		return serialize($sLabelList);

} // end of function GenerateLabels


// Main body of PHP file begins here

// Standard format

$startcol = FilterInput($_GET["startcol"],'int');
if ($startcol < 1) $startcol = 1;

$startrow = FilterInput($_GET["startrow"],'int');
if ($startrow < 1) $startrow = 1;

$sLabelType = FilterInput($_GET["labeltype"],'char',8);
setcookie("labeltype", $sLabelType, time()+60*60*24*90, "/" );

$pdf = new PDF_Label($sLabelType,$startcol,$startrow);
$pdf->Open();

$sFontInfo = FontFromName($_GET["labelfont"]);
setcookie("labelfont", $_GET["labelfont"], time()+60*60*24*90, "/" );
$sFontSize = $_GET["labelfontsize"];
setcookie("labelfontsize", $sFontSize, time()+60*60*24*90, "/");
$pdf->SetFont($sFontInfo[0],$sFontInfo[1]);

if ($sFontSize == "default")
	$sFontSize = "10";

$pdf->Set_Char_Size($sFontSize);

// Manually add a new page if we're using offsets
if ($startcol > 1 || $startrow > 1)	$pdf->AddPage();

$mode = $_GET["groupbymode"];
setcookie("groupbymode", $mode, time()+60*60*24*90, "/");

$bulkmailpresort = $_GET["bulkmailpresort"];
setcookie("bulkmailpresort", $bulkmailpresort, time()+60*60*24*90, "/");

$bulkmailquiet = $_GET["bulkmailquiet"];
setcookie("bulkmailquiet", $bulkmailquiet, time()+60*60*24*90, "/");

$iBulkCode = 0;
if ($bulkmailpresort)
{
	$iBulkCode = 1;
    if (!$bulkmailquiet)
	   $iBulkCode = 2;
}

$bToParents = ($_GET["cartviewtoparents"] == 1);
setcookie("cartviewtoparents", $_GET["cartviewtoparents"], time()+60*60*24*90, "/");
 
$bOnlyComplete = ($_GET["onlyfull"] == 1);

$sFileType = FilterInput($_GET["filetype"],'char',4);

$aLabelList =	unserialize(
				GenerateLabels($pdf, $mode, $iBulkCode, $bToParents, $bOnlyComplete));

if ($sFileType == "PDF"){

	if ($iPDFOutputType == 1)
		$pdf->Output("Labels-" . date("Ymd-Gis") . ".pdf", true);
	else
		$pdf->Output();

} else { // File Type must be CSV

	$sCSVOutput = "";
	if ($iBulkCode)
		$sCSVOutput .= '"ZipBundle",';

	$sCSVOutput .= '"Greeting","Name","Address1","Address2","City","State","Zip"' . "\n";

	while(list($i,$sLT)=each($aLabelList)){

		if ($iBulkCode)
			$sCSVOutput .= '"' . $sLT['Note'] . '",';

		$iNewline = (strpos($sLT['Name'],"\n"));
		if ($iNewline === FALSE){ // There is no newline character
			$sCSVOutput .= '"","' . $sLT['Name'] . '",';
		}
		else
		{
			$sCSVOutput .=	'"' . substr($sLT['Name'],0,$iNewline) . '",' .
							'"' . substr($sLT['Name'],$iNewline+1) . '",';
		}

		$iNewline = (strpos($sLT['Address'],"\n"));
		if ($iNewline === FALSE){ // There is no newline character
			$sCSVOutput .= '"' . $sLT['Address'] . '","",';
		}
		else
		{
			$sCSVOutput .=	'"' . substr($sLT['Address'],0,$iNewline) . '",' .
							'"' . substr($sLT['Address'],$iNewline+1) . '",';
		}

		$sCSVOutput .=	'"' . $sLT['City']		. '",'	.
						'"' . $sLT['State']		. '",'	.
						'"' . $sLT['Zip']		. '"'	.	"\n";
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=Labels-" . date("Ymd-Gis") . ".csv");
	header("Content-Transfer-Encoding: binary");
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public'); 
	echo $sCSVOutput;

}

exit();
?>
