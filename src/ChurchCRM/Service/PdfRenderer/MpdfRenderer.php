<?php

namespace ChurchCRM\Service\PdfRenderer;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Twig\GettextExtension;
use Mpdf\Mpdf;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * PDF renderer backed by mPDF v8.
 *
 * mPDF handles UTF-8 / multilingual text natively so there is no need for the
 * iconv-based Latin-1 conversion that the legacy FPDF-based ChurchInfoReport
 * required.
 *
 * Usage (direct instantiation):
 *
 *   $renderer = new MpdfRenderer();
 *   $renderer->render('tax-report', $data, 'TaxReport-2024');
 *
 * Usage (injected Twig environment, e.g. from DI container):
 *
 *   $renderer = new MpdfRenderer($twigEnvironment);
 */
class MpdfRenderer implements PdfRendererInterface
{
    private const TEMPLATES_DIR = __DIR__ . '/../../../templates/reports';

    private Environment $twig;

    /**
     * @param Environment|null $twig  Optional Twig environment.  When omitted a default
     *                                environment pointing at src/templates/reports/ is created.
     */
    public function __construct(?Environment $twig = null)
    {
        if ($twig !== null) {
            $this->twig = $twig;
        } else {
            $loader = new FilesystemLoader(self::TEMPLATES_DIR);
            $env = new Environment($loader);
            $env->addExtension(new GettextExtension());
            $this->twig = $env;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function render(string $template, array $data, string $filename): void
    {
        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($this->twig->render($template . '.html.twig', $data));
        $outputMode = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'D' : 'I';
        $mpdf->Output($filename . '.pdf', $outputMode);
    }

    /**
     * {@inheritdoc}
     */
    public function renderToString(string $template, array $data): string
    {
        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($this->twig->render($template . '.html.twig', $data));
        // 'S' = return as string
        return $mpdf->Output('', 'S');
    }

    /**
     * Create a configured Mpdf instance using system settings.
     */
    private function createMpdf(): Mpdf
    {
        $paperFormat = SystemConfig::getValue('sPaperFormat') ?: 'Letter';

        return new Mpdf([
            'mode'              => 'utf-8',
            'format'            => $paperFormat,
            // Disable font sub-setting for performance on server-side rendering
            'fontSubsetting'    => false,
            // Use a writable temp directory
            'tempDir'           => sys_get_temp_dir(),
        ]);
    }
}
