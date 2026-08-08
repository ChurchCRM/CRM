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
    // Use API-key auth (makePrivateAdminAPICall) so we don't depend on cy.session()
    // being properly initialised in a before() hook — cy.session() is designed for
    // beforeEach() and may not establish cookies reliably when called in before().
    cy.makePrivateAdminAPICall(
      "POST",
      "/api/groups/",
      { groupName: `RoleMgmt-AddDelete-${Date.now()}`, description: "" },
      200,
    ).then((resp) => {
      testGroupAddDeleteId = resp.body.Id;
    });

    cy.makePrivateAdminAPICall(
      "POST",
      "/api/groups/",
      { groupName: `RoleMgmt-Single-${Date.now()}`, description: "" },
      200,
    ).then((resp) => {
      testGroupSingleId = resp.body.Id;
    });
  });

  after(() => {
    // Hard-fail if IDs were never set — a silent skip would leave orphaned groups in
    // the DB and could cause flakiness in unrelated tests (e.g. standard.group.spec.js).
    if (!testGroupAddDeleteId || !testGroupSingleId) {
      throw new Error(
        `Test group IDs were never assigned — before() likely failed.\n` +
          `testGroupAddDeleteId=${testGroupAddDeleteId}, testGroupSingleId=${testGroupSingleId}`,
      );
    }
    cy.makePrivateAdminAPICall("DELETE", `/api/groups/${testGroupAddDeleteId}`, null, 200);
    cy.makePrivateAdminAPICall("DELETE", `/api/groups/${testGroupSingleId}`, null, 200);
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

      // "Member" is not protected, so the delete button is not disabled
      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#deleteRoleMessage").should("contain", "Member");

      // Clean up: close the modal so subsequent tests start with clean state
      cy.get("#deleteRoleModal .btn-secondary").click();
      cy.get("#deleteRoleModal").should("not.be.visible");
    });

    it("shows last-role warning and disables confirm button when only one role remains", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length", 1);

      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.get("#lastRoleWarning").should("be.visible");
      cy.get("#confirmDeleteRole").should("be.disabled");

      // Clean up: close the modal so subsequent tests start with clean state
      cy.get("#deleteRoleModal .btn-secondary").click();
      cy.get("#deleteRoleModal").should("not.be.visible");
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
    // Force a fresh login to avoid stale session state from prior mutation tests.
    beforeEach(() => cy.setupAdminSession({ forceLogin: true }));

    it("Delete button is disabled with a title for Student and Teacher roles", () => {
      // Sunday school groups (e.g. group 1 - Angels class) have Student/Teacher roles.
      // GroupEditor.js marks these buttons with both the HTML disabled attribute and
      // the Bootstrap .disabled CSS class, so either [disabled] or .disabled selectors work.
      cy.visit("/groups/editor/1");
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable [id^='roleDelete-'][disabled]")
        .first()
        .should("have.attr", "title")
        .and("not.be.empty");
    });
  });
});
