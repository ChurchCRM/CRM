---
title: "Observability, Logging & Metrics"
intent: "Logging in MVC pages and APIs"
tags: ["logging","observability"]
prereqs: ["[[code-standards]]","[[development-workflows]]"]
complexity: "beginner"
---

# Logging

- Log with `LoggerUtils::getAppLogger()`.
- Add context you already hold: user id, route, operation (for example `group.enroll`).
- Keep secrets, donation amounts tied to a person, and raw request bodies out of logs.
- ChurchCRM ships application logs only. Metrics endpoints and alerting stacks start from an issue.

## Tests

- Assert user-visible behavior.
- A log assertion matches a stable message string, not a timestamp.
