<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\ChurchMetaData;
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
        $data['church_logo'] = ChurchMetaData::getChurchLogoPath();
        return $data;
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
