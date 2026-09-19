/// <reference types="cypress" />

// Month filters on the Data & Reports queries are a dropdown of localized month
// names (qrp_Type 4) that defaults to next month.

const MONTH_QUERIES = {
    Birthdays: { id: 18, alias: "birthmonth" },
    "Membership anniversaries": { id: 22, alias: "membermonth" },
    "Wedding Anniversaries": { id: 300, alias: "weddingmonth" },
    "Birthdays & Anniversaries": { id: 301, alias: "month" },
};

const MONTH_NAMES = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
];

// The server computes "next month" in the configured timezone (UTC on the
// test stack). Accept next month relative to either the browser or UTC clock so
// a run that straddles midnight on the last day of a month does not flake.
function acceptableNextMonths() {
    const now = new Date();
    const local = (now.getMonth() + 1) % 12 + 1;
    const utc = (now.getUTCMonth() + 1) % 12 + 1;
    return [String(local), String(utc)];
}

describe("Data & Reports — Month dropdown", () => {
    beforeEach(() => cy.setupAdminSession());

    Object.entries(MONTH_QUERIES).forEach(([name, { id, alias }]) => {
        it(`"${name}" (QueryID ${id}) offers a month dropdown defaulting to next month`, () => {
            cy.visit(`/QueryView.php?QueryID=${id}`);
            cy.get("body").should("not.contain", "Fatal error");
            cy.get(`input[name="${alias}"]`).should("not.exist");
            cy.get(`select[name="${alias}"]`).should("not.have.attr", "multiple");
            cy.get(`select[name="${alias}"] option`).then(($opts) => {
                    const labels = [...$opts].map((o) => o.textContent.trim());
                    const values = [...$opts].map((o) => o.value);
                    expect(labels).to.deep.equal(MONTH_NAMES);
                    expect(values).to.deep.equal(MONTH_NAMES.map((_, i) => String(i + 1)));
                });
            cy.get(`select[name="${alias}"]`)
                .invoke("val")
                .should("be.oneOf", acceptableNextMonths());
        });
    });

    it("Birthdays runs with the chosen month and still validates the value", () => {
        cy.visit("/QueryView.php?QueryID=18");
        cy.get('select[name="birthmonth"]').select("November");
        cy.get('input[name="Submit"]').click();
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("code").should("contain", "per_BirthMonth=11");
        cy.get("table tbody").should("contain", "Marion Hart");
    });
});
