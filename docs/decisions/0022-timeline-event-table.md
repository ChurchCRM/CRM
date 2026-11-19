# RFC: A dedicated timeline event table

> **BLUF**
> The person and family timelines draw their audit stream — record created, edited, photo changed, group membership, account lifecycle — from the **notes table**, where system events are stored as notes with special types. This RFC proposes an append-only **`timeline_event`** table and a single writer API (`TimelineService::record(...)`) for that stream, so the notes table holds only notes again. The read path keeps merging event attendance and the email log live, as it does today. This is a design note only: no implementation is scheduled, and the natural slot is the release after 7.8.6 (which already carries the Volunteer V1 data conversion).

**Status:** Draft for maintainer review — design note only; no implementation issue filed
**Audience:** Technical — maintainer only; no community discussion.
**Date:** 2026-11-18
**Owner:** @dawoudio
**Related:** #9036 and #9039 (timeline visibility rules) · #11506 (NoteEditor as an API endpoint) · `src/ChurchCRM/Service/TimelineService.php`
**Numbering:** `0022` is provisional. A maintainer assigns the final number on merge.

## Problem

- **One table, two systems.** `note_nte` stores user-entered notes (type `note`) and the system/audit stream (types `create`, `edit`, `photo`, `group`, `delete-note`, the account-lifecycle types, and more). Any feature that wants timeline presence hand-builds a `Note` object; writers are spread across the Person, Family, and User models, portal self-service, sign-in services, and people routes (~13 sites).
- **Privacy by type discipline instead of by table.** Because audit rows live in the notes table, the read path carries layered exceptions (#9036, corrected by #9039): plain-auth users see audit items but not notes, system types are never gated on the Notes permission, private notes must vanish entirely rather than redact. Every rule is a special case that exists only because the two kinds of row share a table.
- **Every notes consumer filters.** The notes panel, note counts, and the note editor must exclude audit types; every audit writer must know the type vocabulary. The vocabulary leaks into the UI as the timeline's filter categories.
- **The shape is already straining.** The person timeline merges three sources (notes, attendance, email log) while the family timeline reads notes only, and the notes read is unbounded and sorted in PHP after the merge.

## Proposal

1. **A `timeline_event` table**, append-only: id, person id (nullable), family id (nullable), type, actor (the user or system actor, following the existing `EnteredBy` conventions including self-registration / system upgrade / scheduled task), text, an optional JSON payload for a target reference (for example the id of a deleted note), and the event timestamp. Indexed on person and family plus timestamp.
2. **One writer API.** `TimelineService::record(...)` (or a small dedicated service) is the only way to write an event. The ~13 sites that construct audit `Note` objects today migrate to it; no new audit type is ever added to the notes table again.
3. **Notes become notes only.** After migration the notes table holds type `note` rows exclusively. The Notes permission gates the whole table with no type exceptions, and the #9036 read-path special cases collapse to: notes follow the notes rules, events follow the events rule (visible to any authenticated user who can view the record).
4. **Read path mostly unchanged.** `TimelineService` reads the audit stream from `timeline_event` and keeps merging event attendance and the email log live — those are real domain tables and stay where they are. The timeline's categories and filter chips carry over.
5. **Data migration in the release upgrade.** Non-`note` rows move from `note_nte` to `timeline_event`, preserving timestamps, actors, and text. Suggested phasing when scheduled: (1) table + writer API + dual-read, (2) writer migration, (3) data move and removal of the type vocabulary from note consumers.

## Goals

- One honest store per concept: notes are user content; timeline events are system record.
- Privacy enforced by which table a row is in, not by every reader remembering the type rules.
- A single, greppable place that answers "what can appear on a timeline, and who writes it."

## Non-goals

- Performance rework of the timeline merge or pagination (the read stays a merge; this change is about simplicity and privacy safety, not speed).
- Any user-visible change: same timeline, same categories, same visibility outcomes.
- A retention or pruning policy for events (open question below, not decided here).

## Open questions

1. **Payload vs text only.** Some audit entries are rendered from structured facts (author, links). Is a JSON payload with a target reference enough, or do some event types need first-class columns?
2. **Tombstones.** A `delete-note` event records that a note existed and was removed. Does the event keep the deleted note's id (payload) — and should it keep any of its text?
3. **Family events.** Are family timeline events stored per family, or derived from member events at read time? Today they are stored (family notes), and derivation would change what a family timeline shows for former members.
4. **Retention.** Audit events currently live as long as the record does, because they are notes. Does the new table want the same rule stated explicitly?
