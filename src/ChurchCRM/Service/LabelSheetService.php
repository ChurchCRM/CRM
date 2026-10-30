<?php

namespace ChurchCRM\Service;

/**
 * Lays labels out on a sheet of Avery-style label stock and renders the sheet as a PDF.
 *
 * Every size is in millimetres. A cell is `['text' => string, 'size' => ?int]`, where `size` overrides
 * the sheet's font size, or a name badge from nameTag().
 * Labels fill a column from the top down and then move to the next column.
 */
class LabelSheetService
{
    private const PAPER = [
        'letter' => ['format' => 'Letter', 'width' => 215.9, 'height' => 279.4],
        'A4' => ['format' => 'A4', 'width' => 210.0, 'height' => 297.0],
    ];

    /** Left/top: where the first label starts. NX/NY: labels across/down. */
    private const FORMATS = [
        'Tractor' => ['name' => 'Tractor', 'paper' => 'letter', 'left' => 6.5, 'top' => 5, 'NX' => 1, 'NY' => 10, 'gapX' => 3.175, 'gapY' => 0, 'width' => 120, 'height' => 26.5, 'size' => 12],
        '5160' => ['name' => '5160', 'paper' => 'letter', 'left' => 4, 'top' => 11.5, 'NX' => 3, 'NY' => 10, 'gapX' => 3.175, 'gapY' => 0, 'width' => 66.675, 'height' => 25.4, 'size' => 11],
        '5161' => ['name' => '5161', 'paper' => 'letter', 'left' => 1, 'top' => 10.7, 'NX' => 2, 'NY' => 10, 'gapX' => 3.967, 'gapY' => 0, 'width' => 101.6, 'height' => 25.4, 'size' => 11],
        '5162' => ['name' => '5162', 'paper' => 'letter', 'left' => 4, 'top' => 20.224, 'NX' => 2, 'NY' => 7, 'gapX' => 4.762, 'gapY' => 0, 'width' => 100.807, 'height' => 34, 'size' => 12],
        '5163' => ['name' => '5163', 'paper' => 'letter', 'left' => 1.762, 'top' => 10.7, 'NX' => 2, 'NY' => 5, 'gapX' => 3.175, 'gapY' => 0, 'width' => 101.6, 'height' => 50.8, 'size' => 8],
        '5164' => ['name' => '5164', 'paper' => 'letter', 'left' => 3.759, 'top' => 12.7, 'NX' => 2, 'NY' => 3, 'gapX' => 5.159, 'gapY' => 0, 'width' => 101.6, 'height' => 84.582, 'size' => 12],
        '8600' => ['name' => '8600', 'paper' => 'letter', 'left' => 7.1, 'top' => 19, 'NX' => 3, 'NY' => 10, 'gapX' => 9.5, 'gapY' => 3.1, 'width' => 66.6, 'height' => 25.4, 'size' => 8],
        '74536' => ['name' => '74536(name tags)', 'paper' => 'letter', 'left' => 7.0, 'top' => 25, 'NX' => 2, 'NY' => 3, 'gapX' => 0, 'gapY' => 0, 'width' => 102, 'height' => 76, 'size' => 18],
        'L7163' => ['name' => 'L7163', 'paper' => 'A4', 'left' => 5, 'top' => 15, 'NX' => 2, 'NY' => 7, 'gapX' => 2.5, 'gapY' => 0, 'width' => 99.1, 'height' => 38.1, 'size' => 10],
    ];

    private const FONT_FAMILIES = ['Courier', 'Helvetica', 'Symbol', 'Times', 'Zapfdingbats'];
    private const CSS_FAMILIES = ['courier' => 'monospace', 'times' => 'serif'];

    /** Free space kept below the last row so rounding never starts a stray page. */
    private const BOTTOM_SLACK = 2.0;

    /**
     * @return string[] the formats offered for mailing labels; the name-tag stock is left out
     */
    public static function labelTypes(): array
    {
        return array_values(array_diff(array_keys(self::FORMATS), ['74536']));
    }

