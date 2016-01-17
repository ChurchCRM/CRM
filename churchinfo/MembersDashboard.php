<?php
/**
 * Created by IntelliJ IDEA.
 * User: George Dawoud
 * Date: 1/17/2016
 * Time: 8:01 AM
 */
// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/PersonFunctions.php';

require_once "service/DashboardService.php";

// Set the page title
$sPageTitle = "Members Dashboard";

require 'Include/Header.php';

$dashboardService = new DashboardService();

var_dump($dashboardService->getPersonStats());

echo "<p/>";

var_dump($dashboardService->getFamilyStats());

echo "<p/>";
var_dump($dashboardService->getSundaySchoolStats());

echo "<p/>";
var_dump($dashboardService->getDemographic());

require 'Include/Footer.php';
?>
