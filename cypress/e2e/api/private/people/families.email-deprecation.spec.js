/// <reference types="cypress" />

describe("Deprecated family Email in the family API (7.8.3)", () => {
    const run = Date.now();
    const address = (who) => `cypress-fem-${who}-${run}@example.com`;
    const families = {};
    let savedSelfReg;

    const expectDeprecated = (response, page) => {
        expect(response.headers.deprecation).to.match(/^@\d+$/);
        expect(response.headers.link).to.eq(`<https://docs.churchcrm.io/api/${page}>; rel="deprecation"; type="text/html"`);
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

    const expected = () => ({
        head: address("head"),
        spouse: address("spouse"),
        none: "",
    });

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

    it("POST /api/public/register/family returns it in the new family", () => {
        Object.entries(expected()).forEach(([key, email]) => {
            expect(families[key].status).to.eq(200);
            expectDeprecated(families[key], "public/register-family-api");
            expect(families[key].body.Email, key).to.eq(email);
        });
    });

    it("GET /api/family/{id}: the head's email, else the spouse's, else empty", () => {
        Object.entries(expected()).forEach(([key, email]) => {
            cy.makePrivateAdminAPICall("GET", `/api/family/${families[key].body.Id}`, null, 200).then((response) => {
                expectDeprecated(response, "private/get-family");
                expect(response.body.Email, key).to.eq(email);
            });
        });
    });

    it("GET /api/families/self-register", () => {
        cy.makePrivateAdminAPICall("GET", "/api/families/self-register", null, 200).then((response) => {
            expectDeprecated(response, "private/get-self-registered-families");
            Object.entries(expected()).forEach(([key, email]) => {
                const row = response.body.families.find((family) => family.Id === families[key].body.Id);
                expect(row.Email, key).to.eq(email);
            });
        });
    });

    it("GET /api/families/email/without", () => {
        cy.makePrivateAdminAPICall("GET", "/api/families/email/without", null, 200).then((response) => {
            expectDeprecated(response, "private/get-families-without-email");
            const row = response.body.families.find((family) => family.Id === families.none.body.Id);
            expect(row.Email).to.eq("");
        });
    });

    it("GET /api/families/self-verify", () => {
        Object.keys(expected()).forEach((key) => {
            cy.makePrivateAdminAPICall("GET", `/api/family/${families[key].body.Id}/verify/url`, null, 200).then((response) => {
                const token = response.body.url.split("/external/verify/")[1];
                cy.request({ method: "POST", url: `/external/verify/${token}`, body: { message: `Cypress ${key}` } });
            });
        });
        cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((response) => {
            expectDeprecated(response, "private/get-self-verified-families");
            Object.entries(expected()).forEach(([key, email]) => {
                const note = response.body.families.find((row) => row.Family.Id === families[key].body.Id);
                expect(note.Family.Email, key).to.eq(email);
            });
        });
    });

    it("GET /api/groups/{id}/members: each member's family", () => {
        cy.makePrivateAdminAPICall("GET", "/api/groups/1/members", null, 200).then((response) => {
            expectDeprecated(response, "private/get-group-members");
            const withFamily = response.body.Person2group2roleP2g2rs.filter((member) => member.Person.Family);
            expect(withFamily).to.not.be.empty;
            withFamily.forEach((member) => {
                cy.makePrivateAdminAPICall("GET", `/api/family/${member.Person.FamId}`, null, 200)
                    .its("body.Email")
                    .should("eq", member.Person.Family.Email);
            });
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
