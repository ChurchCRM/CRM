# Kiosk Manager Guide

The Kiosk Manager allows you to set up self-service kiosk devices for event check-in, attendance tracking, and other church activities. This guide covers how to register, configure, and manage kiosk devices.

## Overview

Kiosks are dedicated devices (tablets, computers, or touch screens) that can be placed in your church lobby or classrooms to allow members to check in to events, Sunday school classes, or other activities without requiring staff assistance.

## Prerequisites

Before setting up kiosks, ensure you have:

1. **Administrator access** to ChurchCRM
2. **At least one future event** created in the system (kiosks can only be assigned to events with future start dates)

### Creating an Event for Kiosk Check-in

To use a kiosk, you first need an event to assign it to:

1. Navigate to **Events** → **Add Event** in ChurchCRM
2. Create an event (e.g., "Sunday Service" or "Sunday School")
3. Set the **Start Date/Time** to a future date
4. Associate the event with a **Group** if tracking Sunday School attendance
5. Save the event

> **Note:** Only events with start dates **in the future** will appear in the kiosk assignment dropdown. Past events are automatically filtered out.

## Accessing the Kiosk Manager

1. Log in to ChurchCRM as an **administrator**
2. Navigate to **Admin** → **Kiosk Manager**
3. Or go directly to: `https://your-churchcrm-url/kiosk/admin`

> **Note:** Only administrators can access the Kiosk Manager. Standard users will be denied access.

## Registering a New Kiosk

### Step 1: Enable Kiosk Registration

Before a new device can register as a kiosk, you must temporarily enable registration:

1. Go to **Admin** → **Kiosk Manager**
2. Toggle the **Enable New Kiosk Registration** switch to **Active**
3. You have **30 seconds** to register new devices while this is active
4. The toggle shows a countdown and automatically turns off after 30 seconds

![Enable Registration Toggle](images/kiosk-enable-registration.png)

### Step 2: Open the Kiosk URL on Your Device

On the device you want to use as a kiosk:

1. Open a web browser (Chrome, Firefox, Safari, etc.)
2. Navigate to: `https://your-churchcrm-url/kiosk/`
3. The device will automatically register and receive a unique name (e.g., "ipheec")

> **Important:** Make sure the registration window is still active (within 30 seconds) when you access the kiosk URL.

### What the Kiosk Device Shows

When a newly registered kiosk loads, it will display:

- **"Awaiting Acceptance"** status with a yellow hourglass icon
- The **Kiosk Name** assigned to this device
- **Step-by-step instructions** on how to accept the kiosk from the admin panel

The kiosk will automatically update once accepted by an administrator.

### Step 3: Accept the Kiosk

Once registered, the kiosk will appear in your Kiosk Manager with a status of **Not Accepted**:

1. Return to the **Kiosk Manager** page
2. Find the newly registered kiosk in the table (use the Kiosk Name shown on the device)
3. Click the **Accept** button (green checkmark icon) to activate it

![Accept Kiosk](images/kiosk-accept-button.png)

### Step 4: Assign the Kiosk to an Event

After accepting, you can assign the kiosk to a specific event:

1. In the **Assignment** column, a dropdown will appear
2. Select an event from the dropdown (only future events are listed)
3. The kiosk will now be configured for that event's check-in

