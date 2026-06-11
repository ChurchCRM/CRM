<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\AttendanceService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/attendance', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/attendance/person/{personId}",
     *     operationId="getPersonAttendanceHistory",
     *     summary="Get attendance history for a person",
     *     description="Returns all events a person has been checked in to (checkin_date IS NOT NULL), sorted by event start date descending, along with summary statistics including total events attended, last attendance date, and per-type attendance streaks.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(
     *         name="personId",
     *         in="path",
     *         required=true,
     *         description="Person ID",
     *         @OA\Schema(type="integer", example=104)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance history and summary stats",
     *         @OA\JsonContent(
     *             @OA\Property(property="records", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="attendId", type="integer", example=1),
     *                     @OA\Property(property="eventId", type="integer", example=3),
     *                     @OA\Property(property="eventUrl", type="string", example="/event/view/3"),
     *                     @OA\Property(property="eventTitle", type="string", example="Summer Camp"),
     *                     @OA\Property(property="eventTypeId", type="integer", nullable=true, example=2),
     *                     @OA\Property(property="eventTypeName", type="string", example="Sunday School"),
     *                     @OA\Property(property="eventStart", type="string", format="date-time", example="2017-06-06 09:30:00"),
     *                     @OA\Property(property="eventEnd", type="string", format="date-time", example="2017-06-11 09:30:00"),
     *                     @OA\Property(property="checkinDate", type="string", format="date-time", nullable=true, example="2017-04-15 17:23:46"),
     *                     @OA\Property(property="checkoutDate", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="eventInactive", type="boolean", example=false)
     *                 )
     *             ),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="totalEvents", type="integer", example=5),
     *                 @OA\Property(property="lastAttendanceDate", type="string", format="date-time", nullable=true, example="2017-06-06 09:30:00"),
     *                 @OA\Property(property="streaks", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="typeId", type="integer", nullable=true, example=2),
     *                         @OA\Property(property="typeName", type="string", example="Sunday School"),
     *                         @OA\Property(property="length", type="integer", example=3)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — caller does not have permission to view this person"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->get('/person/{personId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $personId = (int) $args['personId'];

        $person = PersonQuery::create()->findPk($personId);
        if ($person === null) {
            throw new HttpNotFoundException($request, gettext('Person not found'));
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canEditPerson($personId, (int) $person->getFamId())) {
            throw new HttpForbiddenException($request, gettext('You do not have permission to view this person'));
        }

        $service = new AttendanceService();
        $data = $service->getPersonAttendanceHistory($personId);

        return SlimUtils::renderJSON($response, $data);
    });
});
