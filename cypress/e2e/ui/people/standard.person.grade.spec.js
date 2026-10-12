/// <reference types="cypress" />

// School grade on the person (#10189). Seed grades are the US names, option ID = level:
// 1st 6, 3rd 8.
describe("Standard person grade", () => {
    let familyId;
    let personId;

    const gradeOf = () =>
        cy.dbQuery("SELECT per_grd_ID AS grade FROM person_per WHERE per_ID = ?", [personId]).then((result) => result.rows[0].grade);

    before(() => {
        cy.dbQuery("INSERT INTO family_fam (fam_Name, fam_DateEntered) VALUES ('Gradefam', NOW())").then((result) => {
            familyId = result.rows.insertId;
            cy.dbQuery("INSERT INTO family_custom (fam_ID) VALUES (?)", [familyId]);
            cy.dbQuery(
                "INSERT INTO person_per (per_FirstName, per_LastName, per_fam_ID, per_fmr_ID, per_Gender, per_DateEntered) VALUES ('Gina', 'Gradefam', ?, 3, 2, NOW())",
                [familyId],
            ).then((personResult) => {
                personId = personResult.rows.insertId;
                cy.dbQuery("INSERT INTO person_custom (per_ID) VALUES (?)", [personId]);
            });
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
        cy.dbQuery("UPDATE person_per SET per_grd_ID = 6 WHERE per_ID = ?", [personId]);
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
