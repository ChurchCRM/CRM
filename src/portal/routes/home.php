<?php

use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /portal — the member's landing page (design §5.1).
//
// MP2 ships the welcome card and clearly-marked placeholders for the sections
// later issues fill in: the calendar (MP5), volunteering (MP6), my family and
// profile (MP4). Themes typically override this page first.
$homeHandler = function (Request $request, Response $response): Response {
    return PortalTwig::render(
        $response,
        'home.html.twig',
        ['pageTitle' => gettext('Home')],
        PortalNav::HOME
    );
};

// Both spellings answer, so a link to `/portal` works as well as `/portal/`.
$group->get('', $homeHandler);
$group->get('/', $homeHandler);
