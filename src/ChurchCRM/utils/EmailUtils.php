<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

class EmailUtils
{
    /**
     * Validate an email address.
     * Optionally checks DNS MX records ($domainCheck) and probes the SMTP server ($verify).
     *
     * @param string $email           The email address to check
     * @param bool   $domainCheck     Whether to verify the domain via DNS
     * @param bool   $verify          Whether to probe the SMTP server to verify the address
     * @param bool   $return_errors   When true, returns the error string on failure or false on success
     * @return bool|string
     */
    public static function checkEmail(string $email, bool $domainCheck = false, bool $verify = false, bool $return_errors = false)
    {
        // Check syntax with regex
        if (preg_match('/^([a-zA-Z0-9\._\+-]+)\@((\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,7}|[0-9]{1,3})(\]?))$/', $email, $matches)) {
            $domain = $matches[2];
            // Check availability of DNS MX records
            if ($domainCheck && function_exists('checkdnsrr')) {
                // Construct array of available mailservers
                if (getmxrr($domain, $mxhosts, $mxweight)) {
                    for ($i = 0; $i < count($mxhosts); $i++) {
                        $mxs[$mxhosts[$i]] = $mxweight[$i];
                    }
                    asort($mxs);
                    $mailers = array_keys($mxs);
                } elseif (checkdnsrr($domain, 'A')) {
                    $mailers[0] = gethostbyname($domain);
                } else {
                    $mailers = [];
                }
                $total = count($mailers);
                // Query each mailserver
                if ($total > 0 && $verify) {
                    // Check if mailers accept mail
                    for ($n = 0; $n < $total; $n++) {
                        $connect_timeout = 2;
                        $errno = 0;
                        $errstr = '';
                        $probe_address = SystemConfig::getValue('plugin.smtp.bccAddress');
                        // Try to open up socket
                        if ($sock = @fsockopen($mailers[$n], 25, $errno, $errstr, $connect_timeout)) {
                            $response = fgets($sock);
                            stream_set_timeout($sock, 5);
                            $meta = stream_get_meta_data($sock);
                            $cmds = [
                                'HELO ' . SystemConfig::getValue('plugin.smtp.host'),
                                "MAIL FROM: <$probe_address>",
                                "RCPT TO: <$email>",
                                'QUIT',
                            ];
                            // Hard error on connect -> break out
                            if (!$meta['timed_out'] && !preg_match('/^2\d\d[ -]/', $response)) {
                                $error = "Error: $mailers[$n] said: $response\n";
                                break;
                            }
                            foreach ($cmds as $cmd) {
                                fwrite($sock, "$cmd\r\n");
                                $response = fgets($sock, 4096);
                                if (!$meta['timed_out'] && preg_match('/^5\d\d[ -]/', $response)) {
                                    $error = "Unverified address: $mailers[$n] said: $response";
                                    break 2;
                                }
                            }
                            fclose($sock);
                            break;
                        } elseif ($n == $total - 1) {
                            $error = "None of the mailservers listed for $domain could be contacted";
                        }
                    }
                } elseif ($total <= 0) {
                    $error = "No usable DNS records found for domain '$domain'";
                }
            }
        } else {
            $error = 'Address syntax not correct';
        }

        if ($return_errors) {
            if (isset($error)) {
                return htmlentities($error);
            }
            return false;
        } else {
            if (isset($error)) {
                return false;
            }
            return true;
        }
    }
}
