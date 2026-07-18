<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageFundraisersRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
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
 * Apply optional Tier-1 fields (endDate, status, goalAmount, type, fundId) from a request
 * body to a FundRaiser model.
 *
 * WHY this helper exists: both POST (create) and PUT (update) need identical optional-field
 * validation and assignment. Extracting it here avoids duplicating ~50 lines of validation
 * logic. Callers only pass the fields they receive; all guards use array_key_exists() so
 * omitted fields are never touched — this preserves backward compatibility for existing API
 * consumers that only send a subset of fields (e.g. a client that only sets title/date).
 *
 * Returns a 400 error Response on validation failure, or null on success.
 */
function applyFundraiserFields(FundRaiser $fr, array $input, Response $response): ?Response
{
    if (array_key_exists('endDate', $input)) {
        $endDate = trim((string) ($input['endDate'] ?? ''));
        if ($endDate !== '') {
            $dateFmt  = SystemConfig::getValue('sDatePickerFormat');
            $parsedEnd = \DateTime::createFromFormat($dateFmt, $endDate);
            if ($parsedEnd === false || $parsedEnd->format($dateFmt) !== $endDate) {
                return SlimUtils::renderErrorJSON($response, gettext('Not a valid end date'), [], 400);
            }
            // End date must not precede start date.
            $startDate = $fr->getDate();
            if ($startDate !== null && $parsedEnd < $startDate) {
                return SlimUtils::renderErrorJSON($response, gettext('End date must be on or after the start date'), [], 400);
            }
            $fr->setEndDate($parsedEnd->format('Y-m-d')); // normalise to ISO for DB storage
        } else {
            $fr->setEndDate(null);
        }
    }
    if (array_key_exists('status', $input)) {
        $status = (string) ($input['status'] ?? 'Active');
        if (!in_array($status, ['Planning', 'Active', 'Closed'], true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid status value'), [], 400);
        }
        $fr->setStatus($status);
    }
    if (array_key_exists('goalAmount', $input)) {
        $goal = $input['goalAmount'];
        if ($goal !== null && $goal !== '') {
            if (!is_numeric($goal)) {
                return SlimUtils::renderErrorJSON($response, gettext('goalAmount must be a non-negative number'), [], 400);
            }
            $goalFloat = (float) $goal;
            if ($goalFloat < 0) {
                return SlimUtils::renderErrorJSON($response, gettext('goalAmount must be non-negative'), [], 400);
            }
            $fr->setGoalAmount($goalFloat);
        } else {
            $fr->setGoalAmount(null);
        }
    }
    if (array_key_exists('type', $input)) {
        $type = (string) ($input['type'] ?? 'Auction');
        $allowedTypes = ['Silent Auction', 'Live Auction', 'Raffle', 'Gala', 'Mixed', 'Auction'];
        if (!in_array($type, $allowedTypes, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid type value'), [], 400);
        }
        $fr->setType($type);
    }
    if (array_key_exists('fundId', $input)) {
        $fundId = $input['fundId'];
        if ($fundId !== null && $fundId !== '') {
            $fundIdInt = (int) $fundId;
            // Coerce invalid (non-positive) values to null rather than storing
            // a 0 that would incorrectly mark the fundraiser as "linked".
            $fr->setFundId($fundIdInt > 0 ? $fundIdInt : null);
        } else {
            $fr->setFundId(null);
        }
    }
    return null;
}

/**
 * Convert a FundRaiser model to a plain array safe for JSON output.
 */
function fundraiserToArray(FundRaiser $fr): array
{
    $goalAmount = $fr->getGoalAmount() !== null ? (float) $fr->getGoalAmount() : null;

    $dateFmt = SystemConfig::getValue('sDatePickerFormat');

    return [
        'id'                    => (int) $fr->getId(),
        'title'                 => $fr->getTitle(),
        'description'           => $fr->getDescription(),
        'date'                  => $fr->getDate() !== null ? $fr->getDate()->format($dateFmt) : null,
        'endDate'               => $fr->getEndDate() !== null ? $fr->getEndDate()->format($dateFmt) : null,
        'status'                => $fr->getStatus(),
        'goalAmount'            => $goalAmount,
        'goalAmount_formatted'  => $goalAmount !== null ? CurrencyFormatter::format($goalAmount) : null,
        'type'                  => $fr->getType(),
        'fundId'                => $fr->getFundId() !== null ? (int) $fr->getFundId() : null,
        'enteredBy'             => (int) $fr->getEnteredBy(),
        'enteredDate'           => $fr->getEnteredDate() !== null ? $fr->getEnteredDate()->format($dateFmt) : null,
    ];
}

$app->group('/fundraisers', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/fundraisers",
     *     summary="List all fundraisers (Manage Fundraisers role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Array of fundraiser objects"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Manage Fundraisers role required")
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
     *     summary="Create a new fundraiser (Manage Fundraisers role required)",
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
     *     @OA\Response(response=403, description="Manage Fundraisers role required")
     * )
     */
    $group->post('', function (Request $request, Response $response, array $args): Response {
        try {
            $input = (array) $request->getParsedBody();
            $title = (string) ($input['title'] ?? '');
            $description = (string) ($input['description'] ?? '');
            $date = trim((string) ($input['date'] ?? ''));

            if ($title === '') {
                return SlimUtils::renderErrorJSON($response, gettext('Title is required'), [], 400);
            }

            if ($date !== '') {
                $dateFmt = SystemConfig::getValue('sDatePickerFormat');
                $parsed  = \DateTime::createFromFormat($dateFmt, $date);
                if ($parsed === false || $parsed->format($dateFmt) !== $date) {
                    return SlimUtils::renderErrorJSON($response, gettext('Not a valid date'), [], 400);
                }
                $date = $parsed->format('Y-m-d'); // normalise to ISO for DB storage
            } else {
                $date = DateTimeUtils::getToday()->format('Y-m-d');
            }

            $fr = new FundRaiser();
            $fr->setTitle($title);
            $fr->setDescription($description);
            $fr->setDate($date);
            $fieldErr = applyFundraiserFields($fr, $input, $response);
            if ($fieldErr !== null) {
                return $fieldErr;
            }
            $fr->setEnteredBy((int) AuthenticationManager::getCurrentUser()->getId());
            $fr->setEnteredDate(DateTimeUtils::getToday()->format('Y-m-d'));
            $fr->save();
            // Invalidate menu counter cache (new fundraiser may affect active count).
            unset($_SESSION['iFundraiserActiveCount']);

            return SlimUtils::renderJSON($response, ['fundraiser' => fundraiserToArray($fr)], 201);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create fundraiser'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['title' => 'text', 'description' => 'text']));

    /**
     * @OA\Get(
     *     path="/fundraisers/{id}",
     *     summary="Get a single fundraiser (Manage Fundraisers role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fundraiser object"),
     *     @OA\Response(response=404, description="Fundraiser not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Manage Fundraisers role required")
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
     *     summary="Update a fundraiser (Manage Fundraisers role required)",
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
     *     @OA\Response(response=403, description="Manage Fundraisers role required")
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
                $title = (string) $input['title'];
                if ($title === '') {
                    return SlimUtils::renderErrorJSON($response, gettext('Title is required'), [], 400);
                }
                $fr->setTitle($title);
            }

            if (array_key_exists('description', $input)) {
                $fr->setDescription((string) $input['description']);
            }

            if (array_key_exists('date', $input)) {
                $date = trim((string) $input['date']);
                if ($date !== '') {
                    $dateFmt = SystemConfig::getValue('sDatePickerFormat');
                    $parsed  = \DateTime::createFromFormat($dateFmt, $date);
                    if ($parsed === false || $parsed->format($dateFmt) !== $date) {
                        return SlimUtils::renderErrorJSON($response, gettext('Not a valid date'), [], 400);
                    }
                    $fr->setDate($parsed->format('Y-m-d'));
                }
            }
            $fieldErr = applyFundraiserFields($fr, $input, $response);
            if ($fieldErr !== null) {
                return $fieldErr;
            }

            $fr->save();
            // Invalidate menu counter cache (status may have changed).
            unset($_SESSION['iFundraiserActiveCount']);

            return SlimUtils::renderJSON($response, ['fundraiser' => fundraiserToArray($fr)]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update fundraiser'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['title' => 'text', 'description' => 'text']));

    /**
     * @OA\Delete(
     *     path="/fundraisers/{id}",
     *     summary="Delete a fundraiser (Manage Fundraisers role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fundraiser deleted"),
     *     @OA\Response(response=404, description="Fundraiser not found"),
     *     @OA\Response(response=409, description="Fundraiser still has associated donated items"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Manage Fundraisers role required")
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
            // Invalidate menu counter cache (fundraiser removed).
            unset($_SESSION['iFundraiserActiveCount']);

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete fundraiser'), [], 500, $e, $request);
        }
    });
})->add(ManageFundraisersRoleAuthMiddleware::class);
