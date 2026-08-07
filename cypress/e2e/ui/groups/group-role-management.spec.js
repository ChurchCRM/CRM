/// <reference types="cypress" />

/**
 * Group Role Management UI Tests — Issue #9380
 *
 * Seed data used (cypress/data/seed.sql):
 *   GROUP_MULTI  = 7  (Boys Scouts, roleListID=19)  — "Member" only initially; used for add/delete happy path
 *   GROUP_SINGLE = 11 (Clergy,      roleListID=23)  — "Member" only; never mutated — used for last-role tests
 *
 * These tests require an admin session because role management requires bManageGroups permission.
 */
describe("Group Role Management", () => {
  const GROUP_MULTI = 7;
  const GROUP_SINGLE = 11;

  beforeEach(() => cy.setupAdminSession());

  // ─── Add Role modal ────────────────────────────────────────────────────────

  describe("Add Role modal", () => {
    it("opens when Add Role button is clicked", () => {
      cy.visit(`/groups/editor/${GROUP_MULTI}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
    });

    it("submit button is disabled until input is filled", () => {
      cy.visit(`/groups/editor/${GROUP_MULTI}`);
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

      cy.visit(`/groups/editor/${GROUP_MULTI}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.get("#newRole").type(roleName);

      // Click Cancel
      cy.get("#addRoleModal .btn-secondary").click();

      cy.get("#addRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("not.contain", roleName);
    });

    it("successfully adds a role: modal closes, row appears in table, success toast shown", () => {
      const roleName = `TestRole-${Date.now()}`;

      cy.intercept("POST", "**/api/groups/*/roles").as("addRole");

      cy.visit(`/groups/editor/${GROUP_MULTI}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.get("#newRole").type(roleName);
      cy.get("#submitNewRole").click();

      cy.wait("@addRole").its("response.statusCode").should("eq", 200);

      // Modal should close
      cy.get("#addRoleModal").should("not.be.visible");

      // New role row should appear in the table
      cy.get("#groupRoleTable").should("contain", roleName);

      // Success toast should be visible
      cy.get(".notyf__toast--success", { timeout: 5000 }).should("be.visible");
    });
  });

  // ─── Delete Role modal ─────────────────────────────────────────────────────

  describe("Delete Role modal", () => {
    it("shows the role name in the confirmation message", () => {
      cy.visit(`/groups/editor/${GROUP_SINGLE}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      // Click the delete button for the (non-protected) "Member" role
      cy.get("#groupRoleTable")
        .find("[id^='roleDelete-']:not([disabled])")
        .first()
        .click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#deleteRoleMessage").should("contain", "Member");
    });

    it("shows last-role warning and disables confirm button when only one role remains", () => {
      cy.visit(`/groups/editor/${GROUP_SINGLE}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable")
        .find("[id^='roleDelete-']:not([disabled])")
        .first()
        .click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#lastRoleWarning").should("be.visible");
      cy.get("#confirmDeleteRole").should("be.disabled");
    });

    it("cancelling the delete modal makes no changes", () => {
      cy.visit(`/groups/editor/${GROUP_SINGLE}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable")
        .find("[id^='roleDelete-']:not([disabled])")
        .first()
        .click();

      cy.get("#deleteRoleModal").should("be.visible");

      // Click Cancel
      cy.get("#deleteRoleModal .btn-secondary").click();

      cy.get("#deleteRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("contain", "Member");
    });

    it("successfully deletes a role: row removed from table, success toast shown", () => {
      const roleName = `DeleteRole-${Date.now()}`;

      cy.intercept("POST", "**/api/groups/*/roles").as("addRole");
      cy.intercept("DELETE", "**/api/groups/*/roles/*").as("deleteRole");

      cy.visit(`/groups/editor/${GROUP_MULTI}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      // First: add a unique role so we have something to delete without hitting last-role block
      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.get("#newRole").type(roleName);
      cy.get("#submitNewRole").click();
      cy.wait("@addRole").its("response.statusCode").should("eq", 200);
      cy.get("#addRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("contain", roleName);

      // Now delete the newly added role
      cy.contains("#groupRoleTable tr", roleName)
        .find("[id^='roleDelete-']:not([disabled])")
        .click();

      cy.get("#deleteRoleModal").should("be.visible");
      // Last-role warning should NOT appear (table has 2+ roles now)
      cy.get("#lastRoleWarning").should("have.class", "d-none");
      cy.get("#confirmDeleteRole").should("not.be.disabled");

      cy.get("#confirmDeleteRole").click();
      cy.wait("@deleteRole").its("response.statusCode").should("eq", 200);

      // Modal should close and row should be gone
      cy.get("#deleteRoleModal").should("not.be.visible");
      cy.get("#groupRoleTable").should("not.contain", roleName);

      // Success toast
      cy.get(".notyf__toast--success", { timeout: 5000 }).should("be.visible");
    });
  });

  // ─── Protected roles ───────────────────────────────────────────────────────

  describe("Protected roles", () => {
    it("Delete button is disabled with a title for Student and Teacher roles", () => {
      // Sunday school groups (e.g. group 1 - Angels class) have Student/Teacher roles
      cy.visit("/groups/editor/1");
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      // Find a disabled delete button and verify it has a non-empty title attribute
      cy.get("#groupRoleTable")
        .find("[id^='roleDelete-'][disabled]")
        .first()
        .should("have.attr", "title")
        .and("not.be.empty");
    });
  });
});
