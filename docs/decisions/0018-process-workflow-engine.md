# RFC: General process / workflow engine

> **BLUF**
> Churches run repeatable, multi-step processes — hospital visitation follow-up, bereavement care, newcomer assimilation, discipleship pathways, membership preparation — and ChurchCRM gives them no system support for any of it. Issue #348 (filed from real CCB usage) asks for "process queues": define a process once, move people through its stages, assign an owner at each stage, and optionally automate a step such as a mail-merge email. There is no workflow or process engine on master today, so these processes live in spreadsheets, notebooks and memory. This RFC proposes a **general process engine — reusable process definitions made of ordered, owned steps, and per-person/family instances with per-step state** — and poses the design questions a maintainer/community decision must answer before any implementation is specced. RFC 0016 (visitation tracking) is positioned as a candidate first consumer of the engine, not a competitor to it.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-14
**Owner:** @dawoudio
**Proposal issue:** [#348](https://github.com/ChurchCRM/CRM/issues/348) (Process Queue idea)
**Related:** [RFC 0016 — Visitation tracking](0016-visitation-tracking.md) ([#8980](https://github.com/ChurchCRM/CRM/issues/8980)) · [RFC 0002 — RBAC redesign](0002-rbac-redesign.md)
**Numbering:** `0018` is provisional. A maintainer assigns the final number on merge.

## Problem

- **The ask is a general engine, not one process.** #348's reporter used CCB's process queues "a ton": create a process, move people through it, and at each stage either assign an administrator to take care of that stage or automate a mail-merge email. Their uses spanned discipleship tracking, hospital visitation, bereavement follow-up, and series planning — four different processes on one mechanism.
- **No process support exists today.** Master has no workflow/process engine: no pipeline of steps with owners, due dates and completion state. The closest mechanisms are one-shot records (notes, visits) and static group membership — none of them can say "this family is on step 3 of 5 in the bereavement process, step 4 is due Thursday and belongs to Pastor X."
- **The failure mode is silent.** When a follow-up step lives only in a notebook, a missed step is invisible: nobody is assigned, nothing is overdue, nothing surfaces. Pastoral processes are exactly the ones where a dropped step is a dropped person.
- **Two of #348's use cases already have a home — for the record, not the sequence.** RFC 0016 covers hospital visitation and bereavement follow-up as *visit records*. But a bereavement follow-up is a *sequence* — visit, phone call, meal referral, one-month check-in — and no single visit record tracks the sequence, its ownership, or what is still owed. The gap this RFC addresses is the sequence layer, which 0016 deliberately does not provide.

## Proposal

A process engine with two levels:

- **Process definitions.** A named, ordered list of steps. Each step has a title, an assignee (a role, or a specific person), an optional due offset (e.g. "3 days after the previous step completes"), an optional completion note prompt, and optionally an automated action — #348's mail-merge email at a stage is the motivating example.
- **Process instances.** A definition started for a subject — a person or a family. Each step in the instance carries its own state (pending / in progress / done / skipped), its owner, its due date, and who completed it, when, and with what note. An instance can be completed, or ended early with a reason.
- **Surfacing.** A list/dashboard of open instances and overdue steps (the "what needs attention" view), and the subject's person/family view showing their active processes and where they stand in each.

**Position relative to RFC 0016.** RFC 0016's visitation pipelines are candidate *first instances* of this engine: the visitation and bereavement follow-up flows could ship as the engine's first definition set, with the visit itself remaining an RFC 0016 visit record and the engine tracking the sequence around it. The two RFCs are layers, not competitors — 0016 is the record of each pastoral contact; this engine is the pipeline those contacts (and other steps) move through. Whether they converge in a first release is a design question below, not a foregone conclusion.

This RFC is a decision document. It does not spec tables, UI or API; those belong to a post-approval design step, per the RFC process.

## Design questions

1. **Definitions: data or code?** Church-defined definitions stored as data fit the premise — every church's newcomer, bereavement and membership processes differ — but make sharing, versioning and support harder. Shipped code definitions are uniform and testable but force one church's process on all. A hybrid (shipped starter definitions a church can copy and edit as data) is possible; it is also the most machinery. Which model does v1 commit to?
2. **Core feature or core plugin?** Processes touch the person/family core and want dashboard surfacing, which argues for core. Not every church runs formal processes, and the engine is a sizeable surface, which argues for a core plugin in the RFC 0017 sense. This decision shapes everything downstream.
3. **Triggers: manual start only, or event-started?** #348's model is manual — a staff member puts a person into a process. Event-started instances are powerful (a new family record starts newcomer assimilation; a recorded death starts bereavement follow-up) but overlap the trigger/notification territory of #1201's event notifications and can surprise. Does v1 support automatic starts, and if so, for which events, with what opt-in?
4. **Step permissions and RBAC.** Process instances can carry sensitive pastoral context (bereavement, hospital, discipline-adjacent discipleship steps). Who may view an instance, who may complete or reassign a step, and who may edit definitions each need an answer — and the answers should align with the RFC 0002 RBAC redesign rather than invent a parallel permission model. RFC 0016's staff-facing-only reasoning for sensitive records is the local precedent.
5. **RFC 0016: first definition set, or standalone until the engine lands?** Shipping 0016's visitation/bereavement flows as the engine's first definitions proves the engine on the use cases #348 actually named, but couples two unbuilt systems. Keeping 0016 standalone avoids the dependency but risks a visitation pipeline that later has to be re-expressed in the engine. Sequence them, converge them, or deliberately keep them separate?
6. **Automated step actions.** Is #348's mail-merge email at a stage in v1 scope? If so, it builds on the notification/email plumbing (cf. #1201, RFC 0008) rather than a second sending path, and its failure handling (a step whose email failed) becomes engine state.
7. **Instance subject: person, family, or both?** Discipleship and membership preparation are personal; bereavement and newcomer assimilation are often household-level. Supporting both subjects doubles some surfaces (views, reporting); supporting one mis-fits half of #348's examples.

## Alternatives considered

- **Scope down to RFC 0016 only.** Covers the visitation and bereavement *records*, but not the general engine #348 asks for — discipleship, newcomer and membership processes would remain unsupported, and the sequence/ownership gap above remains. **Rejected by the maintainer on 2026-11-14** (decision on #348), while noting 0016 proceeds as the visit-record layer.
- **External tools.** The reporter arrived from CCB; generic tools (Basecamp-style project apps, CRMs, spreadsheets) have no integration with ChurchCRM's people and family data, so every process runs in a second system that drifts out of sync with the member record. That is the status quo #348 is asking to escape.
- **Generic task lists.** A to-do/task feature gives tasks but no process semantics: no reusable definitions, no ordered steps with due offsets, no per-stage ownership pattern. Every instance would be hand-built, which is the notebook problem with better formatting.
- **Status quo (notes and custom fields).** Notes record that something happened; they cannot hold state, ownership or due dates, and nothing surfaces what is overdue. This is how follow-ups are silently dropped today.

## Open questions

- **Reporting.** What does the engine owe beyond the overdue list — completion rates per definition, average time per step, per-owner load? Churches will ask elders for these numbers; the scope belongs in the decision, not as an afterthought.
- **Definition sharing.** Should churches be able to export/import definitions (a denomination sharing its membership-preparation process)? In scope for v1, or explicitly deferred?
- **Overdue notification.** Overdue steps surfacing in a dashboard are passive; do owners get actively notified (and through which channel), or is the dashboard the whole surfacing story for v1?
- **Ended and transferred instances.** When a subject leaves the church, moves, or dies mid-process, what happens to their open instances — and whose call is it to end them?

## Linked issues

Proposal: #348 (Process Queue idea — CCB process queues: staged processes with per-stage administrators and automated mail-merge steps; uses include discipleship tracking, hospital visitation, bereavement follow-up and series planning). Maintainer decision recorded on #348 on 2026-11-14: commission this RFC for the general engine rather than scoping the issue down to RFC 0016.

Related: RFC 0016 / #8980 (visitation tracking — the visit-record layer; candidate first consumer of this engine), #1201 (event-triggered notifications — the trigger and notification-plumbing territory questions 3 and 6 build on), RFC 0002 (RBAC redesign — the permission model question 4 must align with), RFC 0008 (email newsletters — sending-path precedent for question 6).
