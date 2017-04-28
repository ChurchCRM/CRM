<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require "../Include/Config.php";
require "../Include/Functions.php";

$sPageTitle = gettext("Sent Text Messages");
require "../Include/Header.php"; 
include ("fetchersend.php");




require "../Include/Footer.php"; 
?>
