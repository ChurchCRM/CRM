<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * REST API for Fundraiser CRUD — replaces legacy FundRaiserEditor.php /
 * FundRaiserDelete.php / FindFundRaiser.php form-post workflow.
 *
 * Provides the canonical read/write path for fundraiser records so clients
 * (mobile, MVC migration, integrations) don't depend on legacy form posts.
 */

/**
 * Convert a FundRaiser model to a plain array safe for JSON output.
 */
function fundraiserToArray(FundRaiser $fr): array
{
    return [
        'id'          => (int) $fr->getId(),
        'title'       => $fr->getTitle(),
        'description' => $fr->getDescription(),
        'date'        => $fr->getDate() !== null ? $fr->getDate()->format('Y-m-d') : null,
        'enteredBy'   => (int) $fr->getEnteredBy(),
        'enteredDate' => $fr->getEnteredDate() !== null ? $fr->getEnteredDate()->format('Y-m-d') : null,
    ];
}

$app->group('/fundraisers', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/fundraisers",
     *     summary="List all fundraisers (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Array of fundraiser objects"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $list = FundRaiserQuery::create()
            ->orderByDate('desc')
            ->find();

        $out = [];
        foreach ($list as $fr) {
            $out[] = fundraiserToArray($fr);
        }

        return SlimUtils::renderJSON($response, ['fundraisers' => $out]);
    });

    /**
     * @OA\Post(
     *     path="/fundraisers",
     *     summary="Create a new fundraiser (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", maxLength=128),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Newly created fundraiser object"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('', function (Request $request, Response $response, array $args): Response {
        try {
            $input = (array) $request->getParsedBody();
            $title = InputUtils::sanitizeText($input['title'] ?? '');
            $description = InputUtils::sanitizeText($input['description'] ?? '');
            $date = trim((string) ($input['date'] ?? ''));

            if ($title === '') {
                return SlimUtils::renderErrorJSON($response, gettext('Title is required'), [], 400);
            }

            if ($date !== '') {
                $parsed = \DateTime::createFromFormat('Y-m-d', $date);
                if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                    return SlimUtils::renderErrorJSON($response, gettext('Not a valid date'), [], 400);
                }
            } else {
                $date = DateTimeUtils::getToday()->format('Y-m-d');
            }

            $fr = new FundRaiser();
            $fr->setTitle($title);
            $fr->setDescription($description);
            $fr->setDate($date);
            $fr->setEnteredBy((int) AuthenticationManager::getCurrentUser()->getId());
            $fr->setEnteredDate(DateTimeUtils::getToday()->format('Y-m-d'));
            $fr->save();

            return SlimUtils::renderJSON($response, ['fundraiser' => fundraiserToArray($fr)], 201);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create fundraiser'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Get(
     *     path="/fundraisers/{id}",
     *     summary="Get a single fundraiser (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fundraiser object"),
     *     @OA\Response(response=404, description="Fundraiser not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $fr = FundRaiserQuery::create()->findPk((int) $args['id']);
        if ($fr === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Fundraiser not found'), [], 404);
        }

        return SlimUtils::renderJSON($response, ['fundraiser' => fundraiserToArray($fr)]);
    });

    /**
     * @OA\Put(
     *     path="/fundraisers/{id}",
     *     summary="Update a fundraiser (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=128),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated fundraiser object"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Fundraiser not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->put('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $fr = FundRaiserQuery::create()->findPk((int) $args['id']);
            if ($fr === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Fundraiser not found'), [], 404);
            }

            $input = (array) $request->getParsedBody();

            if (array_key_exists('title', $input)) {
                $title = InputUtils::sanitizeText($input['title']);
                if ($title === '') {
                    return SlimUtils::renderErrorJSON($response, gettext('Title is required'), [], 400);
                }
                $fr->setTitle($title);
            }

            if (array_key_exists('description', $input)) {
                $fr->setDescription(InputUtils::sanitizeText($input['description']));
            }

            if (array_key_exists('date', $input)) {
                $date = trim((string) $input['date']);
                if ($date !== '') {
                    $parsed = \DateTime::createFromFormat('Y-m-d', $date);
                    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                        return SlimUtils::renderErrorJSON($response, gettext('Not a valid date'), [], 400);
                    }
                    $fr->setDate($date);
                }
            }

            $fr->save();

            return SlimUtils::renderJSON($response, ['fundraiser' => fundraiserToArray($fr)]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update fundraiser'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Delete(
     *     path="/fundraisers/{id}",
     *     summary="Delete a fundraiser (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fundraiser deleted"),
     *     @OA\Response(response=404, description="Fundraiser not found"),
     *     @OA\Response(response=409, description="Fundraiser still has associated donated items"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $id = (int) $args['id'];
            $fr = FundRaiserQuery::create()->findPk($id);
            if ($fr === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Fundraiser not found'), [], 404);
            }

            // Block deletion when donated items still reference this fundraiser.
            // Callers must remove the items first rather than silently orphan
            // them or cascade-delete auction/raffle history.
            $itemCount = DonatedItemQuery::create()->filterByFrId($id)->count();
            if ($itemCount > 0) {
                return SlimUtils::renderErrorJSON(
                    $response,
                    sprintf(
                        gettext('Cannot delete fundraiser: %d donated items are still associated. Remove the items first.'),
                        $itemCount
                    ),
                    [],
                    409
                );
            }

            $fr->delete();

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete fundraiser'), [], 500, $e, $request);
        }
    });
})->add(FinanceRoleAuthMiddleware::class);
