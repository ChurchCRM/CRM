const editors = [
    ["/admin/finance/pledge-reminders", "Pledge Reminders", ["sReminder1", "sReminderSigner", "sReminderNoPledge", "sReminderNoPayments"]],
    ["/admin/finance/zero-givers", "Zero Givers", ["sZeroGivers", "sZeroGivers2", "sZeroGivers3"]],
    ["/admin/finance/options", "Deposits & Payments", ["iFYMonth", "iMaxTaxYears", "sDepositSlipType", "bDisplayBillCounts", "bUseScannedChecks", "bRequireCheckNumber", "bEnableNonDeductible", "bUseDonationEnvelopes"]],
];

describe("Finance Settings hub", () => {
    it("is links-only and opens each destination", () => {
        cy.setupAdminSession();
        cy.visit("/admin/finance");
        cy.get(".page-title").should("contain.text", "Finance Settings");
        cy.get(".settings-panel-fields").should("not.exist");

        for (const path of [
            "/finance/funds",
            "/finance/envelopes",
            "/finance/statements/settings",
            "/admin/system/report-settings",
            "/admin/finance/pledge-reminders",
            "/admin/finance/zero-givers",
            "/admin/finance/quickbooks",
            "/admin/finance/options",
        ]) {
            cy.get(`a.card[href$='${path}']`).should("be.visible");
        }
        cy.get("a.card").should("have.length", 8);
    });

    describe("dedicated editors", () => {
        for (const [path, title, keys] of editors) {
            it(`${title} shows its settings and links back to the hub`, () => {
                cy.setupAdminSession();
                cy.visit(path);
                cy.get(".page-title").should("contain.text", title);
                cy.get(".settings-panel-fields", { timeout: 30000 }).should("not.be.disabled");
                for (const key of keys) {
                    cy.get(`[name='${key}']`).should("not.be.disabled");
                }
                cy.get(".page-header .breadcrumb a[href$='/admin/finance']").click();
                cy.location("pathname").should("match", /\/admin\/finance$/);
            });
        }
    });

    describe("auto-save", () => {
        const keys = ["sReminderSigner", "bEnableNonDeductible"];
        const originalValues = {};

        before(() => {
            for (const key of keys) {
                cy.getSystemConfig(key).then((value) => {
                    originalValues[key] = value;
                });
            }
        });

        after(() => {
            for (const key of keys) {
                cy.restoreSystemConfig(key, originalValues[key]);
            }
        });

        it("saves text and boolean settings on their pages and reloads their values", () => {
            const signer = "[name='sReminderSigner']";
            const nonDeductible = "input[name='bEnableNonDeductible']";
            const newSigner = `Finance test signer ${Date.now()}`;

            cy.setupAdminSession();
            cy.intercept("POST", "**/admin/api/system/config/sReminderSigner").as("saveSigner");
            cy.intercept("POST", "**/admin/api/system/config/bEnableNonDeductible").as("saveNonDeductible");

            cy.visit("/admin/finance/pledge-reminders");
            cy.get(signer, { timeout: 30000 }).should("not.be.disabled").clear().type(newSigner).blur();
            cy.wait("@saveSigner").its("response.statusCode").should("eq", 200);
            cy.reload();
            cy.get(signer, { timeout: 30000 }).should("have.value", newSigner);

            cy.visit("/admin/finance/options");
            cy.get(".settings-panel-fields", { timeout: 30000 }).should("not.be.disabled");
            cy.get(nonDeductible).first().then(($switch) => {
                const nextValue = $switch.is(":checked") ? "0" : "1";
                cy.wrap($switch).click({ force: true });
                cy.wait("@saveNonDeductible").its("response.statusCode").should("eq", 200);
                cy.reload();
                cy.get(".settings-panel-fields", { timeout: 30000 }).should("not.be.disabled");
                cy.get(nonDeductible).should(nextValue === "1" ? "be.checked" : "not.be.checked");
            });
        });
    });

    describe("QuickBooks editor", () => {
        let original;

        before(() => {
            cy.getSystemConfig("sQBDTSettings").then((value) => {
                original = value;
            });
        });

        after(() => {
            cy.restoreSystemConfig("sQBDTSettings", original);
        });

        it("rejects an incomplete or invalid set and saves a complete one", () => {
            cy.setupAdminSession();
            cy.intercept("POST", "**/admin/api/finance/quickbooks").as("save");
            cy.visit("/admin/finance/quickbooks");
            cy.get("#quickbooksForm input[type='number']").should("have.length", 18);

            cy.get("#qb-leftX").clear();
            cy.get("#quickbooksSave").click();
            cy.get("@save.all").should("have.length", 0);

            cy.get("#qb-leftX").type("-5");
            cy.get("#quickbooksSave").click();
            cy.get("@save.all").should("have.length", 0);

            cy.get("#qb-leftX").clear().type("66");
            cy.get("#quickbooksSave").click();
            cy.wait("@save").its("response.statusCode").should("eq", 200);
            cy.reload();
            cy.get("#qb-leftX").should("have.value", "66");
            cy.get("#qb-titleX").should("not.have.value", "");
        });

        it("refuses an incomplete set from the API", () => {
            cy.setupAdminSession();
            cy.visit("/admin/finance/quickbooks");
            cy.get("#quickbooksForm").then(($form) => {
                const token = $form.attr("data-csrf-token");
                cy.request({
                    method: "POST",
                    url: "/admin/api/finance/quickbooks",
                    headers: { "X-CSRF-Token": token },
                    body: { leftX: "10" },
                    failOnStatusCode: false,
                }).its("status").should("eq", 400);
            });
        });
    });

    describe("navigation from finance pages", () => {
        const pages = [
            ["/finance/funds", "Donation Funds"],
            ["/finance/envelopes", "Envelope Manager"],
        ];
        const breadcrumbLink = ".page-header .breadcrumb a[href$='/admin/finance']";
        const headerLink = ".page-header .btn-list a[href$='/admin/finance']";
        const envelopeNumbersButton = ".page-header .btn-list a[href*='/finance/reports/envelope-numbers']";

        it("shows Finance Settings links for an admin", () => {
            cy.setupAdminSession();

            for (const [path, title] of pages) {
                cy.visit(path);
                cy.get(".page-title").should("contain.text", title);
                cy.get(breadcrumbLink).should("be.visible");
                cy.get(headerLink).should("be.visible");
            }

            cy.get(envelopeNumbersButton).should("be.visible");
        });

        it("hides Finance Settings links for a finance-only user", () => {
            cy.setupFinanceOnlySession();

            for (const [path, title] of pages) {
                cy.visit(path);
                cy.get(".page-title").should("contain.text", title);
                cy.get(breadcrumbLink).should("not.exist");
                cy.get(headerLink).should("not.exist");
            }

            cy.get(envelopeNumbersButton).should("be.visible");
        });
    });

    describe("direct access", () => {
        const paths = ["/admin/finance", ...editors.map(([path]) => path), "/admin/finance/quickbooks"];

        it("denies a standard user", () => {
            cy.setupStandardSession();
            for (const path of paths) {
                cy.visit(path);
                cy.location("pathname").should("match", /\/v2\/access-denied$/);
                cy.location("search").should("include", "role=Admin");
            }
        });

        it("denies a finance-only user", () => {
            cy.setupFinanceOnlySession();
            for (const path of paths) {
                cy.visit(path);
                cy.location("pathname").should("match", /\/v2\/access-denied$/);
                cy.location("search").should("include", "role=Admin");
            }
        });
    });
});
