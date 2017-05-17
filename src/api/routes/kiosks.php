<?php

use ChurchCRM\dto\SystemConfig;

$app->group('/kiosks', function () {

    $this->get('/', function ($request, $response, $args) {
        $Kiosks = \ChurchCRM\KioskDeviceQuery::create()
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
    
     
    
    
 

});
