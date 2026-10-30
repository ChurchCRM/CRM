<?php

namespace ChurchCRM\Service;

interface PdfRendererInterface
{
    /**
     * Render a Twig template to a PDF and return the bytes.
     */
    public function renderToString(string $template, array $data): string;

    /**
     * Render a Twig template to a PDF and stream it.
     * $disposition is "I" (inline) or "D" (download).
     */
    public function render(string $template, array $data, string $filename, string $disposition = 'I'): void;
}
