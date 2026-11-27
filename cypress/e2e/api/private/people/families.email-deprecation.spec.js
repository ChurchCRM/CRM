/// <reference types="cypress" />

import { recordLinkConfirmation } from "../../../../support/member-sign-in-fixtures";

describe("Family API has no family email (7.9.0)", () => {
    const run = Date.now();
    const address = (who) => `cypress-fem-${who}-${run}@example.com`;
    const families = {};
    let savedSelfReg;

    const noFamilyEmail = (response) => {
        expect(response.headers).to.not.have.property("deprecation");
        expect(response.body).to.not.have.property("Email");
    };

    const register = (key, people) =>
        cy
            .request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: `CypressFamilyEmail ${key} ${run}`,
                    Address1: "1 Deprecation Way",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "00000",
                    people,
                },
            })
            .then((response) => {
                families[key] = response;
            });

    const person = (firstName, role, email) => ({ firstName, lastName: "FamilyEmail", gender: 1, role, email });

    before(() => {
        cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
            savedSelfReg = value;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnableSelfRegistration", { value: "1" });

        register("head", [person("Hal", 1, address("head")), person("Sue", 2, address("head-spouse"))]);
        register("spouse", [person("Hank", 1, ""), person("Sara", 2, address("spouse")), person("Cal", 3, address("child"))]);
        register("none", [person("Hugh", 1, ""), person("Sian", 2, "")]);
    });

    after(() => {
        cy.cleanupFamilies(Object.values(families).map((response) => response.body.Id));
        cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
    });

    it("POST /api/public/register/family returns the family without an Email", () => {
        Object.keys(families).forEach((key) => {
            expect(families[key].status).to.eq(200);
            noFamilyEmail(families[key]);
        });
    });

    it("GET /api/family/{id} has no Email", () => {
        Object.keys(families).forEach((key) => {
            cy.makePrivateAdminAPICall("GET", `/api/family/${families[key].body.Id}`, null, 200).then(noFamilyEmail);
        });
    });

    it("GET /api/families/self-register has no Email", () => {
        cy.makePrivateAdminAPICall("GET", "/api/families/self-register", null, 200).then((response) => {
            expect(response.headers).to.not.have.property("deprecation");
            Object.keys(families).forEach((key) => {
                const row = response.body.families.find((family) => family.Id === families[key].body.Id);
                expect(row).to.not.have.property("Email");
            });
        });
    });

    it("GET /api/families/email/without has no Email", () => {
        cy.makePrivateAdminAPICall("GET", "/api/families/email/without", null, 200).then((response) => {
            expect(response.headers).to.not.have.property("deprecation");
            const row = response.body.families.find((family) => family.Id === families.none.body.Id);
            expect(row).to.not.have.property("Email");
        });
    });

    it("GET /api/families/self-verify has no Email on the family", () => {
        Object.keys(families).forEach((key) => {
            recordLinkConfirmation(families[key].body.Id, `Cypress ${key}`);
        });
        cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((response) => {
            expect(response.headers).to.not.have.property("deprecation");
            Object.keys(families).forEach((key) => {
                const note = response.body.families.find((row) => row.Family.Id === families[key].body.Id);
                expect(note.Family).to.not.have.property("Email");
            });
        });
    });

    it("GET /api/groups/{id}/members embeds a family with no Email", () => {
        cy.makePrivateAdminAPICall("GET", "/api/groups/1/members", null, 200).then((response) => {
            expect(response.headers).to.not.have.property("deprecation");
            const withFamily = response.body.Person2group2roleP2g2rs.filter((member) => member.Person.Family);
            expect(withFamily).to.not.be.empty;
            withFamily.forEach((member) => expect(member.Person.Family).to.not.have.property("Email"));
        });
    });

    it("leaves the family endpoints that never returned it alone", () => {
        cy.makePrivateAdminAPICall("GET", "/api/families/latest", null, 200).then((response) => {
            expect(response.headers).to.not.have.property("deprecation");
            expect(response.body.families).to.not.be.empty;
            response.body.families.forEach((family) => expect(family).to.not.have.property("Email"));
        });
    });
});
