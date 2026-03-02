<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Service\DepositService;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/deposits', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Post(
     *     path="/deposits",
     *     summary="Create a new deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"depositType"},
     *             @OA\Property(property="depositType", type="string", enum={"Bank","CreditCard","BankDraft"}),
     *             @OA\Property(property="depositComment", type="string"),
     *             @OA\Property(property="depositDate", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Newly created deposit object"),
     *     @OA\Response(response=400, description="Invalid or missing deposit type"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('', function (Request $request, Response $response, array $args): Response {
        $depositService = new DepositService();
        $input = $request->getParsedBody();
        $depositType = $input['depositType'] ?? '';
        $depositComment = InputUtils::sanitizeText($input['depositComment']) ?? '';
        $depositDate = $input['depositDate'] ?? DateTimeUtils::getTodayDate();

        // Validate depositType against allowed values
        $allowedTypes = ['Bank', 'CreditCard', 'BankDraft'];
        if (!in_array($depositType, $allowedTypes, true)) {
            $errorMsg = $depositType === ''
                ? 'Deposit type is required. Please provide one of: ' . implode(', ', $allowedTypes)
                : "Deposit type '$depositType' is invalid. Allowed types: " . implode(', ', $allowedTypes);
            return SlimUtils::renderJSON($response->withStatus(400), [
                'error' => $errorMsg,
                'allowedTypes' => $allowedTypes
            ]);
        }

        $deposit = $depositService->createDeposit($depositType, $depositComment, $depositDate);
        return SlimUtils::renderJSON($response, $deposit->toArray());
    });

    /**
     * @OA\Get(
     *     path="/deposits/dashboard",
     *     summary="Get deposits from the last 90 days (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Array of deposits within the last 90 days"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/dashboard', function (Request $request, Response $response, array $args): Response {
        $list = DepositQuery::create()
            ->filterByDate(['min' => DateTimeUtils::getRelativeDate('-90 days')])
            ->find();

        return SlimUtils::renderJSON($response, $list->toArray());
    });

    /**
     * @OA\Get(
     *     path="/deposits",
     *     summary="Get all deposits (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="JSON array of all deposits"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get(
        '',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderStringJSON(
            $response,
            DepositQuery::create()->find()->toJSON()
        )
    );

    /**
     * @OA\Get(
     *     path="/deposits/{id}",
     *     summary="Get a single deposit by ID (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deposit object"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $deposit = DepositQuery::create()->findOneById($id);
        return SlimUtils::renderJSON($response, $deposit->toArray());
    });

    /**
     * @OA\Post(
     *     path="/deposits/{id}",
     *     summary="Update an existing deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="depositType", type="string", enum={"Bank","CreditCard","BankDraft"}),
     *             @OA\Property(property="depositComment", type="string"),
     *             @OA\Property(property="depositDate", type="string", format="date"),
     *             @OA\Property(property="depositClosed", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated deposit object"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $input = $request->getParsedBody();
        $appDeposit = DepositQuery::create()->findOneById($id);
        $appDeposit->setType($input['depositType']);
        $appDeposit->setComment(InputUtils::escapeHTML($input['depositComment'] ?? ''));
        $appDeposit->setDate($input['depositDate']);
        $appDeposit->setClosed($input['depositClosed']);
        $appDeposit->save();
        return SlimUtils::renderJSON($response, $appDeposit->toArray());
    });

    /**
     * @OA\Get(
     *     path="/deposits/{id}/ofx",
     *     summary="Get OFX export data for a deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OFX content for the deposit"),
     *     @OA\Response(response=404, description="Deposit not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}/ofx', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $deposit = DepositQuery::create()->findOneById($id);
        if ($deposit === null) {
            return SlimUtils::renderJSON($response->withStatus(404), ['message' => 'Deposit not found']);
        }
        $OFX = $deposit->getOFX();
        header($OFX->header);
        return SlimUtils::renderJSON($response, ['content' => $OFX->content]);
    });

    /**
     * @OA\Get(
     *     path="/deposits/{id}/pdf",
     *     summary="Generate and stream a PDF report for a deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF generated successfully"),
     *     @OA\Response(response=404, description="Deposit not found or has no payments"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}/pdf', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];

        // If there are no payments for this deposit, return a controlled response
        $paymentsCount = PledgeQuery::create()->filterByDepId($id)->count();
        if ($paymentsCount === 0) {
            // Some clients and probes use HEAD; respond with appropriate status without throwing
            if (strtoupper($request->getMethod()) === 'HEAD') {
                return $response->withStatus(404);
            }

            return SlimUtils::renderJSON($response->withStatus(404), ['message' => 'No Payments on this Deposit']);
        }

        $deposit = DepositQuery::create()->findOneById($id);
        if ($deposit === null) {
            return SlimUtils::renderJSON($response->withStatus(404), ['message' => 'Deposit not found']);
        }
        $deposit->getPDF();
        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Get(
     *     path="/deposits/{id}/csv",
     *     summary="Download a CSV export of payments for a deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="CSV file attachment with pledge/payment data"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}/csv', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];

        $filename = 'ChurchCRM-Deposit-' . $id . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv';
        $csvData = PledgeQuery::create()->filterByDepId($id)
            ->joinDonationFund()->useDonationFundQuery()
            ->withColumn(DonationFundTableMap::COL_FUN_NAME, 'DonationFundName')
            ->endUse()
            ->leftJoinFamily()->useFamilyQuery()
            ->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName')
            ->endUse()
            ->find()
            ->toCSV();

        $response = $response->withHeader('Content-Type', 'text/csv');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename=' . $filename);

        $response->getBody()->write($csvData);

        return $response;
    });

    /**
     * @OA\Delete(
     *     path="/deposits/{id}",
     *     summary="Delete a deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deposit deleted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $deposit = DepositQuery::create()->findOneById($id);
        if ($deposit) {
            $deposit->delete();
        }
        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Get(
     *     path="/deposits/{id}/pledges",
     *     summary="Get pledge items for a deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Pledge items associated with the deposit"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}/pledges', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $depositService = new DepositService();
        $result = $depositService->getDepositItemsByType($id, 'Pledge');
        return SlimUtils::renderJSON($response, $result);
    });

    /**
     * @OA\Get(
     *     path="/deposits/{id}/payments",
     *     summary="Get payment items for a deposit (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payment items associated with the deposit"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}/payments', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $depositService = new DepositService();
        $result = $depositService->getDepositItemsByType($id, 'Payment');
        return SlimUtils::renderJSON($response, $result);
    });
})->add(FinanceRoleAuthMiddleware::class);
