# ChurchCRM local Semgrep XSS rule

The local echoed-request.yml is a project-specific replacement for the
Semgrep Registry PHP request-to-echo taint rule
https://semgrep.dev/r/php.lang.security.injection.echoed-request.echoed-request
(reviewed 17 October 2026). The upstream rule's three request sources, echo
sink and other sanitizers are preserved, plus these narrowly reviewed cases:

- InputUtils::escapeHTML() and InputUtils::escapeAttribute() escape via
  htmlspecialchars(stripslashes($s), ENT_QUOTES, 'UTF-8').
- A PHP integer cast prevents markup injection in an echo expression.

InputUtils::jsonEncodeForScript() is **not** an HTML output sanitizer and
remains tainted outside its intended script context. Other routines that only
filter input must not be added casually.

The original registry rule is excluded by exact rule ID, avoiding duplicate
findings, while the full p/security-audit, p/owasp-top-ten and p/php packs
remain active. The local rule has a different ID so exclusion does not
suppress it. When the upstream Semgrep rule changes, compare its sources,
sinks and sanitizers before updating this fork.

Run the regression fixture offline (no PHP web server required):

    semgrep --test .semgrep/

The sibling echoed-request.php intentionally contains unsafe examples;
CI excludes that fixture from normal SARIF scanning, but the dedicated test
step must pass. It verifies that escaped values are clean while raw
GET/POST/REQUEST values, a text-only cleaner and JavaScript-context encoding
still report XSS findings. CI blocks on this fixture test and retains its
existing historical behavior of uploading other Semgrep findings.
