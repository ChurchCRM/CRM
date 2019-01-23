<?php

use ChurchCRM\Deposit;
use ChurchCRM\DepositQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

$app->group('/financereports', function () {
    
    $this->get('/taxreport', function ($request, $response, $args) {
        $taxreport = TaxReport_V2::create()
                ->filterByDate("2018-01-01", "2018-12-31")
                ->GetPDF();
        
    });

})->add(new FinanceRoleAuthMiddleware());
