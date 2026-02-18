---
title: "Observability, Logging & Metrics"
intent: "Guidance for logging, metrics, and monitoring for new MVCs and APIs"
tags: ["logging","monitoring","metrics","observability"]
prereqs: ["code-standards.md","development-workflows.md"]
complexity: "intermediate"
---

# Observability, Logging & Metrics

Recommendations:
- Use `LoggerUtils::getAppLogger()` for structured logs (JSON output desirable in production).
- Add contextual metadata: user id, request id, route, and operation (e.g., `group.enroll`).
- Emit deprecation and migration metrics when shims are used.

Metrics:
- Track request counts, error rates, latency (p95/p99) per endpoint.
- Expose a `/health` and `/metrics` endpoint (Prometheus compatible) at entry-points.

Tracing:
- Correlate logs with request IDs (generate `X-Request-Id` in middleware).
- Optionally add OpenTelemetry spans around service operations (GroupService, DB calls).

Alerting:
- Alert on sustained increased error rates (>1% for 5m) or slow responses (p99 > 2s).

Testing & rollout:
- Add integration tests asserting logs contain required context fields.
- Validate metrics endpoint on staging before production rollout.
