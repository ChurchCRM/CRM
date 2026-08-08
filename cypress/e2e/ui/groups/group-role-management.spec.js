/// <reference types="cypress" />

/**
 * Group Role Management UI Tests — Issue #9380
 *
 * Creates two fresh test groups in before() so no test depends on hardcoded seed data:
 *  - testGroupAddDeleteId  — for add/delete happy-path tests (may accumulate roles)
 *  - testGroupSingleId     — for last-role boundary tests (never mutated; always 1 "Member")
 *
 * The Group model's postInsert hook creates exactly one "Member" role for every new group,
 * so both groups start in a known state without any extra seed dependency.
 * Both groups are deleted in after() for full cleanup.
 *
 * Admin session is required throughout (bManageGroups permission).
 */
describe("Group Role Management", () => {
  let testGroupAddDeleteId;
  let testGroupSingleId;

  before(() => {
    cy.setupAdminSession();

    // Create the group used for add/delete happy-path tests
    cy.request({
      method: "POST",
      url: "/api/groups/",
      body: { groupName: `RoleMgmt-AddDelete-${Date.now()}`, description: "" },
    }).then((res) => {
      testGroupAddDeleteId = res.body.Id;
    });

    // Create the group used for last-role boundary tests — never mutated by this suite
    cy.request({
      method: "POST",
      url: "/api/groups/",
      body: { groupName: `RoleMgmt-Single-${Date.now()}`, description: "" },
    }).then((res) => {
      testGroupSingleId = res.body.Id;
    });
  });

  after(() => {
    cy.setupAdminSession();
    if (testGroupAddDeleteId) {
      cy.request({ method: "DELETE", url: `/api/groups/${testGroupAddDeleteId}` });
    }
    if (testGroupSingleId) {
      cy.request({ method: "DELETE", url: `/api/groups/${testGroupSingleId}` });
    }
  });

  beforeEach(() => cy.setupAdminSession());

  // ─── Add Role modal ────────────────────────────────────────────────────────

  describe("Add Role modal", () => {
    it("opens when Add Role button is clicked", () => {
      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
    });

    it("submit button is disabled until input is filled", () => {
      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");

      // Initially disabled
      cy.get("#submitNewRole").should("be.disabled");

      // Becomes enabled after typing
      cy.get("#newRole").type("SomeRoleName");
      cy.get("#submitNewRole").should("not.be.disabled");
    });

    it("cancelling the modal makes no changes to the role table", () => {
      const roleName = `CancelRole-${Date.now()}`;

      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.get("#newRole").type(roleName);

      cy.get("#addRoleModal .btn-secondary").click();

      cy.get("#addRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("not.contain", roleName);
    });

    it("successfully adds a role: modal closes, row appears in table, success toast shown", () => {
      const roleName = `TestRole-${Date.now()}`;

      cy.intercept("POST", "**/api/groups/*/roles").as("addRole");

      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.get("#newRole").type(roleName);
      cy.get("#submitNewRole").click();

      cy.wait("@addRole").its("response.statusCode").should("eq", 200);

      cy.get("#addRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("contain", roleName);
      cy.get(".notyf__toast--success", { timeout: 5000 }).should("be.visible");
    });
  });

  // ─── Delete Role modal ─────────────────────────────────────────────────────

  describe("Delete Role modal", () => {
    it("shows the role name in the confirmation message", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      // testGroupSingleId has exactly 1 "Member" role — assert this explicitly
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length", 1);

      // The delete button uses class .disabled (not HTML attribute) for protected roles;
      // "Member" is not protected so .deleteRole:not(.disabled) matches it
      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#deleteRoleMessage").should("contain", "Member");
    });

    it("shows last-role warning and disables confirm button when only one role remains", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length", 1);

      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#lastRoleWarning").should("be.visible");
      cy.get("#confirmDeleteRole").should("be.disabled");
    });

    it("cancelling the delete modal makes no changes", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length", 1);

      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#deleteRoleModal .btn-secondary").click();

      cy.get("#deleteRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("contain", "Member");
    });

    it("successfully deletes a role: row removed from table, success toast shown", () => {
      const roleName = `DeleteRole-${Date.now()}`;

      cy.intercept("POST", "**/api/groups/*/roles").as("addRole");
      cy.intercept("DELETE", "**/api/groups/*/roles/*").as("deleteRole");

      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      // Add a second role so deleting it won't hit the last-role block
      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.get("#newRole").type(roleName);
      cy.get("#submitNewRole").click();
      cy.wait("@addRole").its("response.statusCode").should("eq", 200);
      cy.get("#addRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("contain", roleName);

      // Delete the newly added role
      cy.contains("#groupRoleTable tr", roleName)
        .find(".deleteRole:not(.disabled)")
        .click();

      cy.get("#deleteRoleModal").should("be.visible");
      // Last-role warning must be hidden (2+ roles exist)
      cy.get("#lastRoleWarning").should("have.class", "d-none");
      cy.get("#confirmDeleteRole").should("not.be.disabled");

      cy.get("#confirmDeleteRole").click();
      cy.wait("@deleteRole").its("response.statusCode").should("eq", 200);

      cy.get("#deleteRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("not.contain", roleName);
      cy.get(".notyf__toast--success", { timeout: 5000 }).should("be.visible");
    });
  });

  // ─── Protected roles ───────────────────────────────────────────────────────

  describe("Protected roles", () => {
    it("Delete button is disabled with a title for Student and Teacher roles", () => {
      // Sunday school groups (e.g. group 1 - Angels class) have Student/Teacher roles.
      // The refactored render uses CSS class .disabled rather than the HTML disabled attribute.
      cy.visit("/groups/editor/1");
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable .deleteRole.disabled")
        .first()
        .should("have.attr", "title")
        .and("not.be.empty");
    });
  });
});
