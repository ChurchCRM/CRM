<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PropertyType;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\Service\PropertyTypeService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/property-types', function (RouteCollectorProxy $group): void {
    $group->get('/new', 'adminPropertyTypeEditor');
    $group->post('/new', 'adminPropertyTypeEditor')->add(new CSRFMiddleware('property_type_editor'));
    $group->get('/{propertyTypeId:[0-9]+}/edit', 'adminPropertyTypeEditor');
    $group->post('/{propertyTypeId:[0-9]+}/edit', 'adminPropertyTypeEditor')->add(new CSRFMiddleware('property_type_editor'));
});

function adminPropertyTypeEditor(Request $request, Response $response, array $args): Response
{
    $propertyTypeId = (int) ($args['propertyTypeId'] ?? 0);
    $isNew = $propertyTypeId === 0;
    $propertyType = $isNew ? new PropertyType() : PropertyTypeQuery::create()->findPk($propertyTypeId);
    if ($propertyType === null) {
        throw new HttpNotFoundException($request, gettext('Property type not found'));
    }

    $class = (string) $propertyType->getPrtClass();
    $name = (string) $propertyType->getPrtName();
    $description = (string) $propertyType->getPrtDescription();
    $errors = [];

    if ($request->getMethod() === 'POST') {
        $body = (array) $request->getParsedBody();
        $class = (string) ($body['class'] ?? '');
        $name = InputUtils::sanitizeText((string) ($body['name'] ?? ''));
        $description = InputUtils::sanitizeText((string) ($body['description'] ?? ''));

        $errors = PropertyTypeService::save($propertyType, $class, $name, $description);
        if ($errors === []) {
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/PropertyTypeList.php');
        }
    }

    $pageTitle = $isNew ? gettext('Add Property Type') : gettext('Edit Property Type');
    $formAction = SystemURLs::getRootPath() . '/admin/property-types/' . ($isNew ? 'new' : $propertyTypeId . '/edit');

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'property-type-editor.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => $pageTitle,
        'sPageSubtitle' => gettext('Create or edit property type categories'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Property Types'), '/PropertyTypeList.php'],
            [$pageTitle],
        ]),
        'isNew' => $isNew,
        'formAction' => $formAction,
        'classLabels' => PropertyTypeService::classLabels(),
        'nameMaxLength' => PropertyTypeService::NAME_MAX_LENGTH,
        'class' => $class,
        'name' => $name,
        'description' => $description,
        'errors' => $errors,
    ]);
}
