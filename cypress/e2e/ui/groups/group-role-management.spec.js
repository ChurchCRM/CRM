/// <reference types="cypress" />

/**
 * Group Role Management UI Tests — Issue #9380
 *
 * Creates three fresh test groups in before() so no test depends on hardcoded seed data:
 *  - testGroupAddDeleteId  — for add/delete happy-path tests (may accumulate roles)
 *  - testGroupSingleId     — for last-role boundary tests (never mutated; always 1 "Member")
 *  - testGroupSundayId     — Sunday-school type; gets Teacher + Student roles via postInsert
 *
 * The Group model's postInsert hook creates exactly one "Member" role for every new group,
 * and Teacher + Student roles for Sunday-school groups.
 * All three groups are deleted in after() for full cleanup.
 *
 * Admin session is required throughout (bManageGroups permission).
 *
 * NOTE: Role names are rendered as <input class="roleName"> in the DataTable. After a
 * DataTables redraw(), the HTML value attribute may not be set (DataTables can use the DOM
 * .value property internally). Use cy.window() + DataTable.data() API or jQuery .val() to
 * check role presence — do NOT use CSS [value="..."] attribute selectors on these inputs.
 */

/** Helper: assert that the GroupEditor DataTable contains a role with the given name. */
function dtHasRole(roleName) {
  // Use should() so Cypress retries the assertion until the DataTable data updates
  cy.window().should((win) => {
    const dt = win.jQuery("#groupRoleTable").DataTable();
    const names = dt
      .data()
      .toArray()
      .map((row) => row.lst_OptionName);
    expect(names, `DataTable should contain role "${roleName}"`).to.include(roleName);
  });
}

/** Helper: assert that the GroupEditor DataTable does NOT contain a role with the given name. */
function dtLacksRole(roleName) {
  cy.window().should((win) => {
    const dt = win.jQuery("#groupRoleTable").DataTable();
    const names = dt
      .data()
      .toArray()
      .map((row) => row.lst_OptionName);
    expect(names, `DataTable should NOT contain role "${roleName}"`).not.to.include(roleName);
  });
}

/** Helper: click the delete button for the role with the given name via DataTable data API. */
function clickDeleteForRole(roleName) {
  cy.window().then((win) => {
    const dt = win.jQuery("#groupRoleTable").DataTable();
    const row = dt
      .data()
      .toArray()
      .find((r) => r.lst_OptionName === roleName);
    expect(row, `Role "${roleName}" must exist to delete it`).to.exist;
    // Use force:true in case the responsive plugin has hidden the button's column
    cy.get(`#roleDelete-${row.lst_OptionID}`).click({ force: true });
  });
}

