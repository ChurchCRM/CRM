<?php

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use Slim\Views\PhpRenderer;
use Slim\Routing\RouteCollectorProxy;
$app->group('/verify', function (RouteCollectorProxy $group) {
    $group->get('/{token}', function ($request, $response, $args) {
        $renderer = new PhpRenderer('templates/verify/');
        $token = TokenQuery::create()->findPk($args['token']);
        $haveFamily = false;
        if ($token != null && $token->isVerifyFamilyToken() && $token->isValid()) {
            $family = FamilyQuery::create()->findPk($token->getReferenceId());
            $haveFamily = ($family != null);
            if ($token->getRemainingUses() > 0) {
                $token->setRemainingUses($token->getRemainingUses() - 1);
                $token->save();
            }
        }

        if ($haveFamily) {
            return $renderer->render($response, 'verify-family-info.php', ['family' => $family, 'token' => $token]);
        } else {
            return $renderer->render($response, '/../404.php', ['message' => gettext('Unable to load verification info')]);
        }
    });

    $group->post('/{token}', function ($request, $response, $args) {
        $token = TokenQuery::create()->findPk($args['token']);
        if ($token != null && $token->isVerifyFamilyToken() && $token->isValid()) {
            $family = FamilyQuery::create()->findPk($token->getReferenceId());
            if ($family != null) {
                $body = (object) $request->getParsedBody();
                $note = new Note();
                $note->setFamily($family);
                $note->setType('verify');
                $note->setEntered(Person::SELF_VERIFY);
                $note->setText(gettext('No Changes'));
                if (!empty($body->message)) {
                    $note->setText($body->message);
                }
                $note->save();
            }
        }

        return $response->withStatus(200);
    });

});
