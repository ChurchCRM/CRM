# Testing Address Export Fix

## Issue
Family and person addresses don't export to CSV and some are not included in the Church Directory PDF.

## Root Cause
The code was only using person address fields (`per_Address1`, `per_City`, etc.) without falling back to family address fields (`fam_Address1`, `fam_City`, etc.) when the person's address was empty.

## Changes Made

### Files Modified
1. `src/CSVCreateFile.php` (lines 377-402)
   - Changed from using only person address fields to using person address with family fallback
   - Applied to both "rollup" and "default" export formats
   
2. `src/Reports/DirectoryReport.php` (lines 249-258)
   - Changed single-person directory entries to use person address with family fallback
   - Multi-person families already used family address correctly

### Logic
Changed from:
```php
$sAddress1 = $per_Address1 ?? '';
```

To:
```php
$sAddress1 = !empty($per_Address1) ? $per_Address1 : ($fam_Address1 ?? '');
```

This ensures:
- If person has their own address → use person's address
- If person's address is empty → use family's address
- Works for: Address1, Address2, City, State, Zip, Country, Phone, Email

## Manual Testing Scenarios

### Scenario 1: CSV Export - Person with Personal Address
**Setup:**
1. Create a person in a family
2. Set the family address to: "123 Family St, Familyville, ST 12345"
3. Set the person's address to: "456 Personal Ave, Personaltown, ST 67890"

**Test:**
1. Navigate to CSVExport.php
2. Select "Address 1", "City", "State", "Zip"
3. Click "Create File"

**Expected Result:**
- Person's CSV row should show: "456 Personal Ave", "Personaltown", "ST", "67890"
- Should NOT show family address

### Scenario 2: CSV Export - Person without Personal Address
**Setup:**
1. Create a person in a family
2. Set the family address to: "123 Family St, Familyville, ST 12345"
3. Leave the person's address fields empty

**Test:**
1. Navigate to CSVExport.php
2. Select "Address 1", "City", "State", "Zip"
3. Click "Create File"

**Expected Result:**
- Person's CSV row should show: "123 Family St", "Familyville", "ST", "12345"
- Should show family address (THIS WAS THE BUG - it would show empty before)

### Scenario 3: CSV Export - Family Rollup Format
**Setup:**
1. Create a family with 2+ members
2. Set the family address to: "789 Rollup Rd, Rolluptown, ST 11111"
3. Leave all person addresses empty

**Test:**
1. Navigate to CSVExport.php
2. Select "Output Method" → "CSV Combine Families"
3. Select "Address 1", "City", "State", "Zip"
4. Click "Create File"

**Expected Result:**
- Family row should show: "789 Rollup Rd", "Rolluptown", "ST", "11111"

### Scenario 4: Directory PDF - Single Person
**Setup:**
1. Create a single-person family (person not attached to family or family with only 1 member)
2. Set the family address to: "999 Single Ln, Singleville, ST 99999"
3. Leave the person's address fields empty

**Test:**
1. Navigate to DirectoryReports.php
2. Check "Address" option
3. Click "Create Directory"

**Expected Result:**
- Person should show with address: "999 Single Ln, Singleville, ST 99999"
- (THIS WAS THE BUG - would show no address before)

### Scenario 5: Directory PDF - Multi-Person Family
**Setup:**
1. Create a family with 2+ members
2. Set the family address to: "111 Multi Blvd, Multitown, ST 22222"

**Test:**
1. Navigate to DirectoryReports.php
2. Check "Address" option
3. Click "Create Directory"

**Expected Result:**
- Family should show with address: "111 Multi Blvd, Multitown, ST 22222"
- (This already worked before the fix)

### Scenario 6: Backward Compatibility - Person Overrides Family
**Setup:**
1. Create a person in a family
2. Set the family address to: "123 Family St, Familyville, ST 12345"
3. Set the person's address to different values for only some fields:
   - Person Address1: "456 Personal Ave"
   - Person City: "" (empty)
   - Person State: "" (empty)
   - Person Zip: "67890"

**Test:**
1. Export to CSV or generate directory

**Expected Result:**
- Should show: "456 Personal Ave" (person's address1)
- Should show: "Familyville" (family's city)
- Should show: "ST" (family's state)
- Should show: "67890" (person's zip)

## Automated Testing
Currently no automated tests exist for CSV export address functionality. Future work could include:
- Cypress test that creates test data with the scenarios above
- Verifies CSV output contains expected address data
- Verifies PDF directory generation includes addresses

## Regression Testing
Verify that existing functionality still works:
- ✓ PHP syntax validation passed
- ✓ Composer validation passed
- Manual testing of CSV export required
- Manual testing of Directory PDF required

## Related Code
- `src/ChurchCRM/Reports/PdfDirectory.php` - Already uses family address for multi-person families (line 313-326)
- `src/ChurchCRM/Reports/PdfDirectory.php` - Head/spouse strings use person data (line 343-423)
