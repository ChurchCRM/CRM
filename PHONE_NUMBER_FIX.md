# International Phone Number Fix

## Problem
International phone numbers (e.g., Finnish number `+3587570321908` with 14 digits) were being truncated to 10 digits when editing a person or family record. This occurred because:

1. The HTML input fields for phone numbers have a JavaScript input mask applied based on the system's `sPhoneFormat` configuration
2. The default format `(999) 999-9999` only accepts 10 digits
3. Even though a "Do not auto-format" checkbox existed, it only affected server-side processing and did not disable the client-side input mask

## Solution
Added JavaScript code to dynamically toggle the input mask based on the state of the "Do not auto-format" checkbox:

- When the checkbox is **checked**: The input mask is removed, allowing free-form entry of any phone number format
- When the checkbox is **unchecked**: The input mask is re-applied to enforce the configured format

## Files Changed
1. **src/PersonEditor.php**: Added JavaScript to handle checkbox state changes for HomePhone, WorkPhone, and CellPhone fields
2. **src/skin/js/FamilyEditor.js**: Added the same JavaScript logic for family phone fields

## How It Works

### Automatic Detection
The system automatically detects international phone numbers:
- When editing an existing person/family with an international number, the `ExpandPhoneNumber()` function recognizes it as non-standard
- The "Do not auto-format" checkbox is automatically checked
- Our JavaScript code detects the checked state and removes the input mask on page load
- Users can now see and edit the full international number

### Manual Override
Users can also manually:
- Check the "Do not auto-format" checkbox before entering an international number
- Enter the full phone number without the input mask restricting their input
- Save the form, which will preserve the international number as-is

## Testing

### Manual Test Scenario
1. Navigate to Person Editor (PersonEditor.php)
2. Check the "Do not auto-format" checkbox for Home Phone
3. Enter an international phone number (e.g., `+3587570321908`)
4. Click Save
5. Verify the person view shows the full phone number
6. Edit the person again and verify:
   - The "Do not auto-format" checkbox is automatically checked
   - The full phone number is displayed in the input field
   - The input mask is not applied (you can freely edit the number)

### Expected Behavior
- **With checkbox unchecked**: Input is restricted to the configured format (e.g., 10 digits for US format)
- **With checkbox checked**: Input accepts any format and length up to the database limit (30 characters)

## Database Schema
Phone number fields in the database support `varchar(30)`, which is sufficient for most international phone numbers including country codes and extensions.

## Compatibility
- Works with existing "Do not auto-format" server-side logic
- Maintains backward compatibility with US/Canada phone formatting
- No changes to database schema or API required
- No impact on phone number storage or retrieval logic
