<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PdfBookletImposer;
use setasign\Fpdi\PdfParser\StreamReader;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);
SystemConfig::init([], false);
Assert::same('', SystemConfig::getValue('sPDFTempDir'));

$make = new ReflectionMethod(MpdfRenderer::class, 'makeMpdf');
$portrait = $make->invoke(new MpdfRenderer(), false, ['tempDir' => APP_ROOT . '/vendor/unused-report-cache']);
$landscape = $make->invoke(new MpdfRenderer(), true);
Assert::same($portrait->tempDir, $landscape->tempDir);
Assert::false(str_starts_with($portrait->tempDir, realpath(APP_ROOT) . DIRECTORY_SEPARATOR));
Assert::false(is_dir(APP_ROOT . '/vendor/unused-report-cache'));
Assert::true(is_writable($portrait->tempDir));
if (DIRECTORY_SEPARATOR === '/') {
    Assert::same(0700, fileperms($portrait->tempDir) & 0777);
}
Assert::same(215.9, round($portrait->w, 1));
Assert::same(279.4, round($landscape->w, 1));
Assert::false(class_exists('FPDF'));

foreach (['Letter-L' => [139.7, 215.9], 'A4-L' => [148.5, 210.0]] as $format => [$width, $height]) {
    $input = MpdfRenderer::createDocument(['mode' => 'utf-8', 'format' => [$width, $height]]);
    for ($page = 1; $page <= 5; $page++) {
        if ($page > 1) {
            $input->AddPage();
        }
        $input->WriteHTML('<p>Directory ' . $page . ' — Église القاهرة</p>');
    }
    $pdf = PdfBookletImposer::impose($input->Output('', 'S'), $format, $width, $height);
    Assert::same('%PDF-', substr($pdf, 0, 5));
    Assert::same(4, $portrait->setSourceFile(StreamReader::createByString($pdf)));
    preg_match_all('/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/', $pdf, $boxes, PREG_SET_ORDER);
    Assert::true(count($boxes) > 0);
    foreach ($boxes as $box) {
        Assert::true((float) $box[1] > (float) $box[2]);
    }
}

$customRoot = realpath(sys_get_temp_dir()) . '/churchcrm-pdf-test-' . bin2hex(random_bytes(8));
mkdir($customRoot, 0700);
try {
    $custom = MpdfRenderer::temporaryDirectory($customRoot);
    Assert::same($customRoot, dirname($custom));
    SystemConfig::init([new class($customRoot) {
        public function __construct(private string $value) {}
        public function getName(): string { return 'sPDFTempDir'; }
        public function getValue(): string { return $this->value; }
    }], false);
    Assert::same($custom, MpdfRenderer::createDocument([])->tempDir);

    Assert::same($custom, MpdfRenderer::temporaryDirectory($customRoot));
    Assert::exception(static fn () => MpdfRenderer::temporaryDirectory('relative/path'), RuntimeException::class);
    Assert::exception(static fn () => MpdfRenderer::temporaryDirectory($customRoot . '/missing'), RuntimeException::class);
    file_put_contents($customRoot . '/file', 'not a directory');
    Assert::exception(static fn () => MpdfRenderer::temporaryDirectory($customRoot . '/file'), RuntimeException::class);
    if (DIRECTORY_SEPARATOR === '/') {
        chmod($custom, 0755);
        clearstatcache(true, $custom);
        Assert::exception(static fn () => MpdfRenderer::temporaryDirectory($customRoot), RuntimeException::class);
        chmod($custom, 0700);
    }
} finally {
    SystemConfig::init([], false);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($customRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($customRoot);
}
