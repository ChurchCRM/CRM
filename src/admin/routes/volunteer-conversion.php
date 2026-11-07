<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\Service\VolunteerV1Service;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/volunteer-conversion', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $ministries = [];
    foreach (VolunteerMinistryQuery::create()->orderByName()->find() as $ministry) {
        $ministries[] = ['id' => (int) $ministry->getId(), 'name' => (string) $ministry->getName()];
    }

    return $renderer->render($response, 'volunteer-conversion.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Convert Volunteer Opportunities'),
        'sPageSubtitle' => gettext('Volunteer Opportunities will be retired in early 2027. Convert them to Ministries to keep who volunteers for what.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('People Settings'), '/admin/people'],
            [gettext('Convert Volunteer Opportunities')],
        ]),
        'sPageHeaderButtons' => PageHeader::peopleSettingsButton(),
        'conversion' => [
            'ministries' => $ministries,
            'opportunityCount' => VolunteerOpportunityQuery::create()->count(),
            'unconverted' => VolunteerV1Service::unconvertedCount(),
            'previousMinistryId' => VolunteerV1Service::previousMinistryId(VolunteerV1Service::storedConversion()),
            'version' => User::getVolunteerVersion(),
            'defaultMinistryName' => gettext('Volunteer Opportunities'),
            'csrfToken' => CSRFUtils::generateToken(),
        ],
    ]);
});
