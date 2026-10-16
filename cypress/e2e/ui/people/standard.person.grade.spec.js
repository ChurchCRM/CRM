/// <reference types="cypress" />

// School grade on the person (#10189). Seed grades are the US names, option ID = level:
// 1st 6, 3rd 8.
describe("Standard person grade", () => {
    let familyId;
    let personId;

    const gradeOf = () => cy.request(`/api/person/${personId}`).its("body.GradeId");

    before(() => {
        cy.setupStandardSession();
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type("Gradefam");
        cy.get('input[name="FirstName1"]').type("Gina");
        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname").should("include", "/people/family/");
        cy.location("pathname").then((pathname) => {
            familyId = Number(pathname.split("/").pop());
        });
        cy.request("/api/persons/search/Gradefam").then((response) => {
            personId = response.body.find((hit) => hit.objid).objid;
        });
    });

    after(() => {
        cy.cleanupFamilies([familyId]);
    });

    beforeEach(() => cy.setupStandardSession());

    it("sets a grade in the person editor and shows it on the person view", () => {
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get("#Grade option").first().should("have.value", "");
        cy.get("#Grade").select("6");
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("include", `/people/view/${personId}`);
        cy.get("#personGrade").should("contain", "1st");
        gradeOf().should("eq", 6);
    });

    it("filters the People list by grade", () => {
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get("#Grade").select("6");
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("include", `/people/view/${personId}`);
        cy.visit("/people/list?Grade=6");
        cy.get("select.filter-Grade").invoke("val").should("deep.equal", ["6"]);
        cy.get("#members tbody").should("contain", "Gina Gradefam");
        cy.visit("/people/list?Grade=7");
        cy.get("#members tbody").should("not.contain", "Gina Gradefam");
    });

    it("sets a member's grade in the family editor", () => {
        cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
        cy.get("select[name='Grade1']").select("8");
        cy.get("button[name='FamilySubmit']").click();
        cy.url().should("include", `/people/family/${familyId}`);
        gradeOf().should("eq", 8);
    });

    it("clears the grade when Unassigned is chosen", () => {
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get("#Grade").select("");
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("include", `/people/view/${personId}`);
        cy.get("#personGrade").should("not.exist");
        gradeOf().should("be.null");
    });
});
