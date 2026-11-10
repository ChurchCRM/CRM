<?php

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\Utils\InputUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/verify', function (RouteCollectorProxy $group): void {
    $group->get('/{token}', function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer('templates/verify/');
        $token = TokenQuery::create()->findPk($args['token']);
        $haveFamily = false;
        if ($token !== null && $token->isVerifyFamilyToken() && $token->isValid()) {
            $family = FamilyQuery::create()->findPk($token->getReferenceId());
            $haveFamily = ($family !== null);
            // Reading the page never consumes a use (#11145): link previews,
            // email scanners and refreshes must not burn the family's attempts.
        }

        if ($haveFamily) {
            return $renderer->render($response, 'verify-family-info.php', ['family' => $family, 'token' => $token]);
        } else {
            return $renderer->render($response, '/../404.php', ['message' => gettext('Unable to load verification info')]);
        }
    });

    $group->post('/{token}', function (Request $request, Response $response, array $args): Response {
        $token = TokenQuery::create()->findPk($args['token']);
        if ($token !== null && $token->isVerifyFamilyToken() && $token->isValid()) {
            $family = FamilyQuery::create()->findPk($token->getReferenceId());
            if ($family !== null) {
                $body = $request->getParsedBody();
                $note = new Note();
                $note->setFamily($family);
                $note->setType('verify');
                $note->setEntered(Person::SELF_VERIFY);
                $note->setText(gettext('No Changes'));
                if (!empty($body['message'])) {
                    $note->setText(InputUtils::escapeHTML($body['message']));
                } elseif (($body['verifyType'] ?? '') === 'change-needed') {
                    // "change needed" with no comment must not be recorded as
                    // "No Changes" (#11145): keep it a change request.
                    $note->setText(gettext('Change requested'));
                }
                $note->save();

                // Consume the token after successful verification
                $token->consume();
            }
        }

        return $response->withStatus(200);
    });
});
