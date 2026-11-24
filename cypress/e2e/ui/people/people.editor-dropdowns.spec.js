/// <reference types="cypress" />

// The Person and Family editors list classifications, family roles and families,
// re-select the saved values, and print option names escaped.
const suffix = Date.now() % 100000;
// The options API strips tags; `< ` followed by a space survives and must reach the page escaped.
const CLASSIFICATION = `CyCls${suffix} < D & E`;
const FAMILY_ROLE = `CyRole${suffix} < D & E`;
const escaped = (name) => name.replaceAll("&", "&amp;").replaceAll("<", "&lt;");
const LAST_NAME = `CyDropdowns${suffix}`;
const created = { classificationId: null, roleId: null, personId: null, familyId: null };

const createOption = (listId, name, key) =>
    cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
        created[key] = resp.body.optionId ?? resp.body.id;
        expect(created[key], `${name} id`).to.be.a("number");
    });

describe("Person and Family editor dropdowns", () => {
    before(() => {
        createOption(1, CLASSIFICATION, "classificationId");
        createOption(2, FAMILY_ROLE, "roleId");

        cy.freshAdminFormLogin({ clearAll: true, visible: true });
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type("Dropdowns");
        cy.get("#LastName").type(LAST_NAME);
        cy.get("#Gender").select("1");
        cy.then(() => {
            cy.get("#Classification").select(String(created.classificationId));
            cy.get("#familyId").select("-1", { force: true });
            cy.get("#FamilyRole").select(String(created.roleId), { force: true });
        });
        cy.get('button[name="PersonSubmit"]').click();
        cy.location("pathname")
            .should("include", "/people/view/")
            .then((pathname) => {
                created.personId = Number(pathname.split("/").pop());
            });
    });

    after(() => {
        cy.cleanupPeople([created.personId]);
        cy.cleanupFamilies([created.familyId]);
        [
            [1, created.classificationId],
            [2, created.roleId],
        ].forEach(([listId, optionId]) => {
            if (optionId !== null) {
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${listId}/${optionId}`, null, [200, 404]);
            }
        });
    });

    it("re-selects the saved classification, family role and family on the Person editor", () => {
        cy.setupAdminSession();
        cy.visit(`/PersonEditor.php?PersonID=${created.personId}`);
        cy.get("#Classification").should("have.value", String(created.classificationId));
        cy.get("#FamilyRole").should("have.value", String(created.roleId));
        cy.get("#familyId")
            .invoke("val")
            .then((familyId) => {
                expect(Number(familyId), "new family id").to.be.greaterThan(0);
                created.familyId = Number(familyId);
            });
        cy.get("#familyId option:selected").should("contain", LAST_NAME);
    });

    it("prints classification and family role names escaped on the Person editor", () => {
        cy.setupAdminSession();
        cy.visit(`/PersonEditor.php?PersonID=${created.personId}`);
        cy.get(`#Classification option[value="${created.classificationId}"]`).should("contain", CLASSIFICATION);
        cy.get(`#FamilyRole option[value="${created.roleId}"]`).should("contain", FAMILY_ROLE);
        cy.request(`/PersonEditor.php?PersonID=${created.personId}`)
            .its("body")
            .should("contain", escaped(CLASSIFICATION))
            .and("contain", escaped(FAMILY_ROLE));
    });

    it("re-selects the member classification on the Family editor and prints names escaped", () => {
        cy.setupAdminSession();
        cy.then(() => cy.visit(`/FamilyEditor.php?FamilyID=${created.familyId}`));
        cy.get('select[name="Classification1"]').should("have.value", String(created.classificationId));
        cy.get(`select[name="Classification1"] option[value="${created.classificationId}"]`).should(
            "contain",
            CLASSIFICATION,
        );
        cy.then(() => cy.request(`/FamilyEditor.php?FamilyID=${created.familyId}`))
            .its("body")
            .should("contain", escaped(CLASSIFICATION))
            .and("contain", escaped(FAMILY_ROLE));
    });

    it("lists classifications and family roles in an added member row on a new family", () => {
        cy.setupAdminSession();
        cy.visit("/FamilyEditor.php");
        cy.get("#addFamilyMemberRow").click();
        cy.get("#familyMembersTbody tr")
            .last()
            .within(() => {
                cy.get(`select[name^="Classification"] option[value="${created.classificationId}"]`).should(
                    "contain",
                    CLASSIFICATION,
                );
                cy.get(`select[name^="Role"] option[value="${created.roleId}"]`).should("contain", FAMILY_ROLE);
            });
    });
});