> **No events in the dropdown?** You need to create a future event first. See the [Prerequisites](#prerequisites) section above.

Once assigned, the kiosk device will display:
- The **event name** and **start/end times**
- A list of **group members** who can check in
- **Check-in/Check-out buttons** for each person

## Managing Kiosks

### Kiosk Table Columns

| Column | Description |
|--------|-------------|
| **Id** | Unique identifier for the kiosk |
| **Kiosk Name** | Auto-generated name (can be used to identify the device) |
| **Assignment** | The event or function assigned to this kiosk |
| **Last Heartbeat** | When the kiosk last communicated with the server |
| **Accepted** | Whether the kiosk has been accepted for use |
| **Actions** | Available operations for the kiosk |

### Action Buttons

| Button | Icon | Description |
|--------|------|-------------|
| **Reload** | 🔄 | Force the kiosk to reload its page |
| **Identify** | 👁️ | Display an identification message on the kiosk screen |
| **Accept** | ✓ | Accept a newly registered kiosk (only shown for unaccepted kiosks) |
| **Delete** | 🗑️ | Remove the kiosk from the system |

### Reloading a Kiosk

If you make configuration changes or need to refresh a kiosk:

1. Click the **Reload** button for that kiosk
2. The kiosk will refresh on its next heartbeat (within 5 seconds)

### Identifying a Kiosk

If you have multiple kiosks and need to identify which physical device corresponds to which entry:

1. Click the **Identify** button
2. The kiosk will display an identification message on screen
3. This helps you match entries in the table to physical devices

### Deleting a Kiosk

To remove a kiosk from the system:

1. Click the **Delete** button (trash icon)
2. Confirm the deletion in the popup dialog
3. The kiosk and its assignments will be permanently removed

> **Warning:** Deleting a kiosk cannot be undone. The device will need to be re-registered if you want to use it again.

## Kiosk Device Setup Tips

### Recommended Browser Settings

For the best kiosk experience, configure the device's browser:

1. **Enable Full Screen Mode** (F11 on most browsers)
2. **Disable browser notifications**
3. **Set the kiosk URL as the homepage**
4. **Enable auto-start** for the browser on device boot

### Chrome Kiosk Mode

For Chrome, you can launch in kiosk mode:

```bash
chrome --kiosk https://your-churchcrm-url/kiosk/
```

### Security Considerations

- Place kiosk devices in supervised areas
- Consider using a locked kiosk enclosure for tablets
- Use a dedicated user account on the device with limited permissions
- Disable access to browser settings if possible

## Troubleshooting

### Kiosk Shows "This kiosk has not been accepted"

1. Go to **Kiosk Manager**
2. Find the kiosk in the list
3. Click **Accept** to activate it

### Kiosk Won't Register

1. Ensure the **Enable New Kiosk Registration** toggle is active
2. You have 30 seconds to complete registration
3. Clear the browser cookies on the device and try again
4. Check that the device can reach your ChurchCRM server

### Kiosk Shows "401 Unauthorized"

This means the registration window has closed:

1. Return to **Kiosk Manager**
2. Enable registration again
3. Quickly refresh the kiosk page

### Events Not Showing in Assignment Dropdown

Only **future events** appear in the assignment dropdown:

1. Make sure you have created events in ChurchCRM (**Events** → **Add Event**)
2. Verify the events have **start dates in the future** (today or later)
3. Refresh the Kiosk Manager page to reload the event list
4. If you just created an event, it should appear immediately after refresh

### Kiosk Not Responding to Commands

Check the **Last Heartbeat** column:

1. If it shows a recent time, the kiosk is connected
2. If it shows "Never" or an old time, the kiosk may be offline
3. Verify the device has network connectivity
4. Try refreshing the browser on the kiosk device manually

## API Reference

For developers integrating with the kiosk system:

### Admin API Endpoints

All admin endpoints require administrator authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kiosk/api/devices` | List all registered kiosks |
| POST | `/kiosk/api/allowRegistration` | Enable 30-second registration window |
| POST | `/kiosk/api/devices/{id}/reload` | Send reload command to kiosk |
| POST | `/kiosk/api/devices/{id}/identify` | Send identify command to kiosk |
| POST | `/kiosk/api/devices/{id}/accept` | Accept a registered kiosk |
| POST | `/kiosk/api/devices/{id}/assignment` | Set kiosk event assignment |
| DELETE | `/kiosk/api/devices/{id}` | Delete a kiosk |

### Device API Endpoints

These endpoints are used by the kiosk devices themselves:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kiosk/` | Kiosk device main page |
| GET | `/kiosk/heartbeat` | Device heartbeat (returns commands) |
| POST | `/kiosk/checkin` | Check in a person to the event |
| POST | `/kiosk/checkout` | Check out a person from the event |

## Related Documentation

- [Event Management](Events.md)
- [Attendance Tracking](Attendance.md)
- [Sunday School Setup](SundaySchool.md)
