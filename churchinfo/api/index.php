<?php

require '../Include/Config.php';
require '../Include/Functions.php';

//Security
if (!isset($_SESSION['iUserID'])) {
    Redirect("Default.php");
    exit;
}

// Services
require_once "../service/PersonService.php";
require_once "../service/FamilyService.php";
require_once "../service/DataSeedService.php";
require_once "../service/FinancialService.php";
require_once "../service/GroupService.php";
require_once '../service/SystemService.php';

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


$app->group('/groups', function () use ($app) {
    $groupService = $app->GroupService;
    
    $app->post('/:groupID/userRole/:userID', function ($groupID,$userID) use ($app, $groupService) {
        try {
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            echo json_encode($groupService->setGroupMemberRole($groupID,$userID,$input->roleID));
            
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->post('/:groupID/removeuser/:userID', function ($groupID,$userID) use ($groupService) {
        try {
            $groupService->removeUserFromGroup($groupID,$userID);
            echo '{"success":"true"}';
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    $app->post('/:groupID/adduser/:userID', function ($groupID,$userID) use ($groupService) {
        try {
            echo json_encode( $groupService->addUserToGroup($groupID,$userID,0));
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    $app->delete('/:groupID', function ($groupID) use ($groupService) {
        try {
            $groupService->deleteGroup($groupID);
            echo '{"success":"true"}';
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->get('/:groupID', function ($groupID) use ($groupService) {
        try{
            echo $groupService->getGroupJSON($groupService->getGroups($groupID));
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
        
    });
    $app->post('/:groupID', function ($groupID) use ($app, $groupService) {
        try{
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            echo $groupService->updateGroup($groupID,$input);
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    $app->post('/', function () use ($app, $groupService) {
        try{
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            echo json_encode($groupService->createGroup($input->groupName));
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->post('/:groupID/roles/:roleID', function ($groupID,$roleID) use ($app, $groupService) {
        try{
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            if (property_exists($input,"groupRoleName"))
            {
                $groupService->setGroupRoleName($groupID,$roleID,$input->groupRoleName);
            }
            elseif (property_exists($input,"groupRoleOrder"))
            {
                $groupService->setGroupRoleOrder($groupID,$roleID,$input->groupRoleOrder);
            }
            
            echo '{"success":"true"}';
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->delete('/:groupID/roles/:roleID', function ($groupID,$roleID) use ($app, $groupService) {
        try{
            echo json_encode($groupService->deleteGroupRole($groupID,$roleID));
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->post('/:groupID/roles', function ($groupID) use ($app, $groupService) {
        try{
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            echo $groupService->addGroupRole($groupID,$input->roleName);
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->post('/:groupID/defaultRole', function ($groupID) use ($app, $groupService) {
        try{
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            $groupService->setGroupRoleAsDefault($groupID,$input->roleID);
            echo '{"success":"true"}';
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->post('/:groupID/setGroupSpecificPropertyStatus', function ($groupID) use ($app, $groupService) {
        try{
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            if ($input->GroupSpecificPropertyStatus)
            {
                $groupService->enableGroupSpecificProperties($groupID);
                return '{"status":"group specific properties enabled"}';
            }
            else
            {
                $groupService->disableGroupSpecificProperties($groupID);
                return '{"status":"group specific properties disabled"}';
            }
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
});

$app->group('/database', function () use ($app) {
    $systemService = $app->SystemService;
    $app->post('/backup', function () use ($app, $systemService) {
        try {
            $request = $app->request();
            $body = $request->getBody();
            $input = json_decode($body);
            $backup = $systemService->getDatabaseBackup($input);
            echo json_encode($backup);
        } catch (Exception $e) {
             echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->post('/restore', function () use ($app, $systemService) {
        try {
            $request = $app->request();
            $body = $request->getBody();
            $restore = $systemService->restoreDatabaseFromBackup();
            echo json_encode($restore);
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    
    $app->get('/download/:filename',function ($filename) use($app, $systemService) {
        try {
                $systemService->download($filename);
            } catch (Exception $e) {
                echo '{"error":{"text":' . $e->getMessage() . '}}';
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
            echo "[".join(",",array_filter($resultsArray))."]";
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
});


$app->group('/persons', function () use ($app) {
    $personService = $app->PersonService;
    $app->get('/search/:query', function ($query) use ($personService) {
        try {
            echo "[".$personService->getPersonsJSON($personService->search($query))."]";
        } catch (Exception $e) {
            echo exceptionToJSON($e);
        }
    });

    $app->group('/:id', function () use ($app, $personService) {
        $app->get('/',function($id) use ($personService) {
             echo "[".$personService->getPersonsJSON($personService->getPersonByID($id))."]";
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
            echo $app->FamilyService->search($query);
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
	$app->get('/byCheckNumber/:tScanString', function($tScanString) use ($app) 
	{
		try {
			$app->FinancialService->getMemberByScanString($sstrnig);
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
		
	});
	$app->get('/byEnvelopeNumber/:tEnvelopeNumber',function($tEnvelopeNumber) use ($app) 
	{
		try {
			$app->FamilyService->getFamilyStringByEnvelope($tEnvelopeNumber);
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
	});
	
});

$app->group('/deposits',function () use ($app) {
	$app->get('/',function() use ($app) 
	{
		try {
			echo '{"deposits": ' . json_encode($app->FinancialService->getDeposits()) . '}';
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
	});
	$app->get('/:id',function($id) use ($app) 
	{
		try {
			echo '{"deposits": ' . json_encode($app->FinancialService->getDeposits($id)) . '}';
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }	
	})->conditions(array('id' => '[0-9]+'));
	$app->get('/:id/payments',function($id) use ($app) 
	{
		try {
			$app->FinancialService->listPayments($id);
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
	})->conditions(array('id' => '[0-9]+'));
});



$app->group('/payments',function () use ($app) {
	$app->get('/', function () use ($app) {
		try {
			$app->FinancialService->getPayments();
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
	});
	$app->post('/', function () use ($app) {
		try {
			$payment=getJSONFromApp($app);
			$app->FinancialService->submitPledgeOrPayment($payment);
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
		
	});
	$app->get('/:id',function ($id) use ($app) {
		try {
			#$request = $app->request();
			#$body = $request->getBody();
			#$payment = json_decode($body);	
			#$app->FinancialService->getDepositsByFamilyID($fid);
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }	
	});
	$app->get('/byFamily/:familyId(/:fyid)', function ($familyId,$fyid=-1) use ($app) {
		try {
			#$request = $app->request();
			#$body = $request->getBody();
			#$payment = json_decode($body);	
			#$app->FinancialService->getDepositsByFamilyID($fid);
        }catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
	});
	$app->delete('/:groupKey',function ($groupKey) use ($app) {
		try {
			if (!$_SESSION['bAddRecords']) {
				throw new Exception (gettext("You must have at least AddRecords permission to use this API call"));
			}
			$app->FinancialService->deletePayment($groupKey);
			echo '{"status":"ok"}';
        }catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
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
    return '{"error":{"text":' . $e->getMessage() . ' !}}';
}

$app->run();

