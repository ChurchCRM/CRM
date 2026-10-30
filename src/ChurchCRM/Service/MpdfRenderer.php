<?php

namespace ChurchCRM\Service;

use ChurchCRM\Bootstrapper;
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

    public function renderToString(string $template, array $data, bool $landscape = false): string
    {
        return $this->build($template, $data, $landscape)->Output('', 'S');
    }

    public function render(string $template, array $data, string $filename, string $disposition = 'I'): void
    {
        $this->build($template, $data)->Output($filename . '.pdf', $disposition === 'D' ? 'D' : 'I');
    }

    private function build(string $template, array $data, bool $landscape = false): Mpdf
    {
        $data = $this->withChurch($data);
        $mpdf = $this->makeMpdf($landscape);
        if ($data['text_dir'] === 'rtl') {
            $mpdf->SetDirectionality('rtl');
        }
        $mpdf->WriteHTML($this->twig->render($template . '.html.twig', $data));

        return $mpdf;
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
        $locale = Bootstrapper::getCurrentLocale();
        $localeCode = str_replace('_', '-', (string) $locale->getLocale());
        $data['lang'] = str_starts_with($localeCode, 'zh') ? $localeCode : substr($localeCode, 0, 2);
        $data['text_dir'] = $locale->isRTL() ? 'rtl' : 'ltr';
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

    private function makeMpdf(bool $landscape = false): Mpdf
    {
        return new Mpdf([
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'format' => $landscape ? 'Letter-L' : 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);
    }
}
