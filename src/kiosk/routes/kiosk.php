<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\PersonQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;


$app->get('/', function ($request, $response, $args) use ($app) {
    $renderer = new PhpRenderer("templates/kioskDevices/");
    $pageObjects = array("sRootPath" => $_SESSION['sRootPath']);
    return $renderer->render($response, "sunday-school-class-view.php", $pageObjects);
  });

  $app->get('/heartbeat', function ($request, $response, $args) use ($app) {

    return json_encode($app->kiosk->heartbeat());
  });

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

    $Person =PersonQuery::create()
            ->findOneById($input->PersonId);

    $Notification = new Notification();
    $Notification->setPerson($Person);
    $Notification->setRecipients($Person->getFamily()->getAdults());
    $Notification->setProjectorText($app->kiosk->getActiveAssignment()->getEvent()->getType()."-".$Person->getId());
    $Status = $Notification->send();

    return $response->withJson($Status);
  });


   $app->get('/activeClassMembers', function ($request, $response, $args) use ($app) {
    return $app->kiosk->getActiveAssignment()->getActiveGroupMembers()->toJSON();
  });


  $app->get('/activeClassMember/{PersonId}/photo', function (ServerRequestInterface  $request, ResponseInterface  $response, $args) use ($app) {
    $photo = new Photo("Person",$args['PersonId']);
    return $response->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());
  });


