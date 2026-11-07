# Deposit Reports release validation and documentation handoff

Tracks #11015. Implementation merged in #10977; its final build, PHP regression, Cypress shards, OpenAPI, lint and security workflows passed. The following visual and physical checks are still required. Latest published release was 7.8.2 when this handoff was prepared; do not publish this workflow as released documentation until the release containing #10977 exists.

## Acceptance record

- Verification date: 2026-11-03; checkout from master `f0ebcceba7d9ee87df8d1cbdae65a415b0bd27c2`. Implementation remains merged in #10977, #11027 and #11028; #11015 remains open for this evidence and post-release user documentation.
- Preparation: `npm run env:check -- integration --services` exited 0, 19/19 checks passed; `npm run docker:test:reset:db` exited 0 using the standard seed.
- Required workflow command: `npx --no-install cypress run --config-file cypress/configs/docker.config.ts --spec "cypress/e2e/ui/finance/deposit-reports.spec.js,cypress/e2e/ui/finance/finance.deposit-editor.spec.js"` exited 0. Deposit reports: 10 passed, 0 failed, 0 pending, 0 skipped. Deposit editor: 6 passed, 0 failed, 0 pending, 0 skipped. Total: 16 passed, 0 failed, 0 pending, 0 skipped.
- Screenshot inspection: each file below was opened and inspected in `cypress/screenshots/deposit-reports.spec.js/` (copies retained locally in `/tmp/issue-11015-evidence/deposit-reports.spec.js/`). These are screenshot defects; their cause was not established as a live product defect. The six viewport overflow assertions passed, but the captures do not establish horizontal-scroll usability, keyboard access, focus visibility or accessible error feedback. The specs checked error text and retained filters, not keyboard operation or error announcements. Live keyboard/focus inspection was blocked by the native browser tool error `Sky Computer Use native pipe closed before response`.
  - `deposit-report-light-desktop.png` (1440 requested): FAIL — right edge clips Download Report and Payment Date; date/output controls repeat across a capture seam; bank section is cut off.
  - `deposit-report-light-tablet.png` (820): FAIL — warning and Review Payments sections repeat across a capture seam; lower report/bank actions are missing; visible labels wrap legibly.
  - `deposit-report-light-phone.png` (390): FAIL — repeated header/warning/form sections; Apply Report Dates To collides with Advanced Settings, with output/preview controls missing; floating menu obscures the deposit control/help.
  - `deposit-report-dark-desktop.png` (1440 requested): FAIL — right edge clips Download Report and Payment Date; deposit/date/output controls repeat across a capture seam; bank section is cut off.
  - `deposit-report-dark-tablet.png` (820): FAIL — page title/warning/form sections repeat across a capture seam; output/preview and lower report/bank actions are missing; visible labels wrap legibly.
  - `deposit-report-dark-phone.png` (390): FAIL — repeated header/warning/form sections; output/preview and Advanced Settings controls are missing; floating menu obscures the deposit control/help and end-date control.
- Persona gates: existing API and UI `<persona>.gates.spec.js` specs under `cypress/e2e/{api,ui}/personas/`, run with the Docker config for `finance-only`, `office-staff` and `zero-permission-staff`, exited 0. API counts respectively: 13, 16, 42 passed; UI counts respectively: 6, 8, 8 passed. Total: 93 passed, 0 failed, 0 pending, 0 skipped. Deposit Reports opened for Finance and redirected office/zero-permission staff to access denied; finance API gates allowed Finance and denied both other personas.
- Both Cypress commands warned that existing run results could not be trashed (`spawn Unknown system error -86`); both completed successfully. Screenshot existence was not treated as a pass.
- Step 5 BLOCKED — read-only reconciliation preflight of the reset database found no electronic payments: all recorded Payment rows were CASH (9 fund rows, 645.00) or CHECK (24 fund rows, 14,421.00). Deposit 1 (Updated Test Deposit): cash 150.00 + checks 100.00 = 250.00; deposit 2: checks 900.00; deposit 3: checks 421.00; deposit 4: cash 50.00; deposit 5: cash 445.00 + checks 13,000.00 = 13,445.00. Split-fund checks exist in deposit 2 (check 100: 700.00 across three funds) and deposit 3 (check 773: 346.00; check 5532: 75.00, each across three funds), neither with cash or electronic payments. No seeded deposit has the required mix. Stopped without custom seeding or alternate specs. No qualifying deposit, analysis PDF/CSV totals, bank-ticket total or accompanying summary was compared; the automated temporary cash/card fixture does not satisfy this reconciliation.
- Actual PDF appearance and representative RTL content: pending; no appearance pass claimed.
- Physical QuickBooks stock printing, alignment and overflow: still outstanding. No printer or stock configuration was available; no physical printing was attempted.
- Published user documentation and updated capture images: pending the release; post-release publication and the visuals capture run were outside this pass.

## Run and inspect

Use a prepared checkout and disposable seeded test instance:
1. npm run env:check -- integration --services
2. npm run docker:test:reset:db
3. npx --no-install cypress run --config-file cypress/configs/docker.config.ts --spec "cypress/e2e/ui/finance/deposit-reports.spec.js,cypress/e2e/ui/finance/finance.deposit-editor.spec.js"
4. Inspect all six deposit-report screenshots in cypress/screenshots. Verify labels, wrapping, horizontal scrolling, keyboard access, focus and accessible error feedback; a screenshot existing is not a visual pass.
5. Run the finance persona gate specs for allowed Finance users and denied office/zero-permission users.
6. Generate both PDFs from a deposit with cash, split-fund checks and electronic payments. Match the analysis PDF and CSV to recorded payments. Bank ticket cash/check amount must exclude electronic payments; the accompanying summary includes them.
7. Print on the configured QuickBooks stock at actual size. Verify date, every check/amount, subtotal, total, item count, perforation and witness/count-sheet positions. Repeat with enough checks to fill the ticket; do not claim alignment or overflow verified from a PDF header check.
8. Record actual pass counts, screenshot locations, paper/printer configuration and any layout failures in #11015.

Use the separate worktree/project prescribed by the marketing pipeline card for the finance-deposit-reports capture. Inspect desktop/tablet/mobile images; generated images and manifests remain uncommitted. No capture run is claimed by this handoff.

## User documentation draft (publish after release)

### Review payments

Finance staff open **Finance → Financial Reports → Deposit Reports**. Choose a date range and whether it applies to payment dates or deposit dates. Alternatively, choose a deposit; that selection overrides the dates.

Use **Preview Totals** to check the payment count and total before downloading. Choose PDF or CSV and select **Download Report**. Open **Advanced Settings** to group by deposit, fund or family, change detail level, or filter classifications, families, funds and payment methods. Empty filters include all matching payments. **Reset Filters** starts over.

The deposit search also links to Deposit Reports. From a deposit's editor, **Deposit Reports** opens with that deposit already selected.

### Prepare a deposit for the bank

Choose one deposit and select **Print Bank Slip**, or use the direct action in its editor. This output prints the whole deposit, ignoring the analysis filters. The physical ticket totals cash and checks; electronic payments remain visible in the summary but are excluded from the bank ticket amount. Count sheets and witness lines support the offering-counting workflow.

The reports require Finance permission under the current permissions model. Ordinary office staff and members cannot access them. This change does not introduce a separate permission for offering counters.

## Publication checklist

After the containing release is published, open the docs.churchcrm.io change using that release milestone. Verify the menu labels against the released application, link the generated finance-deposit-reports capture, retain these two task-based sections, and link the final docs PR from #11015. Keep #11015 open until the validation and documentation evidence is recorded.
