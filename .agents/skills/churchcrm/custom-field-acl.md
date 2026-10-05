---
title: "Custom Field Access Control (Field-Level ACL)"
intent: "Guidance for developers accessing person and family custom fields with proper permission checks"
tags: ["security", "custom-fields", "acl", "authorization"]
prereqs: ["[[authorization-security]]", "[[code-standards]]"]
complexity: "intermediate"
---
# Custom Field Access Control

Custom fields on **Person** and **Family** entities support field-level security restrictions. Low-privilege users (without Finance, Notes, Admin permissions) must not be able to read or modify restricted fields.

## Security Model

### Field Permissions

Every custom field has a `FieldSecurity` value (mapped from `list_lst` with `lst_ID=5`):

| Permission | Level | Users | Example |
|-----------|-------|-------|---------|
| `bAll` | Public | Everyone | Public Notes |
| `bFinance` | Restricted | Finance-enabled users only | Account Number |
| `bNotes` | Restricted | Notes-enabled users only | Pastoral Notes |
| `bAdmin` | Restricted | Admin users only | Internal ID |
| Custom (e.g., `bManageGroups`) | Restricted | Users with that role | Group-specific data |

### Protected Operations

✅ **Enforce permissions on:**
- Reading custom field values (display in web UI, API JSON exports)
- Displaying custom field definitions in lists/reports
- Exporting custom fields to CSV/PDF
- API responses that include custom fields

⚠️ **When WRITING custom fields:**
- Editing and saving custom fields already check EditRecords role
- Field-level read permissions still apply (don't show fields in edit forms if user can't read)
- See "Editing Custom Fields" section below

## Safe Patterns

### Reading Person Custom Fields

**✅ SAFE: Use helper method on Person model**
```php
// In routes, services, reports
$person = PersonQuery::create()->findPk($personId);
$visibleFields = $person->getVisibleCustomFieldDefinitions();

// Later: iterate only visible fields
foreach ($visibleFields as $field) {
    $value = $customData[$field->getId()] ?? '';
    // display or process
}
```

**✅ SAFE: Use ORM's getCustomFieldsAll() for comprehensive filtering**
```php
// When you need both definitions AND values with filtering
$customFieldsResult = $person->getCustomFieldsAll(
    $allPersonCustomFields,
    $CustomMapping,
    $CustomList,
    $option_name,
    $exportCustomFields
);
// Returns: filterNames (for hidden columns), exportValues (for display)
```

**✅ SAFE: Use isEnabledSecurity() for per-field checks**
```php
$currentUser = AuthenticationManager::getCurrentUser();
if ($currentUser->isEnabledSecurity($field->getFieldSecurity())) {
    // User can see this field
}
```

**❌ UNSAFE: Fetch all custom fields without filtering**
```php
// DO NOT DO THIS:
$allFields = PersonCustomMasterQuery::create()->find();
foreach ($allFields as $field) {
    // Display every field regardless of permission
}
```

**❌ UNSAFE: Check only bAll permission**
```php
// DO NOT DO THIS:
if ($securityType === 'bAll') {
    // display field
}
// Missing: checks for other permissions the user has
```

### Reading Family Custom Fields

**✅ SAFE: Use helper method on Family model**
```php
$family = FamilyQuery::create()->findPk($familyId);
$visibleFields = $family->getVisibleCustomFieldDefinitions();

foreach ($visibleFields as $field) {
    $value = $customData[$field->getId()] ?? '';
    // display or process
}
```

**✅ SAFE: Same isEnabledSecurity() pattern**
```php
if ($currentUser->isEnabledSecurity($field->getFieldSecurity())) {
    // User can see this field
}
```

### Exporting Custom Fields (CSV, PDF, Reports)

**✅ SAFE: Filter definitions before iterating values**
```php
// From CSVCreateFile.php pattern:
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);
$aSecurityType = [];
while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

// Load custom field definitions
$sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);

// When exporting each person
while ($aRow = mysqli_fetch_array($rsCustomFields)) {
    extract($aRow);
    // Check permission before including in export
    if ($aSecurityType[$custom_FieldSec] === 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
        // Include field in export
        $row[] = CustomFieldUtils::display($type_ID, trim($aCustomData[$custom_Field]), $custom_Special);
    }
}
```

**❌ UNSAFE: Export all fields without permission checks**
```php
// DO NOT DO THIS:
while ($aRow = mysqli_fetch_array($rsCustomFields)) {
    // Export every field regardless of user permissions
    $row[] = CustomFieldUtils::display(...);
}
```

### API Endpoints

**✅ SAFE: Filter custom fields in JSON response**
```php
// GET /api/person/{id}
$personJSON = $person->exportTo('JSON');
$personData = json_decode($personJSON, true);

if (isset($personData['singlePersonCustom']) && is_array($personData['singlePersonCustom'])) {
    $filteredCustom = [];
    foreach ($personData['singlePersonCustom'] as $customField) {
        $fieldId = $customField['id'] ?? null;
        if ($fieldId) {
            $fieldDef = PersonCustomMasterQuery::create()->findPk($fieldId);
            if ($fieldDef && $currentUser->isEnabledSecurity($fieldDef->getFieldSecurity())) {
                $filteredCustom[] = $customField;
            }
        }
    }
    $personData['singlePersonCustom'] = $filteredCustom;
}
return SlimUtils::renderStringJSON($response, json_encode($personData));
```

