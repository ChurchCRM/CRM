# Replace `bootstrap-datepicker` and `daterangepicker` with `flatpickr`

## Summary
Replace legacy date pickers (`bootstrap-datepicker`, `daterangepicker`) with `flatpickr` sitewide if both can be fully replaced. Add tests that exercise the existing picker usages so we can validate behavior after replacement and ensure the new pickers match Tabler UX.

## Motivation
- `bootstrap-datepicker` is unmaintained and not well adapted to BS5/Tabler styling.
- `daterangepicker` pulls in `moment.js`, which is large and unshakable.
- Consolidating to a single modern picker reduces bundle size and maintenance surface.

## Proposed plan (workers)
- Audit all usages of `bootstrap-datepicker` and `daterangepicker` across the repo.
- Determine whether every `daterangepicker` usage can be implemented with `flatpickr` range mode. If not, list exceptions.
- Replace picker initialization code with `flatpickr` equivalents, preserving options (format, min/maxDate, callbacks).
- Ensure `flatpickr` is styled to match Tabler (icons, input sizing, focus/hover states). Add small CSS overrides if necessary.
- Add or update Cypress tests and any unit tests to interact with the pickers (open, pick dates, range selections, form submissions) where the old libs were used.
- Remove `bootstrap-datepicker`, `daterangepicker`, and `moment` from `package.json` once verified.
- Update `webpack` config if needed and run full test suite.

## Acceptance criteria
- All pages/flows that previously used `bootstrap-datepicker` or `daterangepicker` behave equivalently (People, Finance, Events, Reports).
- Tests covering picker interactions pass in CI.
- `moment.js` removed from final bundles (or reduced if still needed elsewhere).
- Visual and interaction fidelity matches Tabler UX.

## Tasks (checklist)
- [ ] Audit code for `bootstrap-datepicker` usages
- [ ] Audit code for `daterangepicker` usages
- [ ] Prototype `flatpickr` replacement in one area (e.g., People -> Date of Birth)
- [ ] Implement replacements sitewide
- [ ] Add/adjust tests touching date pickers
- [ ] Remove old dependencies and update build
- [ ] Run and fix tests
- [ ] Open PR and request review

## Notes / Implementation hints
- Use `flatpickr`'s `mode: "range"` for range pickers. It supports callbacks for `onChange`, `onClose` etc.
- For formatting/parse helpers, prefer `date-fns` or native `Intl.DateTimeFormat` instead of `moment`.
- When matching Tabler, we mostly need to ensure input height, border-radius, and icon placement match; Tabler uses small input icons — prefer `flatpickr` `appendTo` or custom button elements for consistent placement.
- Add Cypress interactions that: open picker, select date(s), assert input value formatting, submit form and assert saved date.

## Suggested assignees
- Frontend developer familiar with Tabler/Bootstrap 5 and Cypress tests.

---

Created by automated draft for migration task.
