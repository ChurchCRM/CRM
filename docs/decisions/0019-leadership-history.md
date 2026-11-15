# RFC: Leadership history and terms of office

> **BLUF**
> ChurchCRM can tell you who is in a group today, but it cannot answer "who served as an elder in 2019?", "whose terms end this year?", or "show me this person's service record" — because leadership in ChurchCRM is only a present-tense group role, and removing someone from a group deletes the only trace they were ever in it. Churches, meanwhile, run leadership on **dated terms**: elders, deacons and trustees are elected or appointed to terms (commonly three years, in staggered classes), installed or ordained in recorded ceremonies, sometimes term-limited, and sometimes honoured as emeritus after service. Their clerks publish officer rosters by class in annual reports and answer denominational reports from these records. Mainstream church software does not model this either — with one niche exception, competitors offer group leaders, volunteer positions, or (in Rock RMS) a membership audit trail, but no terms of office. This RFC proposes **first-class leadership term records** — a person, a body, an office, a start, an end, and a status — with roster, rotation and service-record reporting, and leaves the taxonomy and visibility decisions below to the maintainer.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-14
**Owner:** @dawoudio
**Proposal issues:** [#1088](https://github.com/ChurchCRM/CRM/issues/1088) (History of Church leadership)
**Numbering:** `0019` is provisional. A maintainer assigns the final number on merge.

## What "leadership" means in a church

Research for this RFC (14 Nov 2026; sources at the end) found a consistent shape across traditions, with polity deciding the details:

- **Offices, not just roles.** The recurring offices are pastor/minister, elder, deacon, trustee, clerk, treasurer, and committee or board chairs. Some are ordained offices (elder, deacon in Presbyterian practice), some are governance offices (trustee, board member), some are staff appointments (pastors), and churches combine them differently.
- **Service runs in terms.** A typical Presbyterian congregation elects elders and deacons to **three-year terms arranged in three staggered classes**, one class elected each year, with a common limit of two consecutive terms followed by a mandatory year off before re-election ([Faith Presbyterian policies](https://account-media.s3.amazonaws.com/27946/uploaded/c/0e20502737_1760617902_chapter-iv-organization-and-structure-my-draft-616docx.pdf); [OPC Form of Government ch. XXV](https://www.opc.org/os.html?article_id=958)). Trustees often serve longer staggered terms (five years in one Baptist example, one elected each year — [Seventh Day Baptist polity](https://germanseventhdaybaptist.com/polity/)). Elder-led congregational churches show the same pattern without the denominational rulebook: three-year elder terms, roughly a third elected annually, two consecutive terms then a year off, while deacons may be appointed with no term limit but an annual reaffirmation ([Redemption Hill Church bylaws](https://www.redemptionhc.com/copy-of-what-we-believe)). Pastors typically serve indefinitely under "terms of call," and vacancies are filled by appointment for partial terms that may or may not count against limits.
- **Ordination is distinct from a term.** In Presbyterian polity an ordained elder remains an elder for life; a *term* is a period of active service on the session, and an officer not re-elected is not "divested of the office" ([OPC FG XXV](https://www.opc.org/os.html?article_id=958)). Ordination and installation dates are therefore facts churches record in their own right, and **elder emeritus** is a standing honour churches print in their bulletins next to the active session ([Bethany ARP bulletin](https://5mt.bethanyarp.org/wp-content/uploads/2025/12/22080953/BethanyWorship122125.pdf)).
- **Polity changes who appoints, not whether records are kept.** Elder-led churches vest government in the elder board; congregational churches elect officers and boards at the annual meeting; episcopal/connectional churches receive pastors by appointment (itinerancy) while still electing local officers. All three keep officer records — annual reports publish leadership rosters by class and name (e.g. "Ruling Elder, Class of 2028 (three-year term)" in [Hill U.P. Church's 2025 annual report](https://www.hillup.org/s/ANNUAL-REPORT-2025-final.pdf)), and clerks answer denominational statistical reports, quorum counts and officer-eligibility lists from them.

The questions churches actually ask of this history:

1. Who held office X, or sat on body Y, in year Z? (institutional memory, anniversaries, denominational reports)
2. Whose terms end this year — who rotates off, who is eligible or required to rest? (nominating season)
3. What is this person's full record of service across every office and body? (recognition, emeritus decisions, obituaries)
4. Who is on the board *right now*, by class? (the annual-report roster)
5. When was this person ordained or installed? (certificates, ordination anniversaries)

## What other church software does

| Product | How leadership is represented | Dated terms / history? |
| --- | --- | --- |
| **Planning Center** | People profiles, custom fields, lists, workflows; Groups have leaders. No office concept. Churches hand-roll it: one CRC tutorial has churches add an "Elder" dropdown custom field listing the current elders' names. | **No.** A custom field is a snapshot — overwrite it and last year's board is gone. ([Custom fields help](https://pcopeople.zendesk.com/hc/en-us/articles/204263134-Customize-Fields); [CRC tutorial](https://network.crcna.org/topic/church-administration/church-communications/church-data-storage-tutorial-crc-churches)) |
| **Rock RMS** | Groups with member roles and status (Active/Inactive/Pending). Opt-in **Group History** per group type: groups are archived instead of deleted, with a per-day timeline of member added/removed events and a per-person involvement view. | **Closest mainstream answer — but it is an audit trail, not terms.** It can show the dates a person was in a group; it has no term length, class, limit, ordination or emeritus concepts. ([Rock Your Groups manual](https://rockrms.blob.core.windows.net/documentation/PDFs/2439b8a0b96c405792954cd5f35a527f_RockYourGroups.pdf)) |
| **Pushpay ChMS (Church Community Builder)** | A fixed group-leadership hierarchy — Department Admin > Director > Coach > Group Leader > Assistant Leader — mainly as permission levels. Custom leader roles are not supported; support suggests profile notes or custom fields for anything more specific. | **No.** Present-tense permissions on groups only. ([Group leadership roles](https://support.pushpay.com/s/article/Learning-about-Group-Leadership); [Groups FAQ](https://support.pushpay.com/s/article/FAQGroups)) |
| **Breeze** | "Roles" are account-permission roles for users. People are organised with profile fields and tags; volunteers are scheduled per event. | **No** office or term concept. ([Users & Roles](https://support.breezechms.com/hc/en-us/articles/360036061333-Getting-Started-With-Users-Roles)) |
| **Elvanto** | Groups with leaders, assistant leaders and custom positions per group category; Departments/Sub-Departments/Positions exist for volunteer rostering. | **No.** Positions are scheduling slots, all present-tense. ([Groups overview](https://help.elvanto.com/hc/en-us/articles/7481435328791); [Glossary](https://help.elvanto.com/hc/en-us/articles/7482377567767-Elvanto-Glossary)) |
| **Realm (ACS Technologies)** | Groups and serving teams with leaders; volunteer roles and teams for scheduling; group leaders get management tools for their group. | **No** term or history concept in its product material. ([Realm products](https://www.acstechnologies.com/realm/category/products/page/2/)) |
| **Tithe.ly / ChurchTrac** | Tithe.ly's ChMS line is built on Breeze and Elvanto (same pattern). ChurchTrac: Teams & Roles are group types for scheduling, with a Group Leader toggle that grants permissions. | **No.** ([ChurchTrac Teams & Roles](https://www.churchtrac.com/support/worship/using-teams)) |
| **TendFlock** (Presbyterian-focused niche) | Markets **officer term tracking** directly: officer terms & rotation, ordination/installation dates, officer-eligibility lists, quorum reports, annual denomination reports. | **Yes — the exception that proves the gap.** A denomination-shaped niche tool treats officer terms as a headline feature; the generalist market ignores it. ([Features](https://sharon.tendflock.app/features)) |

**Finding:** absence is the norm. No mainstream ChMS models a term of office. Churches that need history keep it in minutes, annual reports and spreadsheets — exactly the records #1088 asks ChurchCRM to hold ("records of its past leadership too, not just having members records").

## Problem

- **Group membership is present-tense and destructive.** ChurchCRM group membership lives in `person2group2role_p2g2r` — person, group, role, nothing else. There is no join or leave date, and removing a member deletes the row outright. Who led, and when, is unanswerable for every group in the system.
- **The gap is already felt elsewhere.** [#11477](https://github.com/ChurchCRM/CRM/issues/11477) (Record group membership start dates) was split out of an attendance-reporting issue because "when did this person join this group?" is unanswerable today. Leadership terms are the sharper version of the same hole, with extra semantics (classes, limits, ordination, emeritus) that a bare join date cannot carry.
- **Custom fields and properties cannot carry this.** A person's leadership history is a *series* of dated records across bodies, not a value on the person. RFC 0017 reached the parallel conclusion for lifecycle events: register-shaped data needs register-shaped records, countable and reportable across people and time.
- **Ordination already touches RFC 0017.** RFC 0017 lists ordination among the milestone events a lifecycle register might record. An ordination *event* (date, place, officiants) and a leadership *term* (office, body, start, end) are different facts that share a date; whichever lands second must not create a second source of truth for it.

## Proposal — recommended model

**A first-class leadership term record**, one row per person per term of service:

| Field | Notes |
| --- | --- |
| Person | The office-holder (a normal person record; past leaders remain people in the system) |
| Body | The board/council the term belongs to — Session, Board of Deacons, Trustees, a committee. Governed seed list + church-defined bodies; a body *may* link to a ChurchCRM group when the church also runs that body as a group |
| Office / role | Elder, Deacon, Trustee, Clerk, Treasurer, Chair, Pastor… Governed seed list + church-defined offices (see Q1) |
| Term start | Date service began (installation date where the church distinguishes it) |
| Term end | Nullable — null means currently serving |
| Status | Active, Completed, Resigned, Emeritus, Deceased, etc. Emeritus is a status a term (or the person-office pairing) can carry, not a separate office |
| Ordination / installation date | Recorded where the tradition uses it; interplay with RFC 0017 in Q4 |
| Note | Free text (elected vs appointed, partial term filling a vacancy, class year) |

Why this shape, from the research:

- **Terms are the unit churches think in.** Every question in the research — rotation, eligibility, rosters by class, service records — is a query over (person, body, office, start, end). A term record answers all five directly; group membership with a join date answers none of them cleanly once a person rotates off, rests a year, and returns.
- **It does not force groups to become something they are not.** Bodies often *are* groups in ChurchCRM (a Session group with an Elder role), and a linked body can pre-fill or cross-check against group membership — but term history must survive group renames, restructures and deletions, so the term record is the source of truth and the group link is optional garnish.
- **It is independent of #11477.** Group membership start dates serve attendance denominators and general group history; leadership terms serve office history. They should share date-handling conventions (and the #11449 annual-date core where observances matter), but neither blocks the other.

**Surfaces (for the architecture doc to detail):**

- A **Leadership** section on the person view: their service record, current offices first.
- A **per-body roster**: current members by class/term-end, past members by period.
- **Reports:** terms ending in a chosen period (rotation-due list for nominating season); office-holders in a chosen year; a printable current-officers roster for the annual report.
- Term-limit and eligibility views are **advisory only** — the system lists who has served N consecutive terms; it does not block recording another, because limits, rest years and partial-term rules vary by polity (see Q3).

## Goals

- Dated leadership term records (person, body, office, start, end, status) that survive group changes and person-record edits.
- The five research questions answerable from the UI and reports: historical office-holders, rotation-due, personal service record, current roster by class, ordination/installation dates.
- Denominational flexibility without code changes: bodies, offices and statuses bend to Presbyterian, elder-led, congregational and episcopal practice, including indefinite pastoral terms and appointed (itinerant) pastors.
- Clean interplay with groups (#11477 spans), RFC 0017 (ordination events) and #11449 (shared annual-date logic) — no second sources of truth.

## Non-goals

- **No detailed schema or UI design in this RFC.** The model above is a recommendation with its reasoning; tables, fields and screens are decided in the post-approval architecture doc, per the RFC process.
- **Not a volunteer/serving tracker.** Sunday serving rotas, group leadership of small groups, and ministry-team positions stay in Groups and the volunteer subsystem; this RFC covers offices and governance bodies. (A church *may* record a ministry office as a term if it wishes — the model does not forbid it — but scheduling is out of scope.)
- **Not a term-limit enforcer.** No hard blocks on appointments or elections; polity rules are advisory reporting.
- **Not an election/nominations workflow.** Nominating committees and ballots are process tooling (see the process-engine discussion on #348); this RFC is the record those processes would write to.

## Open questions (maintainer decisions)

1. **Role taxonomy: governed or free-form?** A shipped seed list (Elder, Deacon, Trustee, Clerk, Treasurer, Chair, Pastor) plus church-defined additions reports consistently and translates; a fully free-form list bends to every tradition but fragments reporting and i18n. Bodies have the same question. Recommendation from the research: governed seeds + church-defined additions, mirroring how the class question was settled elsewhere in the codebase.
2. **Bodies as groups?** Should a body be a ChurchCRM group (reusing membership UI, roles and permissions), a separate list that may optionally link to a group, or purely its own thing? The research favours the separate list with an optional group link — term history must outlive group restructuring — but the maintainer may prefer to build on groups plus #11477's spans and accept the coupling.
3. **Term rules: recorded, computed, or both?** Does a term record store the class (e.g. "Class of 2028") and expected end, or is the end date simply entered when service ends? Storing expected end + class powers the rotation-due report; it also asks churches to maintain data some will let rot. Are consecutive-term counts and rest-year eligibility computed views, or not v1 at all?
4. **Ordination: term record, lifecycle event, or both?** RFC 0017 may land an ordination register entry first. Decide the source of truth for the ordination/installation date — the term record references the lifecycle event, the lifecycle event references the term, or one of them owns it outright.
5. **Visibility and RBAC.** Leadership history is less sensitive than giving, but it is not automatically public: a person's past offices are directory-grade facts in most churches, while notes on a resignation may not be. Who may view terms — anyone who can view the person? Who may edit — admins only, or also a clerk role? Does any of it belong in the Member Portal or the public directory (e.g. a "current officers" page), following the staff-facing-only precedent #11449 set for death anniversaries?
6. **Pastors and staff.** Terms of call are indefinite and episcopal churches receive pastors by appointment — is a pastor's tenure a term record with a null end, or staff data that stays out of this system? Including it answers "who was pastor in 2010?" — the literal first example in #1088 ("history of ministers").
7. **Backfill.** Churches hold decades of officer history in minutes and annual reports. Is a bulk-entry or import path (even a simple per-body year-by-year roster entry screen) in scope for v1, or is manual entry acceptable?
8. **Core feature or core plugin?** Officer terms are near-universal in *some* form, like the RFC 0017 register — but the depth (classes, limits, denominational reports) is polity-specific in a way that argues for a core plugin. This RFC takes no side; as with RFC 0017, the decision shapes everything downstream.

## Linked issues

Proposal and discussion: [#1088](https://github.com/ChurchCRM/CRM/issues/1088) — "can you guys add history of ministers on the system. some churches will want to have records of its past leadership too, not just having members records." This RFC is the research-and-design response to that ask; #1088 stays open as the tracking issue, and the data-model decision (attached entries vs separate record vs group spans) now lives here.

Related: [#11477](https://github.com/ChurchCRM/CRM/issues/11477) (group membership start dates — the general-purpose span this RFC deliberately does not build on); [RFC 0017](0017-lifecycle-tracking.md) (member lifecycle register — ordination interplay, Q4); [Epic #11449](https://github.com/ChurchCRM/CRM/issues/11449) (annual-date centralization — shared date logic for any observance surfaces).

## Sources

Church practice: [Faith Presbyterian Church policies (PCUSA Book of Order terms/classes)](https://account-media.s3.amazonaws.com/27946/uploaded/c/0e20502737_1760617902_chapter-iv-organization-and-structure-my-draft-616docx.pdf) · [OPC Form of Government ch. XXV, via Ordained Servant](https://www.opc.org/os.html?article_id=958) · [Redemption Hill Church constitution & bylaws](https://www.redemptionhc.com/copy-of-what-we-believe) · [Seventh Day Baptist articles of practice](https://germanseventhdaybaptist.com/polity/) · [Bethany ARP worship bulletin (elder emeritus, classes of deacons)](https://5mt.bethanyarp.org/wp-content/uploads/2025/12/22080953/BethanyWorship122125.pdf) · [Hill U.P. Church 2025 annual report (officer classes, leadership roster)](https://www.hillup.org/s/ANNUAL-REPORT-2025-final.pdf)

Competitors: [Planning Center custom fields](https://pcopeople.zendesk.com/hc/en-us/articles/204263134-Customize-Fields) · [CRC Planning Center tutorial](https://network.crcna.org/topic/church-administration/church-communications/church-data-storage-tutorial-crc-churches) · [Rock RMS — Rock Your Groups manual (Group History)](https://rockrms.blob.core.windows.net/documentation/PDFs/2439b8a0b96c405792954cd5f35a527f_RockYourGroups.pdf) · [Pushpay group leadership roles](https://support.pushpay.com/s/article/Learning-about-Group-Leadership) · [Pushpay Groups FAQ](https://support.pushpay.com/s/article/FAQGroups) · [Breeze Users & Roles](https://support.breezechms.com/hc/en-us/articles/360036061333-Getting-Started-With-Users-Roles) · [Elvanto Groups overview](https://help.elvanto.com/hc/en-us/articles/7481435328791) · [Elvanto Glossary](https://help.elvanto.com/hc/en-us/articles/7482377567767-Elvanto-Glossary) · [Realm by ACS Technologies](https://www.acstechnologies.com/realm/category/products/page/2/) · [ChurchTrac Teams & Roles](https://www.churchtrac.com/support/worship/using-teams) · [TendFlock features (officer term tracking)](https://sharon.tendflock.app/features)

All sources accessed 2026-11-14.
