# Security Policy

## Reporting a Vulnerability vs. Filing a Bug

### When to Use a Security Advisory (Private Disclosure)

Use GitHub's [Security Advisory](https://github.com/ChurchCRM/CRM/security/advisories) feature for issues that could be exploited by attackers, including:

- **Cross-Site Scripting (XSS)** – Injection of malicious scripts
- **SQL Injection** – Unauthorized database access
- **Authentication/Authorization Bypass** – Accessing data without proper permissions
- **Session Hijacking** – Stealing or manipulating user sessions
- **Remote Code Execution** – Running arbitrary code on the server
- **Sensitive Data Exposure** – Leaking passwords, personal information, or financial data
- **Cross-Site Request Forgery (CSRF)** – Forcing users to perform unintended actions

**Please do not open a public issue for security vulnerabilities.** Public disclosure gives attackers time to exploit the issue before a fix is available.

When reporting a security vulnerability, include:
- Description of the vulnerability and its potential impact
- Steps to reproduce the vulnerability
- Affected versions
- Any relevant environment and configuration information
- Proof of concept (if available)

We will acknowledge receipt within 1 week and provide updates on the fix timeline.

### When to File a Regular Bug Report

Use [GitHub Issues](https://github.com/ChurchCRM/CRM/issues) for general bugs that do not pose a security risk, such as:

- UI/display issues or broken layouts
- Features not working as expected
- Error messages or crashes (without security implications)
- Performance problems
- Documentation errors
- Installation or upgrade issues

If you're unsure whether an issue is security-related, err on the side of caution and use the Security Advisory.

## Scope

Please note that the following activities are considered within the scope of our responsible disclosure process:

- Reporting security vulnerabilities directly to us via GitHub Security Advisory
- Providing details necessary for us to reproduce and validate the vulnerability

## Security Best Practices

ChurchCRM should **only run on HTTPS connections**. If you don't have an SSL certificate, [Let's Encrypt](https://letsencrypt.org/) provides free certificates.

## No Security Testing on Demo Sites

For security and stability reasons, we kindly request that you do not perform any security testing on the demo sites provided by ChurchCRM. The demo sites are for showcasing purposes only, and any attempts to identify or exploit security vulnerabilities on these sites may lead to unintended disruptions.

If you are interested in security testing or assessments, please focus your efforts on your local development environments or any instances you have set up for testing purposes.

Thank you for your understanding and cooperation in making ChurchCRM a more secure platform.

## Supported Versions

| Version     | Supported          | PHP Version |
|-------------| ------------------ |-------------|
| 6.0 +       | :white_check_mark: | >=8.2      |
| 5.3 +       | :x:                | >=8.1      |
| 5.0 - 5.2.x | :x:                | 8.1        |
| 4.0.x       | :x:                | 7.2.x 7.3.x |
| 3.0.x       | :x:                | 7.x        |
| 2.0.x       | :x:                | 5.6 7.0 7.1 |

## Developer Security

For developers contributing to ChurchCRM, see the [Developer Security Guide](https://github.com/ChurchCRM/CRM/wiki/Developer-Security) which covers security best practices including Content Security Policy (CSP) compliance.
