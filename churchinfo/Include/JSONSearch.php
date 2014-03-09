<?php
//this page written by Stead Halstead on 8/5/10
require "../Include/Config.php";
require "../Include/Functions.php";

//security included, because we aren't running the normal 
if (!isset($_SESSION['iUserID']))
{
	Redirect("Default.php");
	exit;
}

$type=$_REQUEST['type'];
$search = addslashes($_REQUEST['term']);

//we can add more types in the future to allow this single file to handle other requests
if($type=="person") {
	$fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM `person_per` WHERE per_FirstName LIKE \'%'.$search.'%\' OR per_LastName LIKE \'%'.$search.'%\'  OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%'.$search.'%\' LIMIT 15';
}

$result=mysql_query($fetch);

$return_arr = array();
while($row=mysql_fetch_array($result)) {
if($type=="person") {
	$stuff['id']=$row['per_ID'];
	$stuff['famID']=$row['per_fam_ID'];
	$stuff['per_FirstName']=$row['per_FirstName'];
	$stuff['per_LastName']=$row['per_LastName'];
	$stuff['value']=$row['per_FirstName']." ".$row['per_LastName'];
}

array_push($return_arr,$stuff);

}
//echo "<h1>$search</h1>";
echo json_encode($return_arr);

?>
