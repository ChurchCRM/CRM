<?php

namespace ChurchCRM\Service;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Twig\GettextExtension;
use Mpdf\Mpdf;
use RuntimeException;
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
        $data['church_social'] = array_values(array_filter(
            ChurchMetaData::getChurchSocialLinks(),
            static fn (array $link): bool => ChurchMetaData::isValidSocialUrl((string) $link['url'])
        ));
        $data['church_logo'] = $this->churchLogo();
        $locale = Bootstrapper::getCurrentLocale();
        $localeCode = str_replace('_', '-', (string) $locale->getLocale());
        $data['lang'] = str_starts_with($localeCode, 'zh') ? $localeCode : substr($localeCode, 0, 2);
        $data['text_dir'] = $locale->isRTL() ? 'rtl' : 'ltr';
        $data['generated_on'] = DateTimeUtils::formatDate(DateTimeUtils::getToday());
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
        if (LetterheadService::hasUploaded() && is_readable(LetterheadService::getPath())) {
            return LetterheadService::getPath();
        }

        $path = trim((string) SystemConfig::getValue('bDirLetterHead'));
        if ($path !== '' && (is_readable($path) || preg_match('#^https://#i', $path))) {
            return $path;
        }

        return null;
    }

    public static function createDocument(array $options): Mpdf
    {
        return new Mpdf(['tempDir' => self::temporaryDirectory((string) SystemConfig::getValue('sPDFTempDir'))] + $options);
    }

    public static function temporaryDirectory(string $configured): string
    {
        $configured = trim($configured);
        $root = $configured === '' ? sys_get_temp_dir() : $configured;
        if (str_contains($root, "\0") || (!str_starts_with($root, '/') && !str_starts_with($root, '\\\\') && !preg_match('~^[A-Za-z]:[\\\\/]~', $root))) {
            throw new RuntimeException(gettext('The PDF temporary directory must be an absolute directory path.'));
        }
        $temporaryRoot = realpath($root);
        $documentRoot = realpath(__DIR__ . '/../..');
        if ($temporaryRoot === false || !is_dir($temporaryRoot) || $documentRoot === false) {
            throw new RuntimeException(gettext('The PDF temporary directory does not exist or is not accessible.'));
        }

        $account = function_exists('posix_geteuid') ? (string) posix_geteuid() : get_current_user();
        $directory = $temporaryRoot . '/churchcrm-mpdf-' . substr(hash('sha256', $documentRoot . "\0" . $account), 0, 24);
        if (is_link($directory)
            || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))) {
            throw new RuntimeException(gettext('Cannot create private PDF temporary storage in the selected directory.'));
        }
        clearstatcache(true, $directory);
        if (!is_writable($directory)
            || (DIRECTORY_SEPARATOR === '/' && (fileperms($directory) & 0077) !== 0)
            || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) {
            throw new RuntimeException(gettext('PDF temporary storage must be private and writable.'));
        }

        return $directory;
    }

    private function makeMpdf(bool $landscape = false, array $mpdfOptions = []): Mpdf
    {
        return self::createDocument($mpdfOptions + [
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
