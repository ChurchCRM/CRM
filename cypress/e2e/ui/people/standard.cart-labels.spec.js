/// <reference types="cypress" />

/**
 * Mailing labels from the cart (#9873).
 *
 * Two regressions are pinned here:
 *
 * 1. The cart page lost its "Generate Labels" form when the cart moved to
 *    /v2/cart (#4377). Nothing linked to Reports/PDFLabel.php any more.
 * 2. Reports/PDFLabel.php read the address from the person record only
 *    (0190da3d2), so a household whose address lives on the family record
 *    produced blank labels — every one skipped by "Ignore Incomplete
 *    Addresses", or printed with a name and no street.
 *
 * The family is created through the family editor, which stores the address on
 * the family and creates the member without one — the normal shape of a
 * household. The CSV output is asserted because this repo has no PDF text
 * parser; the PDF run is checked for status, content type and the absence of
 * PHP error text, as the other report specs do.
 *
 * Person-over-family precedence is not exercised here: the person editor hides
 * the address card for anyone in a family and no API sets a member's address,
 * so a family member cannot be given an address of their own from a test.
 */
describe("Cart mailing labels (#9873)", () => {
    const familyName = "Labelfamily" + Cypress._.random(0, 1e6);
    const memberFirstName = "Addressless";
    const street = "742 Evergreen Terrace";
    const city = "Springfield";
    const zip = "62704";
    let familyId;

    const labelQuery =
        "labeltype=5160&labelfont=Helvetica&labelfontsize=10&groupbymode=indiv&onlyfull=1&startrow=1&startcol=1";

    /**
     * Direct form login, as in confirm-reports.spec.js: a fresh PHP session
     * uncontaminated by earlier specs, and the cart lives in that session.
     */
    const freshAdminLogin = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.env("admin.username"));
        cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
        cy.url().should("not.include", "/session/begin");
    };

    const emptyCart = () => {
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({}),
        });
    };

    before(() => {
        freshAdminLogin();

        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type(street);
        cy.get('input[name="City"]').clear().type(city);
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get('input[name="Zip"]').clear().type(zip);
        cy.get('input[name="FirstName1"]').type(memberFirstName);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname")
            .should("include", "/people/family/")
            .then((pathname) => {
                familyId = Number(pathname.split("/").pop());
                expect(familyId).to.be.greaterThan(0);
            });
    });

    after(() => {
        if (familyId) {
            cy.request({
                method: "DELETE",
                url: `/api/family/${familyId}?deleteMembers=true`,
                failOnStatusCode: false,
            });
        }
    });

    beforeEach(() => {
        freshAdminLogin();
        emptyCart();
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ Family: familyId }),
        });
        cy.visit("/v2/cart");
        cy.contains("Cart Functions");
        cy.contains(memberFirstName);
    });

    it("offers a Labels button that opens the Generate Labels form", () => {
        cy.get("#cartLabels").should("be.visible").click();

        cy.get("#cartLabelsModal").should("be.visible");
        cy.get("#cartLabelsForm").should("have.attr", "action").and("include", "Reports/PDFLabel.php");

        // Every parameter the report reads has a control.
        cy.get('#cartLabelsForm input[name="groupbymode"]:checked').should("have.value", "indiv");
        cy.get("#cartLabelsForm select[name=labeltype] option").should("have.length.greaterThan", 0);
        cy.get("#cartLabelsForm select[name=labelfont] option").should("have.length.greaterThan", 0);
        cy.get("#cartLabelsForm select[name=labelfontsize] option").should("have.length.greaterThan", 0);
        cy.get("#cartLabelsForm input[name=startrow]").should("have.value", "1");
        cy.get("#cartLabelsForm input[name=startcol]").should("have.value", "1");
        cy.get("#cartLabelsForm input[name=onlyfull]").should("be.checked");
        cy.get("#cartLabelsForm select[name=filetype] option").should("have.length", 2);

        // Quiet presort is only meaningful when presorting.
        cy.get("#bulkmailquiet").should("be.disabled");
        cy.get("#bulkmailpresort").check();
        cy.get("#bulkmailquiet").should("not.be.disabled");
        cy.get("#bulkmailpresort").uncheck();
        cy.get("#bulkmailquiet").should("be.disabled").and("not.be.checked");
    });

    it("addresses a person with no address of their own at the family address (CSV)", () => {
        cy.request(`/Reports/PDFLabel.php?${labelQuery}&filetype=CSV`).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.headers["content-type"] || "").to.include("text/csv");

            const csv = String(response.body);
            expect(csv, "member is on the label").to.include(memberFirstName);
            expect(csv, "street comes from the family").to.include(street);
            expect(csv, "city comes from the family").to.include(city);
            expect(csv, "zip comes from the family").to.include(zip);
        });
    });

    it("generates the PDF without a server error", () => {
        cy.request({
            url: `/Reports/PDFLabel.php?${labelQuery}&filetype=PDF`,
            encoding: "binary",
        }).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.headers["content-type"] || "").to.include("application/pdf");
            const body = String(response.body);
            expect(body.slice(0, 5)).to.equal("%PDF-");
            expect(body).to.not.include("Fatal error");
            expect(body).to.not.include("FPDF error");
        });
    });
});
