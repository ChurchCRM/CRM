<?php
/* contributor Philippe Logel */

// Person APIs
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\Photo;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\dto\MenuEventsCount;

$app->group('/session', function () {
    $this->get('/lock', function ($request, $response, $args) {
    	$_SESSION['iLoginType'] = "Lock";
      Redirect('Login.php');
    });
});