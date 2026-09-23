/// <reference types="cypress" />

/**
 * Regression tests for the empty label font selector — issue #9694.
 *
 * FontSelect() built its list by scanning FPDF's font directory for *.php
 * files. setasign/fpdf 1.9.0 ships its 14 core font metrics as *.json, so the
 * filter matched nothing and <select name="labelfont"> rendered with zero
 * options. An empty select submits nothing, the report received an empty font
 * name, and FPDF::SetFont('','') silently no-opped — the failure only surfaced
 * at the first FPDF::MultiCell() as:
 *
 *   FPDF error: No font has been set
 *      #1 ChurchCRM/Reports/PdfLabel.php(172): FPDF->MultiCell()
 *      #2 Reports/ConfirmLabels.php(51): PdfLabel->addPdfLabel()
 *
 * The option COUNT is the assertion that matters — zero is the signature of
 * the bug. The named-option assertion additionally pins the sort order: the
 * family-detection loop needs SCANDIR_SORT_ASCENDING, and with the previous
 * descending order it emits "Helveticab" rather than "Helvetica Bold".
 */
describe("Label font selector is populated (#9694)", () => {
    function freshAdminLogin() {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.env("admin.username"));
        cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
        cy.url().should("not.include", "/session/begin");
    }

    beforeEach(() => {
        freshAdminLogin();
        cy.visit("/LettersAndLabels.php");
    });

    it("offers the FPDF core fonts rather than an empty dropdown", () => {
        cy.get("select[name=labelfont]").should("exist");

        // Zero options is the bug. FPDF ships 14 core font metric files.
        cy.get("select[name=labelfont] option").should("have.length.greaterThan", 0);

        // Style variants must be labelled, not raw filenames — this fails if the
        // scandir order regresses to descending.
        cy.get("select[name=labelfont]").should("contain.text", "Helvetica");
        cy.get("select[name=labelfont] option")
            .then(($opts) => [...$opts].map((o) => o.textContent.trim()))
            .should((names) => {
                expect(names, "no raw filenames leaked as labels").to.not.include("Helveticab");
                expect(names).to.include("Helvetica Bold");
            });
    });

    it("generates confirm data labels as a PDF instead of a 500", () => {
        cy.intercept("GET", "**/Reports/ConfirmLabels.php*").as("confirmLabels");

        cy.get("input[name=SubmitConfirmLabels]").click();

        cy.wait("@confirmLabels", { timeout: 20000 }).then((interception) => {
            expect(interception.response.statusCode, "no 500").to.equal(200);

            const contentType = interception.response.headers["content-type"] || "";
            expect(contentType).to.include("application/pdf");

            const body = interception.response.body;
            if (typeof body === "string") {
                expect(body).to.not.include("No font has been set");
                expect(body).to.not.include("Fatal error");
            }
        });
    });
});
