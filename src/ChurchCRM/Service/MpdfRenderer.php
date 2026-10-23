<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\dto\SystemConfig;
use Mpdf\Mpdf;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MpdfRenderer implements PdfRendererInterface
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        $this->twig = $twig ?? new Environment(new FilesystemLoader(__DIR__ . '/../../templates/reports'));
    }

    public function renderToString(string $template, array $data): string
    {
        $html = $this->twig->render($template . '.html.twig', $this->withChurch($data));
        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    public function render(string $template, array $data, string $filename, string $disposition = 'I'): void
    {
        $html = $this->twig->render($template . '.html.twig', $this->withChurch($data));
        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename . '.pdf', $disposition === 'D' ? 'D' : 'I');
    }

    private function withChurch(array $data): array
    {
        $data['church_name'] = ChurchMetaData::getChurchName();
        $data['church_address'] = ChurchMetaData::getChurchFullAddress();
        $data['church_phone'] = ChurchMetaData::getChurchPhone();
        $data['church_email'] = ChurchMetaData::getChurchEmail();
        $data['church_logo'] = $this->churchLogo();
        $data['generated_on'] = date(\ChurchCRM\dto\SystemConfig::getValue('sDateFormatLong') ?: 'Y-m-d');
        return $data;
    }

    /**
     * Church-owned logo only. Never the bundled ChurchCRM mark on a letter
     * a donor receives.
     */
    private function churchLogo(): ?string
    {
        if (ChurchLogoService::hasCustomLogo()) {
            return ChurchLogoService::getLogoPath();
        }
        $configured = ChurchMetaData::getChurchLogoURL();
        if ($configured !== '' && !str_contains($configured, 'churchcrm-logo')) {
            return $configured;
        }
        return null;
    }

    private function makeMpdf(): Mpdf
    {
        $format = SystemConfig::getValue('sPaperFormat') ?: 'Letter';
        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);
    }
}
