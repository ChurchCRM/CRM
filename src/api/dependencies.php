<?php


use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Service\GroupService;

use ChurchCRM\Service\CalendarService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\ReportingService;

use ChurchCRM\Service\DataSeedService;
use ChurchCRM\Service\SystemService;

// DIC configuration

$container['PersonService'] = new PersonService();
$container['FamilyService'] = new FamilyService();
$container['GroupService'] = new GroupService();

$container['FinancialService'] = new FinancialService();
$container['ReportingService'] = new ReportingService();

$container['DataSeedService'] = new DataSeedService();
$container['SystemService'] = new SystemService();

$container['CalendarService'] = new CalendarService();

