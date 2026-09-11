/// <reference types="cypress" />

/**
 * Mail is addressed to the family's mailing address (#9743).
 *
 * NewsLetterLabels.php and ConfirmLabels.php build every label from
 * Family::getMailingAddressLines() — the flagged second address when a family has
 * one, the primary address otherwise — and order the run by the ZIP that is
 * actually printed rather than by the primary ZIP.
 *
 * Assertion ceiling: this repo has no PDF text parser (no pdf-parse, no pdftotext
 * task), so the existing report specs assert HTTP status plus
 * `content-type: application/pdf` and inspect the body only when it is not a PDF.
 * These tests do the same, with a family that exercises the new branch, and pin
 * the resolved address through the API instead. The PDF text itself was checked by
 * hand with pdftotext — see the PR description.
 *
 * PDF endpoints cannot be reached with cy.visit() (it requires text/html), so they
 * are triggered with win.location.href, the pattern in confirm-reports.spec.js.
 */
describe("Mailing address on mailed reports (#9743)", () => {
    const createdFamilyIds = [];

    const labelQuery =
        "labeltype=Tractor&labelfont=Helvetica&labelfontsize=default&recipientnamingmethod=familyname";

    /**
     * Direct form login, as in confirm-reports.spec.js: these pages need the
     * MenuOptions role flag and a PHP session uncontaminated by earlier tests.
     */
    const freshAdminLogin = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.env("admin.username"));
        cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
        cy.url().should("not.include", "/session/begin");
    };

    /** Creates a family whose flagged second address is in a different ZIP. */
    const createFamilyWithMailingAddress = (familyName) => {
        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type("742 Evergreen Terrace");
        cy.get('input[name="City"]').clear().type("Springfield");
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get('input[name="Zip"]').clear().type("62704");

        cy.get("#secondAddressToggle").click();
        cy.get("#SecondAddress1").type("PO Box 1204");
        cy.get("#SecondCity").type("Othertown");
        cy.get("#SecondState").select("IL", { force: true });
        cy.get("#SecondZip").type("62998");
        cy.get("#SecondIsMailing").should("not.be.disabled").check();

        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname").should("include", "/people/family/");

        return cy.location("pathname").then((pathname) => {
            const id = Number(pathname.split("/").pop());
            createdFamilyIds.push(id);
            return id;
        });
    };

    /** Shared PDF assertion: a real PDF came back, with no PHP error text in it. */
    const expectPdf = (interception) => {
        expect(interception.response.statusCode, "no server error").to.equal(200);
        const contentType = interception.response.headers["content-type"] || "";
        expect(contentType).to.include("application/pdf");

        const rawBody = interception.response.body;
        if (typeof rawBody === "string") {
            expect(rawBody).to.not.include("Fatal error");
            expect(rawBody).to.not.include("TypeError");
            expect(rawBody).to.not.include("FPDF error");
        }
    };

    const openReport = (path) =>
        cy.window().then((win) => {
            win.location.href = `${win.CRM.root}${path}`;
        });

    beforeEach(() => {
        freshAdminLogin();
        cy.visit("/LettersAndLabels.php");
    });

    after(() => {
        createdFamilyIds.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}`, null, 200);
        });
    });

    it("generates newsletter labels when a family mails to its second address", () => {
        createFamilyWithMailingAddress("MailLabels" + Cypress._.random(0, 1e6));
        cy.visit("/LettersAndLabels.php");

        cy.intercept("GET", "**/Reports/NewsLetterLabels.php*").as("newsletterLabels");
        openReport(`/Reports/NewsLetterLabels.php?${labelQuery}`);
        cy.wait("@newsletterLabels", { timeout: 20000 }).then(expectPdf);
    });

    it("generates confirm data labels when a family mails to its second address", () => {
        // Confirm labels cover every family, and the one created above is still
        // present (cleanup runs in `after`), so this run exercises the new branch.
        cy.intercept("GET", "**/Reports/ConfirmLabels.php*").as("confirmLabels");
        openReport(`/Reports/ConfirmLabels.php?${labelQuery}`);
        cy.wait("@confirmLabels", { timeout: 20000 }).then(expectPdf);
    });

    // Keep last: the API-key call replaces the browser's CRM session server-side.
    it("resolves the printed address to the flagged second address", () => {
        createFamilyWithMailingAddress("MailLabelData" + Cypress._.random(0, 1e6)).then((familyId) => {
            cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).then((response) => {
                // What a label prints, line for line.
                expect(response.body.MailingAddressLines).to.equal("PO Box 1204, Othertown, IL  62998");
                // The ZIP the label run is sorted on.
                expect(response.body.MailingAddress.Zip).to.equal("62998");
                expect(response.body.Zip).to.equal("62704");
            });
        });
    });
});