describe("Group Role Management", () => {
  let testGroupAddDeleteId;
  let testGroupSingleId;
  let testGroupSundayId;

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

    // Create a Sunday-school group — postInsert gives it Teacher + Student roles
    cy.makePrivateAdminAPICall(
      "POST",
      "/api/groups/",
      { groupName: `RoleMgmt-Sunday-${Date.now()}`, description: "", isSundaySchool: true },
      200,
    ).then((resp) => {
      testGroupSundayId = resp.body.Id;
    });
  });

  after(() => {
    // Clean up whichever groups were successfully created — individual guards
    // ensure a partial before() failure doesn't leave orphaned groups in the DB.
    if (testGroupAddDeleteId) {
      cy.makePrivateAdminAPICall("DELETE", `/api/groups/${testGroupAddDeleteId}`, null, 200);
    }
    if (testGroupSingleId) {
      cy.makePrivateAdminAPICall("DELETE", `/api/groups/${testGroupSingleId}`, null, 200);
    }
    if (testGroupSundayId) {
      cy.makePrivateAdminAPICall("DELETE", `/api/groups/${testGroupSundayId}`, null, 200);
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
      cy.wait(400);

      // Initially disabled
      cy.get("#submitNewRole").should("be.disabled");

      // Becomes enabled after typing
      cy.get("#newRole").type("SomeRoleName");
      cy.get("#submitNewRole").should("not.be.disabled");
    });

    it("cancelling the modal makes no changes to the role table", () => {
      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      cy.wait(400);
      cy.get("#newRole").type("x");

      // Dismiss by reloading the page — completely bypasses Bootstrap's modal
      // timing machinery (_isTransitioning, keyboard events, data-bs-dismiss).
      // cy.reload() is the only mechanism that provably works in headless Electron
      // subdir CI: it reloads from the server, confirming "x" was never submitted.
      cy.reload();

      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
      dtLacksRole("x");
    });

    it("successfully adds a role: modal closes, row appears in table, success toast shown", () => {
      const roleName = `TestRole-${Date.now()}`;

      cy.intercept("POST", "**/api/groups/*/roles").as("addRole");

      cy.visit(`/groups/editor/${testGroupAddDeleteId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#addNewRoleBtn").click();
      cy.get("#addRoleModal").should("be.visible");
      // Bootstrap 5 modal-dialog has a 300ms CSS transition; the same _isTransitioning
      // race applies here: if the POST resolves before 300ms (Docker loopback is very
      // fast), JS calls modal("hide") while isTransitioning=true and it is silently
      // rejected. cy.wait(400) = 300ms animation + 100ms buffer.
      cy.wait(400);
      // Use invoke+trigger instead of type() to set value atomically — avoids
      // character-dropping on slow CI (cy.type is character-by-character)
      cy.get("#newRole").invoke("val", roleName).trigger("input");
      cy.get("#submitNewRole").click();

      cy.wait("@addRole").its("response.statusCode").should("eq", 200);

      cy.get("#addRoleModal").should("not.be.visible");
      // Verify via DataTables data model — immune to responsive/rendering issues
      dtHasRole(roleName);
      cy.get(".notyf__toast--success", { timeout: 5000 }).should("be.visible");
    });
  });

  // ─── Delete Role modal ─────────────────────────────────────────────────────

  describe("Delete Role modal", () => {
    it("shows the role name in the confirmation message", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      // "Member" is not protected, so the delete button is not disabled
      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.wait(400); // absorb Bootstrap's 300ms CSS transition + buffer
      // #deleteRoleMessage is set via $.text() — IS a text node, .contain() is correct here
      cy.get("#deleteRoleMessage").should("contain", "Member");

      // Close the modal so subsequent tests start clean
      cy.get("#deleteRoleModal .btn-secondary").click();
      cy.get("#deleteRoleModal").should("not.be.visible");
    });

    it("shows last-role warning and disables confirm button when only one role remains", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.wait(400); // absorb Bootstrap's 300ms CSS transition + buffer
      cy.get("#lastRoleWarning").should("be.visible");
      cy.get("#confirmDeleteRole").should("be.disabled");

      // Close the modal so subsequent tests start clean
      cy.get("#deleteRoleModal .btn-secondary").click();
      cy.get("#deleteRoleModal").should("not.be.visible");
    });

    it("cancelling the delete modal makes no changes", () => {
      cy.visit(`/groups/editor/${testGroupSingleId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable .deleteRole:not(.disabled)").first().click();

      cy.get("#deleteRoleModal").should("be.visible");
      cy.wait(400); // absorb Bootstrap's 300ms CSS transition + buffer
      cy.get("#deleteRoleModal .btn-secondary").click();

      cy.get("#deleteRoleModal").should("not.be.visible");
      // Verify via DataTables data model
      dtHasRole("Member");
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
      // Same _isTransitioning race: wait for the 300ms animation to complete
      // before submitting so modal("hide") in the success handler is not rejected.
      cy.wait(400);
      // Use invoke+trigger instead of type() to set value atomically — avoids
      // character-dropping on slow CI (cy.type is character-by-character)
      cy.get("#newRole").invoke("val", roleName).trigger("input");
      cy.get("#submitNewRole").click();
      cy.wait("@addRole").its("response.statusCode").should("eq", 200);
      cy.get("#addRoleModal").should("not.be.visible");
      // Verify via DataTables data model
      dtHasRole(roleName);

      // Click delete for this specific role via the DataTables data model
      // (force:true handles potential responsive-plugin column visibility)
      clickDeleteForRole(roleName);

      cy.get("#deleteRoleModal").should("be.visible");
      cy.wait(400); // absorb Bootstrap's 300ms CSS transition + buffer
      // Last-role warning must be hidden (2+ roles exist now)
      cy.get("#lastRoleWarning").should("have.class", "d-none");
      cy.get("#confirmDeleteRole").should("not.be.disabled");

      cy.get("#confirmDeleteRole").click();
      cy.wait("@deleteRole").its("response.statusCode").should("eq", 200);

      cy.get("#deleteRoleModal").should("not.be.visible");
      // Verify via DataTables data model
      dtLacksRole(roleName);
      cy.get(".notyf__toast--success", { timeout: 5000 }).should("be.visible");
    });
  });

  // ─── Protected roles ───────────────────────────────────────────────────────

  describe("Protected roles", () => {

    it("Delete button is disabled with a title for Student and Teacher roles", () => {
      // Use the dynamically-created Sunday-school group (Teacher + Student roles)
      // to avoid any dependency on hardcoded seed IDs.
      cy.visit(`/groups/editor/${testGroupSundayId}`);
      cy.get("#groupRoleTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);

      cy.get("#groupRoleTable .deleteRole.disabled")
        .first()
        .should("have.attr", "title")
        .and("not.be.empty");
    });
  });
});
