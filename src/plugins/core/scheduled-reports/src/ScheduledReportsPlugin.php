<?php

namespace ChurchCRM\Plugins\ScheduledReports;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\LoggerUtils;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Scheduled Reports Plugin.
 *
 * Sends periodic membership and financial summary reports to administrators.
 * Demonstrates how to consume the CRON_RUN hook for scheduled background work.
 *
 * Activate this plugin and configure the interval, recipient, and report types
 * to enable automatic periodic reporting.
 */
class ScheduledReportsPlugin extends AbstractPlugin
{
    private static ?ScheduledReportsPlugin $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?ScheduledReportsPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'scheduled-reports';
    }

    public function getName(): string
    {
        return gettext('Scheduled Reports');
    }

    public function getDescription(): string
    {
        return gettext('Send scheduled membership and financial summary reports to administrators by email.');
    }

    public function boot(): void
    {
        // Register a handler for the CRON_RUN hook so that reports are sent
        // automatically without SystemService needing to know about this plugin.
        HookManager::addAction(Hooks::CRON_RUN, [$this, 'onCronRun']);
    }

    /**
     * Called on every CRON_RUN event.
     * Sends a report email if the configured interval has elapsed.
     */
    public function onCronRun(): void
    {
        if (!$this->isEnabled() || !$this->isConfigured()) {
            return;
        }

        if (!$this->isIntervalElapsed()) {
            return;
        }

        $logger = LoggerUtils::getAppLogger();
        $logger->info('Scheduled Reports: Generating periodic report');

        try {
            $report = $this->buildReport();
            $sent = $this->sendReportEmail($report);

            if ($sent) {
                $this->setConfigValue('lastRunTimestamp', date('Y-m-d H:i:s'));
                $logger->info('Scheduled Reports: Report sent successfully');
            } else {
                $logger->warning('Scheduled Reports: Failed to send report email — check SMTP settings');
            }
        } catch (\Throwable $e) {
            $logger->warning('Scheduled Reports: Error generating report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Plugin Interface
    // =========================================================================

    public function isConfigured(): bool
    {
        $interval = (int) $this->getConfigValue('intervalHours');
        if ($interval <= 0) {
            return false;
        }

        // A recipient is required for sending. Fall back to church email if blank.
        $recipient = $this->getRecipientEmail();

        return !empty($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getConfigurationError(): ?string
    {
        $interval = (int) $this->getConfigValue('intervalHours');
        if ($interval <= 0) {
            return gettext('Report interval (hours) must be greater than zero.');
        }

        $recipient = $this->getRecipientEmail();
        if (empty($recipient)) {
            return gettext('No recipient email address configured and no church email address is set.');
        }

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return gettext('The recipient email address is not valid.');
        }

        return null;
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key'      => 'intervalHours',
                'label'    => gettext('Report Interval (hours)'),
                'type'     => 'number',
                'required' => true,
                'help'     => gettext('Hours between report emails. E.g. 24 = daily, 168 = weekly, 720 = monthly.'),
            ],
            [
                'key'      => 'recipientEmail',
                'label'    => gettext('Recipient Email Address'),
                'type'     => 'text',
                'required' => false,
                'help'     => gettext('Email address to receive reports. Leave blank to use the configured church admin email.'),
            ],
            [
                'key'      => 'reportTypes',
                'label'    => gettext('Report Types'),
                'type'     => 'text',
                'required' => false,
                'help'     => gettext('Comma-separated report types to include. Supported: membership, financial.'),
            ],
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Determine the email address that will receive the report.
     * Falls back to the church email when the plugin setting is blank.
     */
    private function getRecipientEmail(): string
    {
        $configured = trim($this->getConfigValue('recipientEmail'));
        if (!empty($configured)) {
            return $configured;
        }

        return ChurchMetaData::getChurchEmail();
    }

    /**
     * Return the interval in hours (default 168 = one week).
     */
    private function getIntervalHours(): int
    {
        $value = (int) $this->getConfigValue('intervalHours');

        return $value > 0 ? $value : 168;
    }

    /**
     * Check whether the reporting interval has elapsed since the last run.
     */
    private function isIntervalElapsed(): bool
    {
        $lastRun = $this->getConfigValue('lastRunTimestamp');
        if (empty($lastRun)) {
            return true; // Never run before — run now.
        }

        $last = \DateTime::createFromFormat('Y-m-d H:i:s', $lastRun);
        if ($last === false) {
            return true; // Unparseable timestamp — assume elapsed.
        }

        $now = new \DateTime();
        $elapsedHours = ($now->getTimestamp() - $last->getTimestamp()) / 3600;

        return $elapsedHours >= $this->getIntervalHours();
    }

    /**
     * Build the report data array based on configured report types.
     *
     * @return array{subject: string, html: string, text: string}
     */
    private function buildReport(): array
    {
        $reportTypesRaw = $this->getConfigValue('reportTypes');
        $types = empty($reportTypesRaw)
            ? ['membership', 'financial']
            : array_map('trim', explode(',', strtolower($reportTypesRaw)));

        $sections = [];
        $textSections = [];

        if (in_array('membership', $types, true)) {
            [$html, $text] = $this->buildMembershipSection();
            $sections[]     = $html;
            $textSections[] = $text;
        }

        if (in_array('financial', $types, true)) {
            [$html, $text] = $this->buildFinancialSection();
            $sections[]     = $html;
            $textSections[] = $text;
        }

        $churchName = ChurchMetaData::getChurchName();
        $date       = date('Y-m-d');
        $subject    = sprintf(gettext('%s Scheduled Report — %s'), $churchName, $date);

        $html = '<html><body>'
            . '<h2>' . htmlspecialchars($subject) . '</h2>'
            . implode('', $sections)
            . '<p style="color:#888;font-size:0.85em;">'
            . gettext('This report was generated automatically by ChurchCRM Scheduled Reports plugin.')
            . '</p>'
            . '</body></html>';

        $text = $subject . "\n\n"
            . implode("\n\n", $textSections)
            . "\n\n"
            . gettext('This report was generated automatically by ChurchCRM Scheduled Reports plugin.');

        return compact('subject', 'html', 'text');
    }

    /**
     * Build the membership summary section.
     *
     * @return array{0: string, 1: string} [html, text]
     */
    private function buildMembershipSection(): array
    {
        $totalPeople  = PersonQuery::create()->count();
        $totalFamilies = FamilyQuery::create()->filterByDateDeactivated(null)->count();

        $html = '<h3>' . gettext('Membership Summary') . '</h3>'
            . '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">'
            . '<tr><th>' . gettext('Metric') . '</th><th>' . gettext('Value') . '</th></tr>'
            . '<tr><td>' . gettext('Total People') . '</td><td>' . $totalPeople . '</td></tr>'
            . '<tr><td>' . gettext('Active Families') . '</td><td>' . $totalFamilies . '</td></tr>'
            . '</table>';

        $text = gettext('Membership Summary') . "\n"
            . gettext('Total People') . ': ' . $totalPeople . "\n"
            . gettext('Active Families') . ': ' . $totalFamilies;

        return [$html, $text];
    }

    /**
     * Build the financial summary section.
     *
     * @return array{0: string, 1: string} [html, text]
     */
    private function buildFinancialSection(): array
    {
        // Note: a full financial summary requires the FinancialService which
        // depends on database tables that may not always be available in all
        // deployment configurations. This section intentionally provides a
        // minimal placeholder that third-party plugins or future core code can
        // extend via the REPORT_PRE_GENERATE hook.
        $html = '<h3>' . gettext('Financial Summary') . '</h3>'
            . '<p>' . gettext('For detailed financial reports, visit the Finance section in ChurchCRM.') . '</p>';

        $text = gettext('Financial Summary') . "\n"
            . gettext('For detailed financial reports, visit the Finance section in ChurchCRM.');

        return [$html, $text];
    }

    /**
     * Send the report email via PHPMailer using the system SMTP configuration.
     *
     * @param array{subject: string, html: string, text: string} $report
     *
     * @return bool True on success, false on failure
     */
    private function sendReportEmail(array $report): bool
    {
        if (!SystemConfig::hasValidMailServerSettings()) {
            LoggerUtils::getAppLogger()->warning(
                'Scheduled Reports: Cannot send report — SMTP is not configured'
            );

            return false;
        }

        $mailer = new PHPMailer();
        $mailer->IsSMTP();
        $mailer->CharSet     = 'UTF-8';
        $mailer->Timeout     = max(30, SystemConfig::getIntValue('iSMTPTimeout'));
        $mailer->Host        = SystemConfig::getValue('sSMTPHost');
        $mailer->SMTPAutoTLS = SystemConfig::getBooleanValue('bPHPMailerAutoTLS');
        $mailer->SMTPSecure  = SystemConfig::getValue('sPHPMailerSMTPSecure');
        $mailer->SMTPDebug   = 0;

        if (SystemConfig::getBooleanValue('bSMTPAuth')) {
            $mailer->SMTPAuth = true;
            $mailer->Username = SystemConfig::getValue('sSMTPUser');
            $mailer->Password = SystemConfig::getValue('sSMTPPass');
        }

        $mailer->setFrom(ChurchMetaData::getChurchEmail(), ChurchMetaData::getChurchName());
        $mailer->addAddress($this->getRecipientEmail());
        $mailer->Subject = $report['subject'];
        $mailer->isHTML(true);
        $mailer->Body    = $report['html'];
        $mailer->AltBody = $report['text'];

        return $mailer->send();
    }
}
