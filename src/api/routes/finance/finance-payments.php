<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use ChurchCRM\Service\FinancialService;

$app->group('/payments', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/payments/",
     *     summary="Get all payments (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Array of payment records",
     *         @OA\JsonContent(@OA\Property(property="payments", type="array", @OA\Items(type="object")))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/', function (Request $request, Response $response, array $args): Response {
        $financialService = new FinancialService();

        return SlimUtils::renderJSON(
            $response,
            ['payments' => $financialService->getPayments()]
        );
    });

    /**
     * @OA\Post(
     *     path="/payments/",
     *     summary="Submit a new pledge or payment (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(description="Pledge or payment fields (FamilyId, FundId, Amount, Date, DepositId, etc.)")
     *     ),
     *     @OA\Response(response=200, description="Created pledge or payment record",
     *         @OA\JsonContent(@OA\Property(property="payment", type="object"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('/', function (Request $request, Response $response, array $args): Response {
        $payment = (object) $request->getParsedBody();
        $financialService = new FinancialService();

        return SlimUtils::renderJSON(
            $response,
            ['payment' => $financialService->submitPledgeOrPayment($payment)]
        );
    });

    /**
     * @OA\Get(
     *     path="/payments/family/{familyId}/list",
     *     summary="Get pledge and payment history for a family (Finance role required)",
     *     description="Results are filtered by the current user's ShowSince date and ShowPayments/ShowPledges preferences.",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Pledge/payment rows for the family",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(
     *             @OA\Property(property="FormattedFY", type="string"),
     *             @OA\Property(property="Amount", type="number"),
     *             @OA\Property(property="PledgeOrPayment", type="string"),
     *             @OA\Property(property="Date", type="string", format="date"),
     *             @OA\Property(property="Fund", type="string")
     *         )))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/family/{familyId:[0-9]+}/list', function (Request $request, Response $response, array $args): Response {
        $familyId = SlimUtils::getRouteArgument($request, 'familyId');
        $query = PledgeQuery::create()->filterByFamId($familyId);
        if (!empty(AuthenticationManager::getCurrentUser()->getShowSince())) {
            $query->filterByDate(AuthenticationManager::getCurrentUser()->getShowSince(), Criteria::GREATER_EQUAL);
        }
        if (!AuthenticationManager::getCurrentUser()->isShowPayments()) {
            $query->filterByPledgeOrPayment('Payment', Criteria::NOT_EQUAL);
        }
        if (!AuthenticationManager::getCurrentUser()->isShowPledges()) {
            $query->filterByPledgeOrPayment('Pledge', Criteria::NOT_EQUAL);
        }
        $query->joinWithDonationFund();
        $data = $query->find();

        $rows = [];
        foreach ($data as $row) {
            $newRow['FormattedFY'] = $row->getFormattedFY();
            $newRow['GroupKey'] = $row->getGroupKey();
            $newRow['Amount'] = $row->getAmount();
            $newRow['Nondeductible'] = $row->getNondeductible();
            $newRow['Schedule'] = $row->getSchedule();
            $newRow['Method'] = $row->getMethod();
            $newRow['Comment'] = InputUtils::escapeHTML($row->getComment() ?? '');
            $newRow['PledgeOrPayment'] = $row->getPledgeOrPayment();
            $newRow['Date'] = $row->getDate('Y-m-d');
            $newRow['DateLastEdited'] = $row->getDateLastEdited('Y-m-d');
            $newRow['EditedBy'] = $row->getPerson() ? $row->getPerson()->getFullName() : '';
            $newRow['Fund'] = $row->getDonationFund() ? $row->getDonationFund()->getName() : '';
            $rows[] = $newRow;
        }

        return SlimUtils::renderJSON($response, ['data' => $rows]);
    });

    /**
     * @OA\Get(
     *     path="/payments/pledges/{groupKey}",
     *     summary="Get pledge/payment details by group key (Finance role required)",
     *     description="Returns all rows sharing the same GroupKey, including per-fund amounts and family information.",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(
     *         name="groupKey",
     *         in="path",
     *         required=true,
     *         description="The pledge group key (plg_GroupKey)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pledge group details",
     *         @OA\JsonContent(
     *             @OA\Property(property="groupKey", type="string", example="abc123"),
     *             @OA\Property(property="familyId", type="integer", example=42),
     *             @OA\Property(property="familyName", type="string", example="Smith Family"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="fyId", type="integer", example=29),
     *             @OA\Property(property="method", type="string", example="CHECK"),
     *             @OA\Property(property="checkNo", type="string", nullable=true, example="1234"),
     *             @OA\Property(property="depositId", type="integer", nullable=true, example=5),
     *             @OA\Property(property="pledgeOrPayment", type="string", enum={"Pledge","Payment"}, example="Payment"),
     *             @OA\Property(property="schedule", type="string", nullable=true, example="Monthly"),
     *             @OA\Property(property="total", type="number", format="float", example=250.00),
     *             @OA\Property(property="funds", type="array", @OA\Items(
     *                 @OA\Property(property="fundId", type="integer", example=1),
     *                 @OA\Property(property="fundName", type="string", example="General Fund"),
     *                 @OA\Property(property="amount", type="number", format="float", example=200.00),
     *                 @OA\Property(property="nonDeductible", type="number", format="float", example=0.00),
     *                 @OA\Property(property="comment", type="string", example="Annual pledge")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid group key"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required"),
     *     @OA\Response(response=404, description="Pledge group not found")
     * )
     */
    $group->get('/pledges/{groupKey}', function (Request $request, Response $response, array $args): Response {
        try {
            $groupKey = $args['groupKey'];
            if (empty($groupKey)) {
                return SlimUtils::renderErrorJSON($response, gettext('Group key is required'), [], 400);
            }
            $financialService = new FinancialService();
            $data = $financialService->getPledgesByGroupKey($groupKey);

            return SlimUtils::renderJSON($response, $data);
        } catch (\InvalidArgumentException $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Pledge group not found'), [], 404);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve pledge'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Post(
     *     path="/payments/pledges",
     *     summary="Create a new pledge or multi-fund payment (Finance role required)",
     *     description="Creates one or more pledge_plg rows sharing a single GroupKey. Supports multi-fund splits where amounts across funds must sum to the desired total. Set `type` to 'Pledge' or 'Payment'.",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"FamilyID","Date","FYID","type","FundSplit","iMethod"},
     *             @OA\Property(property="FamilyID", type="integer", description="Family ID", example=42),
     *             @OA\Property(property="Date", type="string", format="date", description="Pledge/payment date", example="2025-01-15"),
     *             @OA\Property(property="FYID", type="integer", description="Fiscal year ID", example=29),
     *             @OA\Property(property="type", type="string", enum={"Pledge","Payment"}, description="Record type", example="Payment"),
     *             @OA\Property(property="iMethod", type="string", enum={"CHECK","CASH","CREDITCARD","BANKDRAFT"}, description="Payment method", example="CHECK"),
     *             @OA\Property(property="iCheckNo", type="string", nullable=true, description="Check number (required when iMethod=CHECK)", example="1234"),
     *             @OA\Property(property="DepositID", type="integer", nullable=true, description="Deposit slip ID (for Payments)", example=5),
     *             @OA\Property(property="schedule", type="string", nullable=true, description="Payment schedule (for Pledges)", example="Monthly"),
     *             @OA\Property(property="tScanString", type="string", nullable=true, description="Scanned check MICR string", example=""),
     *             @OA\Property(property="FundSplit", type="string", description="JSON-encoded array of fund allocations",
     *                 example="[{\"FundID\":1,\"Amount\":200.00,\"NonDeductible\":0,\"Comment\":\"General\"},{\"FundID\":2,\"Amount\":50.00,\"NonDeductible\":0,\"Comment\":\"\"}]"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Created pledge/payment group key and details",
     *         @OA\JsonContent(
     *             @OA\Property(property="groupKey", type="string", example="abc123"),
     *             @OA\Property(property="payment", type="object", description="Serialised pledge details")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error (invalid date, fund, check number, etc.)"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('/pledges', function (Request $request, Response $response, array $args): Response {
        try {
            $body = $request->getParsedBody() ?? [];
            $payment = (object) $body;

            // Validate required fields
            if (empty($payment->FamilyID)) {
                return SlimUtils::renderErrorJSON($response, gettext('Family is required'), [], 400);
            }
            if (empty($payment->Date)) {
                return SlimUtils::renderErrorJSON($response, gettext('Date is required'), [], 400);
            }
            if (empty($payment->type) || !in_array($payment->type, ['Pledge', 'Payment'], true)) {
                return SlimUtils::renderErrorJSON($response, gettext("Type must be 'Pledge' or 'Payment'"), [], 400);
            }
            if (empty($payment->FundSplit)) {
                return SlimUtils::renderErrorJSON($response, gettext('At least one fund allocation is required'), [], 400);
            }

            $financialService = new FinancialService();
            $groupPayment = $financialService->submitPledgeOrPayment($payment);

            return SlimUtils::renderJSON($response, ['payment' => $groupPayment]);
        } catch (\Exception $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, $e, $request);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create pledge'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Delete(
     *     path="/payments/{groupKey}",
     *     summary="Delete a payment by group key (Finance role required)",
     *     description="Deletes the pledge or payment group and all associated fund allocations.",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(
     *         name="groupKey",
     *         in="path",
     *         required=true,
     *         description="The pledge group key (plg_GroupKey)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Pledge group deleted successfully"),
     *     @OA\Response(response=400, description="Invalid or missing group key"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required"),
     *     @OA\Response(response=404, description="Pledge group not found")
     * )
     */
    $group->delete('/{groupKey}', function (Request $request, Response $response, array $args): Response {
        try {
            $groupKey = $args['groupKey'];
            $financialService = new FinancialService();
            $financialService->deletePledgeGroup($groupKey);

            return SlimUtils::renderSuccessJSON($response);
        } catch (\InvalidArgumentException $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Pledge group not found'), [], 404);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete pledge'), [], 500, $e, $request);
        }
    });
})->add(FinanceRoleAuthMiddleware::class);
