---
title: Volunteer Management v2
intent: Rules to hold while changing ministries, teams, positions, schedules, assignments or volunteer notifications. The full design is docs/design/volunteer-v2.md.
---

# Volunteer Management v2

Full design (domain model, API, screens, tests): [`docs/design/volunteer-v2.md`](../../../docs/design/volunteer-v2.md). Read the section it lists for your change; do not load the whole file.

## Data

- Hierarchy: ministry → team → position; schedules and positions always belong to a team. A ministry always has at least one team.
- A ministry owns exactly one core Group, its volunteer pool (`group_grp.grp_ministry_id`). Pool writes go through `VolunteerPoolWriter` or the coordinator exception in the Group model hooks, never through a link table.
- Qualification is its own person ↔ position relation, separate from Group roles. Qualifying someone adds them to the pool.
- Every occurrence is anchored to a calendar event (`vocc_event_id`). The event owns date and time; V2 generates no dates of its own.
- V2 never reads or writes the V1 tables (`volunteeropportunity_vol`, `person2volunteeropp_p2vo`).
- Schema changes go in `Install.sql`, `orm/schema.xml`, `cypress/data/seed.sql` and the release's `upgrade/*.sql` registered in `upgrade.json`.

## Authorization

- Tiers: Administrator > Global Volunteer Manager (`isManageMinistriesEnabled()`) > Ministry Coordinator (`canManageMinistry()`, Manage My Ministries plus a scope row) > Team Leader (`canManageTeam()`) > Volunteer (own rows only).
- Decide in `VolunteerAuthorizationService`; the admin bypass lives only in `isGlobalManager()`. Do not put authorization in Propel lifecycle hooks.
- Three layers: role middleware (coarse capability), entity middleware per record (`Volunteer*Middleware::postEntityLoad()`), then the handler or service for the specific operation.
- Scope reads are filtered in the query with the managed ministry or team ids; an empty id list returns no rows.
- A team leader reaches their own team only. Ministry-level routes (pool removal, team create/delete, events, scopes) answer `403`. They may add an existing person to their ministry's pool, found by the name-only search.
- Self-service logins (EditSelf-exclusive) can lead a team in the Member Portal. `isVolunteerCoordinatorEnabled()` stays false for them, so the admin shell and the Ministries menu stay closed.
- UI hiding is never the control: every route authorizes the record.

## Notifications

- Email only. Rows go in the outbox (`volunteer_notification_vntf`) inside the transaction that changes state, with an idempotent dedupe key, and the timer job drains them.
- `Reply-To` is resolved at send time, so a coordinator handover is picked up.
- Unfilled-position alerts honour `iVolunteerGapAlertLeadHours` and batch into one daily email per coordinator at `sVolunteerGapAlertBatchTime`.
- `cancelPendingFor()` removes pending rows when an assignment stops being live.
- Demo data and imports record outbox rows as `skipped`; they never send mail.

## UI and tests

- Page logic lives in `webpack/ministries/` entries; the Member Portal team pages reuse `webpack/ministries/components/`.
- Settings live on Admin → Ministry Settings, not `buildCategories()`.
- Specs: `private.volunteer.*.spec.js` (API), `admin.volunteer-v2.*.spec.js` (admin UI), `volunteer-v2.*.spec.js` and portal specs (member UI). Cover the authorization matrix with a negative case for each tier.
