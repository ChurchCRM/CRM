<?php

declare(strict_types=1);

use ChurchCRM\Service\MpdfRenderer;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

$renderer = new MpdfRenderer();
$twig = (new ReflectionProperty(MpdfRenderer::class, 'twig'))->getValue($renderer);
$text = '<b>Offering & "Review"</b>';
$common = [
    'church_name' => $text, 'church_address' => $text, 'church_phone' => '', 'church_email' => '',
    'church_tax_id' => '', 'church_website' => '', 'church_social' => [], 'church_logo' => '',
    'lang' => 'en', 'text_dir' => 'ltr', 'generated_on' => '',
];
$data = [
    'deposit' => [
        'id' => 1, 'date' => '2026-01-01', 'comment' => $text, 'ticket' => null,
        'payments' => [['check_no' => $text, 'fund' => $text, 'method' => 'CHECK', 'from' => $text, 'memo' => $text, 'amount' => '10.00']],
        'total' => '10.00', 'checks_count' => 1, 'checks_total' => '10.00', 'cash_total' => '0.00', 'other_total' => '',
        'funds' => [['name' => $text, 'total' => '10.00']], 'bill_counts' => false,
    ],
    'advanced-deposit' => [
        'summary_lines' => [$text], 'detail' => true,
        'columns' => [['align' => 'left', 'label' => 'Family', 'key' => 'family']],
        'groups' => [['title' => $text, 'subgroups' => [['rows' => [['family' => $text]], 'total' => ['label' => $text, 'count' => '1', 'amount' => '10.00']]], 'total' => ['label' => $text, 'count' => '1', 'amount' => '10.00']]],
        'report_total' => ['label' => $text, 'count' => '1', 'amount' => '10.00'],
        'fund_totals' => [['name' => $text, 'amount' => '10.00']],
    ],
];
foreach ($data as $template => $document) {
    $html = $twig->render($template . '.html.twig', $document + $common);
    Assert::notContains($text, $html, $template);
    Assert::contains(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html, $template);
}
$ticket = $data['deposit'];
$ticket['ticket'] = ['items' => [['align' => 'left', 'left' => 1, 'top' => 1, 'text' => $text]], 'title_x' => 1, 'perforation_y' => 1, 'date_x' => 1, 'date' => $text, 'stub_x' => 1, 'stub_y' => 1, 'totals_x' => 1];
$html = $twig->render('deposit.html.twig', $ticket + $common);
Assert::notContains($text, $html, 'Bank ticket text is escaped');
