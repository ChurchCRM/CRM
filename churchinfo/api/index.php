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
require_once "../service/GroupService.php";

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

$app->container->singleton('GroupService', function () {
    return new GroupService();
});


$app->group('/groups', function () use ($app) {
    $app->post('/:groupID/removeuser/:userID', function ($groupID,$userID) use ($app) {
        try {
            $app->GroupService->removeUserFromGroup($userID,$groupID);
            echo '{"success":"true"}';
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
            array_push($resultsArray, $app->GroupService->getGroupsJSON($app->GroupService->search($query)));
            echo "[".join(",",array_filter($resultsArray))."]";
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
});

$app->group('/persons', function () use ($app) {
    $app->get('/search/:query', function ($query) use ($app) {
        try {
        echo "[".$app->PersonService->getPersonsJSON($app->PersonService->search($query))."]";
        } catch (Exception $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    });
    $app->group('/:id', function () use ($app) {
        $app->get('/',function($id) use ($app) {
             echo "[".$app->PersonService->getPersonsJSON($app->PersonService->getPersonByID($id))."]";
        });
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
            echo $app->FamilyService->search($query);
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


