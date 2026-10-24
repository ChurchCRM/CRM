---
title: "Custom Field Access Control (Field-Level ACL)"
intent: "Guidance for developers accessing person and family custom fields with proper permission checks"
tags: ["security", "custom-fields", "acl", "authorization"]
prereqs: ["[[authorization-security]]", "[[code-standards]]"]
complexity: "intermediate"
---
# Custom Field Access Control

Person and Family custom fields carry a field-level security value. Users without the matching permission (Finance, Notes, Admin, ...) never read restricted fields.

## Security Model

Each custom field has a `FieldSecurity` value (an option of `list_lst` with `lst_ID=5`):

| Permission | Visible to |
|-----------|-------|
| `bAll` | Everyone |
| `bFinance` | Finance-enabled users |
| `bNotes` | Notes-enabled users |
| `bAdmin` | Admin users |
| Other role (e.g. `bManageGroups`) | Users with that role |

`User::isEnabledSecurity($field->getFieldSecurity())` is the single check. It handles admin, `bAll` and every role.

Apply it wherever field values or definitions leave the database: web UI display and edit forms, list and report columns, CSV/PDF exports, API JSON, and plugin output. Saving a field requires the EditRecords role (route middleware); edit forms list only the fields the user can read.

## Pattern

### Person

Use the Person model helpers:

```php
$person = PersonQuery::create()->findPk($personId);

$visibleFields = $person->getVisibleCustomFieldDefinitions();   // PersonCustomMaster[]
$visibleValues = $person->getVisibleCustomFieldValues();        // [['id', 'name', 'value'], ...] non-empty values only
```

`getCustomFieldsAll()` returns filtered export columns and values when a report needs both:

```php
$result = $person->getCustomFieldsAll(
    $allPersonCustomFields,
    $CustomMapping,
    $CustomList,
    $option_name,
    $exportCustomFields
);
// ['filterNames' => ..., 'exportValues' => ...]
```

### Family

Filter `FamilyCustomMasterQuery` with `isEnabledSecurity()`:

```php
$currentUser = AuthenticationManager::getCurrentUser();
$visibleFields = [];
foreach (FamilyCustomMasterQuery::create()->orderByOrder()->find() as $field) {
    if ($currentUser->isEnabledSecurity($field->getFieldSecurity())) {
        $visibleFields[] = $field;
    }
}
```

### API

Build JSON custom data from `getVisibleCustomFieldValues()`, as `src/api/routes/people/people-person.php` does:

```php
$personData = json_decode($person->exportTo('JSON'), true);
$personData['singlePersonCustom'] = $person->getVisibleCustomFieldValues();
return SlimUtils::renderStringJSON($response, json_encode($personData));
```

### Exports and reports

Load the security mapping once, then test each field before adding its column or value:

```php
$currentUser = AuthenticationManager::getCurrentUser();
foreach ($customFieldDefinitions as $field) {
    if ($currentUser->isEnabledSecurity($aSecurityType[$custom_FieldSec])) {
        // include the field
    }
}
```

`src/CSVExport.php` and `src/PersonEditor.php` show this loop.

Any file touched in a PR is brought onto this pattern (ORM queries plus `isEnabledSecurity()`): `src/PersonCustomFieldsEditor.php`, `src/FamilyCustomFieldsEditor.php`, `src/PersonEditor.php`, `src/FamilyEditor.php`, `src/Reports/DirectoryReport.php`.

## Plugins

A plugin that reads custom fields uses `getVisibleCustomFieldDefinitions()` / `getVisibleCustomFieldValues()` for people, and the family loop above for families. A plugin that queries `PersonCustomMasterQuery` directly filters with `isEnabledSecurity()`. See [[plugin-security-scan]] for the audit.

## Testing

- Cypress: cover a low-privilege user, a Finance user and an admin against the web UI, API response and CSV export. Specs live under `cypress/e2e/` (read an existing spec for fixture shapes)
- Assert a restricted field is absent for the low-privilege user and present for the matching role

## Related

- [[authorization-security]] — permission system overview
- [[security-best-practices]] — security patterns
- [[code-standards]] — output escaping and input validation
- [[plugin-security-scan]] — plugin audit process

## Checklist

- [ ] Use the model helpers or `isEnabledSecurity()` for every custom field read
- [ ] API responses carry only visible fields
- [ ] Edit forms list only visible fields
- [ ] Test with low-privilege, Finance and admin users
- [ ] Cypress covers each permission level