**❌ UNSAFE: Return full Person::exportTo('JSON') without filtering**
```php
// DO NOT DO THIS:
return SlimUtils::renderStringJSON($response, $person->exportTo('JSON'));
// Includes all custom fields regardless of user permissions
```

## Editing Custom Fields

When users edit custom fields in forms:

1. **Still check read permissions**: Don't show fields in edit forms if the user can't read them
2. **Write access**: EditRecords role is required (enforced by middleware)
3. **Recommendation**: Use `getVisibleCustomFieldDefinitions()` to populate edit forms too

```php
// In edit form rendering:
$visibleFields = $person->getVisibleCustomFieldDefinitions();
foreach ($visibleFields as $field) {
    // Render edit input for this field
}
```

## Legacy Code (Pre-ORM Files)

These legacy files need auditing and updating:

| File | Status | Notes |
|------|--------|-------|
| `src/PersonCustomFieldsEditor.php` | ⚠️ Legacy | Pre-ORM, admin-only page (less critical) |
| `src/PersonEditor.php` | ⚠️ Legacy | Pre-ORM, check if still used |
| `src/FamilyCustomFieldsEditor.php` | ⚠️ Legacy | Pre-ORM, admin-only page (less critical) |
| `src/FamilyEditor.php` | ⚠️ Legacy | Pre-ORM, check if still used |
| `src/Reports/DirectoryReport.php` | ⚠️ Legacy | Pre-ORM, needs permission checks |

### Updating Legacy Code

When updating legacy files to enforce field-level ACL:

1. Load security type mapping:
   ```php
   $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
   $rsSecurityGrp = RunQuery($sSQL);
   $aSecurityType = [];
   while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
       extract($aRow);
       $aSecurityType[$lst_OptionID] = $lst_OptionName;
   }
   ```

2. Check permission before displaying/exporting:
   ```php
   if ($aSecurityType[$custom_FieldSec] === 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
       // show/export field
   }
   ```

## Plugin Considerations

### Plugins Accessing Custom Fields

If your plugin reads person or family custom fields:

**✅ Safe approach:**
```php
// In your plugin code:
$person = PersonQuery::create()->findPk($personId);
$visibleFields = $person->getVisibleCustomFieldDefinitions();

foreach ($visibleFields as $field) {
    $value = $customData[$field->getId()] ?? '';
    // Process only visible fields
}
```

**❌ Unsafe approach:**
```php
// DO NOT DO THIS IN PLUGINS:
$allFields = PersonCustomMasterQuery::create()->find();
foreach ($allFields as $field) {
    // Plugin reads every field, including restricted ones
}
```

### Plugin Hooks

If your plugin hooks into custom field processing:

- **`PERSON_CUSTOM_FIELD_LOADED`**: Data already loaded by framework
  - Hook receives field value — it's your responsibility to check permissions before using it
  - Recommended: Check `isEnabledSecurity()` before processing

- **`PERSON_CUSTOM_FIELD_DISPLAY`**: Called when rendering fields
  - Hook receives visible fields only (already filtered by framework)
  - Safe to use as-is

### Plugin Installation Check

When plugins are installed, verify they:

1. Don't bypass custom field permissions
2. Use `isEnabledSecurity()` when accessing restricted fields
3. Respect the helper methods on Person/Family models

See [[plugin-security-scan]] for plugin audit process.

## Testing Custom Field ACL

### Unit/Integration Tests

Test that custom fields are properly filtered:

```php
// Test: Low-privilege user doesn't see Finance fields
$lowPrivUser = UserQuery::create()->findByRole('Viewer');
$person = PersonQuery::create()->findPk(1);
$visibleFields = $person->getVisibleCustomFieldDefinitions();
$financeFields = array_filter($visibleFields, fn($f) => $f->getFieldSecurity() === 4); // 4 = bFinance
$this->assertEmpty($financeFields);

// Test: Finance user sees Finance fields
$financeUser = UserQuery::create()->findByRole('Finance');
// ... same test, should find Finance fields
```

### E2E Tests (Cypress)

See `cypress/e2e/security/field-level-acl.spec.js` for comprehensive test patterns covering:
- Web UI display filtering
- API response filtering
- CSV export filtering
- Consistency across all display methods

## Related Documentation

- [[authorization-security]] — Permission system overview
- [[security-best-practices]] — Security patterns
- [[code-standards]] — Output escaping and input validation
- [[plugin-security-scan]] — Plugin security audit process
- GHSA-p6xx-xx98-f323 — Advisory for this vulnerability

## Checklist for Adding New Custom Field Features

When adding features that read/display custom fields:

- [ ] Use `getVisibleCustomFieldDefinitions()` or `getCustomFieldsAll()` from model
- [ ] Check `isEnabledSecurity()` before displaying restricted fields
- [ ] Filter API responses to exclude restricted fields
- [ ] Test with low-privilege, finance, and admin users
- [ ] Add Cypress tests for different permission levels
- [ ] Document in code if accessing custom fields (reference this guide)
- [ ] If plugin-facing, document that plugins must check permissions
