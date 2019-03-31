<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;

$app->group('/people', function () {
    $this->get('/verify', 'viewPeopleVerify');
});


function viewPeopleVerify(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/people/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
    ];

    if ($request->getQueryParam("EmailsError")) {
        $errorArgs = ['sGlobalMessage' => gettext("Error sending email(s)") . " - " . gettext("Please check logs for more information"), "sGlobalMessageClass" => "danger"];
        $pageArgs =  array_merge($pageArgs, $errorArgs);
    }

    if ($request->getQueryParam("AllPDFsEmailed")) {
        $headerArgs = ['sGlobalMessage' =>  gettext('PDFs successfully emailed ').$request->getQueryParam("AllPDFsEmailed").' '.gettext('families').".",
        "sGlobalMessageClass" => "success"];
        $pageArgs =  array_merge($pageArgs, $headerArgs);
    }

    return $renderer->render($response, 'people-verify-view.php', $pageArgs);
}
