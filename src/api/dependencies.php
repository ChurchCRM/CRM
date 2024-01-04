<?php

use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;
use Symfony\Component\DependencyInjection\ContainerInterface;

// DIC configuration
/** @var ContainerInterface $container */

$container->set('PersonService', new PersonService());

$container->set('GroupService', new GroupService());

$container->set('FinancialService', new FinancialService());

$container->set('SystemService', new SystemService());
