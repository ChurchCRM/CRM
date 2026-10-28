<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Twig\GettextExtension;
use Mpdf\Mpdf;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MpdfRenderer implements PdfRendererInterface
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        $this->twig = $twig ?? new Environment(new FilesystemLoader(__DIR__ . '/../../templates/reports'));
        if (!$this->twig->hasExtension(GettextExtension::class)) {
            $this->twig->addExtension(new GettextExtension());
        }
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
        $data['church_tax_id'] = ChurchMetaData::getChurchTaxId();
        $website = ChurchMetaData::getChurchWebSite();
        $data['church_website'] = preg_match('#^https?://#i', $website) ? $website : '';
        $data['church_social'] = ChurchMetaData::getChurchSocialLinks();
        $data['church_logo'] = $this->churchLogo();
        $data['generated_on'] = date(\ChurchCRM\dto\SystemConfig::getValue('sDateFormatLong') ?: 'Y-m-d');
        return $data;
    }

    /** The church's logo, else the bundled ChurchCRM logo, so a letter always carries one. */
    private function churchLogo(): string
    {
        if (ChurchLogoService::hasCustomLogo()) {
            return ChurchLogoService::getLogoPath();
        }
        $configured = ChurchMetaData::getChurchLogoURL();
        if (!str_contains($configured, 'churchcrm-logo')) {
            return $configured;
        }
        return SystemURLs::getDocumentRoot() . '/Images/churchcrm-logo-ink-blue.svg';
    }

    private function makeMpdf(): Mpdf
    {
        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);
    }
}
