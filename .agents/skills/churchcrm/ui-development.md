# Skill: UI Development

## Context
This skill covers UI development practices, conventions, and tools used in ChurchCRM, including Bootstrap, React, and TypeScript.

---

## General Guidelines

1. **Tabler + Bootstrap 5.3.x**:
   - ChurchCRM uses Tabler UI framework (Bootstrap 5.3.x base). AdminLTE and Bootstrap 4 are removed.
   - Use Tabler-specific classes (`btn-ghost-*`, `card-sm`, `avatar`, etc.) alongside standard BS5 utilities.
   - Use BS5 utilities freely: `gap-*`, `d-grid`, `ms-*`, `me-*`, `fw-*` all available.

2. **React + TypeScript**:
   - Use React components for modern UI development.
   - Write components in TypeScript for type safety.
   - Follow the file structure in the `react/` directory.

3. **CSS and Assets**:
   - Use `SystemURLs::getRootPath()` for asset paths (e.g., CSS, images).
   - Avoid inline styles; use Bootstrap classes or external CSS.
   - Move inline `<style>` blocks to webpack assets: `src/assets/css/` for CSS and `src/assets/js/` for JS modules.

4. **Table Design for User-Facing Lists** <!-- learned: 2026-03-14 -->:
   - Keep visible columns focused on essential info (5-6 columns max for scannable views).
   - Use modal popups for detailed information rather than expandable rows (better for print-friendly layouts).
   - For role-specific views (e.g., Sunday School teachers):
     - Show quick-glance stats in cards above the table (enrollment, gender breakdown, activity metrics).
     - Include actionable columns: clickable links to profiles, phone/email for quick contact.
     - Use Bootstrap responsive grid: `col-12 col-md-6 col-lg-4` for mobile-first stacking.
   - Example: Sunday School class view shows (Name, Age, Mobile, Email, Father, Mother) with info icon opening modal for address/parent details.

4. **Internationalization (i18n)**:
   - Wrap all user-facing text in `i18next.t()` (JavaScript) or `gettext()` (PHP).
   - Consolidate terms to reduce translation burden (e.g., reuse "Delete Confirmation").

---

## Bootstrap Usage

- **Grid System**:
  - Use Bootstrap's grid system for layout.
  - Example:
    ```html
    <div class="row">
      <div class="col-md-6">Left Column</div>
      <div class="col-md-6">Right Column</div>
    </div>
    ```

- **Forms**:
  - Use Bootstrap 5 form classes for consistent styling.
  - Use `form-select` for `<select>` elements (not `form-control`).
  - Example:
    ```html
    <form>
      <div class="mb-3">
        <label for="exampleInputEmail1" class="form-label">Email address</label>
        <input type="email" class="form-control" id="exampleInputEmail1">
      </div>
    </form>
    ```

- **Buttons**:
  - Use `btn` classes for buttons.
  - Example:
    ```html
    <button class="btn btn-primary">Submit</button>
    ```

---

## React + TypeScript

1. **Component Structure**:
   - Use functional components.
   - Example:
     ```tsx
     import React from 'react';

     interface Props {
       title: string;
     }

     const MyComponent: React.FC<Props> = ({ title }) => {
       return <h1>{title}</h1>;
     };

     export default MyComponent;
     ```

2. **State Management**:
   - Use React hooks (e.g., `useState`, `useEffect`).
   - Avoid class components.

3. **File Organization**:
   - Place components in the `react/components/` directory.
   - Use descriptive filenames (e.g., `UserCard.tsx`).

4. **Testing**:
   - Write unit tests for components using Jest.
   - Example:
     ```tsx
     import { render, screen } from '@testing-library/react';
     import MyComponent from './MyComponent';

     test('renders title', () => {
       render(<MyComponent title="Hello" />);
       expect(screen.getByText('Hello')).toBeInTheDocument();
     });
     ```

---

## Internationalization (i18n)

1. **JavaScript**:
   - Use `i18next.t()` for translations.
   - Example:
     ```javascript
     window.CRM.notify(i18next.t('Operation completed'), {
       type: 'success',
       delay: 3000
     });
     ```

2. **PHP**:
   - Use `gettext()` for translations.
   - Example:
     ```php
     echo gettext('Welcome to ChurchCRM');
     ```

3. **Consolidation Patterns**:
   - Combine terms to reduce translation workload.
   - Example:
     ```php
     $sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Note');
     ```

---

## Best Practices

- **Responsive Design**:
  - Ensure all pages are mobile-friendly.
  - Test layouts on different screen sizes.

- **Accessibility**:
  - Use semantic HTML (e.g., `<button>` instead of `<div>` for clickable elements).
  - Add `aria-label` attributes for screen readers.

- **Performance**:
  - Optimize images and assets.
  - Avoid unnecessary re-renders in React components.

- **Consistency**:
  - Follow existing patterns in the codebase.
  - Use ChurchCRM's design conventions.

---

For more details, refer to the [Frontend Development](./frontend-development.md) and [Code Standards](./code-standards.md) skills.