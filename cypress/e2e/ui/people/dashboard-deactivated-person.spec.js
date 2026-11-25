/// <reference types="cypress" />

const tbody = (html, id) => {
    const from = html.indexOf(`id="${id}"`);
    return html.slice(from, html.indexOf("</tbody>", from));
};

const sum = (html, pattern) => [...html.matchAll(pattern)].reduce((total, match) => total + Number(match[1]), 0);

const dashboard = () => {
    cy.setupAdminSession();
    return cy.request("/people/dashboard").then(({ body }) => {
        const ageValues = JSON.parse(/var ageGroupValues = (\[[\d,\s]*\]);/.exec(body)[1]);

        return {
            families: Number(/id="familyCountDashboard">(\d+)</.exec(body)[1]),
            people: Number(/id="peopleStatsDashboard">(\d+)</.exec(body)[1]),
            classification: sum(tbody(body, "classification-stats"), /text-body fw-medium">(\d+)</g),
            roles: sum(tbody(body, "family-role-stats"), /text-body fw-medium">(\d+)</g),
            gender: Number(/<strong>(\d+)<\/strong><\/td>\s*<td class="text-end"><strong>100%/.exec(tbody(body, "gender-stats"))[1]),
            age: ageValues.reduce((total, count) => total + count, 0),
        };
    });
};

describe("People dashboard - person deactivated in an active family", () => {
    let baseline;
    let familyId;
    let personId;
    const familyName = `Dashactive${Date.now()}`;

    before(() => {
        dashboard().then((counts) => {
            baseline = counts;
        });
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: familyName,
                Address1: "1 Active Way",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "00000",
                people: [
                    { firstName: "Ada", lastName: familyName, gender: 1, role: 1, birthday: "01/15/1980" },
                    { firstName: "Bea", lastName: familyName, gender: 2, role: 2, birthday: "06/20/1982" },
                ],
            },
        }).then((response) => {
            familyId = response.body.Id;
            expect(familyId).to.be.greaterThan(0);
            cy.setupAdminSession();
            cy.request("POST", `/api/family/${familyId}/approve-review`).its("status").should("eq", 200);
            cy.request(`/api/persons/search/${familyName}`).then(({ body }) => {
                const match = body.find((person) => String(person.text).includes("Bea"));
                expect(match, "spouse to deactivate").to.exist;
                personId = match.objid;
            });
        });
    });

    after(() => {
        if (familyId) {
            cy.setupAdminSession();
            cy.cleanupFamilies([familyId]);
        }
    });

    it("drops that person from the people count and the same breakdowns", () => {
        dashboard().then((approved) => {
            expect(approved.families).to.equal(baseline.families + 1);
            expect(approved.people).to.equal(baseline.people + 2);
            expect(approved.classification).to.equal(approved.people);
            expect(approved.gender).to.equal(approved.people);
            expect(approved.roles).to.equal(approved.people);

            cy.request("POST", `/api/person/${personId}/activate/false`).its("status").should("eq", 200);

            dashboard().then((after) => {
                expect(after.families).to.equal(approved.families);
                expect(after.people).to.equal(approved.people - 1);
                expect(after.classification).to.equal(after.people);
                expect(after.gender).to.equal(after.people);
                expect(after.roles).to.equal(after.people);
                expect(after.age).to.equal(approved.age - 1);
            });
        });
    });
});
