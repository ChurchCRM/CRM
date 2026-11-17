# Ticket: Individual Person Pledge & Payment Attribution

**Status**: Implemented & Verified  
**Category**: Finance / Feature Enhancement  
**Priority**: Medium  
**Created**: 2026-09-29  

***

## 1. Requirement & Background
In standard ChurchCRM / HolyFamily CRM, pledges and payments are associated exclusively with a `Family` entity (`plg_FamID` in table `pledge_plg`). 

Users needed the ability to attribute pledges to **specific individual family members** without modifying core CRM tables, ensuring that:
1. Pledges can be tagged with individual family member IDs (`person_per.per_ID`).
2. Upstream CRM upgrades and database migrations continue running with zero schema conflicts or merge friction.
3. Standard household reporting and tax summaries (`FamilyPledgeSummary`) remain 100% operational.

***

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

***

## 3. Files Created & Modified

### New Files
1. **[custom_person_pledge_map.sql](../../src/mysql/upgrade/custom_person_pledge_map.sql)**
   * DDL script establishing the `person_pledge_map` table (`ppm_plg_id`, `ppm_per_id`).
2. **[PersonPledgeService.php](../../src/ChurchCRM/Service/PersonPledgeService.php)**
   * Service class managing table mapping persistence, lookup, and cleanup methods (`setPersonForPledge`, `getPersonForPledge`, `getPersonsForPledges`, `deleteMappingForPledge`, `getFamilyMembers`).

### Modified Files
1. **[editor.php](../../src/finance/views/pledges/editor.php)**
   * Added `Pledged By (Individual)` selection dropdown populated with family members.
   * Dynamic loading of family members when family selection changes.
   * Saved individual attribution (`PersonId`) in `collectPayload()` upon pledge submission or edit.
2. **[pledges.php](../../src/finance/routes/pledges.php)**
   * Hydrated `personId` for existing pledges using `pledgeId` from the pledge details header.
3. **[finance-payments.php](../../src/api/routes/finance/finance-payments.php)**
   * Updated `POST /api/payments/`, `POST /api/payments/pledges`, and `PUT /api/payments/{groupKey}` endpoints to persist individual pledge attribution.
   * Updated `GET /api/payments/family/{familyId}/list` endpoint with batched lookup to return `PersonId` in payment history without N+1 queries.
   * Added `GET /api/payments/family/{familyId}/members` endpoint for dynamic family member retrieval in the editor.
   * Handled cleanup of `person_pledge_map` on pledge group deletion.
4. **[FinancialService.php](../../src/ChurchCRM/Service/FinancialService.php)**
   * Added `pledgeId` to the header and funds returned by `getPledgesByGroupKey()`.
   * Ensured `person_pledge_map` entries are cleaned up when updating or deleting pledge groups.

***

## 4. Verification & Testing
* **Database Migration**: Verified `person_pledge_map` schema registration in `upgrade.json` and `Install.sql`.
* **Form & UI Test**: Verified member dropdown population and state persistence on `editor.php`.
* **API Test**: Verified `PersonId` submission and extraction in finance API endpoints.
* **Deletion Cleanup Test**: Verified removal of map entries upon pledge deletion.
