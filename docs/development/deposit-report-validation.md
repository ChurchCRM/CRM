# Deposit Reports release validation and documentation handoff

Tracks #11015. Implementation merged in #10977; its final build, PHP regression, Cypress shards, OpenAPI, lint and security workflows passed. The following visual and physical checks are still required. Latest published release was 7.8.2 when this handoff was prepared; do not publish this workflow as released documentation until the release containing #10977 exists.

## Acceptance record

- One Finance hub entry, advanced settings, filter preservation, bank-slip separation, preserved PDF API and persona gates: implemented and covered by the merged automated workflow.
- Light/dark at desktop 1440, tablet 820 and phone 390: new regression cases in deposit-reports.spec.js; screenshots are generated when run, not claimed here.
- Actual PDF appearance, representative RTL content and QuickBooks stock alignment: pending.
- Published user documentation and updated capture images: pending the release.

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
