<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/self-register', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $familyCount = FamilyQuery::create()
        ->filterByEnteredBy(Person::SELF_REGISTER)
        ->count();
    // Everyone waiting for review who is not already counted as part of a
    // self-registered family above: standalone individuals, plus people a
    // member proposed for their own existing family in the Member Portal
    // (#9865). Mirrors GET /api/persons/self-register exactly.
    $selfRegisteredFamilyIds = FamilyQuery::create()
        ->filterByEnteredBy(Person::SELF_REGISTER)
        ->select('Id')
        ->find()
        ->getData();
    $individualQuery = PersonQuery::create()->filterByEnteredBy(Person::SELF_REGISTER);
    if ($selfRegisteredFamilyIds !== []) {
        $individualQuery->filterByFamId($selfRegisteredFamilyIds, Criteria::NOT_IN);
    }
    $individualCount = $individualQuery->count();

    $pageArgs = [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('Self Registrations'),
        'sPageSubtitle'   => gettext('Review new families and individuals who signed up on your public registration form'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Self Registrations')],
        ]),
        'familyCount'     => $familyCount,
        'individualCount' => $individualCount,
    ];

    return $renderer->render($response, 'self-register.php', $pageArgs);
});
