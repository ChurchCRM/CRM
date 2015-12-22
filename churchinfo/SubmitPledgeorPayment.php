<?php

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Security
if (!isset($_SESSION['iUserID']))
{
	Redirect("Default.php");
	exit;
}

echo "out";

?>