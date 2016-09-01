<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\Family;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\ConfigQuery;

$app->group('/family', function () {

  $enableSelfReg = ConfigQuery::create()->filterByName("sEnableSelfRegistration")->findOne();
  if ($enableSelfReg->getBooleanValue()) {

    $this->get('/register', function ($request, $response, $args) {

      $renderer = new PhpRenderer("templates/");

      return $renderer->render($response, "family-register.php", array("token" => "no"));

    });

    $this->post('/register', function ($request, $response, $args) {
      $renderer = new PhpRenderer("templates/");
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

      $pageObjects = array("family" => $family, "familyCount" => $body["familyCount"], "familyRoles" => $familyRoles);

      return $renderer->render($response, "family-register-members.php", $pageObjects);

    });
  }
  $this->get('/verify', function ($request, $response, $args) {

    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "verify-start.php", array("token" => "no"));

  });


  $this->get('/verify/{token}', function ($request, $response, $args) {
    $token = $args['token'];

    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "verify-input.php", array("token" => $token));

  });

  $this->post('/verify/{token}', function ($request, $response, $args) {
    $token = $args['token'];

    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "verify-family-data", array("family" => $token));

  });


});


