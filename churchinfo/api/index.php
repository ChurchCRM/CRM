<?php

require '../Include/Config.php';
require '../Include/Functions.php';

//Security
if (!isset($_SESSION['iUserID'])) {
  Redirect("Login.php");
  exit;
}

// Services
require_once "../Service/PersonService.php";
require_once "../Service/FamilyService.php";
require_once "../Service/DataSeedService.php";
require_once "../Service/FinancialService.php";
require_once "../Service/GroupService.php";
require_once '../Service/SystemService.php';
require_once "../Service/ReportingService.php";
require_once '../Service/NoteService.php';

require_once '../vendor/Slim/slim/Slim/Slim.php';

use Slim\Slim;

Slim::registerAutoloader();

$app = new Slim();

$app->contentType('application/json');

$app->container->singleton('PersonService', function () {
  return new PersonService();
});

$app->container->singleton('FamilyService', function () {
  return new FamilyService();
});

$app->container->singleton('DataSeedService', function () {
  return new DataSeedService();
});
$app->container->singleton('SystemService', function () {
  return new SystemService();
});

$app->container->singleton('FinancialService', function () {
  return new FinancialService();
});

$app->container->singleton('GroupService', function () {
  return new GroupService();
});

$app->container->singleton('NoteService', function () {
  return new NoteService();
});

$app->container->singleton('ReportingService', function () {
  return new ReportingService();
});

