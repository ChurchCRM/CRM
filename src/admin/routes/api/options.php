<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/options', function (RouteCollectorProxy $group): void {
    // Get all options for a list
    $group->get('/{listId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $listId = (int) $args['listId'];
        $options = ListOptionQuery::create()
            ->filterById($listId)
            ->orderByOptionSequence()
            ->find();

        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'optionId' => $option->getOptionId(),
                'optionName' => $option->getOptionName(),
                'optionSequence' => $option->getOptionSequence(),
            ];
        }

        return SlimUtils::renderJSON($response, $result);
    });

    // Add a new option to a list
    $group->post('/{listId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $listId = (int) $args['listId'];
        $input = $request->getParsedBody();

        $name = InputUtils::sanitizeText($input['name'] ?? '');
        if (empty($name)) {
            throw new HttpBadRequestException($request, gettext('Option name is required'));
        }

        // Check for duplicate
        $existing = ListOptionQuery::create()
            ->filterById($listId)
            ->filterByOptionName($name)
            ->findOne();
        if ($existing !== null) {
            throw new HttpBadRequestException($request, gettext('An option with that name already exists'));
        }

        // Get next sequence and option ID
        $maxSeq = ListOptionQuery::create()->filterById($listId)->count();
        $maxOptionId = ListOptionQuery::create()
            ->filterById($listId)
            ->orderByOptionId(\Propel\Runtime\ActiveQuery\Criteria::DESC)
            ->findOne();
        $newOptionId = $maxOptionId ? $maxOptionId->getOptionId() + 1 : 1;

        $option = new ListOption();
        $option
            ->setId($listId)
            ->setOptionId($newOptionId)
            ->setOptionName($name)
            ->setOptionSequence($maxSeq + 1);
        $option->save();

        return SlimUtils::renderJSON($response, [
            'optionId' => $option->getOptionId(),
            'optionName' => $option->getOptionName(),
            'optionSequence' => $option->getOptionSequence(),
        ]);
    });

    // Update option name
    $group->patch('/{listId:[0-9]+}/{optionId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $listId = (int) $args['listId'];
        $optionId = (int) $args['optionId'];
        $input = $request->getParsedBody();

        $option = ListOptionQuery::create()
            ->filterById($listId)
            ->filterByOptionId($optionId)
            ->findOne();
        if ($option === null) {
            throw new HttpNotFoundException($request, gettext('Option not found'));
        }

        if (isset($input['name'])) {
            $name = InputUtils::sanitizeText($input['name']);
            if (empty($name)) {
                throw new HttpBadRequestException($request, gettext('Option name is required'));
            }
            // Check for duplicate
            $duplicate = ListOptionQuery::create()
                ->filterById($listId)
                ->filterByOptionName($name)
                ->filterByOptionId($optionId, \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
                ->findOne();
            if ($duplicate !== null) {
                throw new HttpBadRequestException($request, gettext('An option with that name already exists'));
            }
            $option->setOptionName($name);
        }

        $option->save();

        return SlimUtils::renderJSON($response, [
            'optionId' => $option->getOptionId(),
            'optionName' => $option->getOptionName(),
            'optionSequence' => $option->getOptionSequence(),
        ]);
    });

    // Delete an option
    $group->delete('/{listId:[0-9]+}/{optionId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $listId = (int) $args['listId'];
        $optionId = (int) $args['optionId'];

        // Don't delete the last option
        $count = ListOptionQuery::create()->filterById($listId)->count();
        if ($count <= 1) {
            throw new HttpBadRequestException($request, gettext('Cannot delete the only remaining option'));
        }

        $option = ListOptionQuery::create()
            ->filterById($listId)
            ->filterByOptionId($optionId)
            ->findOne();
        if ($option === null) {
            throw new HttpNotFoundException($request, gettext('Option not found'));
        }

        $deletedSeq = $option->getOptionSequence();
        $option->delete();

        // Resequence remaining options
        $remaining = ListOptionQuery::create()
            ->filterById($listId)
            ->filterByOptionSequence($deletedSeq, \Propel\Runtime\ActiveQuery\Criteria::GREATER_THAN)
            ->orderByOptionSequence()
            ->find();
        foreach ($remaining as $opt) {
            $opt->setOptionSequence($opt->getOptionSequence() - 1);
            $opt->save();
        }

        // Handle cleanup for known list types
        $queryParams = $request->getQueryParams();
        $mode = $queryParams['mode'] ?? '';
        switch ($mode) {
            case 'grproles':
                // Reset default role if it was the deleted one
                $group = GroupQuery::create()->findOneByRoleListId($listId);
                if ($group !== null && $group->getDefaultRole() === $optionId) {
                    $group->setDefaultRole(1);
                    $group->save();
                }
                // Reset members using deleted role to the group default
                if ($group !== null) {
                    $defaultRole = $group->getDefaultRole();
                    \ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery::create()
                        ->filterByGroupId($group->getId())
                        ->filterByRoleId($optionId)
                        ->update(['RoleId' => $defaultRole]);
                }
                break;
            case 'famroles':
                \ChurchCRM\model\ChurchCRM\PersonQuery::create()
                    ->filterByFmrId($optionId)
                    ->update(['FmrId' => 0]);
                break;
            case 'classes':
                \ChurchCRM\model\ChurchCRM\PersonQuery::create()
                    ->filterByClsId($optionId)
                    ->update(['ClsId' => 0]);
                break;
            case 'grptypes':
                \ChurchCRM\model\ChurchCRM\GroupQuery::create()
                    ->filterByType($optionId)
                    ->update(['Type' => 0]);
                break;
        }

        return SlimUtils::renderSuccessJSON($response);
    });

    // Move option up/down
    $group->post('/{listId:[0-9]+}/{optionId:[0-9]+}/reorder', function (Request $request, Response $response, array $args): Response {
        $listId = (int) $args['listId'];
        $optionId = (int) $args['optionId'];
        $input = $request->getParsedBody();
        $direction = $input['direction'] ?? '';

        if ($direction !== 'up' && $direction !== 'down') {
            throw new HttpBadRequestException($request, gettext('Direction must be "up" or "down"'));
        }

        $option = ListOptionQuery::create()
            ->filterById($listId)
            ->filterByOptionId($optionId)
            ->findOne();
        if ($option === null) {
            throw new HttpNotFoundException($request, gettext('Option not found'));
        }

        $currentSeq = $option->getOptionSequence();
        $swapSeq = $direction === 'up' ? $currentSeq - 1 : $currentSeq + 1;

        $swapOption = ListOptionQuery::create()
            ->filterById($listId)
            ->filterByOptionSequence($swapSeq)
            ->findOne();
        if ($swapOption === null) {
            // Already at top/bottom
            return SlimUtils::renderSuccessJSON($response);
        }

        // Swap sequences
        $option->setOptionSequence($swapSeq);
        $swapOption->setOptionSequence($currentSeq);
        $option->save();
        $swapOption->save();

        return SlimUtils::renderSuccessJSON($response);
    });

    // Set default role (grproles mode only)
    $group->post('/{listId:[0-9]+}/{optionId:[0-9]+}/default', function (Request $request, Response $response, array $args): Response {
        $listId = (int) $args['listId'];
        $optionId = (int) $args['optionId'];

        $group = GroupQuery::create()->findOneByRoleListId($listId);
        if ($group === null) {
            throw new HttpNotFoundException($request, gettext('Group not found for this role list'));
        }

        $group->setDefaultRole($optionId);
        $group->save();

        return SlimUtils::renderSuccessJSON($response);
    });

    // Toggle inactive classification
    $group->post('/{listId:[0-9]+}/{optionId:[0-9]+}/inactive', function (Request $request, Response $response, array $args): Response {
        $optionId = (int) $args['optionId'];

        $aInactiveClassificationIds = explode(',', SystemConfig::getValue('sInactiveClassification'));
        $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k) => is_numeric($k));

        if (in_array($optionId, $aInactiveClasses)) {
            $aInactiveClasses = array_values(array_diff($aInactiveClasses, [$optionId]));
        } else {
            $aInactiveClasses[] = $optionId;
        }

        SystemConfig::setValue('sInactiveClassification', implode(',', $aInactiveClasses));

        return SlimUtils::renderJSON($response, ['inactive' => $aInactiveClasses]);
    });
});