    public static function hasFormat(string $labelType): bool
    {
        return isset(self::FORMATS[$labelType]);
    }

    /**
     * The font names offered on the label forms: "Helvetica", "Helvetica Bold", "Helvetica Bold Italic", ...
     *
     * @return string[]
     */
    public static function fontNames(): array
    {
        $names = [];
        foreach (self::FONT_FAMILIES as $family) {
            $names[] = $family;
            if (in_array($family, ['Symbol', 'Zapfdingbats'], true)) {
                continue;
            }
            array_push($names, $family . ' Bold', $family . ' Bold Italic', $family . ' Italic');
        }
        sort($names);

        return $names;
    }

    /**
     * A font name from a label form as CSS values. Only these values reach the page, never the name itself.
     *
     * @return array{family: string, weight: string, style: string}
     */
    public static function fontStyle(string $fontName): array
    {
        $words = array_map('mb_strtolower', preg_split('/\s+/', trim($fontName)) ?: []);

        return [
            'family' => self::CSS_FAMILIES[$words[0] ?? ''] ?? 'sans-serif',
            'weight' => in_array('bold', $words, true) ? 'bold' : 'normal',
            'style' => in_array('italic', $words, true) ? 'italic' : 'normal',
        ];
    }

    /**
     * The point size a form value asks for, or $fallback for "default" and anything that is not a size.
     */
    public static function fontSize(mixed $requested, int $fallback): int
    {
        if (!is_numeric($requested)) {
            return $fallback;
        }

        return max(4, min(72, (int) $requested));
    }

    /**
     * The default point size of a format.
     */
    public static function defaultFontSize(string $labelType): int
    {
        return self::FORMATS[$labelType]['size'];
    }

    /**
     * A format's geometry for the sheet. Columns and rows that would not fit on the paper are dropped.
     *
     * @return array<string, mixed>
     */
    public static function layout(string $labelType): array
    {
        $format = self::FORMATS[$labelType];
        $paper = self::PAPER[$format['paper']];

        $columns = (int) floor(($paper['width'] - $format['left'] + $format['gapX']) / ($format['width'] + $format['gapX']));
        $rows = (int) floor(($paper['height'] - $format['top'] - self::BOTTOM_SLACK + $format['gapY']) / ($format['height'] + $format['gapY']));

        return [
            'format' => $paper['format'],
            'left' => $format['left'],
            'top' => $format['top'],
            'columns' => max(1, min($format['NX'], $columns)),
            'rows' => max(1, min($format['NY'], $rows)),
            'gap_x' => $format['gapX'],
            'gap_y' => $format['gapY'],
            'width' => $format['width'],
            'height' => $format['height'],
        ];
    }

    /**
     * Places the cells on sheets, starting at a column and row of the first sheet.
     *
     * @param array<string, mixed> $layout
     * @param array<int, array<string, mixed>> $cells
     * @return array<int, array<int, array<int, array<string, mixed>|null>>> sheets[sheet][row][column]
     */
    public static function sheets(array $layout, array $cells, int $startColumn = 1, int $startRow = 1): array
    {
        $columns = $layout['columns'];
        $rows = $layout['rows'];
        $perSheet = $columns * $rows;
        $offset = (min(max($startColumn, 1), $columns) - 1) * $rows + (min(max($startRow, 1), $rows) - 1);

        $sheets = [];
        foreach (array_values($cells) as $index => $cell) {
            $slot = $offset + $index;
            $sheets[intdiv($slot, $perSheet)][$slot % $rows][intdiv($slot % $perSheet, $rows)] = $cell;
        }

        foreach (array_keys($sheets) as $sheet) {
            for ($row = 0; $row < $rows; $row++) {
                for ($column = 0; $column < $columns; $column++) {
                    $sheets[$sheet][$row][$column] ??= null;
                }
                ksort($sheets[$sheet][$row]);
            }
            ksort($sheets[$sheet]);
        }

        return array_values($sheets);
    }

