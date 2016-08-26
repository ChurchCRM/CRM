<?php

use Slim\Views\PhpRenderer;


$app->group('/family', function () {

  $this->get('/register', function ($request, $response, $args) {

    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "family-register.php", array("token" => "no"));

  });

  $this->post('/register', function ($request, $response, $args) {
    $renderer = new PhpRenderer("templates/");
    $body = $request->getParsedBody();

    $pageObjects = array("familyName" => $body["familyName"],
      "familyCount" => $body["familyCount"]);

    return $renderer->render($response, "family-register-members.php",
      $pageObjects);

  });


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


