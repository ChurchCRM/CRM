<?php

namespace ChurchCRM\Service;

use Mpdf\Mpdf;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Lays the pages of a PDF out as a folded booklet.
 *
 * The input holds half-size pages (a letter sheet turned landscape gives 5.5 x 8.5 in pages, A4
 * gives A5). The pages are padded to a multiple of four and placed two per landscape sheet so
 * that, printed double sided (flip on the short edge) and folded in half, they read in order.
 *
 * For N padded pages, sheet i (from 0) carries pages N-i and i+1, the outer page on the left
 * for even i and on the right for odd i.
 */
class PdfBookletImposer
{
    /**
     * @param string $pdf         the half-size pages
     * @param string $sheetFormat mPDF landscape format name of the sheet, such as "Letter-L"
     * @param float  $pageWidthMm width of one half-size page
     * @param float  $pageHeightMm height of one half-size page
     */
    public static function impose(string $pdf, string $sheetFormat, float $pageWidthMm, float $pageHeightMm): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $sheetFormat,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $pageCount = $mpdf->setSourceFile(StreamReader::createByString($pdf));
        $padded = (int) (ceil($pageCount / 4) * 4);

        for ($sheet = 0; $sheet < $padded / 2; $sheet++) {
            $outer = $padded - $sheet;
            $inner = $sheet + 1;
            [$left, $right] = $sheet % 2 === 0 ? [$outer, $inner] : [$inner, $outer];

            $mpdf->AddPage();
            foreach ([$left => 0.0, $right => $pageWidthMm] as $number => $x) {
                if ($number <= $pageCount) {
                    $mpdf->useTemplate($mpdf->importPage($number), $x, 0, $pageWidthMm, $pageHeightMm);
                }
            }
        }

        return $mpdf->Output('', 'S');
    }
}
