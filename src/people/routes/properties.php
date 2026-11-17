<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Property lists (assignment list + type admin list)
$app->group('/properties', function (RouteCollectorProxy $group): void {
    $group->get('/types', 'listPropertyTypes');
    $group->get('/types/{class:[pfg]}', 'listPropertyTypes');
    $group->get('/{type:[pfg]}', 'listProperties');
    // Delete action as API
    $group->delete('/types/{id:[0-9]+}', 'deletePropertyType')->add(new CSRFMiddleware('property_type_delete'));
})->add(AdminRoleAuthMiddleware::class);

function listProperties(Request $request, Response $response, array $args): Response
{
    AuthenticationManager::redirectHomeIfNotAdmin();

    $sType = $args['type'] ?? 'p';
    switch ($sType) {
        case 'p':
            $sTypeName = gettext('Person');
            $sRecordType = 'person';
            $sTypeIcon = 'fa-person-half-dress';
            break;
        case 'f':
            $sTypeName = gettext('Family');
            $sRecordType = 'family';
            $sTypeIcon = 'fa-people-roof';
            break;
        case 'g':
            $sTypeName = gettext('Group');
            $sRecordType = 'group';
            $sTypeIcon = 'fa-user-group';
            break;
        default:
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/dashboard');
    }

    // ORM query instead of raw SQL
    $properties = PropertyQuery::create()
        ->filterByProClass($sType)
        ->joinWithPropertyType()
        ->usePropertyTypeQuery()
            ->orderByPrtName()
        ->endUse()
        ->orderByProName()
        ->find();

    $groups = [];
    foreach ($properties as $prop) {
        $prt = $prop->getPropertyType();
        $prtId = $prt->getPrtId();
        if (!isset($groups[$prtId])) {
            $groups[$prtId] = [
                'name' => $prt->getPrtName(),
                'properties' => [],
            ];
        }
        $groups[$prtId]['properties'][] = [
            'pro_ID' => $prop->getProId(),
            'pro_Name' => $prop->getProName(),
            'pro_Description' => $prop->getProDescription(),
            'pro_Prompt' => $prop->getProPrompt(),
        ];
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sType' => $sType,
        'sTypeName' => $sTypeName,
        'sRecordType' => $sRecordType,
        'sTypeIcon' => $sTypeIcon,
        'groups' => $groups,
        'sPageTitle' => $sTypeName . ' ' . gettext('Property List'),
        'sPageSubtitle' => gettext('Define custom properties that can be assigned to') . ' ' . strtolower($sTypeName) . ' ' . gettext('records'),
    ];

    return $renderer->render($response, 'property-list.php', $pageArgs);
}

function listPropertyTypes(Request $request, Response $response, array $args): Response
{
    AuthenticationManager::redirectHomeIfNotAdmin();

    $filterClass = $args['class'] ?? ($request->getQueryParams()['class'] ?? '');
    if ($filterClass !== '' && !in_array($filterClass, ['p', 'f', 'g'], true)) {
        $filterClass = '';
    }

    $query = PropertyTypeQuery::create()->orderByPrtClass()->orderByPrtName();
    if ($filterClass !== '') {
        $query->filterByPrtClass($filterClass);
    }

    $types = [];
    foreach ($query->find() as $pt) {
        $propCount = PropertyQuery::create()->filterByProPrtId($pt->getPrtId())->count();
        $assignmentCount = 0;
        if ($propCount > 0) {
            $propertyIds = PropertyQuery::create()->filterByProPrtId($pt->getPrtId())->select(['ProId'])->find()->toArray();
            if (!empty($propertyIds)) {
                $assignmentCount = RecordPropertyQuery::create()->filterByPropertyId($propertyIds)->count();
            }
        }
        $types[] = [
            'id' => $pt->getPrtId(),
            'class' => $pt->getPrtClass(),
            'name' => $pt->getPrtName(),
            'properties' => $propCount,
            'assignments' => $assignmentCount,
        ];
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'filterClass' => $filterClass,
        'types' => $types,
        'sPageTitle' => gettext('Property Type List'),
        'sPageSubtitle' => gettext('Manage person, family, and group property types'),
    ];

    return $renderer->render($response, 'property-type-list.php', $pageArgs);
}

function deletePropertyType(Request $request, Response $response, array $args): Response
{
    AuthenticationManager::redirectHomeIfNotAdmin();

    $id = (int) $args['id'];
    $pt = PropertyTypeQuery::create()->findPk($id);
    if ($pt === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Property type not found'), [], 404);
    }

    // Cascade: delete record-property mappings, then properties, then the type (matches legacy)
    $propertyIds = PropertyQuery::create()->filterByProPrtId($id)->select(['ProId'])->find()->toArray();
    if (!empty($propertyIds)) {
        RecordPropertyQuery::create()->filterByPropertyId($propertyIds)->delete();
    }
    PropertyQuery::create()->filterByProPrtId($id)->delete();
    $pt->delete();

    return SlimUtils::renderJSON($response, ['success' => true]);
}
