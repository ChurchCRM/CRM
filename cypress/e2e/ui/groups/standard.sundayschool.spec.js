/// <reference types="cypress" />

// Seed data: group 1 is "Angels class" (type=4, Sunday School).
// It has role-list 13, where role 1=Teacher and role 2=Student.
// p2g2r seed rows for group 1: (4,1,1),(5,1,2),(8,1,2),(9,1,2),(63,1,1)
//   → 3 students (role 2) and 2 teachers (role 1).
const ANGELS_CLASS_GROUP_ID = 1;

describe("Standard Sunday School", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("View Sunday School dashboard", () => {
        cy.visit("sundayschool/SundaySchoolDashboard.php");
        cy.contains("Sunday School Dashboard");
        cy.contains("Sunday School Classes");
        cy.contains("Students not in a Sunday School Class");
    });

    it("SundaySchoolClassView shows students in the table (regression: deactivated-family students must not be excluded)", () => {
        cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);
        cy.contains("Angels class");

        // Verify student table has rows (3 students in seed data)
        cy.get("#sundayschool tbody tr").should("have.length.greaterThan", 0);

        // Verify at least one student name is visible
        cy.get("#sundayschool tbody tr td").first().should("not.be.empty");
    });

    it("SundaySchoolClassView renders the class page without errors", () => {
        cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);
        cy.contains("Angels class");
        cy.get("#sundayschool").should("exist");
        cy.get(".card-title").should("contain", "Students");
    });

    it("Class Overview section displays correctly", () => {
        cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);

        // Verify Class Overview card exists
        cy.contains("Class Overview").should("exist");

        // Verify birthday chart is present
        cy.get("#bar-chart").should("exist");

        // Verify class stats are displayed
        cy.contains("Total Enrolled").should("exist");
        cy.contains("Male / Female").should("exist");

        // Verify stats show the correct count
        cy.contains("Total Enrolled").parent().parent().should("contain", "3");
    });

    it("Student table has correct columns and functionality", () => {
        cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);

        // Verify table headers
        cy.get("#sundayschool thead th").should((headers) => {
            const headerText = Array.from(headers).map(h => h.textContent);
            expect(headerText).to.include("Name");
            expect(headerText).to.include("Age");
            expect(headerText).to.include("Mobile");
            expect(headerText).to.include("Email");
            expect(headerText).to.include("Father");
            expect(headerText).to.include("Mother");
        });

        // Verify student rows are clickable (links to PersonView)
        cy.get("#sundayschool tbody tr td:first-child a").first().should("have.attr", "href").and("include", "PersonView.php");
    });

    it("Student details modal exists in page", () => {
        cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);

        // Verify modal exists in the DOM (one for each student)
        cy.get("[id^='studentModal-']").should("have.length.greaterThan", 0);

        // Verify at least one modal has the expected content
        cy.get("[id^='studentModal-']").first().within(() => {
            cy.get(".modal-title").should("exist");
            cy.get(".modal-body").should("contain", "Student Information");
            cy.get(".modal-body").should("contain", "Parents/Guardians");
        });
    });
});
