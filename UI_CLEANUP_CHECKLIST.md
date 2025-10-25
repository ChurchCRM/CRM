# UI Style Cleanup - Master Checklist

**Branch:** `ui/style-cleanup`
**Date Started:** October 25, 2025
**Goal:** Review and improve visual appearance, consistency, and user experience across ChurchCRM

## Review Areas

### Pages & Components to Review
- [ ] Dashboard/Home page
- [ ] Family management pages
- [ ] Member/Person profiles
- [ ] Event management UI
- [ ] Financial reports and forms
- [ ] User settings pages
- [ ] Admin panels
- [ ] Search/Filter components
- [ ] Form inputs and validation messages
- [ ] Navigation menus
- [ ] Mobile responsiveness

### Visual Issues to Address
- [ ] Inconsistent spacing and margins
- [ ] Font sizing hierarchy
- [ ] Color scheme consistency
- [ ] Button styling and states
- [ ] Form field styling
- [ ] Modal/Dialog appearance
- [ ] Alert/notification styling
- [ ] Table formatting and alignment
- [ ] Icon consistency
- [ ] Dark/light mode compatibility

### Bootstrap 4/5 Standards
- [ ] Replace deprecated HTML attributes with CSS
- [ ] Use Bootstrap utility classes
- [ ] Ensure proper responsive grid layout
- [ ] Validate component styling
- [ ] Check accessibility (WCAG compliance)
- [ ] Test form styling

### CSS Organization
- [ ] Consolidate duplicate styles
- [ ] Remove unused CSS
- [ ] Standardize class naming
- [ ] Use Bootstrap variables
- [ ] Document custom styles

## Progress Log

### Session 1 - Family View Discovery Phase ✓
**File:** `src/v2/templates/people/family-view.php`

#### Issues Identified:
1. **Button Layout & Consistency Issues:**
   - Navigation buttons (Previous Family, Family List, Next Family) use `.btn-app` which creates uneven spacing
   - Action buttons (Verify Info, Add New Member, Deactivate, Delete, Add Note, etc.) are in separate rows
   - Buttons wrap awkwardly on smaller screens
   - Different button colors are inconsistent (green, orange, maroon, olive)

2. **Specific Button Problems:**
   - "Previous Family", "Family List", "Next Family" buttons should be consolidated into a button group
   - "Verify Info" button is isolated on its own row
   - Buttons need better responsive design (collapse to single column on mobile)
   - Color scheme: green (Add), orange (Deactivate), maroon (Delete), olive (undefined), blue (List)
   - Button text positioning and icon alignment inconsistent

3. **Card Styling Issues:**
   - Family photo card uses `.card-primary` 
   - Navigation card uses `.card` (no color variant)
   - Inconsistent header styling between cards
   - Image container appears to need better styling

4. **Metadata Section Issues:**
   - Uses inline styles for color: `style="color:<?= ($family->isSendNewsletter() ? "green" : "red") ?>"`
   - Should use Bootstrap utility classes instead
   - Font sizing and spacing inconsistent
   - List icons (.fa-li) styling could be improved

5. **Responsive Design Issues:**
   - 6-column layout for photo, 8-column for actions - not optimal
   - No clear breakpoints for mobile/tablet
   - Long button text causes wrapping

#### Recommended Fixes:
- [ ] Create a button group for navigation buttons
- [ ] Consolidate action buttons into a logical grid (2-3 per row on desktop)
- [ ] Replace inline styles with Bootstrap utility classes
- [ ] Use consistent color scheme for similar actions
- [ ] Improve responsive layout for mobile devices
- [ ] Better spacing between button groups
- [ ] Use `.btn-group` or grid layout for button organization

### Session 2 - Implementation (Pending)

## Notes

- Keep all changes focused on visual appearance only
- Maintain backward compatibility
- Don't modify page structure or functionality
- Test changes locally before committing
- Document all CSS changes in commit messages

## Resources

- Bootstrap 4 Documentation: https://getbootstrap.com/docs/4.6/
- Bootstrap 5 Migration: https://getbootstrap.com/docs/5.0/migration/
- WCAG 2.1 Accessibility: https://www.w3.org/WAI/WCAG21/quickref/
