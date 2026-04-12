<?php

use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\FunctionsUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/groups/{groupId:[0-9]+}/formprops', function (RouteCollectorProxy $group): void {
    $group->put('/{propId:[0-9]+}/order', 'reorderGroupFormProp');
    $group->delete('/{propId:[0-9]+}', 'deleteGroupFormProp');
})->add(ManageGroupRoleAuthMiddleware::class);

/**
 * @OA\Put(
 *     path="/groups/{groupId}/formprops/{propId}/order",
 *     summary="Move a group form property up or down (ManageGroup role required)",
 *     tags={"Groups"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="groupId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="propId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"direction"},
 *             @OA\Property(property="direction", type="string", enum={"up", "down"})
 *         )
 *     ),
 *     @OA\Response(response=200, description="Property reordered"),
 *     @OA\Response(response=400, description="Invalid direction or field"),
 *     @OA\Response(response=403, description="ManageGroup role required"),
 *     @OA\Response(response=404, description="Group not found")
 * )
 */
function reorderGroupFormProp(Request $request, Response $response, array $args): Response
{
    $iGroupID = (int) $args['groupId'];
    $iPropID = (int) $args['propId'];

    $group = GroupQuery::create()->findPk($iGroupID);
    if ($group === null || !$group->getHasSpecialProps()) {
        throw new HttpNotFoundException($request, gettext('Group not found or has no special properties'));
    }

    $body = $request->getParsedBody();
    $direction = $body['direction'] ?? '';
    if ($direction !== 'up' && $direction !== 'down') {
        throw new HttpBadRequestException($request, gettext('Direction must be "up" or "down"'));
    }

    // Find the current property via ORM
    $currentProp = GroupPropMasterQuery::create()
        ->filterByGrpId($iGroupID)
        ->filterByPropId($iPropID)
        ->findOne();

    if ($currentProp === null) {
        throw new HttpNotFoundException($request, gettext('Property not found'));
    }

    $swapPropID = ($direction === 'up') ? $iPropID - 1 : $iPropID + 1;

    // Find the adjacent property to swap with
    $swapProp = GroupPropMasterQuery::create()
        ->filterByGrpId($iGroupID)
        ->filterByPropId($swapPropID)
        ->findOne();

    if ($swapProp === null) {
        throw new HttpBadRequestException($request, gettext('Cannot move property in that direction'));
    }

    // Swap prop_ID values: set swap row to current ID, then current row to swap ID
    $swapProp->setPropId($iPropID);
    $swapProp->save();
    $currentProp->setPropId($swapPropID);
    $currentProp->save();

    return SlimUtils::renderJSON($response, ['success' => true]);
}

/**
 * @OA\Delete(
 *     path="/groups/{groupId}/formprops/{propId}",
 *     summary="Delete a group form property and its column (ManageGroup role required)",
 *     tags={"Groups"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="groupId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="propId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"field"},
 *             @OA\Property(property="field", type="string", description="Column field name (e.g. c1, c2)", example="c1")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Property deleted"),
 *     @OA\Response(response=400, description="Invalid field name"),
 *     @OA\Response(response=403, description="ManageGroup role required"),
 *     @OA\Response(response=404, description="Group not found")
 * )
 */
function deleteGroupFormProp(Request $request, Response $response, array $args): Response
{
    $iGroupID = (int) $args['groupId'];
    $iPropID = (int) $args['propId'];

    $group = GroupQuery::create()->findPk($iGroupID);
    if ($group === null || !$group->getHasSpecialProps()) {
        throw new HttpNotFoundException($request, gettext('Group not found or has no special properties'));
    }

    $body = $request->getParsedBody();
    $sField = $body['field'] ?? '';

    // Validate field name to prevent DDL injection (columns follow pattern c1, c2, etc.)
    if (!preg_match('/^c\d+$/', $sField)) {
        throw new HttpBadRequestException($request, gettext('Invalid field identifier'));
    }

    // Find the property via ORM
    $prop = GroupPropMasterQuery::create()
        ->filterByGrpId($iGroupID)
        ->filterByField($sField)
        ->findOne();

    if ($prop === null) {
        throw new HttpNotFoundException($request, gettext('Property not found'));
    }

    // If this field is a custom list type (type 12), delete the associated list
    if ((int) $prop->getTypeId() === 12) {
        $listId = (int) $prop->getSpecial();
        ListOptionQuery::create()
            ->filterById($listId)
            ->delete();
    }

    // Drop the column from the group properties table (DDL — cannot use ORM)
    $sSQL = 'ALTER TABLE `groupprop_' . $iGroupID . '` DROP `' . $sField . '`';
    FunctionsUtils::runQuery($sSQL);

    // Delete the property definition row
    $prop->delete();

    // Re-number remaining properties to keep sequential IDs
    $remainingProps = GroupPropMasterQuery::create()
        ->filterByGrpId($iGroupID)
        ->orderByPropId()
        ->find();

    $newId = 1;
    foreach ($remainingProps as $remaining) {
        if ($remaining->getPropId() !== $newId) {
            $remaining->setPropId($newId);
            $remaining->save();
        }
        $newId++;
    }

    LoggerUtils::getAppLogger()->info('Deleted group form property: group=' . $iGroupID . ', prop=' . $iPropID . ', field=' . $sField);

    return SlimUtils::renderJSON($response, ['success' => true]);
}
