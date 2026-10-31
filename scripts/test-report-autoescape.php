<?php

require __DIR__ . '/../src/vendor/autoload.php';

use ChurchCRM\Service\MpdfRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

$text = '<b>Église & "مريم" 中文</b>';
$loader = new ArrayLoader(['probe.html.twig' => '{{ name }}|{{ address|nl2br }}']);
$unsafe = new Environment($loader, ['autoescape' => false]);
$unsafe->render('probe.html.twig', ['name' => $text, 'address' => $text]);
$renderer = new MpdfRenderer($unsafe);
$twig = (new ReflectionProperty(MpdfRenderer::class, 'twig'))->getValue($renderer);
$html = $twig->render('probe.html.twig', ['name' => $text, 'address' => $text . "\nSecond line"]);
if (str_contains($html, '<b>') || !str_contains($html, '&lt;b&gt;') || !str_contains($html, '<br') || !str_contains($html, 'مريم')) {
    throw new RuntimeException('Injected/precompiled environments must escape text and preserve Unicode and line breaks');
}
$default = new MpdfRenderer();
$twig = (new ReflectionProperty(MpdfRenderer::class, 'twig'))->getValue($default);
$common = ['church_name' => $text, 'church_address' => $text, 'lang' => 'ar', 'text_dir' => 'rtl'];
$family = ['salutation' => $text, 'address' => $text . "\nSecond line", 'gifts' => [['date' => $text, 'fund' => $text, 'method' => $text, 'amount' => '10.00']], 'total' => '10.00'];
$html = $twig->render('tax-report.html.twig', $common + ['families' => [$family, $family], 'opening' => $text, 'closing' => $text, 'remittance' => true]);
if (str_contains($html, '<b>') || substr_count($html, '&lt;b&gt;') < 8 || !str_contains($html, 'dir="rtl"') || !str_contains($html, '<br')) {
    throw new RuntimeException('Giving statements, repeated letterheads and remittance slips must escape text');
}
foreach (glob(__DIR__ . '/../src/templates/reports/*.twig') as $path) {
    $source = file_get_contents($path);
    if (preg_match('/\|\s*raw\b|autoescape\s+false/', $source)) {
        throw new RuntimeException('Review escaping bypass in ' . basename($path));
    }
}
echo "Report escaping regression checks passed.\n";
