<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Service\PersonService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/self-register', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $familyQuery = fn () => FamilyQuery::create()->filterByEnteredBy(Person::SELF_REGISTER);
    // Members of a self-registered family count under their family.
    $individualQuery = fn () => PersonService::selfRegisteredPersonQuery();

    $pendingCount = $familyQuery()->filterByNeedsReview(true)->count()
        + $individualQuery()->filterByNeedsReview(true)->count();
    $approvedCount = $familyQuery()->filterByNeedsReview(false)->count()
        + $individualQuery()->filterByNeedsReview(false)->count();

    $pageArgs = [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('Self Registrations'),
        'sPageSubtitle'   => gettext('Review new families and individuals who signed up on your public registration form'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Self Registrations')],
        ]),
        'pendingCount'    => $pendingCount,
        'approvedCount'   => $approvedCount,
        'selfRegEnabled'  => SystemConfig::getBooleanValue('bEnableSelfRegistration'),
        'isAdmin'         => AuthenticationManager::getCurrentUser()->isAdmin(),
    ];

    return $renderer->render($response, 'self-register.php', $pageArgs);
});
