/// <reference types="cypress" />

const dashboardCounts = () => {
    cy.setupAdminSession();
    return cy.request("/people/dashboard").then(({ body }) => ({
        families: Number(/id="familyCountDashboard">(\d+)</.exec(body)[1]),
        people: Number(/id="peopleStatsDashboard">(\d+)</.exec(body)[1]),
    }));
};

describe("People dashboard - pending self-registrations", () => {
    let baseline;
    let familyId;

    before(() => {
        dashboardCounts().then((counts) => {
            baseline = counts;
        });
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: `Dashpending${Date.now()}`,
                Address1: "1 Pending Way",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "00000",
                people: [{ firstName: "Cypress", lastName: "Dashpending", gender: 1, role: 1 }],
            },
        }).then((response) => {
            familyId = response.body.Id;
            expect(familyId).to.be.greaterThan(0);
        });
    });

    after(() => {
        if (familyId) {
            cy.setupAdminSession();
            cy.cleanupFamilies([familyId]);
        }
    });

    it("leaves a family awaiting review out of the family and people counts", () => {
        dashboardCounts().should("deep.equal", baseline);
    });

    it("counts the family and its member once they are approved", () => {
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/approve-review`, null, 200);
        dashboardCounts().should("deep.equal", { families: baseline.families + 1, people: baseline.people + 1 });
    });
});
