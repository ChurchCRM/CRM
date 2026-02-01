# Attendance CSV Import

This feature allows you to import attendance data from CSV files into ChurchCRM, making it easy to track member attendance for services and events.

## Overview

The Attendance CSV Import feature is designed to work with barcode scanning systems or any system that generates attendance data in CSV format. Each member can have an ID card with a barcode, and when they scan their card at church, the system captures the member ID, date, and time. This data can then be imported into ChurchCRM.

## Features

- **CSV Import**: Upload attendance data from CSV files
- **Flexible Format**: Supports multiple CSV formats (PersonID + Date + Time, or PersonID + DateTime)
- **Column Mapping**: Intelligent auto-detection of CSV columns with manual override options
- **Event Association**: Associate attendance records with existing events or create new recurring events
- **Person View**: View attendance history for each person on their profile page
- **Import Summary**: Detailed import results showing successful imports and any errors

## CSV File Format

### Option 1: Separate Date and Time Columns

```csv
PersonID,Date,Time
123,2024-01-15,09:30:00
124,2024-01-15,09:35:00
125,2024-01-15,09:32:00
```

### Option 2: Combined DateTime Column

```csv
PersonID,DateTime
123,2024-01-15 09:30:00
124,2024-01-15 09:35:00
125,2024-01-15 09:32:00
```

### Field Descriptions

- **PersonID**: The unique ID of the person in ChurchCRM (required)
- **Date**: The date of attendance in YYYY-MM-DD format (required if using separate date/time)
- **Time**: The time of attendance in HH:MM:SS format (optional, defaults to 00:00:00)
- **DateTime**: Combined date and time (alternative to separate Date and Time fields)

## How to Use

### Step 1: Prepare Your CSV File

1. Export attendance data from your barcode scanning system or create a CSV file manually
2. Ensure the file includes Person IDs that match the IDs in ChurchCRM
3. Save the file with a `.csv` extension

### Step 2: Access the Import Page

1. Log in to ChurchCRM as an administrator
2. Navigate to **Admin** → **Attendance CSV Import**

### Step 3: Upload and Map Columns

1. Click **Choose File** and select your CSV file
2. Click **Upload and Map Columns**
3. Review the data preview showing the first 8 rows
4. Map each CSV column to the appropriate field:
   - **Person ID (Member ID)**: The column containing member IDs
   - **Date**: The column containing the date
   - **Time**: The column containing the time (if separate)
   - **Date & Time Combined**: Use this if your CSV has a single datetime column
5. Check **Ignore first CSV row** if your file has a header row

### Step 4: Select or Create an Event

1. Choose an existing event from the dropdown, or
2. Select one of the "Create New Event" options:
   - **Sunday Service (New)**: For regular Sunday services
   - **Fellowship Group (New)**: For fellowship group meetings
   - **Other (New)**: For custom events (you'll be prompted to enter a name)

### Step 5: Import

1. Click **Import Attendance Data**
2. Review the import summary:
   - **Imported**: Number of successfully imported records
   - **Skipped**: Number of records that were skipped (duplicates, invalid data, etc.)
   - **Errors/Warnings**: List of any issues encountered during import

## Viewing Attendance Records

### Individual Person View

1. Navigate to a person's profile (**People** → select a person)
2. Click on the **Attendance** tab
3. View the attendance history table showing:
   - Event name
   - Check-in date/time
   - Check-out date/time (if recorded)
4. Use the DataTable search and sort features to find specific records

### Event View

Attendance records are also visible from the event management interface:
1. Navigate to **Events** → **Calendar**
2. Select an event to view its details
3. View the list of attendees and their check-in/check-out times

## Database Schema

Attendance data is stored in the `event_attend` table with the following structure:

- `attend_id`: Primary key (auto-increment)
- `event_id`: Foreign key to events table
- `person_id`: Foreign key to person table
- `checkin_date`: Timestamp of check-in
- `checkout_date`: Timestamp of check-out (nullable)

## Error Handling

The import process validates each record and will skip records with issues:

- **Person not found**: If the Person ID doesn't exist in the database
- **Invalid date/time**: If the date/time cannot be parsed
- **Duplicate records**: If an attendance record already exists for the same person, event, and date
- **Missing required fields**: If Person ID or date is missing

All errors are logged and displayed in the import summary for review.

## Best Practices

1. **Test with a small file first**: Import a small sample file to verify the format before importing large datasets
2. **Use consistent date formats**: Stick to YYYY-MM-DD format for dates
3. **Regular imports**: Import attendance data regularly (e.g., weekly) to keep records up-to-date
4. **Create recurring events**: Create events like "Sunday Service" once and reuse them for all imports
5. **Verify Person IDs**: Ensure Person IDs in your CSV match the IDs in ChurchCRM

## Troubleshooting

### "Person not found" errors
- Verify that the Person IDs in your CSV match the IDs in ChurchCRM
- Check if persons were imported with correct IDs

### "Invalid date/time format" errors
- Ensure dates are in YYYY-MM-DD format
- Ensure times are in HH:MM:SS format (or use a combined datetime field)

### Duplicate attendance records
- The system prevents duplicate records for the same person, event, and date
- If you need to re-import, delete the existing attendance records first

### Import shows "Skipped" but no errors
- This usually means duplicate records were found and skipped
- Check the person's attendance history to verify existing records

## API Integration (Advanced)

For developers who want to integrate attendance tracking programmatically, the `AttendanceService` class provides methods for:

- `importAttendanceRecords(array $csvData, int $eventId)`: Import attendance records
- `getPersonAttendance(int $personId, ?string $startDate, ?string $endDate)`: Get attendance history
- `getPersonAttendanceCount(int $personId, ?int $eventId)`: Get attendance count
- `getOrCreateRecurringEvent(string $eventTitle, int $eventTypeId)`: Get or create an event

See `src/ChurchCRM/Service/AttendanceService.php` for implementation details.
