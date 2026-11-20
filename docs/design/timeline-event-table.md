# Design note: a dedicated timeline event table

**The point is RBAC simplification.** The person and family timelines draw their audit
stream — record created, edited, photo changed, group membership, account lifecycle —
from the **notes table**, where system events are stored as notes with special types.
That means timeline reads and writes inherit the notes permission model, plus
special-case visibility handling (#9036, corrected by #9039): plain-auth users see
audit items but not notes, system types are never gated on the Notes permission,
private notes must vanish entirely rather than redact. Every one of those rules is a
special case that exists only because two kinds of row share a table. Give the
timeline its own table and it gets one clear permission rule: notes follow the notes
rules, events follow the events rule (visible to any authenticated user who can view
the record).

This is a design note only: no implementation is scheduled, and the natural slot is
the release after 7.8.6.

## The change

The move itself is mechanical:

1. **Move the audit rows.** Non-`note` rows in `note_nte` (types `create`, `edit`,
   `photo`, `group`, `delete-note`, the account-lifecycle types, and more) move to a
   new append-only **`timeline_event`** table: id, person id (nullable), family id
   (nullable), type, actor (following the existing `EnteredBy` conventions, including
   self-registration / system upgrade / scheduled task), text, an optional JSON
   payload for a target reference (for example the id of a deleted note), and the
   event timestamp. Indexed on person and family plus timestamp. Timestamps, actors,
   and text are preserved.
2. **One writer.** `TimelineService::record(...)` is the only way to write an event.
   The ~13 sites that hand-build audit `Note` objects today (Person, Family, and User
   models, portal self-service, sign-in services, people routes) migrate to it; no
   new audit type is ever added to the notes table again.
3. **Read from the new table.** `TimelineService` reads the audit stream from
   `timeline_event`. The read path keeps merging event attendance and the email log
   live, as it does today — those are real domain tables and stay where they are.
   Same timeline, same categories and filter chips, same visibility outcomes.
4. **Notes become notes only.** After the move, `note_nte` holds type `note` rows
   exclusively, so the Notes permission gates the whole table with no type
   exceptions, and note consumers (panel, counts, editor) stop filtering audit types.

## Migration sketch

1. Table + writer API + dual-read (read both stores, write new events to the new one).
2. Writer migration: move the ~13 audit-writer sites to `TimelineService::record(...)`.
3. Data move in the release upgrade, then removal of the type vocabulary from note
   consumers and of the #9036/#9039 read-path special cases.

## Open questions

1. **Payload vs text only.** Some audit entries render from structured facts (author,
   links). Is a JSON payload with a target reference enough, or do some event types
   need first-class columns?
2. **Tombstones.** A `delete-note` event records that a note existed and was removed.
   Does the event keep the deleted note's id (payload) — and should it keep any of
   its text?
3. **Family events.** Are family timeline events stored per family, or derived from
   member events at read time? Today they are stored (family notes), and derivation
   would change what a family timeline shows for former members.
4. **Retention.** Audit events currently live as long as the record does, because
   they are notes. Does the new table want the same rule stated explicitly?
