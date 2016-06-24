<?php


// Services
require_once "../Service/PersonService.php";
require_once "../Service/FamilyService.php";
require_once "../Service/GroupService.php";
require_once '../Service/NoteService.php';

require_once "../Service/FinancialService.php";
require_once "../Service/ReportingService.php";

require_once "../Service/DataSeedService.php";
require_once '../Service/SystemService.php';

// DIC configuration

$container['PersonService'] = new PersonService();
$container['FamilyService'] = new FamilyService();
$container['GroupService'] = new GroupService();
$container['NoteService'] = new NoteService();

$container['FinancialService'] = new FinancialService();
$container['ReportingService'] = new ReportingService();

$container['DataSeedService'] = new DataSeedService();
$container['SystemService'] = new SystemService();

