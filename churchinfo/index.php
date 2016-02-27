<?php
require 'Include/Config.php';
require 'Include/Functions.php';

if (!isset($_SESSION['iUserID']))
{
    Redirect("Login.php");
} else {
    Redirect("Menu.php");
}
exit;
