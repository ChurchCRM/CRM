# Person deletion history audit

`Person::preDelete()` is the central deletion callback. The history check runs there before it removes memberships, custom fields, login, volunteer opportunities, properties, or notes. Family deletion with member cascading preflights every member with the same service before it deletes family notes or changes membership. The API route owns the family cascade and refuses families with financial history or protected family notes. Both API deletion entry points remove photos only after the deletion transaction succeeds.

Family finance checks, member preflight, and all family/member database cleanup share one transaction and connection. A deletion exception rolls back the database changes before the API returns its error. Filesystem effects are not transactional, so person and family photos are removed by their API routes only after successful database commit. External plugin hooks still occur in the model callbacks and are not transactional.

The history check locks the person row (`SELECT … FOR UPDATE`) inside the deletion transaction, and every note save locks the people it points at (subject, author, editor) in `Note::preSave()`, in id order. Note writes for families and family deletion also take the same family-row lock before their first consistent reads. A note saved while a deletion is checking history therefore waits for that deletion instead of being missed and then deleted with the person.

Family deletion (with either member mode) also refuses a family that has a substantive family note (anything but automatic `create` and `edit` entries), because a note written by someone outside the family is not seen by the member checks.

Concurrent family-membership writers do not share a locking contract with deletion. Issue #11112 tracks that existing race separately; the transaction does not prevent a late membership assignment from referencing a deleted family.

## Protected history

| Relation | Classification | Schema and workflow evidence |
|---|---|---|
| `event_attend.person_id` | Protected attendance | `event_attend` stores event, person, check-in and check-out fields; attendance reports query it by person. No FK in `Install.sql` means deleting a person would leave the row behind. |
| `pledge_plg.plg_FamID` | Protected financial history when deleting a family or its sole member | Pledges and payments are family-owned. Removing one member while the family remains does not change the pledge owner; removing the only member makes the family's attribution no longer identify a person. Family deletion already blocks if any pledge/payment exists, for both cascade and unlink modes. |
| `pledge_plg.plg_EditedBy` | Protected financial audit reference | This schema relation records the person who edited a financial row. The guard blocks deletion when the person is a recorded editor. |
| `note_nte.nte_per_ID`, `nte_EnteredBy`, `nte_EditedBy` | Protected unless `nte_Type` is exactly `create` or `edit` | `Person::createTimeLineNote()` writes those two types for automatic profile lifecycle entries. User notes and other timeline types (including event, group, photo and note-deletion audit entries) are substantive. Author/editor references on other people's substantive notes are protected too, so deletion cannot erase their attribution. The guard tests only the discriminator and never reads note text. |
| `email_log_eml.eml_per_ID`, `eml_fam_ID` | Dependent communication record, database cascade | One row per recipient, including queued and delivered email. Recipient email rows are removed by database foreign keys (`ON DELETE CASCADE`) when the owning person or family is deleted. No application-level recursive deletion or email-only blocking guard is used. Existing orphaned log rows are cleaned during the upgrade before keys are installed. |
| `volunteer_assignment_vasg.vasg_per_ID` | Protected only for accepted, substituted, or completed status | Pending, declined, and cancelled assignments without separate response/swap history represent unserved scheduling state and do not block deletion. Accepted commitments, substitutions, and completed service preserve volunteer history. Person FK cascades once deletion is allowed. |
| `volunteer_response_vrsp.vrsp_per_ID` | Protected service history | Schema calls this append-only response history; it identifies both the responder and the assignment. The person FK cascades. |
| `volunteer_swap_vswp` participant columns | Protected service history | Proposed-by and proposed-person are audit participants in a preserved handover record; both FKs cascade. The decision-maker FK is set null and does not make that person the volunteer whose service record is being removed. |
| `multibuy_mb.mb_per_ID`, `paddlenum_pn.pn_per_ID` | Protected recorded fundraiser participation | These person-linked rows record item counts and paddle assignments. They are not part of the person profile and can represent past event participation. |

### Agreed deletion policy

- Preserve actual service/commitment and append-only volunteer response history; a pending, cancelled, or declined assignment alone does not block deletion.
- Volunteer email/notification delivery is transactional (including attempted and sent outbox records). It never blocks deletion and its dependent rows are removed by database cascade, like email transaction logs.
- "Why Came" onboarding answers are current profile/onboarding data, not protected activity. Person deletion cleans them up.
- Substantive personal, family, pastoral, private, and authored/edited notes always block deletion, regardless of whether they relate to attendance. Only automatic `create`/`edit` timeline notes are excluded.
- Any blocked person or family deletion recommends the existing deactivation workflow. Family cascade errors also recommend deactivating the family.

## Current relationship or profile data

