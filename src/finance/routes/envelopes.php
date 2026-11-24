<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\EnvelopeService;
use ChurchCRM\Service\FlashService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/envelopes', 'financeEnvelopeManager');
$app->post('/envelopes', 'financeEnvelopeManager')->add(new CSRFMiddleware('envelope_manager'));

/**
 * Envelope Manager: lists families with their envelope numbers and saves the changed ones.
 * The zero and assign actions only fill in the form; nothing is stored until it is saved.
 */
function financeEnvelopeManager(Request $request, Response $response): Response
{
    $isPost = $request->getMethod() === 'POST';
    $input = $isPost ? (array) $request->getParsedBody() : $request->getQueryParams();
    $classificationId = InputUtils::filterInt($input['classification'] ?? 0);
    $sortBy = ($input['sortBy'] ?? '') === 'envelope' ? 'envelope' : 'name';
    $action = $isPost ? (string) ($input['action'] ?? '') : '';
    $assignStartNumber = (string) ($input['assignStartNumber'] ?? '1');

    $families = EnvelopeService::familyEnvelopes($classificationId);
    $envelopes = array_map(static fn (array $family): string => (string) $family['envelope'], $families);
    $invalidFamilyIds = [];
    $errorMessage = '';
    $isUnsaved = false;

    if ($action === 'zero') {
        $envelopes = array_map(static fn (): string => '0', $envelopes);
        $isUnsaved = true;
    } elseif ($action === 'assign') {
        $nextEnvelope = EnvelopeService::parseEnvelope($assignStartNumber);
        if ($nextEnvelope === null || $nextEnvelope + count($envelopes) - 1 > EnvelopeService::MAX_ENVELOPE) {
            $errorMessage = sprintf(gettext('Enter a starting number from 0 to %d.'), EnvelopeService::MAX_ENVELOPE);
        } else {
            foreach (array_keys($envelopes) as $familyId) {
                $envelopes[$familyId] = (string) $nextEnvelope++;
            }
            $isUnsaved = true;
        }
    } elseif ($action === 'save') {
        $submitted = (array) ($input['envelopes'] ?? []);
        $toSave = [];
        foreach (array_keys($families) as $familyId) {
            if (!isset($submitted[$familyId]) || !is_string($submitted[$familyId])) {
                continue;
            }
            $envelopes[$familyId] = $submitted[$familyId];
            $envelope = EnvelopeService::parseEnvelope($submitted[$familyId]);
            if ($envelope === null) {
                $invalidFamilyIds[$familyId] = true;
            } else {
                $toSave[$familyId] = $envelope;
            }
        }

        if ($invalidFamilyIds === []) {
            $updated = EnvelopeService::saveChanged($toSave);
            FlashService::set(sprintf(ngettext('%d envelope number saved.', '%d envelope numbers saved.', $updated), $updated));

            return SlimUtils::renderRedirect(
                $response,
                SystemURLs::getRootPath() . '/finance/envelopes?' . http_build_query(['classification' => $classificationId, 'sortBy' => $sortBy])
            );
        }
        $errorMessage = sprintf(gettext('Envelope numbers must be whole numbers from 0 to %d. Nothing was saved.'), EnvelopeService::MAX_ENVELOPE);
        $isUnsaved = true;
    }

    $rows = [];
    foreach ($envelopes as $familyId => $envelope) {
        $rows[] = [
            'familyId' => $familyId,
            'label' => $families[$familyId]['label'],
            'envelope' => $envelope,
            'invalid' => isset($invalidFamilyIds[$familyId]),
        ];
    }
    if ($sortBy === 'envelope') {
        usort($rows, static fn (array $a, array $b): int => (int) $a['envelope'] <=> (int) $b['envelope']);
    }
    $seenEnvelopes = [];
    foreach ($rows as &$row) {
        $envelope = (int) $row['envelope'];
        $row['duplicate'] = $envelope !== 0 && isset($seenEnvelopes[$envelope]);
        $seenEnvelopes[$envelope] = true;
    }
    unset($row);

    $flash = FlashService::take();
    $sGlobalMessage = $flash['message'] ?? '';
    $sGlobalMessageClass = $flash['class'] ?? 'success';

    $classifications = [];
    foreach (Classification::getAll() as $option) {
        $classifications[(int) $option->getOptionId()] = $option->getOptionName();
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'envelopes.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Envelope Manager'),
        'sPageSubtitle' => gettext('Assign and manage donation envelope numbers'),
        'aBreadcrumbs' => PageHeader::breadcrumbs(array_values(array_filter([
            [gettext('Finance'), '/finance/'],
            AuthenticationManager::getCurrentUser()->isAdmin() ? [gettext('Finance Settings'), '/admin/finance'] : null,
            [gettext('Envelope Manager')],
        ]))),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Envelope Numbers'), 'url' => '/finance/reports/envelope-numbers?' . http_build_query(['classification' => $classificationId, 'sortBy' => $sortBy]), 'icon' => 'fa-print', 'adminOnly' => false],
            PageHeader::financeSettingsLink(),
        ]),
        'sGlobalMessage' => $sGlobalMessage,
        'sGlobalMessageClass' => $sGlobalMessageClass,
        'classifications' => $classifications,
        'classificationId' => $classificationId,
        'sortBy' => $sortBy,
        'assignStartNumber' => $assignStartNumber,
        'rows' => $rows,
        'errorMessage' => $errorMessage,
        'isUnsaved' => $isUnsaved,
    ]);
}