$app->group('/groups', function () use ($app) {
  $groupService = $app->GroupService;

  $app->post('/:groupID/userRole/:userID', function ($groupID, $userID) use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      echo json_encode($groupService->setGroupMemberRole($groupID, $userID, $input->roleID));

    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/:groupID/removeuser/:userID', function ($groupID, $userID) use ($groupService) {
    try {
      $groupService->removeUserFromGroup($groupID, $userID);
      echo '{"success":"true"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->post('/:groupID/adduser/:userID', function ($groupID, $userID) use ($groupService) {
    try {
      echo json_encode($groupService->addUserToGroup($groupID, $userID, 0));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->delete('/:groupID', function ($groupID) use ($groupService) {
    try {
      $groupService->deleteGroup($groupID);
      echo '{"success":"true"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->get('/:groupID', function ($groupID) use ($groupService) {
    try {
      echo $groupService->getGroupJSON($groupService->getGroups($groupID));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }

  });
  $app->post('/:groupID', function ($groupID) use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      echo $groupService->updateGroup($groupID, $input);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->post('/', function () use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      echo json_encode($groupService->createGroup($input->groupName));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/:groupID/roles/:roleID', function ($groupID, $roleID) use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      if (property_exists($input, "groupRoleName")) {
        $groupService->setGroupRoleName($groupID, $roleID, $input->groupRoleName);
      } elseif (property_exists($input, "groupRoleOrder")) {
        $groupService->setGroupRoleOrder($groupID, $roleID, $input->groupRoleOrder);
      }

      echo '{"success":"true"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->delete('/:groupID/roles/:roleID', function ($groupID, $roleID) use ($app, $groupService) {
    try {
      echo json_encode($groupService->deleteGroupRole($groupID, $roleID));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/:groupID/roles', function ($groupID) use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      echo $groupService->addGroupRole($groupID, $input->roleName);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/:groupID/defaultRole', function ($groupID) use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      $groupService->setGroupRoleAsDefault($groupID, $input->roleID);
      echo '{"success":"true"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/:groupID/setGroupSpecificPropertyStatus', function ($groupID) use ($app, $groupService) {
    try {
      $input = getJSONFromApp($app);
      if ($input->GroupSpecificPropertyStatus) {
        $groupService->enableGroupSpecificProperties($groupID);
        return '{"status":"group specific properties enabled"}';
      } else {
        $groupService->disableGroupSpecificProperties($groupID);
        return '{"status":"group specific properties disabled"}';
      }
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

});

$app->group('/queries', function () use ($app)
{
  $reportingService = $app->ReportingService;

  $app->get("/", function () use ($app, $reportingService)
  {
    try
    {
      echo $reportingService->getQueriesJSON($reportingService->getQuery());
    }
    catch (Exception $e)
    {
      echo exceptionToJSON($e);
    }
  });

  $app->get("/:id", function ($id) use ($app, $reportingService)
  {
    try
    {
      echo $reportingService->getQueriesJSON($reportingService->getQuery($id));
    }
    catch (Exception $e)
    {
      echo exceptionToJSON($e);
    }
  });

  $app->get("/:id/details", function ($id) use ($app, $reportingService)
  {
    try
    {
      echo '{"Query":' . json_encode($reportingService->getQuery($id)) . ' , "Parameters":' . json_encode($reportingService->getQueryParameters($id)) . '}';
    }
    catch (Exception $e)
    {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/:id', function() use ($app, $reportingService)
  {
    try
    {
      $input = getJSONFromApp($app);
      echo json_encode($reportingService->queryDatabase($input));
    }
    catch (Exception $e)
    {
      echo exceptionToJSON($e);
    }
  });
});

$app->group('/database', function () use ($app) {
  $systemService = $app->SystemService;
  $app->post('/backup', function () use ($app, $systemService) {
    try {
      $input = getJSONFromApp($app);
      $backup = $systemService->getDatabaseBackup($input);
      echo json_encode($backup);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->post('/restore', function () use ($app, $systemService) {
    try {
      $request = $app->request();
      $body = $request->getBody();
      $restore = $systemService->restoreDatabaseFromBackup();
      echo json_encode($restore);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->get('/download/:filename', function ($filename) use ($app, $systemService) {
    try {
      $systemService->download($filename);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }

  });
});

$app->group('/search', function () use ($app) {
  $app->get('/:query', function ($query) use ($app) {
    try {
      $resultsArray = array();
      array_push($resultsArray, $app->PersonService->getPersonsJSON($app->PersonService->search($query)));
      array_push($resultsArray, $app->FamilyService->getFamiliesJSON($app->FamilyService->search($query)));
      array_push($resultsArray, $app->GroupService->getGroupJSON($app->GroupService->search($query)));
      array_push($resultsArray, $app->FinancialService->getDepositJSON($app->FinancialService->searchDeposits($query)));
      array_push($resultsArray, $app->FinancialService->getPaymentJSON($app->FinancialService->searchPayments($query)));
      echo "[" . join(",", array_filter($resultsArray)) . "]";
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
});


$app->group('/persons', function () use ($app) {
  $personService = $app->PersonService;
  $app->get('/search/:query', function ($query) use ($personService) {
    try {
      echo "[" . $personService->getPersonsJSON($personService->search($query)) . "]";
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->group('/:id', function () use ($app, $personService) {
    $app->get('/', function ($id) use ($personService) {
      echo "[" . $personService->getPersonsJSON($personService->getPersonByID($id)) . "]";
    });

    $app->get('/photo', function ($id) use ($personService) {
      try {
        echo $personService->getPhoto($id);
      } catch (Exception $e) {
        echo exceptionToJSON($e);
      }
    });
    $app->delete('/photo', function ($id) use ($personService) {
      try {
        $deleted = $personService->deleteUploadedPhoto($id);
        if (!$deleted)
          echo "{filesDeleted: no images found}";
        else
          echo "{filesDeleted: yes}";
      } catch (Exception $e) {
        echo exceptionToJSON($e);
      }
    });
  });
});

$app->group('/families', function () use ($app) {
  $app->get('/search/:query', function ($query) use ($app) {
    try {
      echo $app->FamilyService->getFamiliesJSON($app->FamilyService->search($query));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->get('/lastedited', function ($query) use ($app) {
    try {
      $app->FamilyService->lastEdited();
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->get('/byCheckNumber/:tScanString', function ($tScanString) use ($app) {
    try {
      echo $app->FinancialService->getMemberByScanString($sstrnig);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }

  });
  $app->get('/byEnvelopeNumber/:tEnvelopeNumber', function ($tEnvelopeNumber) use ($app) {
    try {
      echo $app->FamilyService->getFamilyStringByEnvelope($tEnvelopeNumber);
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

});

$app->group('/deposits', function () use ($app) {

  $app->post('/', function () use ($app) {
    try {
      $input = getJSONFromApp($app);
      echo json_encode($app->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->get('/', function () use ($app) {
    try {
      echo '{"deposits": ' . json_encode($app->FinancialService->getDeposits()) . '}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->get('/:id', function ($id) use ($app) {
    try {
      echo '{"deposits": ' . json_encode($app->FinancialService->getDeposits($id)) . '}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  })->conditions(array('id' => '[0-9]+'));

  $app->post('/:id', function ($id) use ($app) {
    try {
      $input = getJSONFromApp($app);
      echo json_encode($app->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate, $id, $input->depositClosed));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });


  $app->get('/:id/ofx', function ($id) use ($app) {
    try {
      $OFX = $app->FinancialService->getDepositOFX($id);
      header($OFX->header);
      echo $OFX->content;
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/pdf', function ($id) use ($app) {
    try {
      $PDF = $app->FinancialService->getDepositPDF($id);
      header($PDF->header);
      echo $PDF->content;
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/csv', function ($id) use ($app) {
    try {
      $CSV = $app->FinancialService->getDepositCSV($id);
      header($CSV->header);
      echo $CSV->content;
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  })->conditions(array('id' => '[0-9]+'));

  $app->delete('/:id', function ($id) use ($app) {
    try {
      $app->FinancialService->deleteDeposit($id);
      echo '{"success":"true"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/payments', function ($id) use ($app) {
    try {
      echo $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments($id));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  })->conditions(array('id' => '[0-9]+'));
});


$app->group('/payments', function () use ($app) {
  $app->get('/', function () use ($app) {
    try {
      $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments());
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->post('/', function () use ($app) {
    try {
      $payment = getJSONFromApp($app);
      echo '{"payment": ' . json_encode($app->FinancialService->submitPledgeOrPayment($payment)) . '}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }

  });
  $app->get('/:id', function ($id) use ($app) {
    try {
      //$payment = getJSONFromApp($app);
      //echo $app->FinancialService->getDepositsByFamilyID($fid); //This might not work yet...
      echo '{"status":"Not implemented"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->get('/byFamily/:familyId(/:fyid)', function ($familyId, $fyid = -1) use ($app) {
    try {
      echo '{"status":"Not implemented"}';
      //$payment = getJSONFromApp($app);
      #$app->FinancialService->getDepositsByFamilyID($fid);//This might not work yet...
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
  $app->delete('/:groupKey', function ($groupKey) use ($app) {
    try {
      if (!$_SESSION['bAddRecords']) {
        throw new Exception (gettext("You must have at least AddRecords permission to use this API call"));
      }
      $app->FinancialService->deletePayment($groupKey);
      echo '{"status":"ok"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });
});

$app->group('/notes', function () use ($app) {
  $noteService = $app->NoteService;

  $app->delete('/:noteID', function ($noteID) use ($noteService) {
    try {
      $noteService->deleteNoteById($noteID);
      echo '{"success":"true"}';
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }
  });

  $app->get('/:noteId', function ($noteId) use ($noteService) {
    try {
      echo json_encode($noteService->getNoteById($noteId));
    } catch (Exception $e) {
      echo exceptionToJSON($e);
    }

  });

});

$app->group('/data/seed', function () use ($app) {
  $app->post('/families', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $families = $input->families;
    $app->DataSeedService->generateFamilies($families);
  });
  $app->post('/sundaySchoolClasses', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $classes = $input->classes;
    $childrenPerTeacher = $input->childrenPerTeacher;
    $app->DataSeedService->generateSundaySchoolClasses($classes, $childrenPerTeacher);
  });
  $app->post('/deposits', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $deposits = $input->deposits;
    $averagedepositvalue = $input->averagedepositvalue;
    $app->DataSeedService->generateDeposits($deposits, $averagedepositvalue);
  });
  $app->post('/events', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $events = $input->events;
    $averageAttendance = $input->averageAttendance;
    $app->DataSeedService->generateEvents($events, $averageAttendance);
  });
  $app->post('/fundraisers', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $fundraisers = $input->fundraisers;
    $averageItems = $input->averageItems;
    $averageItemPrice = $input->averageItemPrice;
    $app->DataSeedService->generateFundRaisers($fundraisers, $averageItems, $averageItemPrice);
  });

});

function getJSONFromApp($app)
{

  $request = $app->request();
  $body = $request->getBody();
  return json_decode($body);
}

/**
 * @param $e
 */
function exceptionToJSON($e)
{
  if (!isset($e->customSeverity))  // if there is no custom severity in the passed exception object, assign a "1"
  {
    $e->customSeverity = 1;  //Use a custom severity code so that user-side code can determine what level of error should generate a UI Modal dialog.  Anything >0 currently causes a modal.
  }
  if (!isset($e->responseCode)) // if there is no response code, assume unhandled exception (500)
  {
    $e->responseCode = 500;
  }
  http_response_code($e->responseCode);
  return '{"error":{"text":"' . $e->getMessage() . '","severity":"' . $e->customSeverity . '"}}';
}

$app->run();

