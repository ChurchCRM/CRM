/// <reference types="cypress" />

// Seed facts (cypress/data/seed.sql): the Beck family (unclassified) married in July.
// Franklin Beck (head) and his daughter Stella were born in July; his wife Julie was not.
// The family has no ZIP, so these labels leave "Ignore Incomplete Addresses" off.
const LABEL_OPTIONS = "labeltype=5160&labelfont=Helvetica&labelfontsize=10&startrow=1&startcol=1&filetype=CSV";

const labelNames = (query) =>
    cy.request(`/Reports/PDFLabel.php?${query}&${LABEL_OPTIONS}`).then((response) => {
        const lines = response.body.trim().split(/\r?\n/).slice(1);
        return lines.map((line) => line.split(",")[1].replace(/"/g, ""));
    });

const count = (names, name) => names.filter((label) => label === name).length;

describe("People Reports: Print Labels (#10343)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("sits with the other result actions and sends the report and its filters", () => {
        cy.visit("/people/reports/birthdays-anniversaries?month=7&classification[]=0");
        cy.get("#reportFilters").should("not.contain", "Leave empty for all classifications");

        cy.get("#addAllToCart").parent().within(() => {
            cy.get("#printLabels").should("be.visible").and("not.be.disabled").and("have.class", "btn-sm");
            cy.get("#downloadCsv").should("exist");
        });
        cy.get("#printLabels").click();

        cy.get("#labelsModal").should("be.visible");
        cy.get("#labelsForm").should("have.attr", "action").and("include", "Reports/PDFLabel.php");
        cy.get('#labelsForm input[type=hidden][name="report"]').should("have.value", "birthdays-anniversaries");
        cy.get('#labelsForm input[type=hidden][name="month"]').should("have.value", "7");
        cy.get('#labelsForm input[type=hidden][name="classification[]"]').should("have.value", "0");
        cy.get("#labelsGrouping").should("contain", "anniversaries to the couple");
        cy.get('#labelsForm input[name="groupbymode"]').should("not.exist");
    });

    it("offers the grouping choice on reports that do not set one", () => {
        cy.visit("/people/reports/birthdays?month=7");
        cy.get("#printLabels").click();
        cy.get("#labelsModal").should("be.visible");
        cy.get('#labelsForm input[name="groupbymode"]').should("have.length", 2);
        cy.get("#labelsGrouping").should("not.exist");
    });

    it("labels birthdays by name and anniversaries by couple, both for someone with both", () => {
        labelNames("report=birthdays-anniversaries&month=7&classification[]=0").then((names) => {
            expect(count(names, "Mr Franklin Beck"), "Franklin's birthday").to.eq(1);
            expect(count(names, "Miss Stella Beck"), "Stella's birthday").to.eq(1);
            expect(count(names, "Franklin & Julie Beck"), "the Becks' anniversary").to.eq(1);
        });
    });

    it("gives each couple one label on Wedding Anniversaries", () => {
        labelNames("report=wedding-anniversaries&month=7&classification[]=0").then((names) => {
            expect(names).to.deep.equal(["Franklin & Julie Beck"]);
        });
    });

    // Nathan (classification 1) and Vivan Lewis (classification 2) married in February.
    it("names both spouses when the classification filter matches only one", () => {
        labelNames("report=wedding-anniversaries&month=2&classification[]=1").then((names) => {
            expect(names).to.deep.equal(["Nathan & Vivan Lewis"]);
        });
    });

    it("uses the grouping chosen in the dialog on other reports", () => {
        labelNames("report=birthdays&month=7&classification[]=0&groupbymode=indiv").then((names) => {
            expect(names).to.include.members(["Mr Franklin Beck", "Miss Stella Beck"]);
        });
        labelNames("report=birthdays&month=7&classification[]=0&groupbymode=fam").then((names) => {
            expect(names.filter((label) => label.endsWith("Beck"))).to.deep.equal(["Franklin Beck"]);
        });
    });
});

describe("People Reports labels access", () => {
    it("sends non-admins to access denied", () => {
        cy.setupStandardSession();
        cy.request({
            url: `/Reports/PDFLabel.php?report=birthdays&month=7&${LABEL_OPTIONS}`,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("access-denied");
        });
    });
});
