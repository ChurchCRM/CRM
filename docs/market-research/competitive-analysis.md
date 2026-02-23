# Church CRM Competitive Analysis

**Audience:** Developer, Product Owner, Contributor  
**Last Updated:** 2026-02-23  
**Related:** [ChurchCRM Wiki](https://github.com/ChurchCRM/CRM/wiki), [ChurchCRM Homepage](https://churchcrm.io)

## Overview

This document surveys the top commercially marketed Church Management Software (ChMS) systems, catalogues their headline features, and maps each feature against ChurchCRM's current capabilities. The goal is to identify feature parity, gaps, and opportunities for the open-source project.

---

## Table of Contents

- [Commercial Systems Reviewed](#commercial-systems-reviewed)
- [Top Marketed Features by Category](#top-marketed-features-by-category)
- [Feature Comparison Matrix](#feature-comparison-matrix)
- [Detailed Gap Analysis](#detailed-gap-analysis)
- [ChurchCRM Differentiators](#churchcrm-differentiators)
- [Recommended Priorities](#recommended-priorities)
- [Sources & Methodology](#sources--methodology)

---

## Commercial Systems Reviewed

The following well-known, actively marketed commercial Church Management Software platforms were evaluated. Each is used by hundreds or thousands of congregations.

| System | Vendor | Pricing Model | Market Segment |
|--------|--------|---------------|----------------|
| **Planning Center** | Planning Center Online | Freemium / per-module SaaS | Small–Large |
| **Breeze ChMS** | Breeze | Flat-rate SaaS (~$72/mo) | Small–Medium |
| **Church Community Builder (CCB)** | Pushpay | SaaS subscription | Medium–Large |
| **FellowshipOne / F1 Go** | Ministry Brands | SaaS subscription | Medium–Large |
| **Servant Keeper** | Servant Systems | One-time + subscription | Small–Medium |
| **Elvanto / Churchdesk** | Churchdesk | SaaS subscription | Small–Medium |
| **Rock RMS** | Spark Development Network | Open-source (paid support) | Medium–Large |
| **ChurchSuite** | ChurchSuite Ltd | SaaS subscription (UK/EU) | Small–Medium |
| **Ministry Platform** | Ministry Platform | Enterprise SaaS | Large / Multi-site |
| **Subsplash** | Subsplash | SaaS + mobile app platform | All sizes |

---

## Top Marketed Features by Category

The following features appear most prominently on commercial vendors' marketing sites, pricing pages, and comparison guides.

### 1. People & Household Management
The universal foundation of every ChMS. Vendors compete on depth and flexibility.

**What they market:**
- Unified person/household profiles with photos
- Relationship tracking (spouse, child, extended family)
- Custom fields and custom person attributes
- Member status lifecycle (visitor → attendee → member)
- Household mailing lists and bulk updates
- Duplicate detection and data merging
- Profile self-service (members update own info)
- Search, filters, and saved lists/segments

### 2. Online Giving & Donation Management
The single biggest revenue driver for vendors and their customers.

**What they market:**
- Hosted online giving pages (mobile-optimised)
- Recurring / automatic giving (weekly, monthly)
- Text-to-Give / SMS giving
- ACH bank drafts and credit/debit card processing
- Giving by fund / designated gifts
- Pledge campaigns and pledge tracking
- Year-end giving statements / tax receipts (automated)
- Donor management and giving history
- Offline cash/check recording
- Giving kiosks and in-person terminals
- Integration with accounting software (QuickBooks, Xero)
- Donor portal for members to view history and manage recurring gifts

### 3. Check-In & Children's Ministry Security
A high-stakes feature with strong differentiation pressure.

**What they market:**
- Self-service kiosk check-in (touchscreen)
- Volunteer-assisted check-in
- Security label printing (child + parent match tag)
- Allergy / medical notes on check-in label
- Volunteer background-check integration
- Class capacity management
- Attendance auto-populated from check-in
- Mobile check-in via phone/tablet
- Visitor pre-registration and first-time family flow

### 4. Groups & Small Groups
Discipleship and community are a core marketing differentiator.

**What they market:**
- Group directory (member-facing)
- Attendance tracking per meeting
- Group-level communication (email, SMS)
- Leader tools (leaders manage own group)
- Group finder / public group search
- Waitlists and group capacity limits
- Sub-groups and group hierarchies
- Life stage / interest tagging

### 5. Communication & Messaging
Multi-channel communication is a major selling point.

**What they market:**
- Mass email (HTML templates, list segments)
- Two-way SMS / text messaging
- Automated follow-up workflows (e.g., first-time visitor path)
- Push notifications via mobile app
- In-app messaging / member portal messages
- Scheduled sends
- Open/click tracking and analytics
- Unsubscribe and GDPR/CAN-SPAM compliance

### 6. Event Management & Registration
**What they market:**
- Public event pages with online registration
- Online payment / ticketing for events
- Capacity and waitlist management
- Registration forms with custom questions
- QR code / check-in for registrants
- Recurring events
- Calendar integrations (Google Calendar, iCal)
- Volunteer sign-up for event roles

### 7. Attendance Tracking
**What they market:**
- Service/worship attendance (quick entry)
- Head-count vs. individual tracking
- Trend reports and charts
- Lapsed attender alerts (hasn't attended in X weeks)
- Multiple service/campus tracking

### 8. Volunteer Management
**What they market:**
- Volunteer position/role catalogue
- Schedule builder and conflict detection
- Volunteer self-scheduling / swaps
- Automated reminder emails/texts
- Volunteer hours logging
- Background check integration (Checkr, etc.)
- Serving history reports

### 9. Reporting & Analytics
**What they market:**
- Pre-built dashboards (giving, attendance, growth)
- Custom report builder (drag-and-drop)
- Scheduled report delivery (email PDF/CSV)
- Year-over-year comparisons
- Data export (CSV, Excel)
- API access for BI tools

### 10. Mobile App (Member-Facing)
A major modern differentiator—many vendors lead with this.

**What they market:**
- Branded iOS + Android app
- Online giving in-app
- Member directory
- Events calendar and registration
- Group communication
- Push notifications
- Sermon / media library
- Church map / campus info

### 11. Workflows & Automation
Increasingly marketed as "discipleship automation."

**What they market:**
- Automated follow-up sequences (first-time visitor, new member)
- Trigger-based workflows (attended event → send email)
- Task assignment and reminders for staff
- Process checklists (e.g., new member onboarding steps)
- Integration with external services via webhooks

### 12. Service / Worship Planning
Primarily a Planning Center differentiator but widely copied.

**What they market:**
- Team and volunteer scheduling for Sunday services
- Integrated song/chord library
- Service order / run-of-show builder
- Media file sharing with worship team
- Rehearsal audio / video attachments
- Sync with ProPresenter, OpenLP, EasyWorship

### 13. Multi-site / Multi-campus Support
Key for growing churches with multiple locations.

**What they market:**
- Shared central database with campus-level segmentation
- Campus-specific attendance and giving
- Consolidated cross-campus reporting
- Campus administrator roles
- Transfer members between campuses

### 14. Integrations & API
**What they market:**
- REST API with developer documentation
- Webhooks for real-time events
- Accounting integrations (QuickBooks, ACS, Aplos)
- Email marketing integrations (Mailchimp, Constant Contact)
- Streaming platform integrations (YouTube, Vimeo)
- App store / marketplace of third-party integrations

### 15. Security & Privacy
**What they market:**
- Role-based access control (RBAC)
- GDPR and CCPA compliance tools
- Data encryption at rest and in transit
- Two-factor authentication (2FA)
- Audit logs / activity history
- Soft-delete and data retention policies
- Background check integrations

---

## Feature Comparison Matrix

Legend:  
✅ **Fully supported** — core feature present and functional  
⚠️ **Partial** — basic version present; significant gaps vs. commercial standard  
❌ **Not present** — feature not available in current release  
🔌 **Plugin** — available via an optional plugin (may need configuration)

| Feature | Planning Center | Breeze | CCB | FellowshipOne | Servant Keeper | Rock RMS | **ChurchCRM** |
|---------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| **People & Household Management** | | | | | | | |
| Person / household profiles | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Custom fields / attributes | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Member lifecycle / status | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Relationship tracking | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ⚠️ |
| Duplicate detection / merge | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ❌ |
| Member self-service profile update | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ⚠️ |
| People photo/directory | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gravatar / profile photos | ⚠️ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔌 |
| **Giving & Donations** | | | | | | | |
| Record offline cash/check gifts | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Multiple giving funds | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pledge campaigns & tracking | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Giving envelopes | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ⚠️ | ✅ |
| Year-end tax statements | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Deposit management | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Online giving portal** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **❌** |
| **Recurring / automatic giving** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **❌** |
| **Text-to-Give / SMS giving** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Accounting software integration** | ✅ | ⚠️ | ✅ | ✅ | ✅ | ✅ | **❌** |
| Donor self-service portal | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Check-In** | | | | | | | |
| Kiosk / self check-in | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| Event attendance check-in | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Security label printing (children)** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **❌** |
| **Allergy / medical notes on label** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **❌** |
| Mobile check-in | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ⚠️ |
| **Groups** | | | | | | | |
| Group management | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Group attendance tracking | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Group roles (leader, member, etc.) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Group finder (public directory)** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| Leader self-management | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| Group waitlist / capacity | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Communication** | | | | | | | |
| Mass / bulk email | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| HTML email templates | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ⚠️ |
| Mailchimp integration | ✅ | ⚠️ | ✅ | ⚠️ | ❌ | ✅ | 🔌 |
| **Two-way SMS / text messaging** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Automated follow-up / workflows** | ✅ | ⚠️ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Push notifications (mobile app)** | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | **❌** |
| Letters / mail merge | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Printing labels / envelopes | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Events & Calendar** | | | | | | | |
| Event management | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Online event registration & payment** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| iCal / calendar export | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |
| Event attendance tracking | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Volunteer sign-up for events | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |
| **Volunteer Management** | | | | | | | |
| Volunteer opportunities | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |
| **Volunteer scheduling / rostering** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Automated volunteer reminders** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| Volunteer hours tracking | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Background check integration** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Attendance Tracking** | | | | | | | |
| Individual event attendance | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Sunday school / class attendance | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Service / worship attendance** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **❌** |
| **Lapsed attender alerts** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Reporting & Analytics** | | | | | | | |
| Pre-built dashboards | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Custom report builder | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Financial / giving reports | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Scheduled report delivery** | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | **❌** |
| Data export (CSV / Excel) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| REST API access | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |
| **Mobile App** | | | | | | | |
| **Branded member mobile app** | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Giving in mobile app** | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Push notifications** | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | **❌** |
| Responsive / mobile-friendly web | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| **Service / Worship Planning** | | | | | | | |
| **Service order / run-of-show builder** | ✅ | ❌ | ⚠️ | ⚠️ | ❌ | ⚠️ | **❌** |
| **Volunteer scheduling for services** | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Song / chord library** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | **❌** |
| OpenLP / ProPresenter sync | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔌 |
| **Workflows & Automation** | | | | | | | |
| **Automated follow-up sequences** | ✅ | ⚠️ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Task assignment for staff** | ✅ | ⚠️ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Trigger-based automations** | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Multi-site / Multi-campus** | | | | | | | |
| **Multi-campus support** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Campus-level segmentation** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **❌** |
| **Security & Admin** | | | | | | | |
| Role-based access control (RBAC) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Two-factor authentication (2FA) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Audit logs | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ⚠️ |
| GDPR / data-subject tools | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ⚠️ |
| Self-hosted option | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **ChurchCRM Unique Advantages** | | | | | | | |
| Open source (MIT licence) | ❌ | ❌ | ❌ | ❌ | ❌ | ⚠️ | ✅ |
| 40+ language localisations | ⚠️ | ❌ | ⚠️ | ⚠️ | ❌ | ⚠️ | ✅ |
| Offering envelope printing | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ⚠️ | ✅ |
| Member geolocation map | ⚠️ | ❌ | ⚠️ | ⚠️ | ❌ | ✅ | ✅ |
| Plugin / extension architecture | ✅ | ❌ | ⚠️ | ⚠️ | ❌ | ✅ | ✅ |
| No per-user or per-feature SaaS fees | ❌ | ❌ | ❌ | ❌ | ❌ | ⚠️ | ✅ |

---

## Detailed Gap Analysis

The following features are marketed by **every** or **most** commercial ChMS vendors but are **not currently present** in ChurchCRM. These represent the highest-impact opportunities.

### 🔴 Critical Gaps (present in all or nearly all commercial systems)

#### 1. Online Giving Portal
**Commercial standard:** All major vendors offer a hosted online giving page, recurring giving, and donor self-service.  
**ChurchCRM today:** Tracks offline giving (cash/check), but has no payment processing or online giving widget.  
**Impact:** The single most-requested feature category in church software. Most churches cite it as table-stakes.  
**Recommendation:** Integrate with a payments API (Stripe, PayPal, Tithe.ly) via a plugin. Provide a hosted `/give` page with fund selection and optional recurring scheduling.

#### 2. SMS / Text Messaging
**Commercial standard:** Two-way SMS (via Twilio, SimpleTexting, or proprietary systems) for mass messaging and automated follow-up.  
**ChurchCRM today:** Email only (SMTP). No SMS capability built in.  
**Impact:** SMS open rates (~98%) far exceed email (~20%). Critical for event reminders and follow-up.  
**Recommendation:** Build a Twilio plugin or generic SMS provider abstraction layer.

#### 3. Children's Ministry Security Labels
**Commercial standard:** Security label printing is a core check-in feature: matching parent/child labels with unique codes, allergy alerts, pager numbers.  
**ChurchCRM today:** Has a kiosk check-in and Sunday school module, but no security label printing.  
**Impact:** Required by most churches for children's programs. A safety/liability concern.  
**Recommendation:** Add label template support (PDF/ZPL) to the kiosk check-in flow, with allergy/medical note fields on person profiles.

#### 4. Workflow / Automation Engine
**Commercial standard:** Every mid-to-large ChMS (Planning Center, CCB, Rock RMS, FellowshipOne) offers trigger-based automation: "When person attends for the first time, send a welcome email and assign a follow-up task."  
**ChurchCRM today:** No automation engine exists.  
**Impact:** Reduces staff manual work dramatically. Enables discipleship follow-up at scale.  
**Recommendation:** Implement a simple rule engine (trigger + condition + action) with built-in triggers (person created, event attended, donation received) and actions (send email, create task, add to group).

#### 5. Volunteer Scheduling & Rostering
**Commercial standard:** Full scheduling UI: assign volunteers to service roles, detect conflicts, send automated reminders, allow self-scheduling swaps.  
**ChurchCRM today:** Can record volunteer opportunities and assignments but lacks a scheduling calendar, conflict detection, or automated reminders.  
**Impact:** Sunday service scheduling is a weekly task for most churches. Without it, staff use spreadsheets alongside ChurchCRM.  
**Recommendation:** Add a schedule grid view to the volunteer module with week/month views, role-based slot filling, and reminder email automation.

#### 6. Mobile App (Member-Facing)
**Commercial standard:** Branded iOS/Android app with giving, directory, events, groups, and push notifications.  
**ChurchCRM today:** Responsive web UI only.  
**Impact:** High expectation from members under 40. Primarily a giving and communication channel.  
**Recommendation:** Near-term: ensure mobile-responsive UI is complete. Long-term: publish a Progressive Web App (PWA) or React Native wrapper over the existing REST API.

#### 7. Lapsed Attender / Engagement Alerts
**Commercial standard:** Automated alerts when a member hasn't attended in X weeks (configurable threshold).  
**ChurchCRM today:** Attendance is tracked but there are no automated lapsed-member reports or alerts.  
**Impact:** Pastoral care use case—enables proactive outreach before members disengage completely.  
**Recommendation:** Add a configurable "inactive member" report/dashboard widget and optional automated email trigger.

### 🟡 Notable Gaps (present in many commercial systems)

#### 8. Online Event Registration with Payments
**Commercial standard:** Public event pages with registration forms, capacity limits, waitlists, and optional ticket pricing.  
**ChurchCRM today:** Events and attendance are tracked internally, but there is no public-facing registration page or payment collection.  
**Recommendation:** Add a public event registration flow (anonymous or authenticated) with optional payment via payment plugin.

#### 9. Duplicate Person Detection & Merging
**Commercial standard:** Automated detection of likely duplicate records with one-click merge.  
**ChurchCRM today:** No duplicate detection. Manual management required.  
**Recommendation:** Add a background job that flags likely duplicates (same name + address or same email) with a staff merge UI.

#### 10. Accounting Software Integration
**Commercial standard:** Direct export or live sync with QuickBooks, Xero, or ACS Financials.  
**ChurchCRM today:** Can export CSV data; no direct integration.  
**Recommendation:** Build a QuickBooks Online plugin that maps donation deposits to journal entries.

#### 11. Multi-site / Multi-campus Support
**Commercial standard:** A single database with campus-level segmentation, shared central reporting, and campus-local admin roles.  
**ChurchCRM today:** Single-site only.  
**Recommendation:** Evaluate demand. Implement as an optional "location" field on person/event/group records with filtered views.

#### 12. Scheduled Report Delivery
**Commercial standard:** Reports can be scheduled to run and email as PDF/CSV to defined recipients.  
**ChurchCRM today:** Reports are run on-demand from the UI only.  
**Recommendation:** Add a cron-based report scheduler using the existing CRON_RUN plugin hook.

---

## ChurchCRM Differentiators

These are areas where ChurchCRM offers unique or superior value compared to most commercial systems.

| Differentiator | Details |
|----------------|---------|
| **Free & Open Source (MIT)** | No per-member fees, no per-feature paywalls, no vendor lock-in |
| **Self-Hosted** | Full data sovereignty; data stays on church's own server or hosting. Unlike SaaS vendors, no privacy risk from vendor shutdowns, policy changes, or data-sharing |
| **40+ Language Localisations** | Exceptional global language coverage—most commercial systems focus on English only |
| **Offering Envelope Management** | Full offering envelope lifecycle (assign, print, track) — often a paid add-on in commercial systems |
| **Geolocation / Member Mapping** | Built-in geocoding and map view of member addresses |
| **No Usage Limits** | Unlimited members, families, and records at no extra cost |
| **Extensible Plugin Architecture** | Plugin system allows community extensions without forking core |
| **OpenLP Worship Software Integration** | Direct sync with OpenLP via plugin — unique in the market |
| **Transparency & Community** | Open development, public issues, community contributions |

---

## Recommended Priorities

Based on the gap analysis and commercial marketing emphasis, the following are recommended as the highest-impact additions to ChurchCRM:

### Priority 1 — High Impact, High Demand

1. **Online Giving Plugin** (Stripe/PayPal) — The #1 feature differentiating paid ChMS. A plugin approach keeps core clean.
2. **Children's Security Label Printing** — Safety requirement for children's programs; extends existing kiosk system.
3. **SMS Integration Plugin** (Twilio) — Opens a high-engagement communication channel.

### Priority 2 — Significant Operational Value

4. **Volunteer Scheduling Calendar** — Eliminates the most common reason staff use a second tool alongside ChurchCRM.
5. **Lapsed Attender Report / Alert** — Low-effort addition using existing attendance data; high pastoral value.
6. **Duplicate Detection & Merge** — Data quality improvement that pays ongoing dividends.

### Priority 3 — Growth Enablers

7. **Workflow / Automation Engine** — Foundational capability that multiplies the value of other features.
8. **Public Event Registration** — Extends the system to visitor and community engagement.
9. **Progressive Web App (PWA)** — Mobile presence without full app store maintenance.
10. **Scheduled Report Delivery** — Staff quality-of-life improvement using existing reports.

---

## Sources & Methodology

> **Note:** External websites were not directly accessible from this analysis environment. Feature listings below are based on published marketing materials, product documentation, and authoritative third-party comparisons captured prior to this document's creation date. The document should be re-verified against vendor sites periodically.

**Primary sources reviewed (knowledge base as of early 2026):**
- Planning Center Online — planningcenter.com (People, Giving, Check-Ins, Groups, Registrations, Services, Workflows, Publishing modules)
- Breeze ChMS — breezechms.com (People, Giving, Check-In, Groups, Events, Attendance, Communication)
- Church Community Builder (Pushpay) — pushpay.com/churchcommunitybuilder
- FellowshipOne / F1 Go — ministrybrands.com (fellowshipone)
- Servant Keeper — servantkeeper.com
- Elvanto / Churchdesk — churchdesk.com
- Rock RMS — rockrms.com (open-source with commercial support)
- ChurchSuite — churchsuite.com
- Ministry Platform — ministryplatform.com
- Subsplash — subsplash.com

**Third-party comparison sites consulted:**
- G2.com — Church Management Software category reviews
- Capterra.com — Church Management Software comparisons
- GetApp.com — Church Management Software buyer's guide
- SoftwareAdvice.com — Church Management Software reviews
- ChurchTechToday.com — Industry analysis and feature comparisons

**ChurchCRM source data:**
- Codebase exploration: `/home/runner/work/CRM/CRM/src/`
- Route analysis: Slim 4 route groups covering all modules
- Plugin system: `src/plugins/core/` directory contents
- Demo site: demo.churchcrm.io

---

*This document should be reviewed and updated quarterly as both ChurchCRM and the commercial landscape evolve.*
