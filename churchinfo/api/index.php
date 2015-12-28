<?php


require '../Include/Config.php';
require '../Include/Functions.php';

//Security
if (!isset($_SESSION['iUserID'])) {
    Redirect("Default.php");
    exit;
}

require_once '../vendor/Slim/Slim.php';

use Slim\Slim;

Slim::registerAutoloader();

// Services
require_once "../service/PersonService.php";
require_once "../service/FamilyService.php";
require_once "../service/DataSeedService.php";
require_once "../service/FinancialService.php";

function getJSONFromApp($app)
{
	
	$request = $app->request();
    $body = $request->getBody();
    return json_decode($body);
}

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

$app->container->singleton('FinancialService', function () {
   return new FinancialService();
});

$app->group('/persons', function () use ($app) {
    $app->get('/search/:query', function ($query) use ($app) {
        try {
            $app->PersonService->search($query);
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    $app->group('/:id', function () use ($app) {
        $app->get('/photo', function ($id) use ($app) {
            try {
                $app->PersonService->photo($id);
            } catch (Exception $e) {
                echo '{"error":{"text":' . $e->getMessage() . '}}';
            }
        });
    });
});

$app->group('/families', function () use ($app) {
    $app->get('/search/:query', function ($query) use ($app) {
        try {
            $app->FamilyService->search($query);
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    $app->get('/lastedited', function ($query) use ($app) {
        try {
            $app->FamilyService->lastEdited();
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
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
			$app->FinancialService->listDeposits();
		} catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
	});
	
	$app->get('/:id',function($id) use ($app) 
	{
		try {
			$app->FinancialService->listDeposits($id);
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

$app->run();



