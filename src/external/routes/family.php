<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\Family;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\ConfigQuery;

$app->group('/family', function () {

  $enableSelfReg = ConfigQuery::create()->filterByName("sEnableSelfRegistration")->findOne();
  if ($enableSelfReg->getBooleanValue()) {

    $this->get('/register', function ($request, $response, $args) {

      $renderer = new PhpRenderer("templates/registration/");

      return $renderer->render($response, "family-register.php", array("sRootPath" =>  $_SESSION['sRootPath'], "token" => "no"));

    });

    $this->post('/register', function ($request, $response, $args) {
      $renderer = new PhpRenderer("templates/registration/");
      $body = $request->getParsedBody();

      $family = new Family();
      $family->setName($body["familyName"]);
      $family->setAddress1($body["familyAddress1"]);
      $family->setCity($body["familyCity"]);
      $family->setState($body["familyState"]);
      $family->setCountry($body["familyCountry"]);

      $className = "Regular Attender";
      if ($body["familyPrimaryChurch"] == "No") {
        $className = "Guest";
      }
      $familyMembership = ListOptionQuery::create()->filterById(1)->filterByOptionName($className)->findOne();

      $_SESSION[regFamily] = $family;
      $_SESSION[regFamilyClassId] = $familyMembership->getOptionId();

      $familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();

      $pageObjects = array("sRootPath" =>  $_SESSION['sRootPath'] ,"family" => $family, "familyCount" => $body["familyCount"], "familyRoles" => $familyRoles);

      return $renderer->render($response, "family-register-members.php", $pageObjects);

    });
  }

});


