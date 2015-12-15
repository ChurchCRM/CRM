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