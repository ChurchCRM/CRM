<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Property;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Service\PropertyService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

const PROPERTY_EDITOR_CLASSES = [
    'person' => PropertyService::CLASS_PERSON,
    'family' => PropertyService::CLASS_FAMILY,
    'group' => PropertyService::CLASS_GROUP,
];

$app->group('/properties', function (RouteCollectorProxy $group): void {
    $group->get('/{recordType:person|family|group}/new', 'adminPropertyEditor');
    $group->post('/{recordType:person|family|group}/new', 'adminPropertyEditor')->add(new CSRFMiddleware('property_editor'));
    $group->get('/{propertyId:[0-9]+}/edit', 'adminPropertyEditor');
    $group->post('/{propertyId:[0-9]+}/edit', 'adminPropertyEditor')->add(new CSRFMiddleware('property_editor'));
});

function adminPropertyEditor(Request $request, Response $response, array $args): Response
{
    $propertyId = (int) ($args['propertyId'] ?? 0);
    $isNew = $propertyId === 0;
    if ($isNew) {
        $property = (new Property())
            ->setProClass(PROPERTY_EDITOR_CLASSES[$args['recordType']])
            ->setProName('');
    } else {
        $property = PropertyQuery::create()->findPk($propertyId);
        if ($property === null) {
            throw new HttpNotFoundException($request, gettext('Property not found'));
        }
    }

    $class = (string) $property->getProClass();
    $recordType = array_search($class, PROPERTY_EDITOR_CLASSES, true);
    if ($recordType === false) {
        throw new HttpNotFoundException($request, gettext('Property not found'));
    }

    $propertyTypeId = (int) $property->getProPrtId();
    $name = (string) $property->getProName();
    $description = (string) $property->getProDescription();
    $prompt = (string) $property->getProPrompt();
    $errors = [];
    $listUrl = '/PropertyList.php?Type=' . $class;

    if ($request->getMethod() === 'POST') {
        $body = (array) $request->getParsedBody();
        $propertyTypeId = InputUtils::filterInt($body['propertyTypeId'] ?? 0);
        $name = InputUtils::sanitizeText((string) ($body['name'] ?? ''));
        $description = InputUtils::sanitizeText((string) ($body['description'] ?? ''));
        $prompt = InputUtils::sanitizeText((string) ($body['prompt'] ?? ''));

        $errors = PropertyService::saveDefinition($property, $propertyTypeId, $name, $description, $prompt);
        if ($errors === []) {
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . $listUrl);
        }
    }

    $recordTypeName = match ($class) {
        PropertyService::CLASS_PERSON => gettext('Person'),
        PropertyService::CLASS_FAMILY => gettext('Family'),
        PropertyService::CLASS_GROUP => gettext('Group'),
    };
    $pageTitle = $recordTypeName . ' ' . gettext('Property Editor');
    $formAction = SystemURLs::getRootPath() . '/admin/properties/' . ($isNew ? $recordType . '/new' : $propertyId . '/edit');

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'property-editor.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => $pageTitle,
        'sPageSubtitle' => gettext('Define custom properties for tracking data'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            $class === PropertyService::CLASS_GROUP
                ? [gettext('Groups'), '/groups/dashboard']
                : [gettext('People'), '/people/dashboard'],
            [$recordTypeName . ' ' . gettext('Properties'), $listUrl],
            [$isNew ? gettext('Add Property') : gettext('Edit Property')],
        ]),
        'isNew' => $isNew,
        'formAction' => $formAction,
        'listUrl' => SystemURLs::getRootPath() . $listUrl,
        'recordTypeName' => $recordTypeName,
        'propertyTypes' => PropertyService::getTypes($class),
        'nameMaxLength' => PropertyService::NAME_MAX_LENGTH,
        'promptMaxLength' => PropertyService::PROMPT_MAX_LENGTH,
        'propertyTypeId' => $propertyTypeId,
        'name' => $name,
        'description' => $description,
        'prompt' => $prompt,
        'errors' => $errors,
    ]);
}
