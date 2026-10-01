/// <reference types="cypress" />

// Records are removed through the page's own session, the way a second tab would,
// because an API-key request in the middle of a test can drop the browser session.

const createdGroupIds = [];

function createGroup(body) {
    return cy.makePrivateAdminAPICall("POST", "/api/groups/", body).then((resp) => {
        createdGroupIds.push(resp.body.Id);
        return resp.body.Id;
    });
}

function deleteFromPage(path, body) {
    cy.window().then((win) =>
        win
            .fetch(`${win.CRM.root}/api/${path}`, {
                method: "DELETE",
                headers: { "Content-Type": "application/json" },
                body: body ? JSON.stringify(body) : undefined,
            })
            .then((res) => expect(res.status).to.eq(200)),
    );
}

function expectServerReason(alias) {
    cy.wait(alias).then(({ response }) => {
        expect(response.statusCode).to.be.within(400, 599);
        expect(response.body.message).to.be.a("string").and.not.be.empty;
        cy.waitForNotification(response.body.message);
    });
}

describe("Group and Sunday School actions show the server's reason (#10126)", () => {
    after(() => {
        createdGroupIds.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${id}`, null, [200, 404]);
        });
    });

    describe("as an admin", () => {
        const className = `Reason Class ${Date.now()}`;
        let editorSaveId;
        let editorOrderId;
        let viewId;
        let propsId;
        let classId;

        before(() => {
            createGroup({ groupName: `Reason Save ${Date.now()}` }).then((id) => {
                editorSaveId = id;
            });
            createGroup({ groupName: `Reason Order ${Date.now()}` }).then((id) => {
                editorOrderId = id;
                cy.makePrivateAdminAPICall("POST", `/api/groups/${id}/roles`, { roleName: "Helper" });
            });
            createGroup({ groupName: `Reason View ${Date.now()}` }).then((id) => {
                viewId = id;
            });
            createGroup({ groupName: `Reason Props ${Date.now()}` }).then((id) => {
                propsId = id;
                cy.makePrivateAdminAPICall("POST", `/api/groups/${id}/setGroupSpecificPropertyStatus`, {
                    GroupSpecificPropertyStatus: true,
                });
            });
            createGroup({ groupName: className, isSundaySchool: true }).then((id) => {
                classId = id;
                cy.makePrivateAdminAPICall("POST", `/api/groups/${id}/addperson/2`, { RoleID: 2 });
            });
        });

        after(() => {
            if (propsId) {
                cy.makePrivateAdminAPICall("POST", `/api/groups/${propsId}/setGroupSpecificPropertyStatus`, {
                    GroupSpecificPropertyStatus: false,
                });
            }
        });

        beforeEach(() => cy.setupAdminSession());

        it("Groups list: Add Group", () => {
            cy.intercept("POST", "**/api/groups/").as("createGroup");
            cy.visit("/groups/dashboard");
            cy.get("#groupName").type("A group name longer than the fifty characters the column can hold");
            cy.get("#addNewGroup").click();
            expectServerReason("@createGroup");
        });

        it("Group Editor: Save after the group was deleted elsewhere", () => {
            cy.intercept("POST", `**/api/groups/${editorSaveId}`).as("saveGroup");
            cy.visit(`/groups/editor/${editorSaveId}`);
            cy.get("#groupRoleTable tbody tr").should("have.length.at.least", 1);
            deleteFromPage(`groups/${editorSaveId}`);
            cy.get("#saveGroup").click();
            expectServerReason("@saveGroup");
        });

        it("Group Editor: role order reports a failure once", () => {
            cy.intercept("POST", `**/api/groups/${editorOrderId}/roles/*`).as("roleOrder");
            cy.visit(`/groups/editor/${editorOrderId}`);
            cy.get("#groupRoleTable .rollOrder").should("have.length", 2);

            cy.get("#groupRoleTable .rollOrder").first().click();
            cy.wait(["@roleOrder", "@roleOrder"]).each(({ response }) => {
                expect(response.statusCode).to.eq(200);
            });
            cy.get(".notyf__toast").should("not.exist");

            deleteFromPage(`groups/${editorOrderId}`);
            cy.get("#groupRoleTable .rollOrder").first().click();
            cy.wait("@roleOrder");
            expectServerReason("@roleOrder");
            cy.get(".notyf__toast").should("have.length", 1);
        });

        it("Group View: Active toggle after the group was deleted elsewhere", () => {
            cy.intercept("POST", `**/api/groups/${viewId}/settings/active/*`).as("toggleActive");
            cy.visit(`/groups/view/${viewId}`);
            cy.get("#membersTable_wrapper").should("exist");
            deleteFromPage(`groups/${viewId}`);
            cy.get("#group-view-toolbar").contains("button", "Actions").click();
            cy.get("#toggleGroupActive").click();
            expectServerReason("@toggleActive");
        });

        it("Group-Specific Properties form: move a field that was deleted elsewhere", () => {
            cy.visit(`/groups/${propsId}/properties/form`);
            ["Allergies", "Shirt Size"].forEach((fieldName) => {
                cy.get("input#newFieldName").clear().type(fieldName);
                cy.get('button[name="AddField"]').click();
                cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).should("exist");
            });

            cy.get('.js-delete-field[data-field-name="Shirt Size"]').then(($field) => {
                const propId = $field.data("prop-id");
                cy.intercept("PUT", `**/api/groups/${propsId}/formprops/${propId}/order`).as("moveField");
                deleteFromPage(`groups/${propsId}/formprops/${propId}`, { field: $field.data("field-id") });
                cy.wrap($field).closest(".dropdown").find("[data-bs-toggle='dropdown']").click();
                cy.get(`.js-reorder-field[data-prop-id="${propId}"]`).click();
            });
            expectServerReason("@moveField");
        });

        it("Sunday School dashboard: add the students of a class deleted elsewhere to the cart", () => {
            cy.intercept("GET", `**/api/groups/${classId}/roles`).as("classRoles");
            cy.visit("/groups/sundayschool/dashboard");
            cy.get("#sundayschoolClasses_wrapper input[type='search']").type(className);
            cy.contains("#sundayschoolClasses tbody tr", className).find('[data-bs-toggle="dropdown"]').click();
            deleteFromPage(`groups/${classId}`);
            cy.get(`.add-ss-role-to-cart[data-group-id="${classId}"][data-role-name="Student"]`).click();
            expectServerReason("@classRoles");
        });
    });

    describe("as a class manager without Add Event permission", () => {
        let classId;

        before(() => {
            createGroup({ groupName: `Reason Event ${Date.now()}`, isSundaySchool: true }).then((id) => {
                classId = id;
            });
        });

        beforeEach(() => cy.setupStandardSession());

        it("Sunday School class: Create Today's Event shows the refusal once", () => {
            cy.intercept("POST", "**/api/events/quick-create").as("quickCreate");
            cy.visit(`/groups/sundayschool/class/${classId}`);
            cy.get("#quickCreateTodaysEventBtn").click();
            expectServerReason("@quickCreate");
            cy.get(".notyf__toast").should("have.length", 1);
        });
    });
});
