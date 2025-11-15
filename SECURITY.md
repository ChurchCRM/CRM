# Security Policy

## Reporting a Vulnerability

At ChurchCRM, we take the security of our software seriously. If you discover any security issues, we appreciate your cooperation in responsibly disclosing the information to us.

**Please do not open a public issue.** Instead, use GitHub's [Security Advisory](https://github.com/ChurchCRM/CRM/security/advisories) feature to privately report vulnerabilities.

When reporting, include:
- Description of the vulnerability
- Steps to reproduce the vulnerability
- Affected versions
- Any relevant environment and configuration information

We will acknowledge receipt within 48 hours and provide updates on the fix timeline.

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
| 5.3 +       | :white_check_mark: | >=8.1      |
| 5.0 - 5.2.x | :x:                | 8.1        |
| 4.0.x       | :x:                | 7.2.x 7.3.x |
| 3.0.x       | :x:                | 7.x        |
| 2.0.x       | :x:                | 5.6 7.0 7.1 |

## Developer Security

For developers contributing to ChurchCRM, see the [Developer Security Guide](https://github.com/ChurchCRM/CRM/wiki/Developer-Security) which covers security best practices including Content Security Policy (CSP) compliance.
