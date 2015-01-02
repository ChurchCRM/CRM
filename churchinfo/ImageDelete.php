<?php

require "Include/Config.php";
require "Include/Functions.php";
require "Include/PersonFunctions.php";

$redirectURL="Menu.php";

echo "hi";

if (isset($_GET['PersonID'])) {
    $personId = FilterInput($_GET["PersonID"],'int');
    deletePersonPhoto($personId);
    $redirectURL="PersonView.php?PersonID=".$personId;
}

Redirect($redirectURL."&ProfileImageDeleted=true");

?>