| Relation | Classification | Schema and workflow evidence |
|---|---|---|
| `whycame_why.why_per_ID` | Onboarding/profile data | Answers about why a person came or joined are removed by `Person::preDelete()`; the legacy installed table has no physical person foreign key. |
| `volunteer_notification_vntf.vntf_per_ID` | Transactional notification outbox | Sent, failed, pending, and attempted delivery rows never block deletion; the person relationship uses database `ON DELETE CASCADE`. |
| `person2group2role_p2g2r` | Current relationship and role | The table description says it records which group a person belongs to and their current role. `GroupService::removeUserFromGroup()` removes the row and that member's group-specific values. It has no dated role history. |
| `person2volunteeropp_p2vo` | Current relationship | The table description says it indicates which people are tied to volunteer opportunities; it has no status or date history. Existing cleanup remains. |
| `groupprop_[groupID]` | Current group profile | `GroupService::removeUserFromGroup()` removes the member's group-specific values along with the membership. These generated tables store current member fields, not dated history. |
| `person_custom` and `person_custom_master` | Current profile | `person_custom` stores the current per-person values; its field definitions live in `person_custom_master`. Existing person cleanup remains. |
| `property_prt` + `record2property_r2p` (class `p`) | Current profile | These represent current person properties and are removed by the existing person cleanup. |
| `events_event.primary_contact_person_id`, `secondary_contact_person_id` | Current event contact relationship | The ORM relations are marked `skipSql`, and the installed schema has no FK. Person deletion clears these two current contact fields after the history guard passes. |
| `volunteer_qualification_vqal` | Current qualification | It records current eligibility for a position; person deletion cascades. The separate grantor reference is set null. No earned/completed service is stored here. |
| `volunteer_requirement_default_vrdf` | Current scheduling preference | This is the person's current default response to a requirement; the person's relation cascades, and the setter reference is set null. |
| `volunteer_scope_vscp` | Current access grant | This describes current ministry scope. The subject's grant cascades; the grantor reference is set null. |
| `family_fam` / `person_per.per_fam_ID` | Current household relationship | The person points to the family. Deleting a person does not delete the family. Family giving remains with the family when other members remain. |
| `person_per` fields | Current profile | Name, address, contact, membership, class, family role, status and deactivation fields describe the current profile. |
| `person_custom_master`, `family_custom_master`, `propertytype_prt`, `group_grp` | Definitions, not per-person history | These are shared definitions; person deletion does not remove them. |

## Safe cleanup and actor references

| Relation | Classification | Schema and workflow evidence |
|---|---|---|
| `user_usr.usr_per_ID` | Existing linked-login cleanup, subject to existing safeguards | User row is keyed by person ID. `Person::getLoginDeletionBlockedReason()` protects self, non-admin deletion of a login, and the last administrator. This behavior remains before cleanup. |
| `userconfig_ucfg.ucfg_per_id`, `user_settings.user_id` | Current user preferences / permissions | Both point to the linked login in `schema.xml`; neither table stores person activity. `Install.sql` declares no FK and `User::preDelete()` has no cleanup, so current deletion behavior may leave these preference rows behind. They are not protected history and do not block deletion. |
| Person photo | Safe file cleanup | The deleting API removes photos only after successful database commit to avoid rollback restoring a record without its photo. |
| `property_prt` / `record2property_r2p` with class `p` | Safe current profile cleanup | No dated history is stored. |
| `volunteer_ministry_vmin.vmin_CreatedBy_per_ID` | Actor reference, safe nulling | This is a creator attribution on a ministry, not a volunteer's service history; the schema sets it null on deletion. |
| `volunteer_qualification_vqal.vqal_GrantedBy_per_ID` | Actor reference, safe nulling | The qualifier remains current; the grantor reference is set null. |
| `volunteer_requirement_default_vrdf.vrdf_SetBy_per_ID` | Actor reference, safe nulling | The default remains current; the setter reference is set null. |
| `volunteer_assignment_vasg.vasg_AssignedBy_per_ID` | Actor reference, safe nulling | The assignment itself blocks when the person is the volunteer; deleting an assigning actor only nulls this attribution. |
| `volunteer_swap_vswp.vswp_DecidedBy_per_ID` | Actor reference, safe nulling | The volunteer swap row blocks for either volunteer participant; the decision-maker reference is set null. |
| `volunteer_scope_vscp.vscp_GrantedBy_per_ID` | Actor reference, safe nulling | The subject's scope is current; the grantor reference is set null. |
| `email_log_eml.eml_usr_ID` | Sender reference, kept | This identifies the user who sent a composer message. It has no foreign key, so deleting a sender never removes mail they sent to other people, and it does not block deletion. |

## Plugin references and deletion entry points

The core plugin tree registers `PERSON_DELETED` only in MailChimp. Its handler unsubscribes the address from remote lists; it owns no local person-reference table. There is no pre-delete plugin hook or plugin-owned relational schema in the repository to consult. The model guard executes before `PERSON_DELETED`, so no registered deletion hook runs for a blocked person. Future plugins that store durable person references need a core pre-delete contract or a documented service registration before they can participate safely.

The audited person deletion callers are:

1. `DELETE /api/person/{personId}` -> `Person::delete()` -> `Person::preDelete()`.
2. `DELETE /api/family/{familyId}?deleteMembers=true` -> preflight each `Person` with `PersonDeletionService::assertCanDelete()` before family notes/properties or member rows are touched, then delete the loaded collection. Perpl's collection calls each person's `delete()` within a transaction, running `Person::preDelete()` for every member. Both preflight and model deletion use the same service for login safeguards and history checks.

Repository search of production `src/` finds no raw SQL `DELETE FROM person_per` or `DELETE FROM family_fam`, no `PersonQuery` / `FamilyQuery` bulk delete, and only these model delete callers. The `DELETE FROM person2group2role_p2g2r` and `DELETE FROM groupprop_<id>` in `GroupService` remove current group relationships only; they cannot remove a person or family row. Cypress fixture cleanup may use direct SQL for test activity rows, but deletes test people and families through these same API/model paths.

The person UI delete controls in the person page, family page, people list, report, and Sunday School dashboard all call the first API route through the shared `.delete-person` handler. There are no other core `Person::delete()` / `PersonQuery::find()->delete()` call sites. Other relationship removal (for example removing a person from a group) is not person deletion and continues to work.
