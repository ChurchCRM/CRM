<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\PersonQuery;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ChurchCRM\dto\Notification;


$this->get('/', function ($request, $response, $args) {

    $renderer = new PhpRenderer("templates/kioskDevices/");
    $pageObjects = array("sRootPath" => $_SESSION['sRootPath'], "thisDeviceGuid" => $args['guid']);
    return $renderer->render($response, "sunday-school-class-view.php", $pageObjects);

  });

  $this->get('/{guid}/activeClassMembers', function ($request, $response, $args) {

    $guid = $args['guid'];
    $Kiosk = ChurchCRM\KioskDeviceQuery::create()
            ->findOneByGUID($guid)
            ->getActiveGroupMembers();
    return $Kiosk->toJSON();
  });


  $this->get('/{guid}/heartbeat', function ($request, $response, $args) {
    $guid = $args['guid'];
    $Kiosk = ChurchCRM\KioskDeviceQuery::create()
            ->findOneByGUID($guid);
    return json_encode($Kiosk->heartbeat());     
  });

  $this->post('/{guid}/checkin', function ($request, $response, $args) {
    $guid = $args['guid'];
    $input = (object) $request->getParsedBody();
    $status = ChurchCRM\KioskDeviceQuery::create()
            ->findOneByGUID($guid)
            ->checkInPerson($input->PersonId);

    return $response->withJSON($status);



  });

  $this->post('/{guid}/checkout', function ($request, $response, $args) {
    $guid = $args['guid'];
    $input = (object) $request->getParsedBody();
    $status = ChurchCRM\KioskDeviceQuery::create()
            ->findOneByGUID($guid)
            ->checkOutPerson($input->PersonId);
    return $response->withJSON($status);
  });

   $this->post('/{guid}/triggerNotification', function ($request, $response, $args) {
    $guid = $args['guid'];
    $input = (object) $request->getParsedBody();

    $Kiosk = ChurchCRM\KioskDeviceQuery::create()
            ->findOneByGUID($guid);

    $Person =PersonQuery::create()
            ->findOneById($input->PersonId);

    $Notification = new Notification();
    $Notification->setRecipients($Person->getFamily()->getPeople());
    $Notification->setProjectorText($Kiosk->getEvents()[0]->getType()."-".$Person->getId());
    $Status = $Notification->send();

    return $response->withJSON($Status);
  });



  $this->get('/{guid}/activeClassMember/{PersonId}/photo', function (ServerRequestInterface  $request, ResponseInterface  $response, $args) {
   $person = PersonQuery::create()->findPk($args['PersonId']);
      if ($person->isPhotoLocal()) {
          return $response->write($person->getPhotoBytes())->withHeader('Content-type', $person->getPhotoContentType());
      } else if ($person->isPhotoRemote()) {
          return $response->withRedirect($person->getPhotoURI());
      } else {
          return $response->withStatus(404);
      }
  });


