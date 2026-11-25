/// <reference types="cypress" />

/**
 * Family profile layout: header card, member rows with phones, Address card above the
 * tabs, Giving History jump, record history, and the Add / Edit Property popup.
 * Seed family 1 (Campbell) has geocoded coordinates, member phones and emails.
 */
describe("Family profile layout", () => {
    const familyId = 1;
    const valuedPropertyId = 5; // family property with a value prompt
    const plainPropertyId = 2; // family property without a value prompt

    const unassign = (propertyId) =>
        cy.makePrivateAdminAPICall("DELETE", `/api/people/properties/family/${familyId}/${propertyId}`, null, [200, 404]);

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows the profile header with status, member count and the toolbar", () => {
        cy.visit(`/people/family/${familyId}`);
        cy.get("#family-profile-header").within(() => {
            cy.contains(".badge", "Active").should("be.visible");
            cy.contains(".badge", "Members").should("be.visible");
            cy.get("#uploadImageTrigger").should("exist");
            cy.contains("a", "Edit").should("be.visible");
            cy.contains("button", "Add Note").should("be.visible");
        });
    });

    it("shows each member's phone numbers with call links in the member rows", () => {
        cy.visit(`/people/family/${familyId}`);
        cy.get(".member-phones a[href^='tel:']").should("have.length.at.least", 1);
        cy.get(".member-phones a[href^='sms:']").should("have.length.at.least", 1);
        cy.get(".member-table .copy-email-btn").should("have.length.at.least", 1);
    });

    it("puts the Address card above the tabs and Notes first", () => {
        cy.visit(`/people/family/${familyId}`);
        cy.get("#family-address-card").should("contain.text", "Daisy Dr");
        cy.get("#map1").should("exist");
        cy.get("#family-address-card")
            .invoke("offset")
            .its("top")
            .then((addressTop) => {
                cy.get("#nav-item-notes").invoke("offset").its("top").should("be.gt", addressTop);
            });
        cy.get("#nav-item-notes").should("have.class", "active");
        cy.get("#notes").should("have.class", "active");
    });

    it("jumps to Giving History from the Finance menu and from a #giving-history link", () => {
        cy.visit(`/people/family/${familyId}`);
        cy.contains("#family-profile-header .dropdown-toggle", "Finance").click();
        cy.get('.dropdown-item[data-open-tab="giving-history"]').click();
        cy.get("#giving-history").should("have.class", "active");

        cy.visit(`/people/family/${familyId}#giving-history`);
        cy.get("#nav-item-giving").should("have.class", "active");
    });

    it("shows who created and last edited the family", () => {
        cy.visit(`/people/family/${familyId}`);
        cy.get("#family-record-history").should("contain.text", "Created").and("contain.text", "Last edited");
    });

    it("keeps Verify now on the Verification section", () => {
        cy.visit(`/people/family/${familyId}`);
        cy.get("#family-verification-card #verify-now-btn").click();
        cy.get("#confirm-verify").should("be.visible");
    });

    describe("Add and edit a property in the popup", () => {
        afterEach(() => {
            unassign(plainPropertyId);
            unassign(valuedPropertyId);
        });

        it("adds a property from the popup and no longer offers it", () => {
            unassign(plainPropertyId);
            cy.visit(`/people/family/${familyId}`);
            cy.get("#add-family-property-btn").click();
            cy.get("#add-family-property-modal").should("be.visible");
            cy.get("#input-family-properties").select(String(plainPropertyId));
            cy.get("#assign-family-property-btn").click();
            cy.get("#family-properties", { timeout: 15000 }).should("contain.text", "Single Parent");

            cy.get("#add-family-property-btn").click();
            cy.get(`#input-family-properties option[value="${plainPropertyId}"]`).should("not.exist");
            cy.get("#family-properties .edit-property-btn").should("not.exist");
        });

        it("edits the value of an assigned property without reassigning it", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/people/properties/family/${familyId}/${valuedPropertyId}`,
                { value: "first note" },
                200,
            );
            cy.visit(`/people/family/${familyId}`);
            cy.get("#family-properties .edit-property-btn").first().click();
            cy.get("#add-family-property-modal").should("be.visible").and("contain.text", "Edit Property");
            cy.focused().should("have.id", "add-family-property-modal"); // the fade-in has finished
            cy.get("#input-family-properties").should("not.be.visible");
            cy.get("#family-property-prompt-box textarea").should("have.value", "first note").clear().type("second note");
            cy.get("#assign-family-property-btn").should("contain.text", "Save").click();
            cy.get("#family-properties", { timeout: 15000 }).should("contain.text", "second note").and("not.contain.text", "first note");
        });
    });

    describe("Phone width", () => {
        beforeEach(() => {
            cy.viewport(390, 844);
            cy.visit(`/people/family/${familyId}`);
        });

        it("stacks each member into a row and never scrolls the page sideways", () => {
            cy.get(".member-table thead").first().should("not.be.visible");
            cy.get(".member-email-cell a[href^='mailto:']")
                .first()
                .should("be.visible")
                .then(($link) => {
                    expect($link[0].getBoundingClientRect().right).to.be.at.most(390);
                });
            cy.document().then((doc) => {
                expect(doc.documentElement.scrollWidth).to.be.at.most(390);
            });
        });

        it("keeps the toolbar and Address card reachable", () => {
            cy.get("#family-profile-header .card-footer").should("be.visible");
            cy.get("#family-address-card").scrollIntoView().should("be.visible");
            cy.contains("#family-address-card a", "Get Directions").should("be.visible");
        });
    });
});
