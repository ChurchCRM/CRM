<?php
/**
 * CAPTCHA image server
 * 
 */
session_name ("privatedemo");
session_start ();

require_once ( './class.captcha_x.php');
$server = &new captcha_x ();
$server->handle_request ();
?>
