---
title: "Observability, Logging & Metrics"
intent: "Guidance for logging in new MVCs and APIs. Do not invent monitoring endpoints."
tags: ["logging","monitoring","metrics","observability"]
prereqs: ["[[code-standards]]","[[development-workflows]]"]
complexity: "intermediate"
---

# Observability, Logging & Metrics

## Current product (do this)

- Use `LoggerUtils::getAppLogger()` for application logs.
- Add useful context when you already have it: user id, route, operation (for example `group.enroll`).
- Do not log secrets, donation amounts tied to a person in debug dumps, or raw request bodies that may contain passwords.

## Not current product (do not add unless an issue asks)

There is **no** Prometheus `/metrics` endpoint, **no** OpenTelemetry pipeline, and **no** first-party alerting stack in ChurchCRM today. Do not implement those as part of an unrelated feature and do not describe them as shipping capabilities.

If a church or host needs metrics, that is a future issue, not a silent add-on.

## Tests

- Prefer asserting user-visible behaviour over asserting log line shape.
- If you add a log assertion, keep it on a stable message string, not a timestamp.
