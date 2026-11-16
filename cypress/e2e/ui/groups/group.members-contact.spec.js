/// <reference types="cypress" />

describe("Group member contact details (#1827)", () => {
    const people = [];
    let groupId;
    let family;
    let savedHideAddress;

    function createMember(name, contact) {
        cy.visit("/people/person/editor");
        return cy.get("#personEditor").then(($form) => {
            const body = new URLSearchParams(new FormData($form[0]));
            Object.entries({
                firstName: name,
                lastName: "Contact1827",
                gender: "1",
                family: "1",
                homePhone: "",
                cellPhone: "",
                address1: "",
                address2: "",
                city: "",
                state: "",
                zip: "",
                country: "",
                personSubmit: "",
                ...contact,
            }).forEach(([key, value]) => body.set(key, value));
            return cy.request({
                method: "POST",
                url: new URL($form.attr("action"), $form[0].ownerDocument.location.href).href,
                headers: { "content-type": "application/x-www-form-urlencoded" },
                body: body.toString(),
            }).then((response) => {
                const id = Number(response.redirects.pop().split("/").pop());
                expect(id).to.be.greaterThan(0);
                people.push(id);
                return cy.request("POST", `/api/groups/${groupId}/addperson/${id}`, {}).then(() => id);
            });
        });
    }

    before(() => {
        cy.setupAdminSession();
        cy.getSystemConfig("bHidePersonAddress").then((value) => { savedHideAddress = value; });
        cy.setSystemConfig("bHidePersonAddress", "0");
        cy.setupAdminSession();
        cy.request("/api/family/1").its("body").then((value) => { family = value; });
        cy.request("POST", "/api/groups/", { groupName: `Contact1827 ${Date.now()}` }).then(({ body }) => {
            groupId = body.Id;
        });
        createMember("Inherited", {});
        createMember("Personal", {
            address1: "1827 Main & Oak",
            city: "Denton",
            state: "TX",
            zip: "76201",
            country: "US",
            homePhone: "18275550123",
            noFormatHomePhone: "1",
            cellPhone: "18275550456",
            noFormatCellPhone: "1",
        });
        createMember("Partial", { city: "Personal City" });
        createMember("Empty", { family: "0" });
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        if (groupId) cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, 200);
        cy.cleanupPeople(people);
        cy.restoreSystemConfig("bHidePersonAddress", savedHideAddress);
    });

    it("resolves family contacts, preserves personal overrides and leaves missing contacts empty", () => {
        cy.request(`/api/groups/${groupId}/members`).then(({ body }) => {
            const person = (name) => body.Person2group2roleP2g2rs.find((m) => m.Person.FirstName === name).Person;
            expect(person("Inherited").Address1).to.equal(family.Address1);
            expect(person("Inherited").Address).to.include(family.Address1);
            if (family.Address2) expect(person("Inherited").Address).to.include(family.Address2);
            expect(person("Inherited").HomePhone).to.equal(family.HomePhone);
            expect(person("Personal").Address1).to.equal("1827 Main & Oak");
            expect(person("Personal").Address).to.include("1827 Main & Oak");
            expect(person("Personal").HomePhone).to.equal("18275550123");
            expect(person("Partial").Address1).to.equal(family.Address1);
            expect(person("Partial").City).to.equal("Personal City");
            expect(person("Partial").Address).to.include("Personal City");
            if (family.City && family.City !== "Personal City") {
                expect(person("Partial").Address).not.to.include(family.City);
            }
            expect(person("Empty").Address).to.equal("");
            expect(person("Empty").HomePhone).to.equal("");
        });
        cy.request(`/api/person/${people[0]}`).its("body").then((person) => {
            expect(person.Address1 || "").to.equal("");
            expect(person.HomePhone || "").to.equal("");
        });
    });

    it("shows addresses and clickable home phones as literal text in the roster", () => {
        cy.viewport(1440, 1000);
        cy.intercept("GET", `**/api/groups/${groupId}/members*`).as("members");
        cy.visit(`/groups/view/${groupId}`);
        cy.wait("@members");
        cy.get("#membersTable thead").should("contain.text", "Home Phone").and("contain.text", "Address");
        cy.contains("#membersTable tbody tr", "Inherited Contact1827").within(() => {
            cy.contains("a", family.HomePhone).should("have.attr", "href", `tel:${family.HomePhone}`);
            cy.root().should("contain.text", family.Address1);
            if (family.SecondAddress1 && family.SecondAddress1 !== family.Address1) {
                cy.root().should("not.contain.text", family.SecondAddress1);
            }
        });
        cy.contains("#membersTable tbody tr", "Personal Contact1827").within(() => {
            cy.get('a[href="tel:18275550123"]').should("be.visible");
            cy.get('a[href="tel:18275550456"]').should("be.visible");
            cy.root().should("contain.text", "1827 Main & Oak");
        });
    });

    it("escapes contact text returned by the server", () => {
        const address = '<main data-contact-injection="true">1827 Main & Oak</main>';
        cy.intercept("GET", `**/api/groups/${groupId}/members*`, (request) => {
            request.continue((response) => {
                const member = response.body.Person2group2roleP2g2rs.find((m) => m.Person.FirstName === "Personal");
                member.Person.Address = address;
                member.Person.HomePhone = '123" data-contact-injection="true';
            });
        }).as("contacts");
        cy.viewport(1440, 1000);
        cy.visit(`/groups/view/${groupId}`);
        cy.wait("@contacts");
        cy.contains("#membersTable tbody tr", "Personal Contact1827").should("contain.text", address);
        cy.get("#membersTable [data-contact-injection]").should("not.exist");
    });
});
