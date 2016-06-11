<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
include("../Include/Config.php");

$hasSession = isset($_SESSION['iUserID']);
$redirectTo = ($hasSession) ? '/menu' : '/login';
if (!$hasSession)
{
  // Must show login form if no session
  require '../Login.php';
die();
}


$item_per_page = 10;
//sanitize post value
if(isset($_POST["page"])){
	$page_number = filter_var($_POST["page"], FILTER_SANITIZE_NUMBER_INT, FILTER_FLAG_STRIP_HIGH);
	if(!is_numeric($page_number)){die('Invalid page number!');} //incase of invalid page number
}else{
	$page_number = 1;
}

//get current starting point of records
$position = (($page_number-1) * $item_per_page);

//Limit our results within a specified range. 
$sql = "select sender,msg,receivedtime from messagein order by receivedtime desc ";
$sql .= "LIMIT $position, $item_per_page";
$results = mysql_query($sql);
//echo $results;

//output results from database
//echo '<ul class="page_result">';
echo '<div style="overflow-x:auto;">';
echo "<table border='1'><col width='100'><col width='380'><col width='180'><tr><th>Sender</th><th>Message</th><th>Received Time</th></tr>";
while($row = mysql_fetch_array($results))
{
echo "<tr><td>" . $row["sender"] . "</td><td>" .$row["msg"]."</td><td>".$row["receivedtime"]."</td></tr>";
}
//echo '</ul>';
?>

