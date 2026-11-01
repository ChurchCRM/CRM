<?php

declare(strict_types=1);

use ChurchCRM\Service\MpdfRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

$text = '<b>Église & "مريم" 中文</b>';
$loader = new ArrayLoader(['probe.html.twig' => '{{ name }}|{{ address|nl2br }}']);
$unsafe = new Environment($loader, ['autoescape' => false]);
$unsafe->render('probe.html.twig', ['name' => $text, 'address' => $text]);
$renderer = new MpdfRenderer($unsafe);
$twig = (new ReflectionProperty(MpdfRenderer::class, 'twig'))->getValue($renderer);
$html = $twig->render('probe.html.twig', ['name' => $text, 'address' => $text . "\nSecond line"]);
Assert::notContains('<b>', $html);
Assert::contains('&lt;b&gt;', $html);
Assert::contains('<br', $html);
Assert::contains('مريم', $html);
$default = new MpdfRenderer();
$twig = (new ReflectionProperty(MpdfRenderer::class, 'twig'))->getValue($default);
$common = ['church_name' => $text, 'church_address' => $text, 'lang' => 'ar', 'text_dir' => 'rtl'];
$family = ['salutation' => $text, 'address' => $text . "\nSecond line", 'gifts' => [['date' => $text, 'fund' => $text, 'method' => $text, 'amount' => '10.00']], 'total' => '10.00'];
$html = $twig->render('tax-report.html.twig', $common + ['families' => [$family, $family], 'opening' => $text, 'closing' => $text, 'remittance' => true]);
Assert::notContains('<b>', $html);
Assert::true(substr_count($html, '&lt;b&gt;') >= 8, 'Repeated letterheads and remittance slips escape text');
Assert::contains('dir="rtl"', $html);
Assert::contains('<br', $html);
foreach (glob(APP_ROOT . '/templates/reports/*.twig') as $path) {
    $source = file_get_contents($path);
    Assert::same(0, preg_match('/\|\s*raw\b|autoescape\s+false/', $source), basename($path) . ' escapes plain text');
}
