<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordProperty;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\MenuOptionsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Api\GroupMiddleware;
use ChurchCRM\Slim\Middleware\Api\PropertyMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/groups', function (RouteCollectorProxy $group): void {
    $groupPropertyMiddleware = new PropertyMiddleware('g');
    $groupMiddleware = new GroupMiddleware();

    $group->get('/properties', 'getAllGroupPropertyDefinitions');
    $group->get('/{groupID}/properties', 'getGroupAssignedProperties')->add($groupMiddleware);
    $group->post('/{groupID}/properties/{propertyId}', 'assignPropertyToGroup')
        ->add($groupMiddleware)
        ->add($groupPropertyMiddleware)
        ->add(ManageGroupRoleAuthMiddleware::class);
    $group->delete('/{groupID}/properties/{propertyId}', 'unassignPropertyFromGroup')
        ->add($groupMiddleware)
        ->add($groupPropertyMiddleware)
        ->add(ManageGroupRoleAuthMiddleware::class);
})->add(MenuOptionsRoleAuthMiddleware::class);

/**
 * @OA\Get(
 *     path="/groups/properties",
 *     summary="Get all available group property definitions",
 *     tags={"Groups", "Properties"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of group property definitions"),
 *     @OA\Response(response=403, description="MenuOptions role required")
 * )
 */
function getAllGroupPropertyDefinitions(Request $request, Response $response, array $args): Response
{
    $properties = PropertyQuery::create()
        ->filterByProClass('g')
        ->find();

    return SlimUtils::renderJSON($response, $properties->toArray());
}

/**
 * @OA\Get(
 *     path="/groups/{groupID}/properties",
 *     summary="Get properties assigned to a specific group",
 *     tags={"Groups", "Properties"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Array of assigned properties with edit/delete flags",
 *         @OA\JsonContent(type="array", @OA\Items(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="value", type="string"),
 *             @OA\Property(property="prompt", type="string"),
 *             @OA\Property(property="allowEdit", type="boolean"),
 *             @OA\Property(property="allowDelete", type="boolean")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="MenuOptions role required"),
 *     @OA\Response(response=404, description="Group not found")
 * )
 */
function getGroupAssignedProperties(Request $request, Response $response, array $args): Response
{
    $group = $request->getAttribute('group');
    $canManage = AuthenticationManager::getCurrentUser()->isManageGroupsEnabled();

    $assignments = RecordPropertyQuery::create()
        ->filterByRecordId($group->getId())
        ->find();

    $result = [];
    foreach ($assignments as $assignment) {
        $def = $assignment->getProperty();
        if ($def->getProClass() !== 'g') {
            continue;
        }
        $result[] = [
            'id'          => $assignment->getPropertyId(),
            'name'        => $def->getProName(),
            'value'       => $assignment->getPropertyValue(),
            'prompt'      => $def->getProPrompt(),
            'allowEdit'   => $canManage && !empty(trim((string) $def->getProPrompt())),
            'allowDelete' => $canManage,
        ];
    }

    return SlimUtils::renderJSON($response, $result);
}

/**
 * @OA\Post(
 *     path="/groups/{groupID}/properties/{propertyId}",
 *     summary="Assign or update a property on a group (ManageGroup role required)",
 *     tags={"Groups", "Properties"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(@OA\Property(property="value", type="string", description="Property value (required only when the property has a prompt)"))
 *     ),
 *     @OA\Response(response=200, description="Property assigned successfully"),
 *     @OA\Response(response=403, description="ManageGroup role required"),
 *     @OA\Response(response=404, description="Group or property not found")
 * )
 */
function assignPropertyToGroup(Request $request, Response $response, array $args): Response
{
    $group    = $request->getAttribute('group');
    $property = $request->getAttribute('property');

    $existing = RecordPropertyQuery::create()
        ->filterByRecordId($group->getId())
        ->filterByPropertyId($property->getProId())
        ->findOne();

    $value = '';
    if (!empty($property->getProPrompt())) {
        $data  = $request->getParsedBody();
        $raw   = empty($data['value']) ? 'N/A' : $data['value'];
        $value = InputUtils::sanitizeText($raw);
    }

    if ($existing !== null) {
        if (empty($property->getProPrompt()) || $existing->getPropertyValue() === $value) {
            return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is already assigned.')]);
        }
        $existing->setPropertyValue($value);
        $existing->save();
    } else {
        $record = new RecordProperty();
        $record->setPropertyId($property->getProId());
        $record->setRecordId($group->getId());
        $record->setPropertyValue($value);
        $record->save();
    }

    return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
}

/**
 * @OA\Delete(
 *     path="/groups/{groupID}/properties/{propertyId}",
 *     summary="Remove a property from a group (ManageGroup role required)",
 *     tags={"Groups", "Properties"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Property removed successfully"),
 *     @OA\Response(response=404, description="Assignment not found"),
 *     @OA\Response(response=403, description="ManageGroup role required")
 * )
 */
function unassignPropertyFromGroup(Request $request, Response $response, array $args): Response
{
    $group    = $request->getAttribute('group');
    $property = $request->getAttribute('property');

    $existing = RecordPropertyQuery::create()
        ->filterByRecordId($group->getId())
        ->filterByPropertyId($property->getProId())
        ->findOne();

    if ($existing === null) {
        throw new HttpNotFoundException($request, gettext('Property assignment not found.'));
    }

    $existing->delete();
    if (!$existing->isDeleted()) {
        return SlimUtils::renderErrorJSON($response, gettext('The property could not be removed.'), [], 500);
    }

    return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is successfully removed.')]);
}
