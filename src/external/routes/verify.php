<?php
use Slim\Views\PhpRenderer;


$app->group('/verify/family', function () {

  $this->get('/{token}', function ($request, $response, $args) {
    $token = $args['token'];

    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "verify-input.php", array("token" => $token));

  });

  $this->post('/{token}', function ($request, $response, $args) {
    $token = $args['token'];

    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "verify-family-data", array("family" => $token));

  });


});


