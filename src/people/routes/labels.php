<?php

/**
 * People module — mailing labels
 *
 * GET /people/labels  Mailing labels for the cart or a People Report, as a PDF sheet or a CSV.
 *
 * Open to every staff user, as the cart and People Reports that link here are.
 */

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\LabelSheetService;
use ChurchCRM\Service\MailingLabelService;
use ChurchCRM\Service\PeopleReportService;
use ChurchCRM\Slim\Middleware\Request\Auth\StaffRoleAuthMiddleware;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Routing\RouteCollectorProxy;

/**
 * Remembers a label form choice for 90 days, so the Generate Labels dialog opens with it next time.
 */
function rememberLabelChoice(string $name, string $value): void
{
    setcookie($name, $value, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/', 'samesite' => 'Lax']);
}

function respondWithLabelPdf(Response $response, string $pdf, string $baseName): Response
{
    $filename = $baseName . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $response->getBody()->write($pdf);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
}

$app->group('/labels', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams();
        $text = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';

        $labelType = $text('labeltype');
        if (!LabelSheetService::hasFormat($labelType)) {
            throw new HttpBadRequestException($request, gettext('Invalid request data'));
        }
        $fontName = in_array($text('labelfont'), LabelSheetService::fontNames(), true) ? $text('labelfont') : 'Helvetica';
        $fontSize = LabelSheetService::fontSize($text('labelfontsize'), 10);
        $mode = $text('groupbymode') === 'fam' ? 'fam' : 'indiv';
        $presort = !empty($text('bulkmailpresort'));
        $quiet = !empty($text('bulkmailquiet'));
        $toParents = $text('toparents') === '1';

        rememberLabelChoice('labeltype', $labelType);
        rememberLabelChoice('labelfont', $fontName);
        rememberLabelChoice('labelfontsize', $text('labelfontsize') === 'default' ? 'default' : (string) $fontSize);
        if ($text('groupbymode') !== '') {
            rememberLabelChoice('groupbymode', $mode);
        }
        rememberLabelChoice('bulkmailpresort', $presort ? '1' : '');
        rememberLabelChoice('bulkmailquiet', $quiet ? '1' : '');
        rememberLabelChoice('toparents', $toParents ? '1' : '');

        // A People Report can address some rows by person and others by household; the cart uses the chosen grouping.
        $reportSlug = $text('report');
        if ($reportSlug !== '') {
            $entries = (new PeopleReportService())->labelEntries($reportSlug, $query, $mode);
        } else {
            $entries = array_fill_keys(array_map('intval', array_filter(explode(',', Cart::getCartIdString()))), [$mode]);
        }

        $bulk = $presort ? ($quiet ? MailingLabelService::BULK_QUIET : MailingLabelService::BULK_NOTED) : MailingLabelService::BULK_NONE;
        $labels = MailingLabelService::labels($entries, $bulk, $toParents, $text('onlyfull') === '1');

        if (strtoupper($text('filetype')) === 'CSV') {
            ['headers' => $headers, 'rows' => $rows] = MailingLabelService::csv($labels, $bulk);
            $exporter = new CsvExporter();
            $exporter->insertHeaders($headers);
            $exporter->insertRows($rows);
            $response->getBody()->write($exporter->getContent());

            return $response
                ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->withHeader('Content-Disposition', 'attachment; filename="Labels-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv"')
                ->withHeader('Cache-Control', 'no-store');
        }

        $cells = array_map(static fn (array $label): array => ['text' => MailingLabelService::text($label, $bulk)], $labels);
        $pdf = (new LabelSheetService())->renderPdf(
            $labelType,
            $cells,
            max(1, InputUtils::filterInt($text('startcol'))),
            max(1, InputUtils::filterInt($text('startrow'))),
            $fontName,
            $fontSize
        );

        return respondWithLabelPdf($response, $pdf, 'Labels-');
    });
})->add(StaffRoleAuthMiddleware::class);
