<?php
/*******************************************************************************
*
*  filename    : Reports/PDFLabel.php
*  last change : 2003-08-08
*  description : Creates a PDF document containing the addresses of
*                The people in the Cart
*
*  http://www.infocentral.org/
*  Copyright 2003  Jason York
*
*  Portions based on code by LPA (lpasseb@numericable.fr)
*  and Steve Dillon (steved@mad.scientist.com) from www.fpdf.org
*
*  InfoCentral is free software; you can redistribute it and/or modify
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


function GroupBySalutation($famID) {
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
// Simalar logic is applied if mailing to Sunday School children.

	// Read values from config table into local variables
	// we will use directory settings to determine Adults and Youth
	// sDirRoleHead, and sDirRoleSpouse are assumed to be adults
	// sDirRoleChild is assumed to be children
	// **************************************************
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

$adc = array(005=>117,006=>006,007=>006,008=>006,009=>006,010=>010,011=>010,012=>010,013=>010,014=>010,015=>010,016=>010,017=>010,018=>021,019=>021,020=>028,021=>021,022=>021,023=>028,024=>021,025=>028,026=>028,027=>028,028=>028,029=>028,030=>038,031=>038,032=>038,033=>038,034=>038,035=>050,036=>050,037=>050,038=>038,039=>038,040=>040,041=>040,042=>040,043=>040,044=>040,045=>040,046=>040,047=>040,048=>040,049=>040,050=>050,051=>050,052=>050,053=>050,054=>050,055=>021,056=>050,057=>050,058=>050,059=>050,060=>064,061=>064,062=>064,063=>064,064=>064,065=>064,066=>064,067=>064,068=>064,069=>064,070=>070,071=>070,072=>070,073=>070,074=>070,075=>070,076=>070,077=>070,078=>070,079=>070,080=>080,081=>080,082=>080,083=>080,084=>080,085=>070,086=>070,087=>070,088=>070,089=>070,090=>090,091=>090,092=>090,093=>090,094=>090,095=>090,096=>090,097=>090,098=>090,099=>090,100=>100,101=>100,102=>100,103=>110,104=>100,105=>105,106=>105,107=>105,108=>105,109=>105,110=>110,111=>110,112=>110,113=>110,114=>110,115=>117,116=>110,117=>117,118=>117,119=>117,120=>120,121=>120,122=>120,123=>120,124=>120,125=>120,126=>120,127=>120,128=>120,129=>120,130=>130,131=>130,132=>130,133=>130,134=>130,135=>130,136=>130,137=>130,138=>130,139=>130,140=>140,141=>140,142=>140,143=>140,144=>140,145=>140,146=>140,147=>140,148=>140,149=>140,150=>150,151=>150,152=>150,153=>150,154=>150,155=>150,156=>150,157=>150,158=>150,159=>150,160=>150,161=>150,162=>150,163=>150,164=>150,165=>150,166=>150,167=>150,168=>150,169=>170,170=>170,171=>170,172=>170,173=>170,174=>170,175=>170,176=>170,177=>170,178=>170,179=>189,180=>180,181=>180,182=>180,183=>180,184=>180,185=>180,186=>180,187=>180,188=>180,189=>189,190=>190,191=>190,192=>190,193=>189,194=>189,195=>189,196=>189,197=>197,198=>197,199=>197,200=>200,201=>220,202=>202,203=>202,204=>202,205=>202,206=>207,207=>207,208=>207,209=>207,210=>210,211=>210,212=>210,214=>210,215=>210,216=>210,217=>210,218=>210,219=>210,220=>220,221=>220,222=>220,223=>220,224=>230,225=>230,226=>220,227=>220,228=>230,229=>230,230=>230,231=>230,232=>230,233=>230,234=>230,235=>230,236=>230,237=>230,238=>230,239=>230,240=>240,241=>240,242=>240,243=>240,244=>230,245=>240,246=>250,247=>250,248=>250,249=>250,250=>250,251=>250,252=>250,253=>250,254=>210,255=>250,256=>250,257=>250,258=>250,259=>250,260=>150,261=>263,262=>263,263=>263,264=>263,265=>263,266=>263,267=>210,268=>263,270=>270,271=>270,272=>270,273=>270,274=>270,275=>270,276=>270,277=>270,278=>270,279=>270,280=>280,281=>280,282=>280,283=>280,284=>280,285=>270,286=>280,287=>280,288=>280,289=>280,290=>290,291=>290,292=>290,293=>290,294=>290,295=>290,296=>290,297=>280,298=>301,299=>320,300=>301,301=>301,302=>303,303=>303,304=>320,305=>301,306=>301,307=>370,308=>301,309=>301,310=>312,311=>303,312=>312,313=>320,314=>320,315=>320,316=>312,317=>312,318=>312,319=>312,320=>320,321=>320,322=>320,323=>320,324=>320,325=>700,326=>320,327=>327,328=>327,329=>327,330=>332,331=>332,332=>332,334=>327,335=>342,336=>342,337=>342,338=>342,339=>342,340=>332,341=>342,342=>342,344=>320,346=>342,347=>327,349=>327,350=>350,351=>350,352=>350,354=>350,355=>350,356=>350,357=>350,358=>350,359=>350,360=>360,361=>360,362=>350,363=>360,364=>360,365=>700,366=>700,367=>360,368=>360,369=>390,370=>370,371=>370,372=>370,373=>370,374=>370,375=>380,376=>370,377=>370,378=>370,379=>370,380=>380,381=>380,382=>380,383=>380,384=>370,385=>370,386=>380,387=>380,388=>380,389=>380,390=>390,391=>390,392=>390,393=>390,394=>700,395=>700,396=>390,397=>390,398=>312,399=>303,400=>400,401=>400,402=>400,403=>400,404=>400,405=>400,406=>400,407=>400,408=>400,409=>400,410=>450,411=>400,412=>400,413=>400,414=>400,415=>400,416=>400,417=>400,418=>400,420=>400,421=>400,422=>400,423=>400,424=>400,425=>400,426=>400,427=>400,430=>430,431=>430,432=>430,433=>430,434=>430,435=>430,436=>430,437=>430,438=>430,439=>440,440=>440,441=>440,442=>440,443=>440,444=>440,445=>440,446=>440,447=>440,448=>440,449=>440,450=>450,451=>450,452=>450,453=>450,454=>450,455=>450,456=>430,457=>430,458=>450,459=>450,460=>460,461=>460,462=>460,463=>460,464=>460,465=>460,466=>460,467=>460,468=>460,469=>460,470=>450,471=>400,472=>460,473=>460,474=>460,475=>460,476=>400,477=>400,478=>460,479=>460,480=>481,481=>481,482=>481,483=>481,484=>481,485=>481,486=>481,487=>481,488=>481,489=>481,490=>493,491=>493,492=>481,493=>493,494=>493,495=>493,496=>493,497=>493,498=>530,499=>530,500=>500,501=>500,502=>500,503=>500,504=>500,505=>500,506=>500,507=>500,508=>500,509=>500,510=>680,511=>680,512=>680,513=>680,514=>680,515=>680,516=>680,520=>500,521=>500,522=>500,523=>500,524=>500,525=>500,526=>500,527=>500,528=>500,530=>530,531=>530,532=>530,534=>530,535=>530,537=>530,538=>530,539=>530,540=>552,541=>530,542=>530,543=>530,544=>530,545=>530,546=>552,547=>552,548=>552,549=>530,550=>552,551=>552,553=>055,554=>055,555=>055,556=>552,557=>552,558=>552,559=>552,560=>055,561=>055,562=>055,563=>055,564=>055,565=>580,567=>580,570=>570,571=>570,572=>570,573=>570,574=>570,575=>570,576=>570,577=>570,580=>580,581=>580,582=>580,583=>580,584=>580,585=>580,586=>580,587=>580,588=>580,590=>590,591=>590,592=>590,593=>590,594=>590,595=>590,596=>590,597=>590,598=>590,599=>590,600=>601,601=>601,602=>601,603=>601,604=>604,605=>604,606=>606,607=>606,608=>606,609=>604,610=>601,611=>601,612=>500,613=>604,614=>601,615=>601,616=>601,617=>604,618=>604,619=>604,620=>632,622=>632,623=>632,624=>632,625=>632,626=>632,627=>632,628=>632,629=>632,630=>632,631=>632,633=>632,634=>632,635=>632,636=>632,637=>632,638=>632,639=>632,640=>663,641=>663,644=>663,645=>663,646=>663,647=>663,648=>663,649=>663,650=>663,651=>663,652=>663,653=>663,654=>663,655=>663,656=>663,657=>663,658=>663,660=>663,661=>663,662=>663,664=>663,665=>663,666=>663,667=>663,668=>663,669=>670,670=>670,671=>670,672=>670,673=>670,674=>670,675=>670,676=>670,677=>670,678=>670,679=>670,680=>680,681=>680,683=>680,684=>680,685=>680,686=>680,687=>680,688=>680,689=>680,690=>680,691=>680,692=>680,693=>680,700=>700,701=>700,703=>700,704=>700,705=>700,706=>700,707=>700,708=>700,710=>710,711=>710,712=>710,713=>710,714=>710,716=>720,717=>720,718=>720,719=>720,720=>720,721=>720,722=>720,723=>380,724=>720,725=>720,726=>720,727=>720,728=>720,729=>720,730=>730,731=>730,733=>780,734=>730,735=>730,736=>730,737=>730,738=>730,739=>670,740=>740,741=>740,743=>740,744=>740,745=>740,746=>740,747=>740,748=>730,749=>740,750=>750,751=>750,752=>750,753=>750,754=>750,755=>750,756=>750,757=>750,758=>750,759=>750,760=>760,761=>760,762=>760,763=>760,764=>760,765=>760,766=>760,767=>760,768=>760,769=>760,770=>773,771=>773,772=>773,773=>773,774=>773,775=>773,776=>773,777=>773,778=>773,779=>780,780=>780,781=>780,782=>780,783=>780,784=>780,785=>780,786=>780,787=>780,788=>780,789=>780,790=>760,791=>760,792=>760,793=>760,794=>760,795=>760,796=>760,797=>760,798=>798,799=>798,800=>800,801=>800,802=>800,803=>800,804=>800,805=>800,806=>800,807=>800,808=>800,809=>800,810=>800,811=>800,812=>800,813=>800,814=>800,815=>800,816=>800,820=>820,821=>590,822=>820,823=>820,824=>820,825=>820,826=>820,827=>820,828=>820,829=>820,830=>820,831=>820,832=>836,833=>836,834=>836,835=>980,836=>836,837=>836,838=>980,840=>840,841=>840,842=>840,843=>840,844=>840,845=>840,846=>840,847=>840,850=>852,852=>852,853=>852,855=>852,856=>856,857=>856,859=>852,860=>852,863=>852,864=>890,865=>870,870=>870,871=>870,872=>870,873=>870,874=>870,875=>870,877=>870,878=>870,879=>870,880=>798,881=>870,882=>870,883=>870,884=>870,885=>798,889=>890,890=>890,891=>890,893=>890,894=>890,895=>890,897=>890,898=>840,900=>900,901=>900,902=>900,903=>900,904=>900,905=>917,906=>917,907=>917,908=>917,909=>917,910=>914,911=>914,912=>914,913=>914,914=>914,915=>914,916=>914,917=>917,918=>917,919=>920,920=>920,921=>920,922=>923,923=>923,924=>923,925=>923,926=>926,927=>926,928=>926,930=>914,931=>914,932=>914,933=>914,934=>914,935=>914,936=>945,937=>945,938=>945,939=>945,940=>941,941=>941,942=>956,943=>941,944=>941,945=>945,946=>945,947=>945,948=>945,949=>941,950=>945,951=>945,952=>956,953=>956,954=>941,955=>941,956=>956,957=>956,958=>956,959=>956,960=>956,961=>890,962=>962,963=>962,964=>962,965=>962,966=>962,967=>967,968=>967,969=>945,970=>970,971=>970,972=>970,973=>970,974=>970,975=>970,976=>970,977=>970,978=>970,979=>836,981=>980,982=>980,983=>980,984=>980,985=>980,986=>970,988=>980,995=>995,996=>995,997=>995,998=>980,999=>980);
$db=0;
//
// Step 1 - create an array of only the zipcodes of length 5
//
// 80

$n = count($inLabels);
$nTotalLabels = $n;
if($db) echo "{$n} Labels passed to bundle function....<br>";

for($i=0; $i < $n; $i++) $Zips[$i] = substr($inLabels[$i]['Zip'],0,5);

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
			if(substr($inLabels[$i]['Zip'],0,5)==$z) {
				$outList[] = array_merge($inLabels[$i], $NoteText);
				$inLabels[$i]['Zip']="done";
				$nz5++;
			} 
		}
	}
}
if($db) echo "<br>{$nz5} Labels moved to the output list<br>";

//
//  remove processed labels for inLabels array
//

for($i=0; $i<$n; $i++)	if($inLabels[$i]['Zip'] != "done") $inLabels2[] = $inLabels[$i];
unset($inLabels);
$inLabels = $inLabels2;

//
// Pass 2 looking for ZIP3 bundles
//

unset($Zips);
$n = count($inLabels);
if($db) echo "<br>...pass 2 ZIP3..{$n} labels to process<br>";

//print_r($inLabels);

for($i=0; $i < $n; $i++) $Zips[$i] = substr($inLabels[$i]['Zip'],0,3);

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
			if(substr($inLabels[$i]['Zip'],0,3)==$z) {
				$outList[] = array_merge($inLabels[$i], $NoteText);
				$inLabels[$i]['Zip']="done";
				$nz3++;
			} 
		}
	}
}

if($db) echo "{$nz3} Labels moved to the output list...<br>";
unset($inLabels2);
for($i=0; $i<$n; $i++)	if($inLabels[$i]['Zip'] != "done") $inLabels2[] = $inLabels[$i];
unset($inLabels);
$inLabels = $inLabels2;

//
// Pass 3 looking for ADC bundles
//

unset($Zips);
$n = count($inLabels);
if($db) echo "...pass 3 ADC..{$n} labels to process\r\n";

for($i=0; $i < $n; $i++) $Zips[$i] = $adc[substr($inLabels[$i]['Zip'],0,3)];

//
// perform a count of the array values 
//
	
$ZipCounts=array_count_values($Zips);

//	
// walk through the input array and pull all matching records where count > 10
//

$ncounts = count($ZipCounts);
$nadc=0;
while (list($z,$zc) = each($ZipCounts)){
	if($zc >= 10){
		$NoteText = array('Note'=>"******* Presort ADC ".$z);
		$NameText = array('Name'=>" ".$zc." Addresses in Bundle ADC ".$z);
		$AddressText = array('Address'=>" ".$nTotalLabels." Total Addresses");
		$CityText = array('City'=>"******* Presort ADC ".$z."  ");
		$outList[]=array_merge($NoteText,$NameText,$AddressText,$CityText);
		for($i=0; $i<$n; $i++){
			if($adc[substr($inLabels[$i]['Zip'],0,3)]==$z) {
				$outList[] = array_merge($inLabels[$i], $NoteText);
				$inLabels[$i]['Zip'] = "done";
				$nadc++;
			} 
		}
	}
}

if($db) echo "{$nadc} Labels moved to the output list<br>";
unset($inLabels2);
for($i=0; $i<$n; $i++)	if($inLabels[$i]['Zip'] != "done") $inLabels2[] = $inLabels[$i];
unset($inLabels);
$inLabels = $inLabels2;

//
// Pass 3 looking for remaining Mixed ADC bundles
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


function GenerateLabels(&$pdf, $mode, $iBulkMailPresort, $bOnlyComplete = false)
{
	// $mode is "indiv" or "fam"

	$sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY fam_Zip, per_LastName, per_FirstName";
	$rsCartItems = RunQuery($sSQL);

	while ($aRow = mysql_fetch_array($rsCartItems))
	{

	// It's possible (but unlikely) that three labels can be generated for a
	// family even when they are grouped.
	// At most one label for all adults
	// At most one label for all children
	// At most one label for all others (for example, another church or the lansdscape 
	// company)

	$sUniqueKey = $aRow['per_fam_ID'];

	$sRowClass = AlternateRowStyle($sRowClass);

	if (($aRow['per_fam_ID'] == 0) && ($mode == "fam")) { 
		// Skip people with no family ID
		continue;
	}

	// Skip if mode is fam and we have already printed labels
	if ($didFam[$sUniqueKey] && ($mode == "fam"))
		continue;

	$didFam[$sUniqueKey] = 1;

	unset($aName);
	$sName = "";
	if ($mode == "fam")
		$aName = GroupBySalutation($aRow['per_fam_ID']);
	else {
		$sName = FormatFullName($aRow['per_Title'], $aRow['per_FirstName'], "", $aRow['per_LastName'], $aRow['per_Suffix'], 1);
		$aName['indiv'] = substr($sName,0,33);
	}


	foreach($aName as $sName){

		// Bail out if nothing to print
		if ($sName == "Nothing to return")
			continue;

		SelectWhichAddress($sAddress1, $sAddress2, $aRow['per_Address1'], $aRow['per_Address2'], $aRow['fam_Address1'], $aRow['fam_Address2'], false);

		$sCity = SelectWhichInfo($aRow['per_City'], $aRow['fam_City'], False);
		$sState = SelectWhichInfo($aRow['per_State'], $aRow['fam_State'], False);
		$sZip = SelectWhichInfo($aRow['per_Zip'], $aRow['fam_Zip'], False);

		$sAddress = $sAddress1;
		if ($sAddress2 != "")
			$sAddress .= "\n" . $sAddress2;

		if (!$bOnlyComplete || ( (strlen($sAddress)) && strlen($sCity) && strlen($sState) && strlen($sZip) ) )
		{
			$sLabelList[]=array('Name'=>$sName, 'Address'=>$sAddress,'City'=>$sCity,'State'=>$sState,'Zip'=>$sZip);
		}
	} // end of foreach loop
	} // end of while loop

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

} // end of function GenerateLabels


// Main body of PHP file begins here

// Standard format

$startcol = FilterInput($_GET["startcol"],'int');
if ($startcol < 1) $startcol = 1;

$startrow = FilterInput($_GET["startrow"],'int');
if ($startrow < 1) $startrow = 1;

$sLabelType = FilterInput($_GET["cartviewlabeltype"],'char',8);
setcookie("cartviewlabeltype", $sLabelType, time()+60*60*24*90, "/" );

$pdf = new PDF_Label($sLabelType,$startcol,$startrow);
$pdf->Open();

$sFontInfo = FontFromName($_GET["cartviewlabelfont"]);
setcookie("cartviewlabelfont", $_GET["cartviewlabelfont"], time()+60*60*24*90, "/" );
$sFontSize = $_GET["cartviewlabelfontsize"];
setcookie("cartviewlabelfontsize", $sFontSize, time()+60*60*24*90, "/");
$pdf->SetFont($sFontInfo[0],$sFontInfo[1]);

if ($sFontSize == "default")
	$sFontSize = "10";

$pdf->Set_Char_Size($sFontSize);

// Manually add a new page if we're using offsets
if ($startcol > 1 || $startrow > 1)	$pdf->AddPage();

$mode = $_GET["cartviewgroupbymode"];
setcookie("cartviewgroupbymode", $mode, time()+60*60*24*90, "/");

$bulkmailpresort = $_GET["cartviewbulkmailpresort"];
setcookie("cartviewbulkmailpresort", $bulkmailpresort, time()+60*60*24*90, "/");

$iBulkCode = 0;
if ($bulkmailpresort == "without")
	$iBulkCode = 1;
elseif ($bulkmailpresort == "with")
	$iBulkCode = 2;

$bOnlyComplete = ($_GET["onlyfull"] == 1);

GenerateLabels($pdf, $mode, $iBulkCode, $bOnlyComplete);

if ($iPDFOutputType == 1)
	$pdf->Output("Labels-" . date("Ymd-Gis") . ".pdf", true);
else
	$pdf->Output();
?>
