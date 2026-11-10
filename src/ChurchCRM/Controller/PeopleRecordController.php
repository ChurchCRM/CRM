<?php

namespace ChurchCRM\Controller;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\FamilyRecordService;
use ChurchCRM\Service\PersonRecordService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

class PeopleRecordController
{
    public function person(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $personId = $this->id($request, $params, 'personId');
        $familyId = $this->id($request, $params, 'familyId');
        $user = AuthenticationManager::getCurrentUser();
        if (!($personId > 0 ? $user->isEditRecordsEnabled() : $user->isAddRecordsEnabled())) {
            return $this->denied($request, $response);
        }
        $person = $personId > 0 ? PersonQuery::create()->findPk($personId) : null;
        if ($personId > 0 && $person === null) {
            throw new HttpNotFoundException($request, gettext('Person not found'));
        }
        if ($person !== null && !$user->canEditPerson($personId, (int) $person->getFamId())) {
            return $this->denied($request, $response);
        }
        if ($familyId > 0 && (!$user->canViewFamily($familyId) || FamilyQuery::create()->findPk($familyId) === null)) {
            throw new HttpNotFoundException($request, gettext('Family not found'));
        }
        $submitted = $request->getMethod() === 'POST';
        $body = (array) $request->getParsedBody();
        PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
        try {
            $values = (new PersonRecordService())->apply($person, $body, $submitted, $familyId ?: null);
        } catch (\InvalidArgumentException $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }
        if ($values['saved']) {
            $target = Person::getViewURIForId($values['personId']);
            if (isset($body['personSubmitAndAdd']) && $user->isAddRecordsEnabled()) {
                $target = '/people/person/editor';
                if ($values['familyId'] > 0) {
                    $target .= '?familyId=' . $values['familyId'];
                }
            }
            if (!empty($params['linkBack'])) {
                $target = RedirectUtils::validateRedirectUrl((string) $params['linkBack'], $target);
            }
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/' . ltrim(RedirectUtils::stripAndValidatePath($target), '/'));
        }
        $action = SystemURLs::getRootPath() . '/people/person/editor?personId=' . $personId;
        if ($familyId > 0) {
            $action .= '&familyId=' . $familyId;
        }
        if (!empty($params['linkBack'])) {
            $action .= '&' . http_build_query(['linkBack' => RedirectUtils::validateRedirectUrl((string) $params['linkBack'])]);
        }
        return $this->render($response, 'person', $values, $action, gettext('Person Editor'), gettext('Add or edit individual person records'), [
            [gettext('People'), '/people/dashboard'],
            [$personId > 0 ? gettext('Edit Person') : gettext('New Person')],
        ]);
    }

    public function family(Request $request, Response $response): Response
    {
        $familyId = $this->id($request, $request->getQueryParams(), 'familyId');
        $user = AuthenticationManager::getCurrentUser();
        if (!($familyId > 0 ? $user->isEditRecordsEnabled() : $user->isAddRecordsEnabled()) || !$user->canViewFamily($familyId)) {
            return $this->denied($request, $response);
        }
        $family = $familyId > 0 ? FamilyQuery::create()->findPk($familyId) : null;
        if ($familyId > 0 && $family === null) {
            throw new HttpNotFoundException($request, gettext('Family not found'));
        }
        $body = (array) $request->getParsedBody();
        PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
        try {
            $values = (new FamilyRecordService())->apply($family, $body, $request->getMethod() === 'POST');
        } catch (\InvalidArgumentException $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }
        if ($values['saved']) {
            $target = isset($body['familySubmitAndAdd']) && $user->isAddRecordsEnabled()
                ? '/people/family/editor'
                : '/people/family/' . $values['familyId'];
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . $target);
        }
        return $this->render($response, 'family', $values, SystemURLs::getRootPath() . '/people/family/editor?familyId=' . $familyId, gettext('Family Editor'), gettext('Create and edit family information and relationships'), [
            [gettext('People'), '/people/dashboard'],
            [gettext('Families'), '/people/family'],
            [$familyId > 0 ? gettext('Edit Family') : gettext('New Family')],
        ]);
    }

    private function id(Request $request, array $params, string $key): int
    {
        if (!isset($params[$key]) || $params[$key] === '') {
            return 0;
        }
        $id = filter_var($params[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($id === false) {
            throw new HttpBadRequestException($request, gettext('Invalid record ID'));
        }
        return $id;
    }

    private function denied(Request $request, Response $response): Response
    {
        if ($request->getHeaderLine('X-API-Key') !== '' || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/access-denied');
    }

    private function render(Response $response, string $kind, array $values, string $action, string $title, string $subtitle, array $breadcrumbs): Response
    {
        return (new PhpRenderer(SystemURLs::getDocumentRoot() . '/people/views/'))->render($response, 'record-editor.php', array_merge($values, [
            'recordTemplate' => $kind . '-editor.twig',
            'recordValues' => array_merge($values, ['formAction' => $action]),
            'sPageTitle' => $title,
            'sPageSubtitle' => $subtitle,
            'aBreadcrumbs' => PageHeader::breadcrumbs($breadcrumbs),
            'sRootPath' => SystemURLs::getRootPath(),
        ]));
    }
}
