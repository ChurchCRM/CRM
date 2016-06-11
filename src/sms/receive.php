<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require "../Include/Config.php";
date_default_timezone_set('Africa/Nairobi');
$sender = $_GET["ORIGINATOR"];
$receiver = $_GET["RECEIVER"];
$msg = $_GET["SMS"];
$msg = addslashes($msg);
$source_prv = $_GET["SOURCE_PRV"];
$receivedtime = date('Y-m-d H:i:s');

//echo "Sender is".$sender;
//echo "Receiver is".$receiver;
//echo "SMS is".$msg;
//echo "Source is".$source_prv;
//echo "Received time is".$receivedtime;

        
$query = "insert into messagein(sender,receiver,msg,receivedtime) values ('$sender', '$receiver', '$msg', '$receivedtime')";
$result = mysql_query($query);
if(!$result) die(mysql_error());
?>
