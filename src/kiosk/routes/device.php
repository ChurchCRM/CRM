<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Device routes - these are accessed by kiosk devices themselves (not admins)
// They use kiosk cookie authentication, not user authentication
$app->group('/device', function (RouteCollectorProxy $group) use ($getKioskFromCookie): void {
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

    $group->get('/heartbeat', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }

        return SlimUtils::renderJSON($response, $kiosk->heartbeat());
    });

    $group->post('/checkin', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();
        $personId = InputUtils::filterInt($input['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }
        
        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }
        $status = $kiosk->getActiveAssignment()->getEvent()->checkInPerson($personId);

        return SlimUtils::renderJSON($response, $status);
    });

    $group->post('/checkout', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();
        $personId = InputUtils::filterInt($input['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }
        
        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }
        $status = $kiosk->getActiveAssignment()->getEvent()->checkOutPerson($personId);

        return SlimUtils::renderJSON($response, $status);
    });

    $group->post('/triggerNotification', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();
        $personId = InputUtils::filterInt($input['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        $Person = PersonQuery::create()
                ->findOneById($personId);
        
        if ($Person === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not found'), [], 404);
        }

        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }
        $event = $kiosk->getActiveAssignment()->getEvent();
        
        // Get event/group name for the notification
        $groups = $event->getGroups();
        $eventName = $groups->count() > 0 ? $groups->getFirst()->getName() : $event->getTitle();

        $family = $Person->getFamily();
        if ($family === null) {
            LoggerUtils::getAppLogger()->warning('triggerNotification: Person has no family', [
                'personId' => $personId,
                'personName' => $Person->getFirstName() . ' ' . $Person->getLastName(),
                'eventName' => $eventName,
            ]);
            return SlimUtils::renderErrorJSON($response, gettext('Person has no family'), [], 404);
        }

        LoggerUtils::getAppLogger()->info('triggerNotification: Sending notification', [
            'personId' => $personId,
            'personName' => $Person->getFirstName() . ' ' . $Person->getLastName(),
            'eventName' => $eventName,
            'familyId' => $family->getId(),
            'recipientCount' => count($family->getAdults()),
        ]);

        $Notification = new Notification();
        $Notification->setPerson($Person);
        $Notification->setRecipients($family->getAdults());
        $Notification->setEventName($eventName);
        $Notification->setProjectorText($event->getType() . '-' . $Person->getId());
        $status = $Notification->send();

        // Log notification result — send() returns ['status' => '', 'methods' => ['email: 1', 'sms: 1', ...]]
        // A method is successful when its string ends with ': 1' (PHP bool true cast to string)
        $methods = $status['methods'] ?? [];
        $anySuccess = !empty(array_filter($methods, fn ($m) => str_ends_with((string) $m, '1')));
        $logContext = [
            'personId' => $personId,
            'personName' => $Person->getFirstName() . ' ' . $Person->getLastName(),
            'eventName' => $eventName,
            'methods' => $methods,
        ];
        if ($anySuccess) {
            LoggerUtils::getAppLogger()->info('triggerNotification: Notification sent', $logContext);
        } else {
            LoggerUtils::getAppLogger()->warning('triggerNotification: No notification channel delivered', $logContext);
        }

        return SlimUtils::renderJSON($response, $status);
    });

    $group->get('/activeClassMembers', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }
        $members = $kiosk->getActiveAssignment()->getActiveGroupMembers();

        // Get the group name for context
        $event = $kiosk->getActiveAssignment()->getEvent();
        $groups = $event->getGroups();
        $groupName = $groups->count() > 0 ? $groups->getFirst()->getName() : '';

        // Build response array using Person object methods
        // Use configured timezone for "today" calculations
        $today = DateTimeUtils::getToday();
        $currentMonth = (int) $today->format('n');
        $currentDay = (int) $today->format('j');
        $currentYear = (int) $today->format('Y');
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
                $birthDate = DateTimeUtils::createDateTime("$birthYear-$birthMonth-$birthDay");
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
                // Calculate this year's birthday using configured timezone
                $thisBirthday = DateTimeUtils::getToday();
                $thisBirthday->setDate($currentYear, $birthMonth, $birthDay);

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

            $family = $person->getFamily();
            $familyId = $family !== null ? $family->getId() : null;

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
                'familyId' => $familyId,
            ];
        }

        // Check if any notification method is configured
        $openLpPlugin = PluginManager::getPlugin('openlp');
        $openLpEnabled = $openLpPlugin !== null && $openLpPlugin->isEnabled() && $openLpPlugin->isConfigured();

        $vonagePlugin = PluginManager::getPlugin('vonage');
        $smsEnabled = $vonagePlugin !== null && $vonagePlugin->isEnabled() && $vonagePlugin->isConfigured();

        $notificationsEnabled = SystemConfig::hasValidMailServerSettings() ||
                                $smsEnabled ||
                                $openLpEnabled;

        return SlimUtils::renderJSON($response, [
            'People' => $peopleData,
            'GroupName' => $groupName,
            'notificationsEnabled' => $notificationsEnabled,
        ]);
    });

    $group->get('/activeClassMember/{PersonId}/photo', function (Request $request, Response $response, array $args): Response {
        $photo = new Photo('Person', $args['PersonId']);

        $response->getBody()->write($photo->getPhotoBytes());

        return $response->withAddedHeader('Content-type', $photo->getPhotoContentType());
    });

    $group->post('/checkoutAll', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }
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
