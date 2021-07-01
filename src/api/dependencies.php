<?php


use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;

// DIC configuration

$container['PersonService'] = new PersonService();
$container['GroupService'] = new GroupService();

$container['FinancialService'] = new FinancialService();

$container['SystemService'] = new SystemService();
