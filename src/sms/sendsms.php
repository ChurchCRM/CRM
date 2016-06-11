<?php

require "../Include/Config.php";
$hasSession = isset($_SESSION['iUserID']);
$redirectTo = ($hasSession) ? '/menu' : '/login';
if (!$hasSession)
{
  // Must show login form if no session
  require '../Login.php';
}
error_reporting(E_ALL);
########################################################
# Login information for the SMS Gateway
########################################################

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

########################################################
# GET data from sendsms.html
########################################################

$phonenum = $_POST['phonenum'];
$message = $_POST['messagetext'];
$debug = true;

ozekiSend($phonenum,$message,$debug);

?>
