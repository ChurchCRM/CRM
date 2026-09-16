<?php

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /portal — the member's landing page (design §5.1).
//
// My Family and Profile are real cards since MP4 (#9865); the calendar (MP5)
// and volunteering (MP6) are still clearly-marked placeholders. Themes
// typically override this page first.
$homeHandler = function (Request $request, Response $response): Response {
    $actor = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    $family = $actor instanceof Person ? $actor->getFamily() : null;

    return PortalTwig::render(
        $response,
        'home.html.twig',
        [
            'pageTitle' => gettext('Home'),
            'familySummary' => $family === null ? null : [
                'name' => (string) $family->getName(),
                'memberCount' => count($family->getPeople()),
            ],
        ],
        PortalNav::HOME
    );
};

// Both spellings answer, so a link to `/portal` works as well as `/portal/`.
$group->get('', $homeHandler);
$group->get('/', $homeHandler);
