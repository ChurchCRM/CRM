<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\dto\Photo;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->get('/', function ($request, $response, $args) {
    $renderer = new PhpRenderer('templates/kioskDevices/');
    $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

    return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
});

$app->get('/heartbeat', fn ($request, $response, $args) => json_encode($app->kiosk->heartbeat(), JSON_THROW_ON_ERROR));

$app->post('/checkin', function ($request, $response, $args) use ($app) {
    $input = (object) $request->getParsedBody();
    $status = $app->kiosk->getActiveAssignment()->getEvent()->checkInPerson($input->PersonId);

    return $response->withJson($status);
});

$app->post('/checkout', function ($request, $response, $args) use ($app) {
    $input = (object) $request->getParsedBody();
    $status = $app->kiosk->getActiveAssignment()->getEvent()->checkOutPerson($input->PersonId);

    return $response->withJson($status);
});

$app->post('/triggerNotification', function ($request, $response, $args) use ($app) {
    $input = (object) $request->getParsedBody();

    $Person = PersonQuery::create()
            ->findOneById($input->PersonId);

    $Notification = new Notification();
    $Notification->setPerson($Person);
    $Notification->setRecipients($Person->getFamily()->getAdults());
    $Notification->setProjectorText($app->kiosk->getActiveAssignment()->getEvent()->getType() . '-' . $Person->getId());
    $Status = $Notification->send();

    return $response->withJson($Status);
});

$app->get('/activeClassMembers', fn ($request, $response, $args) => $app->kiosk->getActiveAssignment()->getActiveGroupMembers()->toJSON());

$app->get('/activeClassMember/{PersonId}/photo', function (ServerRequestInterface $request, Response $response, $args) {
    $photo = new Photo('Person', $args['PersonId']);

    return $response->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());
});
