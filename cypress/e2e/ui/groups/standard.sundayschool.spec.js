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

    it("SundaySchoolClassView shows all enrolled students (regression: deactivated-family students must not be excluded)", () => {
        // Step 1: use the Groups API as the authoritative source of truth for
        // enrolled students.  The API queries p2g2r directly with no family
        // filter, so it always returns every enrolled member.
        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${ANGELS_CLASS_GROUP_ID}/members`,
            null,
            200
        ).then((resp) => {
            const allMembers = resp.body.Person2group2roleP2g2rs;
            // Role 2 = Student (list_lst for this group's role list).
            // The API returns RoleId for each member.
            const studentCount = allMembers.filter((m) => m.RoleId === 2).length;
            expect(studentCount).to.be.greaterThan(0);

            // Step 2: visit the class view and verify the student table row
            // count matches exactly what the API says.  Before the fix, the
            // legacy raw-SQL query excluded students whose family was
            // deactivated or had no family record, causing the mismatch that
            // triggered the original bug report.
            cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);
            cy.contains("Angels class");

            cy.get("#sundayschool tbody tr").should("have.length", studentCount);
        });
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

    it("Student details modal opens when info button clicked", () => {
        cy.visit(`sundayschool/SundaySchoolClassView.php?groupId=${ANGELS_CLASS_GROUP_ID}`);

        // Get the first student row and find the modal button
        cy.get("#sundayschool tbody tr").first().within(() => {
            // Get the data-target attribute from the info button
            cy.get("button[data-target^='#studentModal-']").first().then(($btn) => {
                const modalTarget = $btn.attr("data-target");

                // Click the button to open modal
                cy.wrap($btn).click();

                // Verify the modal is visible
                cy.get(modalTarget).should("be.visible");
                cy.get(`${modalTarget} .modal-title`).should("exist");
                cy.get(`${modalTarget} .modal-body`).should("contain", "Student Information");
                cy.get(`${modalTarget} .modal-body`).should("contain", "Parents/Guardians");
            });
        });
    });
});
