<?php
use Slim\Views\PhpRenderer;


$app->group('/user', function () {

  $this->get('/password', function ($request, $response, $args) {
    
    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "password-start.php", array("token" => "no"));

  });


  $this->post('/password', function ($request, $response, $args) {
    
    $renderer = new PhpRenderer("templates/");

    return $renderer->render($response, "password-end.php", array("family" => $token));

  });


});


