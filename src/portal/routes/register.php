<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\EmailSignInService;
use ChurchCRM\Slim\Middleware\Request\Setting\PublicRegistrationAuthMiddleware;
use ChurchCRM\Utils\AddressDefaults;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/register', function (Request $request, Response $response): Response {
    PortalTwig::preparePage();

    $familyRoles = [];
    foreach (ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find() as $role) {
        $familyRoles[] = ['id' => $role->getOptionId(), 'name' => $role->getOptionName()];
    }

    return PortalTwig::render($response, 'register/index.html.twig', [
        'pageTitle' => gettext('Register your family'),
        'guestPage' => 'register',
        'memberSignInEnabled' => EmailSignInService::isEnabled(),
        'selfRegistrationEnabled' => true,
        'familyRoles' => $familyRoles,
        'phoneFormat' => SystemConfig::getValue('sPhoneFormat'),
        'phoneFormatCell' => SystemConfig::getValue('sPhoneFormatCell'),
        'defaults' => [
            'city' => AddressDefaults::city(),
            'state' => AddressDefaults::state(),
            'zip' => AddressDefaults::zip(),
            'country' => AddressDefaults::country(),
        ],
    ]);
})->add(PublicRegistrationAuthMiddleware::class);
