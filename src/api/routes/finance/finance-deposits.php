<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/deposits', function (RouteCollectorProxy $group): void {
    $group->post('', function (Request $request, Response $response, array $args): Response {
        /** @var ChurchCRM\Service\DepositService $depositService */
        $depositService = $this->get('DepositService');
        $input = $request->getParsedBody();
        $depositType = $input['depositType'] ?? '';
        $depositComment = InputUtils::sanitizeText($input['depositComment']) ?? '';
        $depositDate = $input['depositDate'] ?? date('Y-m-d');

        // Validate depositType against allowed values
        $allowedTypes = ['Bank', 'CreditCard', 'BankDraft', 'eGive'];
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

    $group->get('/dashboard', function (Request $request, Response $response, array $args): Response {
        $list = DepositQuery::create()
            ->filterByDate(['min' => date('Y-m-d', strtotime('-90 days'))])
            ->find();

        return SlimUtils::renderJSON($response, $list->toArray());
    });

    $group->get(
        '',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderStringJSON(
            $response,
            DepositQuery::create()->find()->toJSON()
        )
    );

    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $deposit = DepositQuery::create()->findOneById($id);
        return SlimUtils::renderJSON($response, $deposit->toArray());
    });

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

    $group->get('/{id:[0-9]+}/ofx', function (Request $request, Response $response, array $args): Response {
    $id = (int) $args['id'];
    $deposit = DepositQuery::create()->findOneById($id);
    if ($deposit === null) {
        return SlimUtils::renderJson($response->withStatus(404), ['message' => 'Deposit not found']);
    }
    $OFX = $deposit->getOFX();
    header($OFX->header);
    return SlimUtils::renderJSON($response, ['content' => $OFX->content]);
    });

    $group->get('/{id:[0-9]+}/pdf', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];

        // If there are no payments for this deposit, return a controlled response
        $paymentsCount = PledgeQuery::create()->filterByDepId($id)->count();
        if ($paymentsCount === 0) {
            // Some clients and probes use HEAD; respond with appropriate status without throwing
            if (strtoupper($request->getMethod()) === 'HEAD') {
                return $response->withStatus(404);
            }

            return SlimUtils::renderJson($response->withStatus(404), ['message' => 'No Payments on this Deposit']);
        }

        $deposit = DepositQuery::create()->findOneById($id);
        if ($deposit === null) {
            return SlimUtils::renderJson($response->withStatus(404), ['message' => 'Deposit not found']);
        }
        $deposit->getPDF();
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->get('/{id:[0-9]+}/csv', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];

        $filename = 'ChurchCRM-Deposit-' . $id . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv';
        $csvData = PledgeQuery::create()->filterByDepId($id)
            ->joinDonationFund()->useDonationFundQuery()
            ->withColumn('DonationFund.Name', 'DonationFundName')
            ->endUse()
            ->leftJoinFamily()->useFamilyQuery()
            ->withColumn('Family.Name', 'FamilyName')
            ->endUse()
            ->find()
            ->toCSV();

        $response = $response->withHeader('Content-Type', 'text/csv');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename=' . $filename);

        $response->getBody()->write($csvData);

        return $response;
    });

    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $deposit = DepositQuery::create()->findOneById($id);
        if ($deposit) {
            $deposit->delete();
        }
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->get('/{id:[0-9]+}/pledges', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        /** @var ChurchCRM\Service\DepositService $depositService */
        $depositService = $this->get('DepositService');
        $result = $depositService->getDepositItemsByType($id, 'Pledge');
        return SlimUtils::renderJSON($response, $result);
    });

    $group->get('/{id:[0-9]+}/payments', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        /** @var ChurchCRM\Service\DepositService $depositService */
        $depositService = $this->get('DepositService');
        $result = $depositService->getDepositItemsByType($id, 'Payment');
        return SlimUtils::renderJSON($response, $result);
    });
})->add(FinanceRoleAuthMiddleware::class);
