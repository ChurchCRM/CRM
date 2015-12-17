<?php

require 'Slim/Slim.php';

use Slim\Slim;
Slim::registerAutoloader();


require '../Include/Config.php';
require '../Include/Functions.php';

//Security
if (!isset($_SESSION['iUserID']))
{
    Redirect("Default.php");
    exit;
}

$app = new Slim();
$app->contentType('application/json');

$app->group('/data/seed',function () use ($app) {
	$app->post('/families',function () use ($app) {
		$request = $app->request();
		$body = $request->getBody();
		$input = json_decode($body);	
		$families=$input->families;
		generateFamilies($families);
	});
	$app->post('/sundaySchoolClasses',function () use ($app) {
		$request = $app->request();
		$body = $request->getBody();
		$input = json_decode($body);	
		$classes=$input->classes;
		$childrenPerTeacher=$input->childrenPerTeacher;
		generateSundaySchoolClasses($classes,$childrenPerTeacher);
	});
	$app->post('/deposits',function () use ($app) {
		$request = $app->request();
		$body = $request->getBody();
		$input = json_decode($body);	
		$deposits=$input->deposits;
		$averagedepositvalue=$input->averagedepositvalue;
		generateDeposits($deposits,$averagedepositvalue);
	});
	$app->post('/events',function () use ($app) {
		$request = $app->request();
		$body = $request->getBody();
		$input = json_decode($body);	
		$events=$input->events;
		$averageAttendance=$input->averageAttendance;
		generateEvents($events,$averageAttendance);
	});
	$app->post('/fundraisers',function () use ($app) {
		$request = $app->request();
		$body = $request->getBody();
		$input = json_decode($body);	
		$fundraisers=$input->fundraisers;
		$averageItems=$input->averageItems;
		$averageItemPrice=$input->averageItemPrice;
		generateFundRaisers($fundraisers,$averageItems,$averageItemPrice);
	});

});



$app->get('/members/list/search/:query', 'searchMembers');

$app->group('/deposits',function () use ($app) {
	$app->get('/','listDeposits');
	$app->get('/:id','listDeposits')->conditions(array('id' => '[0-9]+'));
	$app->get('/:id/payments','listPayments')->conditions(array('id' => '[0-9]+'));
	
});


$app->group('/payments',function () use ($app) {
	$app->get('/','listPayments');
	$app->get('/:id','listPayments')->conditions(array('id' => '[0-9]+'));
	
});

$app->run();


function getPerson($rs,&$personPointer)
{
	$user=$rs[$personPointer]->user;
	$personPointer += 1;
	return $user;
}

function sanitize($str)
{
	return str_replace("'","",$str);
}

