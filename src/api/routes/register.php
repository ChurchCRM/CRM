<?php

$app->group('/register', function () {
  $this->post('', function ($request, $response, $args) {
    global $systemConfig;
    $input = (object)$request->getParsedBody();

    $registrationData = new \stdClass();
    $registrationData->sName = $systemConfig->getValue("sChurchName");
    $registrationData->sAddress = $systemConfig->getValue("sChurchAddress");
    $registrationData->sCity = $systemConfig->getValue("sChurchCity");
    $registrationData->sState = $systemConfig->getValue("sChurchState");
    $registrationData->sZip = $systemConfig->getValue("sChurchZip");
    $registrationData->sCountry = $systemConfig->getValue("sDefaultCountry");
    $registrationData->sEmail = $systemConfig->getValue("sChurchEmail");
    $registrationData->ChurchCRMURL = $input->ChurchCRMURL;
    $registrationData->Version = $this->SystemService->getInstalledVersion();

    $registrationData->sComments = $input->emailmessage;
    $curlService = curl_init("http://demo.churchcrm.io/register.php");

    curl_setopt($curlService, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curlService, CURLOPT_POST, true);
    curl_setopt($curlService, CURLOPT_POSTFIELDS, json_encode($registrationData));
    curl_setopt($curlService, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlService, CURLOPT_CONNECTTIMEOUT, 1);

    $result = curl_exec($curlService);
    if ($result === FALSE) {
      throw new \Exception("Unable to reach the registration server", 500);
    }

    // =Turn off the registration flag so the menu option is less obtrusive
    $systemConfig->setValue("bRegistered","1");
    $bRegistered = 1;
    return $response->withJson(array("status"=>"success"));

  });
});
 ?>