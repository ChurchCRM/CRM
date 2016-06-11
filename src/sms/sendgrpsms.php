<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "../Include/Config.php";
$hasSession = isset($_SESSION['iUserID']);
$redirectTo = ($hasSession) ? '/menu' : '/login';
if (!$hasSession)
{
  // Must show login form if no session
  require '../Login.php';
}

$ozeki_user = "church";
$ozeki_password = "Pass01";
$ozeki_url = "http://127.0.0.1:13013/cgi-bin/sendsms?";

########################################################
# Functions used to send the SMS message
########################################################
function httpRequest($url){
    $pattern = "/http...([0-9a-zA-Z-.]*).([0-9]*).(.*)/";
    preg_match($pattern,$url,$args);
    $in = "";
    $fp = fsockopen("$args[1]", $args[2], $errno, $errstr, 30);
    if (!$fp) {
       return("$errstr ($errno)");
    } else {
        $out = "GET /$args[3] HTTP/1.1\r\n";
        $out .= "Host: $args[1]:$args[2]\r\n";
        $out .= "User-agent: Ozeki PHP client\r\n";
        $out .= "Accept: */*\r\n";
        $out .= "Connection: Close\r\n\r\n";

        fwrite($fp, $out);
        while (!feof($fp)) {
           $in.=fgets($fp, 128);
        }
    }
    fclose($fp);
    return($in);
}



function ozekiSend($phone, $msg, $debug=false){
      global $ozeki_user,$ozeki_password,$ozeki_url;

      $url = 'user='.$ozeki_user;
      $url.= '&pass='.$ozeki_password;
      $url.= '&text='.urlencode($msg);
      $url.= '&to='.$phone;
      $url.= '&from=CCCTassia';
      $urltouse =  $ozeki_url.$url;
      if ($debug) { echo "Request: <br>$urltouse<br><br>"; }

      //Open the URL to send the message
      $response = httpRequest($urltouse);
      if ($debug) {
           echo "Response: <br><pre>".
           str_replace(array("<",">"),array("&lt;","&gt;"),$response).
           "</pre><br>"; }

      return($response);
}
$grpstatus = isset($_POST['grp_ID']);
$group = $_POST['grp_ID'];
if(!$grpstatus) header ("Location: sendgroup.php");
//die();
$message = $_POST['messagetext'];
$message = addslashes($message);
$query = "select per_CellPhone,per_ID from person_per where per_ID  in (select p2g2r_per_ID from person2group2role_p2g2r where p2g2r_grp_ID='$group')";
//$query2 = "insert into church.messageout(receiver,msg) values (
$result = mysql_query($query);
if(!$result) die(mysql_error());
while ($row = mysql_fetch_array($result)) {
     $mobile=$row['per_CellPhone'];
$mobile = str_replace("(","",$mobile);
$mobile = str_replace(")","",$mobile);
$mobile = str_replace("-","",$mobile);
$mobile = str_replace(" ","",$mobile);
$tosend = "tosend";
$query2 = "insert into messageout (receiver,msg,status) values ('$mobile','$message','$tosend')";
$result2 = mysql_query($query2);
if (!$result2) die(mysql_error());
}
//sleep(2);
//send messages
$query3 = "select * from messageout where status = 'tosend'";
$result3 = mysql_query($query3);
if (!$result3) die (mysql_error());
while ($row = mysql_fetch_array($result3)) {
$timesend = date("Y/m/d h:i:s");
$id = $row['id'];
//echo $id;
$query4 = "update messageout set status='send',senttime='$timesend' where id='$id'";
$phonenum = $row['receiver'];
$message = mysql_real_escape_string($row['msg']);
$debug = false;
//ozekiSend($phonenum,$message,$debug);
$result4 = mysql_query($query4);
if(!$result4) die (mysql_error());
}
if(!$grpstatus) header ("Location: sendgroup.php");
//die();
$send=1;
header ("Location: sendgroup.php?send=$send&group=$group");
?>
