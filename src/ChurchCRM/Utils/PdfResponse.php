<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use Psr\Http\Message\ResponseInterface as Response;

final class PdfResponse
{
    public static function create(Response $response, string $pdf, string $label): Response
    {
        $filename = PdfFilename::create($label);
        $ascii = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', substr($filename, 0, -4)), '-');
        $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';
        if ($pdf !== '') {
            $response->getBody()->write($pdf);
        }

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Content-Disposition', sprintf(
                '%s; filename="%s.pdf"; filename*=UTF-8\'\'%s',
                $disposition,
                $ascii !== '' ? $ascii : 'Report',
                rawurlencode($filename)
            ));
    }
}
