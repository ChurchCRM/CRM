/// <reference types="cypress" />

describe("Group member contact details (#1827)", () => {
    const people = [];
    let groupId;
    let family;
    let savedHideAddress;

    function createMember(name, contact) {
        cy.visit("/PersonEditor.php");
        return cy.get("#personEditor").then(($form) => {
            const body = new URLSearchParams(new FormData($form[0]));
            Object.entries({
                FirstName: name,
                LastName: "Contact1827",
                Gender: "1",
                Family: "1",
                HomePhone: "",
                CellPhone: "",
                Address1: "",
                Address2: "",
                City: "",
                State: "",
                Zip: "",
                Country: "",
                PersonSubmit: "",
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
            Address1: "1827 Main & Oak",
            City: "Denton",
            State: "TX",
            Zip: "76201",
            Country: "US",
            HomePhone: "18275550123",
            NoFormat_HomePhone: "1",
            CellPhone: "18275550456",
            NoFormat_CellPhone: "1",
        });
        createMember("Empty", { Family: "0" });
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
            expect(person("Inherited").HomePhone).to.equal(family.HomePhone);
            expect(person("Personal").Address1).to.equal("1827 Main & Oak");
            expect(person("Personal").Address).to.include("1827 Main & Oak");
            expect(person("Personal").HomePhone).to.equal("18275550123");
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
        cy.intercept("GET", `**/api/groups/${groupId}/members`).as("members");
        cy.visit(`/groups/view/${groupId}`);
        cy.wait("@members");
        cy.get("#membersTable thead").should("contain.text", "Home Phone").and("contain.text", "Address");
        cy.contains("#membersTable tbody tr", "Inherited Contact1827").within(() => {
            cy.contains("a", family.HomePhone).should("have.attr", "href", `tel:${family.HomePhone}`);
            cy.root().should("contain.text", family.Address1);
        });
        cy.contains("#membersTable tbody tr", "Personal Contact1827").within(() => {
            cy.get('a[href="tel:18275550123"]').should("be.visible");
            cy.get('a[href="tel:18275550456"]').should("be.visible");
            cy.root().should("contain.text", "1827 Main & Oak");
        });
    });

    it("escapes contact text returned by the server", () => {
        const address = '<main data-contact-injection="true">1827 Main & Oak</main>';
        cy.intercept("GET", `**/api/groups/${groupId}/members`, (request) => {
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
