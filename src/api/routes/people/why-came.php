<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\WhyCame;
use ChurchCRM\model\ChurchCRM\WhyCameQuery;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Convert a WhyCame ORM object to a plain array for API responses.
 */
function whyCameToArray(WhyCame $whyCame): array
{
    return [
        'id'         => $whyCame->getId(),
        'perId'      => $whyCame->getPerId(),
        'join'       => $whyCame->getJoin(),
        'come'       => $whyCame->getCome(),
        'suggest'    => $whyCame->getSuggest(),
        'hearOfUs'   => $whyCame->getHearOfUs(),
    ];
}

$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/person/{personId}/why-came",
     *     operationId="getPersonWhyCame",
     *     summary="Get why-came entry for a person",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Why-came entry or null"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="EditRecords required"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->get('/why-came', function (Request $request, Response $response): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        $whyCame = WhyCameQuery::create()->findOneByPerId($person->getId());

        return SlimUtils::renderJSON($response, [
            'whyCame' => $whyCame ? whyCameToArray($whyCame) : null,
        ]);
    })->add(EditRecordsRoleAuthMiddleware::class);

    /**
     * @OA\Post(
     *     path="/person/{personId}/why-came",
     *     operationId="createOrUpdatePersonWhyCame",
     *     summary="Create or update why-came entry for a person",
     *     description="Matches legacy WhyCameEditor permission (EditRecords). Creates if none exists for the person.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="join", type="string"),
     *             @OA\Property(property="come", type="string"),
     *             @OA\Property(property="suggest", type="string"),
     *             @OA\Property(property="hearOfUs", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Why-came saved"),
     *     @OA\Response(response=201, description="Why-came created"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="EditRecords required"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->post('/why-came', function (Request $request, Response $response): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        $input = (array) $request->getParsedBody();

        $whyCame = WhyCameQuery::create()->findOneByPerId($person->getId());
        $isNew = $whyCame === null;
        if ($isNew) {
            $whyCame = new WhyCame();
            $whyCame->setPerId($person->getId());
        }

        $whyCame
            ->setJoin((string) ($input['join'] ?? ''))
            ->setCome((string) ($input['come'] ?? ''))
            ->setSuggest((string) ($input['suggest'] ?? ''))
            ->setHearOfUs((string) ($input['hearOfUs'] ?? ''));

        $whyCame->save();

        return SlimUtils::renderJSON($response, ['whyCame' => whyCameToArray($whyCame)], $isNew ? 201 : 200);
    })->add(EditRecordsRoleAuthMiddleware::class);
})->add(PersonMiddleware::class);
