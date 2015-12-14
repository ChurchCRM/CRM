<?php

require 'Slim/Slim.php';
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

$app->run();

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