function insertPerson($user)
{
	$sSQL = "INSERT INTO person_per 
	(per_Title, 
	per_FirstName, 
	per_MiddleName, 
	per_LastName, 
	per_Suffix, 
	per_Gender, 
	per_Address1, 
	per_Address2, 
	per_City, 
	per_State, 
	per_Zip, 
	per_Country, 
	per_HomePhone, 
	per_WorkPhone, 
	per_CellPhone, 
	per_Email, 
	per_WorkEmail, 
	per_BirthMonth, 
	per_BirthDay, 
	per_BirthYear, 
	per_Envelope, 
	per_fam_ID, 
	per_fmr_ID, 
	per_MembershipDate, 
	per_cls_ID, 
	per_DateEntered, 
	per_EnteredBy, 
	per_FriendDate, 
	per_Flags ) 
	VALUES ('" . 
	sanitize($user->name->title) . "','" . 
	sanitize($user->name->first) . "',NULL,'" . 
	sanitize($user->name->last) . "',NULL,'" . 
	sanitize($user->gender) . "','" . 
	sanitize($user->location->street) . "',NULL,'" . 
	sanitize($user->location->city) . "','" . 
	sanitize($user->location->state) . "','" . 
	sanitize($user->location->zip) . "','USA','" . 
	sanitize($user->phone) . "',NULL,'" . 
	sanitize($user->cell) . "','" . 
	sanitize($user->email) . "',NULL," . 
	date('m', $user->dob) . "," .
	date('d', $user->dob) . "," . 
	date('Y', $user->dob) . ",NULL,'".
	sanitize($user->famID) ."',". 
	sanitize($user->per_fmr_id) .","."\"" . 
	date('Y-m-d', $user->registered) . 
	"\"". ",1,'" . 
	date("YmdHis") . 
	"'," . 
	sanitize($_SESSION['iUserID']) . ",";
	
	if ( strlen($dFriendDate) > 0 )
	$sSQL .= "\"" . $dFriendDate . "\"";
	else
	$sSQL .= "NULL";
	$sSQL .= ", 0" ;
	$sSQL .= ")";
	$bGetKeyBack = True;
	RunQuery($sSQL);
	// If this is a new person, get the key back and insert a blank row into the person_custom table
	if ($bGetKeyBack)
	{
		$sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
		$rsPersonID = RunQuery($sSQL);
		extract(mysql_fetch_array($rsPersonID));
		$sSQL = "INSERT INTO `person_custom` (`per_ID`) VALUES ('" . $iPersonID . "')";
		RunQuery($sSQL);
	}

}

function insertFamily($user)
{
$dWeddingDate="NULL";
$iCanvasser=0;
$nLatitude=0;
$nLongitude=0;
$nEnvelope=0;   
$sSQL = "INSERT INTO family_fam (
						fam_Name, 
						fam_Address1, 
						fam_Address2, 
						fam_City, 
						fam_State, 
						fam_Zip, 
						fam_Country, 
						fam_HomePhone, 
						fam_WorkPhone, 
						fam_CellPhone, 
						fam_Email, 
						fam_WeddingDate, 
						fam_DateEntered, 
						fam_EnteredBy, 
						fam_SendNewsLetter,
						fam_OkToCanvass,
						fam_Canvasser,
						fam_Latitude,
						fam_Longitude,
						fam_Envelope)
					VALUES ('"							. 
						$user->name->last				. "','" . 
						$user->location->street				. "','" . 
						$sAddress2				. "','" . 
						$user->location->city				. "','" . 
						$user->location->state					. "','" . 
						$user->location->zip					. "','" . 
						$sCountry				. "','" . 
						$sHomePhone				. "','" . 
						$sWorkPhone				. "','" . 
						$sCellPhone				. "','" . 
						$sEmail					. "'," . 
						$dWeddingDate			. ",'" . 
						date("YmdHis")			. "'," . 
						$_SESSION['iUserID']	. "," . 
						"FALSE," . 
						"FALSE,'" .
						$iCanvasser				. "'," .
						$nLatitude				. "," .
						$nLongitude				. "," .
						$nEnvelope              . ")";
				RunQuery($sSQL);
				$sSQL = "SELECT MAX(fam_ID) AS iFamilyID FROM family_fam";
				
			$rsLastEntry = RunQuery($sSQL);
			extract(mysql_fetch_array($rsLastEntry));
			return $iFamilyID;

}

function generateFamilies($families)
{
		$kidsPerFamily=3;
		$kidsdev=3;
		$personPointer=1;
		$count = $families * ($kidsPerFamily+$kidsdev+2);
		$response = file_get_contents("http://api.randomuser.me/?nat=US&results=".$count);
		$data=json_decode($response);
		$rs = $data->results;
		$rTotalHoh=0;
		$rTotalSpouse=0;
		$rTotalChildren=0;

		for ($i=0;$i<$families;$i++)
		{	
			$hoh = getPerson($rs,$personPointer);
			$FamilyID = insertFamily($hoh);
			$familyName = $hoh->name->last;
			$hoh->famID = $FamilyID;
			$hoh->per_fmr_id = 1;
			
			$spouse = getPerson($rs,$personPointer);
			$spouse->name->last = $familyName;
			$spouse->famID = $FamilyID;
			$spouse->per_fmr_id = 2;
			
			insertPerson($hoh);
			$rTotalHoh +=1;
			insertPerson($spouse);
			$rTotalSpouse+=1;
			
			#$thisFamChildren = stats_rand_gen_normal ($kidsPerFamily, $stddev);
			$thisFamChildren = rand($kidsPerFamily-$kidsdev,$kidsPerFamily+$kidsdev);
			
			for ($y=0;$y<$thisFamChildren;$y++)
			{
				$child = getPerson($rs,$personPointer);
				$child->name->last = $familyName;
				$child->famID = $FamilyID;
				$child->per_fmr_id = 3;
				insertPerson($child);
				$rTotalChildren+=1;
			}
			
		}
		 echo '{"random.me response":'.$response.'"families created": '.$families.',"heads of household created": '.$rTotalHoh.', "spouses created":'.$rTotalSpouse.', "children created":'.$rTotalChildren.'}';

}

function generateSundaySchoolClasses($classes,$childrenPerTeacher)
{

	echo '{"status":"Sunday School Seed Data Not Implemented"}';

}

function generateEvents($events,$averageAttendance)
{

	echo '{"status":"Events Seed Data Not Implemented"}';

}

function generateDeposits($deposits,$averagedepositvalue)
{
	echo '{"status":"Deposits Seed Data Not Implemented"}';
}

function generateFundRaisers($fundraisers,$averageItems,$averageItemPrice)
{
	echo '{"status":"Fundraisers Seed Data Not Implemented"}';
}

function listDeposits($id) {

	$sSQL = "SELECT dep_ID, dep_Date, dep_Comment, dep_Closed, dep_Type FROM deposit_dep";
	if ($id)
	{
			$sSQL.=" WHERE dep_ID = ".$id;
	}
	$rsDep = RunQuery($sSQL);
	$return = array();
	while ($aRow = mysql_fetch_array($rsDep))
	{
		extract ($aRow);
		$values['dep_ID']=$dep_ID;
		$values['dep_Date']=$dep_Date;
		$values['dep_Comment']=$dep_Comment;
		$values['dep_Closed']=$dep_Closed;
		$values['dep_Type']=$dep_Type;
		array_push($return,$values);
	}
	echo '{"deposits": ' . json_encode($return) . '}';
}

function listPayments($id) {
	$sSQL = "SELECT * from pledge_plg";
	if ($id)
	{
			$sSQL.=" WHERE plg_plgID = ".$id;
	}
	$rsDep = RunQuery($sSQL);
	$return = array();
	while ($aRow = mysql_fetch_array($rsDep))
	{
		extract ($aRow);
		$values['plg_plgID']=$plg_plgID;
		$values['plg_FamID']=$plg_FamID;
		$values['plg_FYID']=$plg_FYID;
		$values['plg_date']=$plg_date;
		$values['plg_amount']=$plg_amount;
		$values['plg_schedule']=$plg_schedule;
		$values['plg_method']=$plg_method;
		$values['plg_comment']=$plg_comment;
		$values['plg_DateLastEdited']=$plg_DateLastEdited;
		$values['plg_EditedBy']=$plg_EditedBy;
		$values['plg_PledgeOrPayment']=$plg_PledgeOrPayment;
		$values['plg_fundID']=$plg_fundID;
		$values['plg_depID']=$plg_depID;
		$values['plg_CheckNo']=$plg_CheckNo;
		$values['plg_Problem']=$plg_Problem;
		$values['plg_scanString']=$plg_scanString;
		$values['plg_aut_ID']=$plg_aut_ID;
		$values['plg_aut_Cleared']=$plg_aut_Cleared;
		$values['plg_aut_ResultID']=$plg_aut_ResultID;
		$values['plg_NonDeductible']=$plg_NonDeductible;
		$values['plg_GroupKey']=$plg_GroupKey;

		array_push($return,$values);
	}
	echo '{"pledges": ' . json_encode($return) . '}';
	
}

function searchMembers($query) {
        $sSearchTerm = $query;
		$sSearchType = "person";
        $fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM `person_per` WHERE per_FirstName LIKE \'%'.$sSearchTerm.'%\' OR per_LastName LIKE \'%'.$sSearchTerm.'%\' OR per_Email LIKE \'%'.$sSearchTerm.'%\' OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%'.$sSearchTerm.'%\' LIMIT 15';
        $result=mysql_query($fetch);

        $return = array();
        while($row=mysql_fetch_array($result)) {
            if($sSearchType=="person") {
                $values['id']=$row['per_ID'];
                $values['famID']=$row['per_fam_ID'];
                $values['per_FirstName']=$row['per_FirstName'];
                $values['per_LastName']=$row['per_LastName'];
                $values['value']=$row['per_FirstName']." ".$row['per_LastName'];
            }

            array_push($return,$values);
        }

    echo '{"members": ' . json_encode($return) . '}';
}

?>