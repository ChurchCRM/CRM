<?php

$app->group('/kiosks', function () {

    $this->get('/', function ($request, $response, $args) {
        $Kiosks = \ChurchCRM\KioskDeviceQuery::create()
                ->find();
        return $response->write($Kiosks->toJSON());
    });
    
     $this->post('/{kioskId:[0-9]+}/reloadKiosk', function ($request, $response, $args) {
        $kioskId = $args['kioskId'];
        $reload = \ChurchCRM\KioskDeviceQuery::create()
                ->findOneById($kioskId)
                ->reloadKiosk();
        return $response->write(json_encode($reload));
    });
    
    
 

});
