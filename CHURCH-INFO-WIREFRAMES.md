# Church Information Configuration - UI/UX Wireframe & Flow

**Issue**: [#8190](https://github.com/ChurchCRM/CRM/issues/8190)

---

## Page Layout & Wireframe

### Desktop View - Church Information Page

```
┌─────────────────────────────────────────────────────────────────┐
│ ChurchCRM Admin Panel                              Dark Menu    │
├────────────────────────────────┬────────────────────────────────┤
│ Vertical Tabs                  │ Form Content Area              │
│                                │                                │
│ ☑ Basic Information            │  ┌──────────────────────────┐ │
│   Location                     │  │ Church Name *             │ │
│   Contact Info                 │  │ ┌────────────────────────┐│ │
│   Map & Coordinates            │  │ │ First Baptist Church  ││ │
│   Display Options              │  │ └────────────────────────┘│ │
│                                │  │                            │ │
│                                │  │ Website                  │ │
│                                │  │ ┌────────────────────────┐│ │
│                                │  │ │ www.firstbaptist.com ││ │
│                                │  │ └────────────────────────┘│ │
│                                │  │                            │ │
│                                │  │ [Save Changes] [Cancel]   │ │
│                                │  └──────────────────────────┘ │
│                                │                                │
└────────────────────────────────┴────────────────────────────────┘
```

### Tab Content: Basic Information

```
┌─ Basic Information ────────────────────────────────────────┐
│                                                             │
│ Church Name *                                               │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ First Baptist Church                                 │  │
│ └──────────────────────────────────────────────────────┘  │
│ Required. Used on all reports and communications.          │
│                                                             │
│ Website                                                     │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ https://www.firstbaptist.com                         │  │
│ └──────────────────────────────────────────────────────┘  │
│ Optional. URL for your church website.                     │
│                                                             │
│ Church Logo/Header                                         │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ Choose Image... [Browse]                             │  │
│ └──────────────────────────────────────────────────────┘  │
│ Optional. Image used in letters and reports.              │
│                                                             │
│ [Save Changes] [Reset]                                    │
└─────────────────────────────────────────────────────────────┘
```

### Tab Content: Location

```
┌─ Location ────────────────────────────────────────────────┐
│                                                             │
│ Street Address                                              │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ 123 Main Street                                      │  │
│ └──────────────────────────────────────────────────────┘  │
│                                                             │
│ City                    State              ZIP             │
│ ┌─────────────────┐ ┌──────────┐ ┌──────────────┐         │
│ │ Springfield     │ │ IL       │ │ 62701        │         │
│ └─────────────────┘ └──────────┘ └──────────────┘         │
│                                                             │
│ Country                                                     │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ United States                                        │  │
│ └──────────────────────────────────────────────────────┘  │
│                                                             │
│ [ ] Auto-lookup coordinates from address                  │
│                                                             │
│ [Save Changes] [Reset]                                    │
└─────────────────────────────────────────────────────────────┘
```

### Tab Content: Contact Information

```
┌─ Contact Information ─────────────────────────────────────┐
│                                                             │
│ Phone Number                                                │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ (217) 555-0123                                       │  │
│ └──────────────────────────────────────────────────────┘  │
│ Format: (555) 555-5555                                     │
│                                                             │
│ Email Address                                               │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ info@firstbaptist.com                                │  │
│ └──────────────────────────────────────────────────────┘  │
│ Main contact email for church communications.              │
│                                                             │
│ [Save Changes] [Reset]                                    │
└─────────────────────────────────────────────────────────────┘
```

### Tab Content: Map & Coordinates

```
┌─ Map & Coordinates ───────────────────────────────────────┐
│                                                             │
│ Latitude & Longitude                                        │
│ ┌──────────────────┐  ┌──────────────────┐                │
│ │ 39.7817°N        │  │ -89.6501°W       │                │
│ └──────────────────┘  └──────────────────┘                │
│                                                             │
│ Coordinates used for mapping features and distance         │
│ calculations. Click location on a map service to get       │
│ coordinates (e.g., latlong.net)                           │
│                                                             │
│ ☐ Auto-lookup from address [if Google Maps API set]      │
│                                                             │
│ Time Zone *                                                 │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ America/Chicago                                      │  │
│ └──────────────────────────────────────────────────────┘  │
│ Used for scheduling events and reporting times.            │
│                                                             │
│ [Save Changes] [Reset]                                    │
└─────────────────────────────────────────────────────────────┘
```

### Tab Content: Display Preferences

```
┌─ Display Preferences ─────────────────────────────────────┐
│                                                             │
│ Custom Header HTML                                          │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ <h2>First Baptist Church</h2>                        │  │
│ │ <p>Established 1952</p>                              │  │
│ │                                                      │  │
│ └──────────────────────────────────────────────────────┘  │
│ HTML displayed at top of ChurchCRM pages.                  │
│                                                             │
│ Letterhead Image Path                                       │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ ../Images/church_letterhead.jpg                      │  │
│ └──────────────────────────────────────────────────────┘  │
│ Image used on printed directories.                         │
│                                                             │
│ [Save Changes] [Reset]                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## First-Run Workflow Diagram

```
                         ┌─────────────────────────┐
                         │ Fresh Install Complete  │
                         │ Database Created        │
                         └────────────┬────────────┘
                                      │
                                      │ Admin logs in
                                      ▼
                         ┌─────────────────────────┐
                         │ Dashboard Load          │
                         │ Check: sChurchName?     │
                         └────────────┬────────────┘
                                      │
                    ┌─────────────────┴──────────────────┐
                    │                                     │
                    ▼                                     ▼
       ┌────────────────────────┐        ┌──────────────────────────┐
       │ Church Name SET        │        │ Church Name EMPTY        │
       │ → Allow normal nav     │        │ → REDIRECT to /admin/... │
       │   Dashboard shows ✓    │        │   /church-info           │
       └────────────────────────┘        └────────────┬─────────────┘
                        ▲                             │
                        │                             │
                        │                ┌────────────▼─────────────┐
                        │                │ Church Info Page        │
                        │                │ All tabs empty          │
                        │                │ Church Name * required  │
                        │                └────────────┬─────────────┘
                        │                             │
                        │                 ┌───────────▼──────────┐
                        │                 │ Admin fills in tabs  │
                        │                 │ Validates on submit  │
                        │                 └───────────┬──────────┘
                        │                             │
                        │                ┌────────────▼──────────┐
                        │                │ Church Name missing?  │
                        │                │ Yes → Show error      │
                        │                └───────────┬──────────┘
                        │                           │
                        │                    No → Save
                        │                           │
                        └───────────────────────────┘
                                      │
                                      ▼
                        ┌─────────────────────────┐
                        │ Redirect to Dashboard  │
                        │ Show success message   │
                        │ Dashboard shows ✓      │
                        │ Admin can proceed      │
                        └─────────────────────────┘
```

---

## Error & Status States

### Form Validation - Church Name Missing

```
┌─ Basic Information ───────────────────────────────────────┐
│                                                             │
│ Church Name * ⚠️ REQUIRED                                   │
│ ┌──────────────────────────────────────────────────────┐  │
│ │                                                      │  │
│ └──────────────────────────────────────────────────────┘  │
│ ❌ Church name is required. Please enter your church name. │
│ Used on all reports and communications.                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Success Notification (Toast)

```
┌────────────────────────────────────────────────────────────┐
│ ✅ Church information saved successfully!                  │
│ Redirecting to dashboard...                                │
└────────────────────────────────────────────────────────────┘
```

### Dashboard Status - Complete

```
┌─ Update Church Info ──────────────────────────────────────┐
│                                                             │
│ ✅ [green badge]                                            │
│ Church Information                                          │
│ Verify church name, address, and contact info appears      │
│ on tax statements.                                          │
│                              [Edit Church Info] ►           │
└───────────────────────────────────────────────────────────┘
```

### Dashboard Status - Incomplete

```
┌─ Update Church Info ──────────────────────────────────────┐
│                                                             │
│ ⚠️ [red badge]                                              │
│ Church Information INCOMPLETE                              │
│ Your church name is not yet configured. Please set this    │
│ before proceeding.                                          │
│                              [Configure Now] ►              │
└───────────────────────────────────────────────────────────┘
```

---

## Responsive Design - Mobile View

### Mobile: Church Info Page - Tabbed Navigation

```
┌──────────────────────────────┐
│ ◀ Church Information          │
├──────────────────────────────┤
│ • Basic Information (active)  │
│ • Location                    │
│ • Contact Info                │
│ • Map & Coordinates           │
│ • Display Preferences         │
├──────────────────────────────┤
│ Church Name *                 │
│ ┌────────────────────────────┐│
│ │First Baptist Church        ││
│ └────────────────────────────┘│
│                               │
│ Website                       │
│ ┌────────────────────────────┐│
│ │www.firstbaptist.com        ││
│ └────────────────────────────┘│
│                               │
│ [Save Changes]                │
└──────────────────────────────┘
```

---

## Accessibility Features

### ARIA Labels & Semantic HTML

```html
<form role="form" aria-label="Church Information Form">
    <fieldset>
        <legend>Basic Information</legend>
        
        <div class="form-group">
            <label for="sChurchName">
                Church Name
                <span aria-label="required" class="text-danger">*</span>
            </label>
            <input 
                type="text" 
                id="sChurchName"
                name="sChurchName"
                required
                aria-required="true"
                aria-describedby="churchNameHelp"
                autofocus
            >
            <small id="churchNameHelp" class="form-text text-muted">
                Required. Used on all reports and communications.
            </small>
            <div class="invalid-feedback" role="alert">
                Church name is required.
            </div>
        </div>
    </fieldset>
</form>
```

### Keyboard Navigation

- **Tab**: Navigate between fields
- **Shift+Tab**: Navigate backwards
- **Enter** (in form): Submit form
- **Ctrl+S**: Potentially save (bonus enhancement)

### Screen Reader Hints

- Form labels use `<label for="...">` association
- Required fields marked with `aria-required="true"` and visual asterisk
- Help text linked with `aria-describedby`
- Error messages use `role="alert"` for immediate announcement
- Tab names have semantic meaning

---

## Integration Points

### Bootstrap/AdminLTE Integration

Uses existing ChurchCRM:
- Bootstrap 4 utility classes
- AdminLTE card layout
- AdminLTE form styling
- Consistent button styling (btn-primary, btn-secondary)
- Existing color scheme and typography

### Localization Integration

All strings use `gettext()`:
```html
<label><?= gettext('Church Name') ?> <span class="text-danger">*</span></label>
<small class="form-text text-muted">
    <?= gettext('Required. Used on all reports and communications.') ?>
</small>
```

### JavaScript Enhancements (Optional)

- **Form validation**: Client-side validation before submit
- **Tab switching**: Smooth tab transitions
- **Auto-save**: Save individual tabs without full form submit
- **Map integration**: Click-to-lookup coordinates via Google Maps/Nominatim

---

## StateFlow - Tab System

```
┌──────────────────────────────────────────────────────┐
│         Church Info Configuration Page               │
├─────────────────────┬────────────────────────────────┤
│ Tabs (Radio Groups) │ Tab Panes                       │
│                     │                                 │
│ ○ Basic *           │ [Form fields for selected tab]  │
│ ○ Location          │ • Only active tab visible       │
│ ○ Contact           │ • Preserves state on switch     │
│ ○ Map               │ • Form persists until save      │
│ ○ Display           │                                 │
│                     │                                 │
│ Disabled tabs       │ [Save] [Cancel] [Reset]         │
│ (future):           │                                 │
│ • Logo Upload       │                                 │
│ • Multi-Church  │                                 │
└─────────────────────┴────────────────────────────────┘

Tab Selection Event → Update Active Pane
Form Input → Persist to local state / session
Save Click → Validate ALL visible fields → POST to server
Cancel → Reset to last saved state (or close)
```

---

## Color & Visual Hierarchy

```
Typography:
- Page Title: 28px, #333, bold
- Section (tab): 16px, #666, bold
- Form label: 14px, #333, medium
- Help text: 12px, #999, regular
- Input text: 14px, #333, regular

Colors:
- Success (✓): #28a745 (green)
- Error (✗): #dc3545 (red)
- Warning: #ffc107 (yellow)
- Info: #17a2b8 (blue)
- Neutral: #6c757d (gray)

Spacing:
- Form groups: 20px vertical margin
- Tab content: 16px padding
- Button spacing: 8px horizontal margin
- Field spacing: 12px

Borders:
- Form inputs: 1px #ced4da (light gray)
- Focus: 1px #80bdff (light blue), 0.2rem blue shadow
- Tabs: Bottom border on active tab (2px)
```

---

## Delivery Artifacts

### Screenshot Requirements

1. **desktop-tab-basic.png** - Desktop view with Basic Info tab active
2. **desktop-tab-location.png** - Location tab active
3. **mobile-tab-navigation.png** - Mobile view with tab menu open
4. **mobile-form-filled.png** - Mobile view with form values
5. **validation-error.png** - Form with validation error
6. **success-notification.png** - Success toast message
7. **dashboard-status-complete.png** - Dashboard with ✓ badge
8. **dashboard-status-incomplete.png** - Dashboard with ✗ badge
9. **first-run-flow-diagram.png** - First-run workflow visual

---

**Status**: ✅ UX/Wireframes Complete  
**Reference**: [#8190](https://github.com/ChurchCRM/CRM/issues/8190)
