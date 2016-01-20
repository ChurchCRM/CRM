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
require_once '../vendor/Slim/Slim/Slim.php';

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



$app->group('/search', function () use ($app) {
    $app->get('/:query', function ($query) use ($app) {
        try {
            echo "[ ".$app->PersonService->search($query).", ";
            echo $app->FamilyService->search($query)."]";
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
});

$app->group('/persons', function () use ($app) {
    $personService = $app->PersonService;
    $app->get('/search/:query', function ($query) use ($personService) {
        try {
            echo $personService->search($query);
        } catch (Exception $e) {
            echo exceptionToJSON($e);
        }
    });
    $app->group('/:id', function () use ($app, $personService) {
        $app->get('/', function ($id) use ($personService) {
            try {
                $person = $personService->get($id);
                echo $person;
            } catch (Exception $e) {
                echo exceptionToJSON($e);
            }
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

/**
 * @param $e
 */
function exceptionToJSON($e)
{
    return '{"error":{"text":' . $e->getMessage() . ' !}}';
}

$app->run();
