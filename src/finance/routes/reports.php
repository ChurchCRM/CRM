<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Service\DonationFundService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {

    // Financial Reports selection page
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
            'sPageSubtitle' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports')],
            ]),
        ];

        return $renderer->render($response, 'reports.php', $pageArgs);
    });




    // Tax report year picker (migrated from root TaxReport.php)
    $group->get('/tax', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $year = (int) date('Y') - 1;
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Tax statements'),
            'sPageSubtitle' => gettext('Generate giving statements for a calendar year'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Tax statements')],
            ]),
            'year' => $year,
        ];
        return $renderer->render($response, 'reports/tax.php', $pageArgs);
    });

    // Family statement email (migrated from Reports/FamilyTaxReportEmail.php)
    $group->post('/tax/email', function (Request $request, Response $response): Response {
        require_once __DIR__ . '/../../Reports/FamilyTaxReportEmail.php';
        return $response;
    });

    // Generate year-end statements (POST) — thin MVC wrapper around TaxReport
    $group->post('/year-end/generate', function (Request $request, Response $response): Response {
        // Forward the posted filters to the existing generator.
        // No redirect shim for the old page; this is the supported entry.
        require_once __DIR__ . '/../../Reports/TaxReport.php';
        return $response;
    });

    // Zero givers under finance
    $group->post('/zero-givers', function (Request $request, Response $response): Response {
        require_once __DIR__ . '/../../Reports/ZeroGivers.php';
        return $response;
    });

    // Year-end statements wizard
    $group->get('/year-end', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $query = $request->getQueryParams();
        $step = (int) ($query['step'] ?? 1);

        $fyMonth = (int) \ChurchCRM\dto\SystemConfig::getIntValue('iFYMonth');
        $y = (int) date('Y');
        $m = (int) date('n');
        $fyYear = ($m >= $fyMonth) ? $y : $y - 1;
        $fyStart = sprintf('%04d-%02d-01', $fyYear, $fyMonth);
        $endMonth = $fyMonth === 1 ? 12 : $fyMonth - 1;
        $endYear = $fyMonth === 1 ? $fyYear : $fyYear + 1;
        $fyEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));

        $fyLabel = ($fyMonth === 1) ? (string) $fyYear : $fyYear . '/' . substr((string) ($fyYear + 1), 2, 2);

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Year-end statements'),
            'sPageSubtitle' => gettext('Close the books, review, generate, and deliver'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Year-end statements')],
            ]),
            'step' => $step,
            'fyLabel' => $fyLabel,
            'fyStart' => $fyStart,
            'fyEnd' => $fyEnd,
        ];

        return $renderer->render($response, 'reports/year-end-wizard.php', $pageArgs);
    });

    // Report filter form (migrated from FinancialReports.php second pass)
    $group->get('/{reportType}', function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $reportType = urldecode($args['reportType']);

        $actions = [
            'Giving Report' => 'Reports/TaxReport.php',
            'Zero Givers' => 'Reports/ZeroGivers.php',
            'Pledge Family Summary' => 'Reports/FamilyPledgeSummary.php',
            'Pledge Reminders' => 'Reports/ReminderReport.php',
            'Voting Members' => 'Reports/VotingMembers.php',
            'Individual Deposit Report' => 'Reports/PrintDeposit.php',
            'Advanced Deposit Report' => 'Reports/AdvancedDeposit.php',
        ];

        if (!isset($actions[$reportType])) {
            return $response->withHeader('Location', SystemURLs::getRootPath() . '/finance/reports')->withStatus(302);
        }

        $query = $request->getQueryParams();
        $sDateStart = $query['DateStart'] ?? '';
        $sDateEnd = $query['DateEnd'] ?? '';
        $datetype = $query['datetype'] ?? '';
        $returnMessage = $query['ReturnMessage'] ?? '';

        // Load supporting data
        $classifications = [];
        $families = [];
        $heads = [];
        $deposits = [];
        $funds = [];
        $fundGroups = [];
        $hasCategories = false;

        if (in_array($reportType, ['Giving Report', 'Pledge Reminders', 'Pledge Family Summary', 'Advanced Deposit Report'], true)) {
            $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
            $rs = RunQuery($sSQL);
            while ($row = mysqli_fetch_array($rs)) {
                $classifications[] = $row;
            }

            $sSQL = 'SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam ORDER BY fam_Name';
            $rsFam = RunQuery($sSQL);
            while ($row = mysqli_fetch_array($rsFam)) {
                $families[] = $row;
            }

            global $sDirRoleHead, $sDirRoleSpouse;
            if (!$sDirRoleHead) {
                $sDirRoleHead = '1';
            }
            $head_criteria = ' per_fmr_ID = ' . $sDirRoleHead;
            $head_criteria = str_replace(',', ' OR per_fmr_ID = ', $head_criteria);
            if (intval($sDirRoleSpouse) > 0) {
                $head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
            }
            $sSQL = 'SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (' . $head_criteria . ') ORDER BY per_fam_ID';
            $rsHead = RunQuery($sSQL);
            while (list($head_firstname, $head_famid) = mysqli_fetch_row($rsHead)) {
                if ($head_firstname && array_key_exists($head_famid, $heads)) {
                    $heads[$head_famid] .= ' & ' . $head_firstname;
                } elseif ($head_firstname) {
                    $heads[$head_famid] = $head_firstname;
                }
            }
        }

        if (in_array($reportType, ['Giving Report', 'Individual Deposit Report', 'Advanced Deposit Report'], true)) {
            $sSQL = 'SELECT dep_ID, dep_Date, dep_Type FROM deposit_dep ORDER BY dep_ID DESC LIMIT 0,200';
            $rsDep = RunQuery($sSQL);
            while ($row = mysqli_fetch_array($rsDep)) {
                $deposits[] = $row;
            }
        }

        if (in_array($reportType, ['Pledge Family Summary', 'Giving Report', 'Advanced Deposit Report', 'Pledge Reminders'], true)) {
            $funds = DonationFundQuery::create()->orderByActive()->orderByName()->find();
            $fundGroups = (new DonationFundService())->groupByCategory($funds);
            $hasCategories = array_keys($fundGroups) !== [''];
        }

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
            'sPageSubtitle' => gettext('Configure filters and generate the report'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext($reportType)],
            ]),
            'reportType' => $reportType,
            'action' => $actions[$reportType],
            'sDateStart' => $sDateStart,
            'sDateEnd' => $sDateEnd,
            'datetype' => $datetype,
            'returnMessage' => $returnMessage,
            'classifications' => $classifications,
            'families' => $families,
            'heads' => $heads,
            'deposits' => $deposits,
            'fundGroups' => $fundGroups,
            'hasCategories' => $hasCategories,
            'iFYID' => $_SESSION['idefaultFY'] ?? FiscalYearUtils::getCurrentFiscalYearId(),
        ];

        return $renderer->render($response, 'reports/filters.php', $pageArgs);
    });
});
