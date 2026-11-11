<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\CurrencyFormatter;
use Propel\Runtime\Adapter\AdapterInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

$_SESSION = [];
SystemURLs::init('', ['http://localhost'], APP_ROOT);
$settings = [];
foreach ([
    'sTaxReport1' => 'Saved opening', 'sTaxReport2' => 'Saved closing',
    'sTaxReport3' => 'Saved questions', 'sTaxSigner' => 'Saved signer',
    'sChurchName' => 'Sample Test Church', 'sChurchTaxId' => 'TEST-TAX-ID',
] as $name => $value) {
    $settings[] = new class($name, $value) {
        public function __construct(private string $name, private string $value) {}
        public function getName(): string { return $this->name; }
        public function getValue(): string { return $this->value; }
    };
}
SystemConfig::init($settings, false);

// Any ORM access fails before a database connection or donor query can run.
$connections = new class('churchcrm') extends ConnectionManagerSingle {
    public int $attempts = 0;
    public function getReadConnection(?AdapterInterface $adapter = null): ConnectionInterface
    {
        $this->attempts++;
        throw new LogicException('Sample preview must not query donor data');
    }
    public function getWriteConnection(?AdapterInterface $adapter = null): ConnectionInterface
    {
        return $this->getReadConnection($adapter);
    }
};
Propel::getServiceContainer()->setAdapterClass('churchcrm', 'mysql');
Propel::getServiceContainer()->setConnectionManager($connections);

$document = GivingStatementService::sampleDocument();
Assert::count(1, $document['families']);
$family = $document['families'][0];
Assert::same('Sample Family (fictional)', $family['salutation']);
Assert::same(['Sample General Fund', 'Sample Missions Fund'], array_column($family['gifts'], 'fund'));
Assert::same([CurrencyFormatter::format(100), CurrencyFormatter::format(50)], array_column($family['gifts'], 'amount'));
Assert::same(CurrencyFormatter::format(150), $family['total']);
Assert::contains('Saved opening', $document['opening']);
Assert::same('Saved closing', $document['closing']);
Assert::same('Saved questions', $document['questions']);
Assert::same('Saved signer', $document['signer']);

$renderer = new MpdfRenderer();
$withChurch = new ReflectionMethod(MpdfRenderer::class, 'withChurch');
$data = $withChurch->invoke($renderer, $document);
$twig = (new ReflectionProperty(MpdfRenderer::class, 'twig'))->getValue($renderer);
$html = $twig->render('tax-report.html.twig', $data);
foreach (['Sample Family (fictional)', 'Sample General Fund', 'Sample Missions Fund', 'TEST-TAX-ID', 'Saved closing'] as $text) {
    Assert::contains($text, $html);
}

$app = AppFactory::create();
require APP_ROOT . '/finance/routes/reports.php';
foreach ([0 => 'inline', 1 => 'attachment'] as $output => $disposition) {
    $outputSetting = new class($output) {
        public function __construct(private int $value) {}
        public function getName(): string { return 'iPDFOutputType'; }
        public function getValue(): int { return $this->value; }
    };
    SystemConfig::init([...$settings, $outputSetting], false);
    // Real-data filters supplied to the sample route must have no effect.
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/reports/giving-statements/sample')
        ->withQueryParams(['familyId' => 1, 'deposit' => 1, 'output' => 'csv']);
    $response = $app->handle($request);
    Assert::same(200, $response->getStatusCode());
    Assert::same('application/pdf', $response->getHeaderLine('Content-Type'));
    Assert::same('%PDF-', substr((string) $response->getBody(), 0, 5));
    Assert::contains($disposition . ';', $response->getHeaderLine('Content-Disposition'));
    Assert::contains('GivingStatement-Sample.pdf', $response->getHeaderLine('Content-Disposition'));
    Assert::same('private, no-store', $response->getHeaderLine('Cache-Control'));
}
Assert::same(0, $connections->attempts);
