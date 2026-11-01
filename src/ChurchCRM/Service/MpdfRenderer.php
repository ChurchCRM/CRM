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

class MpdfRenderer
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        $loader = $twig?->getLoader() ?? new FilesystemLoader(__DIR__ . '/../../templates/reports');
        $this->twig = new Environment(new EscapedReportLoader($loader), [
            'autoescape' => 'html',
            'charset' => $twig?->getCharset() ?? 'UTF-8',
            'strict_variables' => $twig?->isStrictVariables() ?? false,
        ]);
        if ($twig !== null) {
            foreach ($twig->getExtensions() as $extension) {
                if (!$this->twig->hasExtension($extension::class)) {
                    $this->twig->addExtension($extension);
                }
            }
            foreach ($twig->getFunctions() as $function) {
                $this->twig->addFunction($function);
            }
            foreach ($twig->getFilters() as $filter) {
                if (!in_array($filter->getName(), ['escape', 'e', 'raw'], true)) {
                    $this->twig->addFilter($filter);
                }
            }
            foreach ($twig->getTests() as $test) {
                $this->twig->addTest($test);
            }
            foreach ($twig->getGlobals() as $name => $value) {
                $this->twig->addGlobal($name, $value);
            }
        }
        if (!$this->twig->hasExtension(GettextExtension::class)) {
            $this->twig->addExtension(new GettextExtension());
        }
    }

    /**
     * @param array<string, mixed> $mpdfOptions Mpdf constructor settings that replace the Letter-page defaults, such as a custom format and margins.
     */
    public function renderToString(string $template, array $data, bool $landscape = false, array $mpdfOptions = []): string
    {
        return $this->build($template, $data, $landscape, $mpdfOptions)->Output('', 'S');
    }

    private function build(string $template, array $data, bool $landscape = false, array $mpdfOptions = []): Mpdf
    {
        $data = $this->withChurch($data);
        $mpdf = $this->makeMpdf($landscape, $mpdfOptions);
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
        $data['generated_on'] = date(SystemConfig::getValue('sDateFormatLong') ?: 'Y-m-d');
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

    /**
     * The graphic letterhead PDF letters print: the uploaded image, else the readable
     * `bDirLetterHead` path or https URL, else null (the church address block prints).
     */
    public static function graphicLetterhead(): ?string
    {
        if (LetterheadService::hasUploaded()) {
            return LetterheadService::getPath();
        }

        $path = trim((string) SystemConfig::getValue('bDirLetterHead'));
        if ($path !== '' && (is_readable($path) || preg_match('#^https://#i', $path))) {
            return $path;
        }

        return null;
    }

    private function makeMpdf(bool $landscape = false, array $mpdfOptions = []): Mpdf
    {
        return new Mpdf($mpdfOptions + [
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
