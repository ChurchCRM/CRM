# Ticket: Individual Person Pledge & Payment Attribution

**Status**: Implemented & Verified  
**Category**: Finance / Feature Enhancement  
**Priority**: Medium  
**Created**: 2026-09-29  

---

## 1. Requirement & Background
In standard ChurchCRM / HolyFamily CRM, pledges and payments are associated exclusively with a `Family` entity (`plg_FamID` in table `pledge_plg`). 

Users needed the ability to attribute pledges to **specific individual family members** without modifying core CRM tables, ensuring that:
1. Pledges can be tagged with individual family member IDs (`person_per.per_ID`).
2. Upstream CRM upgrades and database migrations continue running with zero schema conflicts or merge friction.
3. Standard household reporting and tax summaries (`FamilyPledgeSummary`) remain 100% operational.

---

## 2. Architecture & Design

A non-invasive **sidecar mapping table** approach was implemented.

```
+------------------+         +--------------------+         +-------------------+
|    pledge_plg    | 1     1 | person_pledge_map  | *     1 |    person_per     |
+------------------+ <-----> +--------------------+ <-----> +-------------------+
| plg_plgID (PK)   |         | ppm_plg_id (PK)    |         | per_ID (PK)       |
| plg_FamID (FK)   |         | ppm_per_id (FK)    |         | per_FirstName...  |
+------------------+         +--------------------+         +-------------------+
```

### Key Architectural Benefits
* **Non-Invasive**: Core `pledge_plg` table schema is untouched.
* **Upgrade-Safe**: Upstream migrations (`mysql/upgrade/*.sql`) will execute cleanly without column conflicts.
* **High Performance**: $O(1)$ indexed lookup on `ppm_plg_id` with sub-millisecond overhead.

---

## 3. Files Created & Modified

### New Files
1. **[custom_person_pledge_map.sql](file:///c:/Users/HP/source/work/HolyFamily/hf-crm/src/mysql/upgrade/custom_person_pledge_map.sql)**
   * DDL script establishing the `person_pledge_map` table (`ppm_plg_id`, `ppm_per_id`).
2. **[PersonPledgeService.php](file:///c:/Users/HP/source/work/HolyFamily/hf-crm/src/ChurchCRM/Service/PersonPledgeService.php)**
   * Service class managing table auto-creation, mapping persistence, lookup, and cleanup methods (`setPersonForPledge`, `getPersonForPledge`, `deleteMappingForPledge`, `getFamilyMembers`).

### Modified Files
1. **[PledgeEditor.php](file:///c:/Users/HP/source/work/HolyFamily/hf-crm/src/PledgeEditor.php#L560)**
   * Added `Pledged By (Individual)` selection dropdown populated with family members.
   * Saved individual attribution to `person_pledge_map` upon pledge submission or edit.
2. **[PledgeDelete.php](file:///c:/Users/HP/source/work/HolyFamily/hf-crm/src/PledgeDelete.php#L48)**
   * Cleaned up `person_pledge_map` rows when a pledge or pledge group key is deleted.
3. **[finance-payments.php](file:///c:/Users/HP/source/work/HolyFamily/hf-crm/src/api/routes/finance/finance-payments.php#L54)**
   * Updated `POST /api/payments` endpoint to accept optional `PersonId`.
   * Updated `GET /api/payments/family/{familyId}/list` endpoint to return `PersonId` in payment history payload.

---

## 4. Verification & Testing
* **Database Creation**: Verified `person_pledge_map` schema initialization.
* **Form & UI Test**: Verified member dropdown population and state persistence on `PledgeEditor.php`.
* **API Test**: Verified `PersonId` submission and extraction in finance API endpoints.
* **Deletion Cleanup Test**: Verified removal of map entries upon pledge deletion.
