<?php

use ChurchCRM\Authentication\AuthenticationManager;
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
            $newRow['EditedBy'] = $row->getPerson()->getFullName();
            $newRow['Fund'] = $row->getDonationFund()->getName();
            $rows[] = $newRow;
        }

        return SlimUtils::renderJSON($response, ['data' => $rows]);
    });

    /**
     * @OA\Delete(
     *     path="/payments/{groupKey}",
     *     summary="Delete a payment by group key (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupKey", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payment deleted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->delete('/{groupKey}', function (Request $request, Response $response, array $args): Response {
        $groupKey = $args['groupKey'];
        $financialService = new FinancialService();
        $financialService->deletePayment($groupKey);

        return SlimUtils::renderSuccessJSON($response);
    });
})->add(FinanceRoleAuthMiddleware::class);
