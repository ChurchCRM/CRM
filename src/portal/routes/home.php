<?php

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /portal — the member's landing page (design §5.1).
//
// MP2 shipped the welcome card and clearly-marked placeholders for the sections
// later issues fill in. My Family and Profile are real cards since MP4 (#9865),
// and MP6 (#9867) turns the volunteering one into a real card too, filled
// client-side from `/api/volunteer/me/assignments`. The calendar (MP5)
// placeholder remains. Themes typically override this page first.
$homeHandler = function (Request $request, Response $response): Response {
    $actor = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    $family = $actor instanceof Person ? $actor->getFamily() : null;

    return PortalTwig::render(
        $response,
        'home.html.twig',
        [
            'pageTitle' => gettext('Home'),
            // Whether the volunteering card is a real card or nothing at all.
            // The nav entry is decided by the same call, so the card and the
            // navigation can never disagree.
            'showVolunteering' => PortalNav::isVolunteeringVisible(),
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
