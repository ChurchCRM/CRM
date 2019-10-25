<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\SessionUser;
use ChurchCRM\UserQuery;

$app->group('/user/current', function () {

    $this->post("/settings/show/finance", "updateSessionFinance");
});

function updateSessionFinance(Request $request, Response $response, array $args)
{
    $user = UserQuery::create()->findPk(SessionUser::getId());

    if ($request->getContentLength() > 0) {
        $setting = (object)$request->getParsedBody();
        $user->setShowPledges(ConvertToBoolean($setting->pledges));
        $user->setShowPayments(ConvertToBoolean($setting->payments));
        $user->setShowSince($setting->since);
        $user->save();
        $_SESSION['user'] = $user;
    }

    return $response->withJson([
        "user" => $user->getName(),
        "userId" => $user->getId(),
        "showPledges" => $user->isShowPledges(),
        "showPayments" => $user->isShowPayments(),
        "showSince" => $user->getFormattedShowSince()
    ]);

}
