<?php

use ChurchCRM\ConfigQuery;
use ChurchCRM\Family;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Person;
use Slim\Views\PhpRenderer;
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;


$app->group('/kioskdevices', function () {

  $this->get('/{guid}', function ($request, $response, $args) {

      $renderer = new PhpRenderer("templates/kioskDevices/");
    
      return $renderer->render($response, "sunday-school-class-view.php", array("sRootPath" => $_SESSION['sRootPath'], ""=>$ssClass));

    });
    
    $this->get('/{guid}/activeClassMembers', function ($request, $response, $args) {
     $ssClass = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId(2);
      return $ssClass->toJSON();
     

    });
  
});


