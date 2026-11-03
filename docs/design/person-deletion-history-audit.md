# Person deletion history audit

`Person::preDelete()` is the central deletion callback. The history check runs there before it removes the photo, memberships, custom fields, login, volunteer opportunities, properties, or notes. Family deletion with member cascading preflights every member with the same service before it deletes family notes or changes membership. `Family::preDelete()` removes only the family photo; its API route owns the cascade and already refuses a family with any pledge or payment.

## Protected history

| Relation | Classification | Schema and workflow evidence |
|---|---|---|
| `event_attend.person_id` | Protected attendance | `event_attend` stores event, person, check-in and check-out fields; attendance reports query it by person. No FK in `Install.sql` means deleting a person would leave the row behind. |
| `pledge_plg.plg_FamID` | Protected financial history when deleting a family or its sole member | Pledges and payments are family-owned. Removing one member while the family remains does not change the pledge owner; removing the only member makes the family's attribution no longer identify a person. Family deletion already blocks if any pledge/payment exists, for both cascade and unlink modes. |
| `pledge_plg.plg_EditedBy` | Protected financial audit reference | This schema relation records the person who edited a financial row. The guard blocks deletion when the person is a recorded editor. |
| `note_nte.nte_per_ID`, `nte_EnteredBy`, `nte_EditedBy` | Protected unless `nte_Type` is exactly `create` or `edit` | `Person::createTimeLineNote()` writes those two types for automatic profile lifecycle entries. User notes and other timeline types (including event, group, photo and note-deletion audit entries) are substantive. Author/editor references on other people's substantive notes are protected too, so deletion cannot erase their attribution. The guard tests only the discriminator and never reads note text. |
| `whycame_why.why_per_ID` | Protected recorded activity | The row stores the person's recorded answers to why they joined, came, recommended, or heard of the church. Those answers are durable history, not profile fields. |
| `email_log_eml.eml_per_ID`, `eml_usr_ID` | Protected communication history | The schema describes one row per email recipient and retains subject, body when applicable, send status and timestamp. Install SQL has no FK, so deletion could leave recipient or sender attribution dangling. |
| `volunteer_assignment_vasg.vasg_per_ID` | Protected service history | Assignments have lifecycle statuses including completed, cancelled, declined and substituted. The person FK cascades in `Install.sql`, so deleting a person erases these records. Any status blocks. |
| `volunteer_response_vrsp.vrsp_per_ID` | Protected service history | Schema calls this append-only response history; it identifies both the responder and the assignment. The person FK cascades. |
| `volunteer_swap_vswp` participant columns | Protected service history | Proposed-by and proposed-person are audit participants in a preserved handover record; both FKs cascade. The decision-maker FK is set null and does not make that person the volunteer whose service record is being removed. |
| `volunteer_notification_vntf.vntf_per_ID` | Protected service communication | Persisted person notifications are retained with status/type/timestamp; the person FK cascades. |
| `multibuy_mb.mb_per_ID`, `paddlenum_pn.pn_per_ID` | Protected recorded fundraiser participation | These person-linked rows record item counts and paddle assignments. They are not part of the person profile and can represent past event participation. |

## Current relationship or profile data

| Relation | Classification | Schema and workflow evidence |
|---|---|---|
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
| Person photo | Safe file cleanup | `Person::preDelete()` unlinks the uploaded photo only after all guards pass. |
| `property_prt` / `record2property_r2p` with class `p` | Safe current profile cleanup | No dated history is stored. |
| `volunteer_ministry_vmin.vmin_CreatedBy_per_ID` | Actor reference, safe nulling | This is a creator attribution on a ministry, not a volunteer's service history; the schema sets it null on deletion. |
| `volunteer_qualification_vqal.vqal_GrantedBy_per_ID` | Actor reference, safe nulling | The qualifier remains current; the grantor reference is set null. |
| `volunteer_requirement_default_vrdf.vrdf_SetBy_per_ID` | Actor reference, safe nulling | The default remains current; the setter reference is set null. |
| `volunteer_assignment_vasg.vasg_AssignedBy_per_ID` | Actor reference, safe nulling | The assignment itself blocks when the person is the volunteer; deleting an assigning actor only nulls this attribution. |
| `volunteer_swap_vswp.vswp_DecidedBy_per_ID` | Actor reference, safe nulling | The volunteer swap row blocks for either volunteer participant; the decision-maker reference is set null. |
| `volunteer_scope_vscp.vscp_GrantedBy_per_ID` | Actor reference, safe nulling | The subject's scope is current; the grantor reference is set null. |
| `email_log_eml.eml_usr_ID` | Sender reference | This identifies a user sender. It does not replace the separate recipient history reference, which is protected above. |

## Plugin references and deletion entry points

The core plugin tree registers `PERSON_DELETED` only in MailChimp. Its handler unsubscribes the address from remote lists; it owns no local person-reference table. There is no pre-delete plugin hook or plugin-owned relational schema in the repository to consult. The model guard executes before `PERSON_DELETED`, so no registered deletion hook runs for a blocked person. Future plugins that store durable person references need a core pre-delete contract or a documented service registration before they can participate safely.

The audited person deletion callers are:

1. `DELETE /api/person/{personId}` -> `Person::delete()` -> `Person::preDelete()`.
2. `DELETE /api/family/{familyId}?deleteMembers=true` -> preflight each `Person` with `PersonDeletionService::assertCanDelete()` before family notes/properties or member rows are touched, then call `delete()` on each loaded `Person`, which runs `Person::preDelete()`.

Repository search of production `src/` finds no raw SQL `DELETE FROM person_per` or `DELETE FROM family_fam`, no `PersonQuery` / `FamilyQuery` bulk delete, and only these model delete callers. The `DELETE FROM person2group2role_p2g2r` and `DELETE FROM groupprop_<id>` in `GroupService` remove current group relationships only; they cannot remove a person or family row. Cypress fixture cleanup may use direct SQL for test activity rows, but deletes test people and families through these same API/model paths.

The person UI delete controls in the person page, family page, people list, report, and Sunday School dashboard all call the first API route through the shared `.delete-person` handler. There are no other core `Person::delete()` / `PersonQuery::find()->delete()` call sites. Other relationship removal (for example removing a person from a group) is not person deletion and continues to work.
