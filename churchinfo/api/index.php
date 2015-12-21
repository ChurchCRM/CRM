<?php


require '../Include/Config.php';
require '../Include/Functions.php';

//Security
if (!isset($_SESSION['iUserID'])) {
    Redirect("Default.php");
    exit;
}


require 'vendor/Slim/Slim.php';

use Slim\Slim;


Slim::registerAutoloader();

// Services
require_once "services/PersonService.php";
require_once "services/FamilyService.php";
require_once "services/DataSeedService.php";
require_once "services/FinancialService.php";

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
    $app->get('/:query', function ($query) use ($app) {
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
		getMemberByScanString($sstrnig);
	});
	$app->get('/list/byEnvelopeNumber/:tEnvelopeNumber',function($tEnvelopeNumber) use ($app) 
	{
		$return[] = getFamilyStringByEnvelope($tEnvelopeNumber);
		echo json_encode($return);
		
	});
	
});

$app->group('/deposits',function () use ($app) {
	$app->get('/','listDeposits');
	$app->get('/:id','listDeposits')->conditions(array('id' => '[0-9]+'));
	$app->get('/:id/payments','listPayments')->conditions(array('id' => '[0-9]+'));
});



$app->group('/payments',function () use ($app) {
	$app->get('/','listPayments');
	$app->post('/', function () use ($app) {
		$request = $app->request();
		$body = $request->getBody();
		$payment = json_decode($body);	
		processPayment($payment);
	});
	$app->get('/:id','listPayments')->conditions(array('id' => '[0-9]+'));
	$app->get('/byFamily/:familyId(/:fyid)', function ($familyId,$fyid=-1) use ($app) {
		getDepositsByFamilyID($fid);
		
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


