<?php

//Include the function library
require "Include/Config.php";

use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\InputUtils;

$iFamilyID = InputUtils::LegacyFilterInput($_GET['FamilyID'], 'int');
RedirectUtils::Redirect("v2/family/" . $iFamilyID . "/view");
