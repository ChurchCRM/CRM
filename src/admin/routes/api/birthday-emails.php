<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Emails\notifications\BirthdayEmail;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/admin/birthday-emails', function (RouteCollectorProxy $group): void {

    /**
     * @OA\Post(
     *     path="/api/admin/birthday-emails/test",
     *     summary="Send a test birthday email to the current admin (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Test email sent"),
     *     @OA\Response(response=400, description="Current admin has no email on file"),
     *     @OA\Response(response=500, description="Test email failed to send")
     * )
     */
    $group->post('/test', function (Request $request, Response $response, array $args): Response {
        $person = AuthenticationManager::getCurrentUser()->getPerson();
        $email = $person->getEmail();

        if (empty($email)) {
            return SlimUtils::renderErrorJSON($response, gettext('Your account has no email address on file. Add one to your profile to send a test.'), [], 400);
        }

        try {
            $birthdayEmail = new BirthdayEmail([$email], $person);
            if ($birthdayEmail->send()) {
                return SlimUtils::renderJSON($response, [
                    'success' => true,
                    'message' => gettext('Test email sent to') . ' ' . $email,
                ]);
            }

            return SlimUtils::renderErrorJSON($response, gettext('Test email failed to send') . ': ' . $birthdayEmail->getError(), [], 500);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Test email failed to send'), [], 500, $e, $request);
        }
    });

});