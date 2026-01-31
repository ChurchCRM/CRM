<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\dto\Photo;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Device routes - these are accessed by kiosk devices themselves (not admins)
// They use kiosk cookie authentication, not user authentication
$app->group('/device', function (RouteCollectorProxy $group) use ($app): void {
    $group->get('', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../templates/kioskDevices/');
        $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

        return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
    });

    $group->get('/', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../templates/kioskDevices/');
        $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

        return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
    });

    $group->get('/heartbeat', function (Request $request, Response $response) use ($app): Response {
        $kiosk = $app->getContainer()->get('kiosk');

        return SlimUtils::renderJSON($response, $kiosk->heartbeat());
    });

    $group->post('/checkin', function (Request $request, Response $response) use ($app): Response {
        $input = $request->getParsedBody();
        $kiosk = $app->getContainer()->get('kiosk');
        $status = $kiosk->getActiveAssignment()->getEvent()->checkInPerson($input['PersonId']);

        return SlimUtils::renderJSON($response, $status);
    });

    $group->post('/checkout', function (Request $request, Response $response) use ($app): Response {
        $input = $request->getParsedBody();
        $kiosk = $app->getContainer()->get('kiosk');
        $status = $kiosk->getActiveAssignment()->getEvent()->checkOutPerson($input['PersonId']);

        return SlimUtils::renderJSON($response, $status);
    });

    $group->post('/triggerNotification', function (Request $request, Response $response) use ($app): Response {
        $input = $request->getParsedBody();

        $Person = PersonQuery::create()
                ->findOneById($input['PersonId']);

        $Notification = new Notification();
        $Notification->setPerson($Person);
        $Notification->setRecipients($Person->getFamily()->getAdults());
        $kiosk = $app->getContainer()->get('kiosk');
        $Notification->setProjectorText($kiosk->getActiveAssignment()->getEvent()->getType() . '-' . $Person->getId());
        $status = $Notification->send();

        return SlimUtils::renderJSON($response, $status);
    });

    $group->get('/activeClassMembers', function (Request $request, Response $response) use ($app): Response {
        $kiosk = $app->getContainer()->get('kiosk');
        $members = $kiosk->getActiveAssignment()->getActiveGroupMembers();

        // Get the group name for context
        $event = $kiosk->getActiveAssignment()->getEvent();
        $groups = $event->getGroups();
        $groupName = $groups->count() > 0 ? $groups->getFirst()->getName() : '';

        // Build response array using Person object methods
        $currentMonth = (int) date('n');
        $currentDay = (int) date('j');
        $currentYear = (int) date('Y');
        $peopleData = [];
        foreach ($members as $person) {
            $photo = new Photo('Person', $person->getId());

            // Get birth data - returns 0 when not set
            $birthMonth = (int) $person->getBirthMonth();
            $birthDay = (int) $person->getBirthDay();
            $birthYear = $person->getBirthYear();

            // Calculate age - try multiple approaches
            $age = null;
            if (!empty($birthYear) && $birthMonth > 0 && $birthDay > 0) {
                // Calculate age manually to avoid hideAge() interference
                $birthDate = new \DateTime("$birthYear-$birthMonth-$birthDay");
                $today = new \DateTime('today');
                $ageInterval = $today->diff($birthDate);
                $age = $ageInterval->y;
            }

            // Birthday is "this month" if birthMonth matches current month
            $birthdayThisMonth = ($birthMonth > 0 && $birthMonth === $currentMonth);

            // Calculate if birthday is upcoming (within next 14 days) or recent (within past 14 days)
            $birthdayUpcoming = false;
            $birthdayRecent = false;
            $birthdayToday = false;
            if ($birthMonth > 0 && $birthDay > 0) {
                // Calculate this year's birthday
                $thisBirthday = new \DateTime();
                $thisBirthday->setDate($currentYear, $birthMonth, $birthDay);
                $today = new \DateTime('today');

                // Get the difference in days
                $interval = $today->diff($thisBirthday);
                $daysDiff = (int) $interval->format('%r%a');

                // Check if birthday is today
                if ($daysDiff === 0) {
                    $birthdayToday = true;
                    $birthdayUpcoming = true;
                }
                // Upcoming: birthday in next 14 days (positive diff)
                elseif ($daysDiff > 0 && $daysDiff <= 14) {
                    $birthdayUpcoming = true;
                }
                // Recent: birthday in past 14 days (negative diff)
                elseif ($daysDiff < 0 && $daysDiff >= -14) {
                    $birthdayRecent = true;
                }
            }

            $peopleData[] = [
                'Id' => $person->getId(),
                'FirstName' => $person->getFirstName(),
                'LastName' => $person->getLastName(),
                'Gender' => $person->getGender(),
                'age' => $age,
                'birthdayThisMonth' => $birthdayThisMonth,
                'birthdayUpcoming' => $birthdayUpcoming,
                'birthdayRecent' => $birthdayRecent,
                'birthdayToday' => $birthdayToday,
                'birthDay' => $birthDay > 0 ? $birthDay : null,
                'birthMonth' => $birthMonth > 0 ? $birthMonth : null,
                'hasPhoto' => $photo->hasUploadedPhoto(),
                'RoleName' => $person->getVirtualColumn('RoleName'),
                'status' => $person->getVirtualColumn('status'),
            ];
        }

        return SlimUtils::renderJSON($response, [
            'People' => $peopleData,
            'GroupName' => $groupName,
        ]);
    });

    $group->get('/activeClassMember/{PersonId}/photo', function (Request $request, Response $response, array $args): Response {
        $photo = new Photo('Person', $args['PersonId']);

        $response->getBody()->write($photo->getPhotoBytes());

        return $response->withAddedHeader('Content-type', $photo->getPhotoContentType());
    });

    $group->post('/checkoutAll', function (Request $request, Response $response) use ($app): Response {
        $kiosk = $app->getContainer()->get('kiosk');
        $event = $kiosk->getActiveAssignment()->getEvent();
        $checkedInPeople = $event->getEventAttends();
        
        $checkedOutCount = 0;
        foreach ($checkedInPeople as $attendance) {
            if ($attendance->getCheckoutDate() === null) {
                $event->checkOutPerson($attendance->getPersonId());
                $checkedOutCount++;
            }
        }

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'checkedOut' => $checkedOutCount,
        ]);
    });
});

// Legacy routes - redirect to /device prefix
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates/kioskDevices/');
    $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

    return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
});
