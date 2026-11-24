describe("Finance Settings hub", () => {
    it("shows all finance settings and quick links for an admin", () => {
        const settingsBySection = {
            financeTaxStatements: [
                "sGivingStatementOpening",
                "sGivingStatementClosing",
                "sGivingStatementQuestions",
                "sTaxSigner",
            ],
            financePledgeReminders: [
                "sReminder1",
                "sReminderSigner",
                "sReminderNoPledge",
                "sReminderNoPayments",
            ],
            financeZeroGivers: ["sZeroGivers", "sZeroGivers2", "sZeroGivers3"],
            financeQuickBooks: ["sQBDTSettings"],
            financeOptions: ["bEnableNonDeductible"],
        };

        cy.setupAdminSession();
        cy.visit("/admin/finance");
        cy.get(".page-title").should("contain.text", "Finance Settings");

        for (const [section, keys] of Object.entries(settingsBySection)) {
            cy.get(`#${section} .settings-panel-fields`, {
                timeout: 30000,
            }).should("not.be.disabled");

            for (const key of keys) {
                cy.get(`#${section} [name='${key}']`).should("not.be.disabled");
            }
        }

        for (const path of [
            "/finance/funds",
            "/finance/envelopes",
            "/finance/statements/settings",
        ]) {
            cy.get(`a.card[href$='${path}']`).should("be.visible");
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

        it("saves text and boolean settings and reloads their values", () => {
            const signer = "#financePledgeReminders [name='sReminderSigner']";
            const nonDeductible =
                "#financeOptions input[name='bEnableNonDeductible']";
            const options = "#financeOptions .settings-panel-fields";
            const newSigner = `Finance test signer ${Date.now()}`;

            cy.setupAdminSession();
            cy.intercept(
                "POST",
                "**/admin/api/system/config/sReminderSigner",
            ).as("saveSigner");
            cy.intercept(
                "POST",
                "**/admin/api/system/config/bEnableNonDeductible",
            ).as("saveNonDeductible");
            cy.visit("/admin/finance");

            cy.get(signer, { timeout: 30000 })
                .should("not.be.disabled")
                .clear()
                .type(newSigner)
                .blur();
            cy.wait("@saveSigner").its("response.statusCode").should("eq", 200);

            cy.get(options, { timeout: 30000 }).should("not.be.disabled");
            cy.get(`${nonDeductible}:checked`)
                .invoke("val")
                .then((currentValue) => {
                    const nextValue = currentValue === "1" ? "0" : "1";

                    cy.get(`${nonDeductible}[value='${nextValue}']`)
                        .closest("label")
                        .click();
                    cy.wait("@saveNonDeductible")
                        .its("response.statusCode")
                        .should("eq", 200);

                    cy.reload();
                    cy.get(signer, { timeout: 30000 }).should(
                        "have.value",
                        newSigner,
                    );
                    cy.get(options, { timeout: 30000 }).should(
                        "not.be.disabled",
                    );
                    cy.get(`${nonDeductible}:checked`).should(
                        "have.value",
                        nextValue,
                    );
                });
        });
    });
    it("removes the moved settings from their old pages", () => {
        cy.setupAdminSession();

        cy.visit("/SystemSettings.php");
        cy.get(".page-title").should("contain.text", "System Settings");
        cy.get("[name='new_value[sConfirm1]']").should("exist");

        for (const key of [
            "sQBDTSettings",
            "sReminder1",
            "sReminderSigner",
            "sReminderNoPledge",
            "sReminderNoPayments",
            "sZeroGivers",
            "sZeroGivers2",
            "sZeroGivers3",
        ]) {
            cy.get(`[name='new_value[${key}]']`).should("not.exist");
        }

        cy.visit("/admin/system/feature-toggles");
        cy.get(".page-title").should("contain.text", "Feature Toggles");
        cy.get(".feature-toggle[data-setting='bEnabledEvents']").should(
            "exist",
        );
        cy.get("[data-setting='bEnableNonDeductible']").should("not.exist");
        cy.contains(".card-title", /^Finance Features$/).should("not.exist");
    });
    describe("navigation from finance pages", () => {
        const pages = [
            ["/finance/funds", "Donation Funds"],
            ["/finance/envelopes", "Envelope Manager"],
        ];
        const breadcrumbLink =
            ".page-header .breadcrumb a[href$='/admin/finance']";
        const headerLink = ".page-header .btn-list a[href$='/admin/finance']";
        const envelopeNumbersButton =
            ".page-header .btn-list a[href*='/finance/reports/envelope-numbers']";

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
    describe("direct access to Finance Settings", () => {
        it("denies access to a standard user", () => {
            cy.setupStandardSession();
            cy.visit("/admin/finance");

            cy.location("pathname").should("match", /\/v2\/access-denied$/);
            cy.location("search").should("include", "role=Admin");
        });

        it("denies access to a finance-only user", () => {
            cy.setupFinanceOnlySession();
            cy.visit("/admin/finance");

            cy.location("pathname").should("match", /\/v2\/access-denied$/);
            cy.location("search").should("include", "role=Admin");
        });
    });
});
