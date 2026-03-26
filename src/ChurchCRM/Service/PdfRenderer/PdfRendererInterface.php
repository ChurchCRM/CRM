<?php

namespace ChurchCRM\Service\PdfRenderer;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface for PDF rendering services.
 *
 * Implementations receive a Twig template name and data, produce a PDF, and either
 * write it to a PSR-7 response or return it as a string.  Swapping the underlying
 * PDF library (mPDF, Dompdf, …) only requires a new implementation of this interface.
 */
interface PdfRendererInterface
{
    /**
     * Render a report Twig template to PDF and write the output to a PSR-7 response.
     *
     * The returned response has Content-Type: application/pdf and a Content-Disposition
     * header set according to the `iPDFOutputType` system config value:
     *   1 = attachment (download)
     *   2 = inline (open in browser)
     *
     * @param string            $template  Template name relative to src/templates/reports/
     *                                     (without the ".html.twig" suffix), e.g. "tax-report"
     * @param array             $data      Variables available inside the Twig template
     * @param string            $filename  Base filename sent to the browser (without ".pdf" extension)
     * @param ResponseInterface $response  PSR-7 response to write into
     *
     * @return ResponseInterface The response with PDF content and headers set
     */
    public function render(string $template, array $data, string $filename, ResponseInterface $response): ResponseInterface;

    /**
     * Render a report Twig template to a PDF binary string.
     *
     * Useful for attaching PDFs to emails or saving them to disk.
     *
     * @param string $template Template name (same convention as render())
     * @param array  $data     Variables available inside the Twig template
     *
     * @return string Raw PDF bytes
     */
    public function renderToString(string $template, array $data): string;
}
