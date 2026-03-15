<?php

namespace ChurchCRM\Service\PdfRenderer;

/**
 * Interface for PDF rendering services.
 *
 * Implementations receive a Twig template name and data, produce a PDF, and either
 * stream it to the browser or return it as a string.  Swapping the underlying PDF
 * library (mPDF, Dompdf, …) only requires a new implementation of this interface.
 */
interface PdfRendererInterface
{
    /**
     * Render a report Twig template to PDF and stream the output to the browser.
     *
     * Whether the PDF is sent as an inline attachment ("I") or a file download ("D")
     * is determined by the `iPDFOutputType` system configuration value:
     *   1 = download (D)
     *   2 = open inline (I)
     *
     * @param string $template  Template name relative to src/templates/reports/ (without the
     *                          ".html.twig" suffix), e.g. "tax-report"
     * @param array  $data      Variables available inside the Twig template
     * @param string $filename  Base filename sent to the browser (without ".pdf" extension)
     */
    public function render(string $template, array $data, string $filename): void;

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