    /**
     * @param array<int, array<string, mixed>> $cells
     */
    public function renderPdf(string $labelType, array $cells, int $startColumn, int $startRow, string $fontName, int $fontSize): string
    {
        $layout = self::layout($labelType);
        $cells = array_map(static fn (array $cell): array => isset($cell['tag']) ? $cell : self::fitCell($cell, $layout['width'], $fontSize), $cells);

        return (new MpdfRenderer())->renderToString('label-sheet', [
            'layout' => $layout,
            'sheets' => self::sheets($layout, $cells, $startColumn, $startRow),
            'font' => self::fontStyle($fontName),
            'font_size' => $fontSize,
        ], false, [
            'format' => $layout['format'],
            'margin_left' => $layout['left'],
            'margin_right' => 0,
            'margin_top' => $layout['top'],
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
    }

    /**
     * A name badge: the church name across the top, then the first name large and the last name
     * smaller, centred in the space beside the photo. Positions are millimetres from the badge's corner.
     *
     * @return array{tag: array<string, mixed>}
     */
    public static function nameTag(string $firstName, string $lastName, ?string $photoPath, float $width, float $height, int $fontSize): array
    {
        $bodyTop = 12.0;
        $bodyHeight = $height - $bodyTop - 4;
        $photo = $photoPath !== null ? self::fitPhoto($photoPath, $width * 0.32, $bodyHeight) : null;

        $textLeft = 5.0;
        if ($photo !== null) {
            $photo['x'] = $textLeft;
            $photo['y'] = round($bodyTop + ($bodyHeight - $photo['height']) / 2, 2);
            $textLeft += $photo['width'] + 4;
        }
        $textWidth = $width - $textLeft - 5;

        $firstSize = self::fitFontSize($fontSize, mb_strlen($firstName), $textWidth);
        $lastSize = self::fitFontSize(min($firstSize, (int) round($fontSize * 0.55)), mb_strlen($lastName), $textWidth);
        $textHeight = ($firstSize + $lastSize) * 1.2 * 0.3528;

        return ['tag' => [
            'first' => $firstName,
            'last' => $lastName,
            'first_size' => $firstSize,
            'last_size' => $lastSize,
            'photo' => $photo,
            'text_x' => round($textLeft, 2),
            'text_y' => round($bodyTop + max(0, ($bodyHeight - $textHeight) / 2), 2),
            'text_width' => round($textWidth, 2),
        ]];
    }

    /**
     * A photo scaled to fit a box, keeping its shape.
     *
     * @return array{src: string, width: float, height: float}|null null when the file is not a readable image
     */
    public static function fitPhoto(string $path, float $maxWidth, float $maxHeight): ?array
    {
        $size = is_file($path) ? @getimagesize($path) : false;
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return ['src' => $path, 'width' => round($size[0] * $scale, 2), 'height' => round($size[1] * $scale, 2)];
    }

    /**
     * Sets the cell's font size to the largest that keeps its longest line inside the label.
     * The text is not wrapped, so a line wider than the label would be cut off.
     *
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    public static function fitCell(array $cell, float $labelWidth, int $fontSize): array
    {
        $longest = 0;
        foreach (explode("\n", (string) $cell['text']) as $line) {
            $longest = max($longest, mb_strlen($line));
        }
        $textWidth = $labelWidth - 5;
        $cell['size'] = self::fitFontSize($cell['size'] ?? $fontSize, $longest, $textWidth);

        return $cell;
    }

    /**
     * The font size that fits a line of $characters across $width millimetres, never above $size.
     * Glyph width is estimated at 0.6 em, which holds for the proportional and bold faces on the forms.
     */
    public static function fitFontSize(int $size, int $characters, float $width): int
    {
        if ($characters < 1) {
            return $size;
        }
        $fitting = (int) floor($width / ($characters * 0.6 * 0.3528));

        return max(4, min($size, $fitting));
    }
}
