<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\dto\Photo;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/', function (Request $request, Response $response, array $args): Response {
    $renderer = new PhpRenderer('templates/kioskDevices/');
    $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

    return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
});

$app->get('/heartbeat', function (Request $request, Response $response, array $args) use ($app): Response {
    $kiosk = $app->getContainer()->get('kiosk');
    return SlimUtils::renderJSON($response, $kiosk->heartbeat());
});

$app->post('/checkin', function (Request $request, Response $response, array $args) use ($app): Response {
    $input = $request->getParsedBody();
    $kiosk = $app->getContainer()->get('kiosk');
    $status = $kiosk->getActiveAssignment()->getEvent()->checkInPerson($input['PersonId']);

    return SlimUtils::renderJSON($response, $status);
});

$app->post('/checkout', function (Request $request, Response $response, array $args) use ($app): Response {
    $input = $request->getParsedBody();
    $kiosk = $app->getContainer()->get('kiosk');
    $status = $kiosk->getActiveAssignment()->getEvent()->checkOutPerson($input['PersonId']);

    return SlimUtils::renderJSON($response, $status);
});

$app->post('/triggerNotification', function (Request $request, Response $response, array $args) use ($app): Response {
    $input = $request->getParsedBody();

    $Person = PersonQuery::create()
            ->findOneById($input['PersonId']);

    $Notification = new Notification();
    $Notification->setPerson($Person);
    $Notification->setRecipients($Person->getFamily()->getAdults());
    $kiosk = $app->getContainer()->get('kiosk');
    $Notification->setProjectorText($kiosk->getActiveAssignment()->getEvent()->getType() . '-' . $Person->getId());
    $status = $Notification->send();

    return SlimUtils::renderJSON($response, $status);
});

$app->get('/activeClassMembers', function (Request $request, Response $response, array $args) use ($app): Response {
    $kiosk = $app->getContainer()->get('kiosk');
    return $kiosk->getActiveAssignment()->getActiveGroupMembers()->toJSON();
});

$app->get('/activeClassMember/{PersonId}/photo', function (ServerRequestInterface $request, Response $response, array $args) {
    $photo = new Photo('Person', $args['PersonId']);

    $response->getBody()->write($photo->getPhotoBytes());

    return $response->withAddedHeader('Content-type', $photo->getPhotoContentType());
});
