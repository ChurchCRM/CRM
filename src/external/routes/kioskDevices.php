<?php

use ChurchCRM\ConfigQuery;
use ChurchCRM\Family;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Person;
use Slim\Views\PhpRenderer;
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;


$app->group('/kioskdevices', function () {

  $this->get('/{guid}', function ($request, $response, $args) {

      $renderer = new PhpRenderer("templates/kioskDevices/");
      $pageObjects = array("sRootPath" => $_SESSION['sRootPath'], "thisDeviceGuid" => $args['guid']);
      return $renderer->render($response, "sunday-school-class-view.php", $pageObjects);

    });
    
    $this->get('/{guid}/activeClassMembers', function ($request, $response, $args) {
     $ssClass = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithGroup()
            ->joinWithPerson()
            ->addJoin(ChurchCRM\Map\GroupTableMap::COL_GRP_ROLELISTID, ChurchCRM\Map\ListOptionTableMap::COL_LST_ID , Propel\Runtime\ActiveQuery\Criteria::INNER_JOIN)
            ->addJoinCondition(self::RoleId, "ListOptionO.ptionId")
             ->withColumn(ChurchCRM\Map\ListOptionTableMap::COL_LST_OPTIONNAME,"RoleName")
            ->findByGroupId(2);
      return $ssClass->toJSON();
    });
    
    $this->get('/{guid}/activeClassMember/{PersonId}/photo', function (ServerRequestInterface  $request, ResponseInterface  $response, $args) {
     $photo = ChurchCRM\PersonQuery::create()
              ->findOneById($args['PersonId'])
              ->getPhoto();
    
     $image = file_get_contents(dirname(dirname(__DIR__))."/".$photo);
     $finfo = new finfo(FILEINFO_MIME_TYPE);
     $response->getBody()->write($image);      
      return $response->withHeader('Content-Type', 'content-type: ' . $finfo->buffer($image));
    });
  
});


