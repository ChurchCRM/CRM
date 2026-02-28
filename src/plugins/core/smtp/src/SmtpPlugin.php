<?php

namespace ChurchCRM\Plugins\Smtp;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Plugin\AbstractPlugin;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP Email Plugin.
 *
 * Manages SMTP server configuration for outgoing email.
 * Supports Gmail, Outlook / Microsoft 365, and custom SMTP servers.
 * The "Test Connection" button triggers testWithSettings(), which
 * sends a debug test email and returns captured PHPMailer output.
 */
class SmtpPlugin extends AbstractPlugin
{
    private static ?SmtpPlugin $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?SmtpPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'smtp';
    }

    public function getName(): string
    {
        return 'SMTP Email';
    }

    public function getDescription(): string
    {
        return 'Send emails via an SMTP server.';
    }

    public function boot(): void
    {
        // Nothing to initialise at boot time
    }

    public function isConfigured(): bool
    {
        return !empty($this->getConfigValue('host'));
    }

    public function getConfigurationError(): ?string
    {
        if (empty($this->getConfigValue('host'))) {
            return gettext('SMTP Host is required');
        }

        if ($this->getBooleanConfigValue('auth')) {
            if (empty($this->getConfigValue('username'))) {
                return gettext('SMTP Username is required when authentication is enabled');
            }
            if (empty($this->getConfigValue('password'))) {
                return gettext('SMTP Password is required when authentication is enabled');
            }
        }

        return null;
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key'      => 'host',
                'label'    => gettext('SMTP Host'),
                'type'     => 'text',
                'required' => true,
                'help'     => gettext('SMTP server hostname (e.g. smtp.gmail.com)'),
            ],
            [
                'key'      => 'port',
                'label'    => gettext('SMTP Port'),
                'type'     => 'text',
                'required' => false,
                'help'     => gettext('Port number: 25 (plain), 465 (SSL/TLS), or 587 (STARTTLS)'),
            ],
            [
                'key'          => 'smtpSecure',
                'label'        => gettext('Encryption'),
                'type'         => 'select',
                'required'     => false,
                'help'         => gettext('Encryption method for connecting to the SMTP server'),
                'options'      => ['', 'tls', 'ssl'],
                'optionLabels' => [gettext('None'), gettext('TLS (STARTTLS) - Port 587'), gettext('SSL/TLS - Port 465')],
            ],
            [
                'key'      => 'autoTLS',
                'label'    => gettext('Auto TLS'),
                'type'     => 'boolean',
                'required' => false,
                'help'     => gettext('Automatically upgrade to TLS encryption if the server offers it'),
            ],
            [
                'key'      => 'auth',
                'label'    => gettext('Require Authentication'),
                'type'     => 'boolean',
                'required' => false,
                'help'     => gettext('Enable if your SMTP server requires a username and password'),
            ],
            [
                'key'      => 'username',
                'label'    => gettext('SMTP Username'),
                'type'     => 'text',
                'required' => false,
                'help'     => gettext('Your SMTP account username or email address'),
            ],
            [
                'key'      => 'password',
                'label'    => gettext('SMTP Password'),
                'type'     => 'password',
                'required' => false,
                'help'     => gettext('Your SMTP account password or app-specific password'),
            ],
            [
                'key'      => 'timeout',
                'label'    => gettext('Timeout (seconds)'),
                'type'     => 'text',
                'required' => false,
                'help'     => gettext('Connection timeout in seconds (default: 10)'),
            ],
        ];
    }

    /**
     * Return SMTP configuration as an array.
     *
     * Used by BaseEmail to configure PHPMailer without requiring the plugin
     * to be loaded — callers may also read plugin.smtp.* keys from SystemConfig
     * directly.
     *
     * @return array{host: string, port: int, auth: bool, username: string, password: string, smtpSecure: string, autoTLS: bool, timeout: int}
     */
    public function getSmtpConfig(): array
    {
        return [
            'host'       => $this->getConfigValue('host'),
            'port'       => (int) ($this->getConfigValue('port') ?: 25),
            'auth'       => $this->getBooleanConfigValue('auth'),
            'username'   => $this->getConfigValue('username'),
            'password'   => $this->getConfigValue('password'),
            'smtpSecure' => trim($this->getConfigValue('smtpSecure')),
            'autoTLS'    => $this->getBooleanConfigValue('autoTLS'),
            'timeout'    => (int) ($this->getConfigValue('timeout') ?: 10),
        ];
    }

    // =========================================================================
    // Connection Testing
    // =========================================================================

    /**
     * Test the SMTP connection using the settings supplied in the request body.
     *
     * Sends a real test email to the church address and captures PHPMailer debug
     * output (level 3) so the admin can see exactly what happened.
     *
     * The `password` field may be absent when testing already-saved settings
     * (the form never re-populates password fields); in that case the saved
     * password is used as a fallback.
     *
     * Called by POST /plugins/api/plugins/smtp/test.
     *
     * @param array $settings Keys: host, port, smtpSecure, autoTLS, auth, username, password, timeout
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function testWithSettings(array $settings): array
    {
        $host       = trim($settings['host'] ?? '');
        $port       = (int) ($settings['port'] ?? 25);
        $smtpSecure = trim($settings['smtpSecure'] ?? '');
        $autoTLS    = ($settings['autoTLS'] ?? '0') === '1';
        $auth       = ($settings['auth'] ?? '0') === '1';
        $username   = $settings['username'] ?? '';
        $password   = $settings['password'] ?? '';
        $timeout    = (int) ($settings['timeout'] ?: 10);

        // Fall back to the saved password when the form omits it
        if (empty($password)) {
            $password = $this->getConfigValue('password');
        }

        if (empty($host)) {
            return ['success' => false, 'message' => gettext('SMTP Host is required')];
        }

        $churchEmail = ChurchMetaData::getChurchEmail();
        if (empty($churchEmail)) {
            return [
                'success' => false,
                'message' => gettext('Church Email is not configured. Please set it in Church Information settings.'),
            ];
        }

        $mail             = new PHPMailer();
        $mail->IsSMTP();
        $mail->CharSet    = 'UTF-8';
        $mail->Host       = $host;
        $mail->Port       = $port ?: 25;
        $mail->Timeout    = $timeout;
        $mail->SMTPAutoTLS = $autoTLS;
        $mail->SMTPSecure = $smtpSecure;

        if ($auth) {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }

        // Capture PHPMailer debug output
        $mail->SMTPDebug  = 3;
        $debugOutput      = '';
        $mail->Debugoutput = function (string $str, int $level) use (&$debugOutput): void {
            $debugOutput .= $str . "\n";
        };

        $mail->setFrom($churchEmail, ChurchMetaData::getChurchName());
        $mail->addAddress($churchEmail);
        $mail->Subject = 'ChurchCRM SMTP Test';
        $mail->Body    = gettext('This is a test email sent from ChurchCRM to verify your SMTP settings.');

        try {
            $result = $mail->send();

            if ($result) {
                $this->log('SMTP test email sent successfully', 'info', ['host' => $host]);

                return [
                    'success' => true,
                    'message' => gettext('Test email sent successfully! Check your inbox.'),
                    'details' => ['debug' => $debugOutput],
                ];
            }

            return [
                'success' => false,
                'message' => gettext('Failed to send test email: ') . $mail->ErrorInfo,
                'details' => ['debug' => $debugOutput],
            ];
        } catch (\Exception $e) {
            $this->log('SMTP test failed: ' . $e->getMessage(), 'error', ['host' => $host]);

            return [
                'success' => false,
                'message' => gettext('SMTP error: ') . $e->getMessage(),
                'details' => ['debug' => $debugOutput],
            ];
        }
    }
}
