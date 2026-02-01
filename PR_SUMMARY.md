# PR Summary: Fix Address Export Issue

## Problem Statement
Family and person addresses don't export to CSV and some are not included in the Church Directory PDF.

## Root Cause Analysis
The code was only using person-level address fields without falling back to family-level address fields when person data was empty.

### Before (Problematic Code)
```php
// Only used person fields - no fallback
$sAddress1 = $per_Address1 ?? '';
$sCity = $per_City ?? '';
$sState = $per_State ?? '';
$sZip = $per_Zip ?? '';
// Result: Empty addresses when person record has no address
```

### After (Fixed Code)
```php
// Uses person data with family fallback + null safety
$sAddress1 = !empty($per_Address1) ? $per_Address1 : ($fam_Address1 ?? '');
$sCity = !empty($per_City) ? $per_City : ($fam_City ?? '');
$sState = !empty($per_State) ? $per_State : ($fam_State ?? '');
$sZip = !empty($per_Zip) ? $per_Zip : ($fam_Zip ?? '');
// Result: Shows family address when person address is empty
```

## Changes Summary

### Files Modified
1. **src/CSVCreateFile.php** (lines 377-402)
   - Added family address fallback for both "rollup" and "default" CSV export formats
   - Applied to: Address1, Address2, City, State, Zip, Country, HomePhone, Email
   - Includes null coalescing operators for safety

2. **src/Reports/DirectoryReport.php** (lines 249-258)
   - Added family address fallback for single-person directory entries
   - Multi-person family entries already used family address correctly
   - Applied to: Address1, Address2, City, State, Zip, HomePhone, Email

3. **TESTING_ADDRESS_EXPORT.md** (new file)
   - Comprehensive manual testing documentation
   - 6 detailed test scenarios
   - Expected results for each scenario
   - Regression testing checklist

### Commit History
1. `Initial plan` - Analysis and planning
2. `Fix address export: use family address as fallback when person address is empty` - Core fix
3. `Add comprehensive testing documentation for address export fix` - Testing guide
4. `Add null coalescing operators to prevent null values in address fallback` - Null safety
5. `Update documentation to reflect null coalescing operator in code` - Doc accuracy

## Statistics
- **3 files changed**
- **173 insertions(+)**, **27 deletions(-)**
- **146 lines of testing documentation**
- **0 security vulnerabilities introduced**
- **0 syntax errors**

## Testing Status
- ✅ PHP syntax validation passed
- ✅ Composer validation passed  
- ✅ Code review completed and feedback addressed
- ✅ Null safety ensured
- ✅ Documentation created and verified
- ⏳ Manual testing pending (requires running instance)

## Impact Analysis

### What This Fixes
1. **CSV Export - Individual Records**: Persons without personal address now show family address
2. **CSV Export - Family Rollup**: Families show address even when individual members lack personal addresses
3. **PDF Directory - Single Person**: Single-person families now show address in directory
4. **PDF Directory - Multi-Person**: Already worked, unchanged

### What Remains Unchanged
- Person records with personal address still use their own address (no regression)
- Multi-person family directory entries still work the same way
- All other export and directory functionality unchanged

### Backward Compatibility
- Fully backward compatible
- Only changes behavior for empty person address fields (which previously showed nothing)
- Does not affect records where person has their own address entered

## Code Quality Improvements
1. **Null Safety**: Added null coalescing operators to prevent null values in output
2. **Consistency**: Both CSV and Directory now use the same fallback pattern
3. **Documentation**: Comprehensive testing guide for maintainers
4. **Comments**: Updated code comments to reflect new behavior

## Future Considerations
Code review suggested extracting the repeated fallback pattern into a helper function:
```php
function getFieldWithFamilyFallback($personValue, $familyValue) {
    return !empty($personValue) ? $personValue : ($familyValue ?? '');
}
```

This is a good refactoring opportunity but was excluded from this PR to maintain minimal changes per project guidelines. It can be addressed in a future cleanup PR.

## Verification Steps for Reviewers
1. Check CSV export with person having no address → should show family address
2. Check CSV export with person having address → should show person address
3. Check PDF directory with single-person family → should show address
4. Verify no regressions in multi-person family directory entries
5. Confirm null safety with missing family data

## Related Issue
Fixes the issue reported in ChurchCRM version 6.7.1 where addresses were not appearing in exports and directories.
