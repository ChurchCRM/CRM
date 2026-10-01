/// <reference types="cypress" />

describe("Standard Groups", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Add Group ", () => {
        const uniqueSeed = Date.now().toString();
        const newGroupName = "New Test Group " + uniqueSeed;

        cy.visit("groups/dashboard");
        cy.get("#groupName").type(newGroupName);
        cy.get("#addNewGroup").click();

        // Should redirect to group editor page
        cy.url().should("contain", "/groups/editor/");

        // Verify we're on the editor page with the new group name
        // Using a more flexible selector that works with both Name and name attributes
        cy.get("input[type='text'].form-control").first().should("have.value", newGroupName);
    });

    it("Add Group - Empty Name Validation", () => {
        cy.visit("groups/dashboard");

        // Try to submit with empty group name
        cy.get("#addNewGroup").click();

        // Input should receive the is-invalid class and focus
        cy.get("#groupName")
            .should("have.class", "is-invalid")
            .and("have.focus");

        // Should remain on the groups dashboard
        cy.url().should("contain", "groups/dashboard");
    });

    it("View Group ", () => {
        cy.visit("groups/view/9");
        cy.contains("Group View : Church Board");
        // Two-column layout with members card and properties sidebar
        cy.get("#membersTable").should("exist");
        cy.get("#role-pills").should("exist");
    });

    it("Group View members table has action menus", () => {
        cy.visit("groups/view/9");
        cy.get("#membersTable", { timeout: 10000 }).should("exist");
        cy.get("#membersTable tbody tr", { timeout: 10000 }).then(($rows) => {
            if ($rows.length > 0) {
                cy.get("#membersTable tbody tr:first").within(() => {
                    cy.get('[data-bs-toggle="dropdown"]').first().click();
                });
                cy.get(".dropdown-menu.show").within(() => {
                    cy.contains("View").should("exist");
                    cy.contains("Change Role").should("exist");
                    cy.get(".AddToCart, .RemoveFromCart").should("exist");
                    cy.contains("Remove").should("exist");
                });
            }
        });
    });

    describe("Delete Group from Group View (#10129)", () => {
        let groupId;

        before(() => {
            cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName: `View Delete ${Date.now()}` }).then(
                (resp) => {
                    groupId = resp.body.Id;
                },
            );
        });

        after(() => {
            if (groupId) cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        });

        it("returns to the Groups list after the delete", () => {
            cy.intercept("DELETE", `**/api/groups/${groupId}`).as("deleteGroup");
            cy.visit(`/groups/view/${groupId}`);
            cy.get("#group-view-toolbar").contains("button", "Actions").click();
            cy.get("#deleteGroupButton").click();
            cy.get(".bootbox .btn-danger").click();
            cy.wait("@deleteGroup").its("response.statusCode").should("eq", 200);
            cy.location("pathname").should("match", /\/groups\/dashboard$/);
        });
    });

    it("Groups dashboard table has action menus", () => {
        cy.visit("groups/dashboard");
        cy.get("#groupsTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
        cy.get("#groupsTable tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.contains("Delete").should("exist");
        });
    });

    it("Groups dashboard table has action menus", () => {
        cy.visit("groups/dashboard");
        cy.get("#groupsTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
        cy.get("#groupsTable tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.contains("Delete").should("exist");
        });
    });

    it("Group Report", () => {
        cy.visit("groups/reports");
        cy.contains("Group Reports");
        cy.contains("Select Group");
        // Select the first real group so the form passes validation (GroupID=0 redirects back)
        cy.get("#GroupID").find("option").not("[value='0']").first().then(($opt) => {
            cy.get("#GroupID").select($opt.val());
        });
        cy.get(".card-body > form").submit();
        cy.url().should("contain", "groups/reports");
        cy.contains("Select Fields to Include");
    });

    describe("Delete a group that is an event's audience (#10126)", () => {
        const groupName = `Audience Group ${Date.now()}`;
        let groupId;
        let eventId;

        before(() => {
            cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName })
                .then((resp) => {
                    groupId = resp.body.Id;
                    return cy.makePrivateAdminAPICall("POST", "/api/events/quick-create", { groupId });
                })
                .then((resp) => {
                    eventId = resp.body.eventId;
                });
        });

        // The #10129 block's API-key `after` hook leaves the cached standard session
        // unusable (visit redirects to login); a fresh login sidesteps the cache.
        beforeEach(() => cy.setupStandardSession({ forceLogin: true }));

        after(() => {
            if (eventId) cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventId}`);
            if (groupId) cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`);
        });

        it("shows the server's reason instead of a generic error", () => {
            cy.intercept("DELETE", `**/api/groups/${groupId}`).as("deleteGroup");
            cy.visit("/groups/dashboard");
            cy.get("#groupsTable_wrapper input[type='search']").type(groupName);
            cy.contains("#groupsTable tbody tr", groupName).find('[data-bs-toggle="dropdown"]').click();
            cy.get(`.delete-group[data-group-id="${groupId}"]`).click();
            cy.get(".bootbox .btn-danger").click();

            cy.wait("@deleteGroup").then(({ response }) => {
                expect(response.statusCode).to.eq(409);
                cy.waitForNotification(response.body.message);
            });
        });
    });
});
