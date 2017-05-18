<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\KioskDeviceQuery;

$app->group('/kiosks', function () {

    $this->get('/', function ($request, $response, $args) {
        $Kiosks = KioskDeviceQuery::create()
                ->leftJoinKioskAssignment()
                ->find();
        return $response->write($Kiosks->toJSON());
    });
    
    $this->post('/allowRegistration', function ($request, $response, $args) {
        $window =new DateTime();
        $window->add(new DateInterval("PT05S"));
        SystemConfig::setValue("sKioskVisibilityTimestamp",$window->format('Y-m-d H:i:s'));
        return $response->write(json_encode(array("visibleUntil"=>$window)));
    });
    
    $this->post('/{kioskId:[0-9]+}/reloadKiosk', function ($request, $response, $args) {
        $kioskId = $args['kioskId'];
        $reload = \ChurchCRM\KioskDeviceQuery::create()
                ->findOneById($kioskId)
                ->reloadKiosk();
        return $response->write(json_encode($reload));
    });
    
    $this->post('/{kioskId:[0-9]+}/acceptKiosk', function ($request, $response, $args) {
        $kioskId = $args['kioskId'];
        $accept = \ChurchCRM\KioskDeviceQuery::create()
                ->findOneById($kioskId)
                ->setAccepted(true)
                ->save();
        return $response->write(json_encode($accept));
    });
    
     
    
    
 

});
