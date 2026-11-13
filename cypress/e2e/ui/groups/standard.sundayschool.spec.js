/// <reference types="cypress" />

// Seed data: group 1 is "Angels class" (type=4, Sunday School).
// It has role-list 13, where role 1=Teacher and role 2=Student.
// p2g2r seed rows for group 1: (4,1,1),(5,1,2),(8,1,2),(9,1,2),(63,1,1)
//   → 3 students (role 2) and 2 teachers (role 1).
const ANGELS_CLASS_GROUP_ID = 1;

describe("Standard Sunday School", () => {
    beforeEach(() => cy.setupStandardSession());

    it("shows the dashboard's class and student counts and its report links", () => {
        cy.visit("groups/sundayschool/dashboard");
        cy.contains("Sunday School Dashboard");
        cy.contains("Sunday School Classes");
        cy.contains("Students not in a Sunday School Class");
        cy.get("#sundayschoolMissing thead th").should("contain", "Actions");
        // Numeric stats from the batch getDashboardStudentStats() query
        ["Boys", "Girls", "Families"].forEach((label) => {
            cy.get(".text-muted, .text-body-secondary")
                .contains(label)
                .prev(".fw-medium")
                .invoke("text")
                .then((text) => {
                    expect(Number(text.trim())).to.be.at.least(0);
                });
        });
        cy.contains("Teachers")
            .parent()
            .find(".fw-medium")
            .invoke("text")
            .then((text) => {
                expect(Number(text.trim())).to.be.at.least(0);
            });
        cy.contains("Classes").should("exist");
        cy.get('a[href$="/groups/reports/photo-book"]').should("be.visible");
        cy.get('a[href$="/groups/sundayschool/reports/class-list"]').should("be.visible");
        cy.get('a[href$="/groups/reports"]').should("be.visible");
        cy.get('a[href$="/groups/sundayschool/reports"]').should("contain", "Attendance Sheet");
        cy.get('a[href*="/api/groups/sundayschool/export/classlist"]').should("contain", "Class List Export");
        cy.get('a[href*="/api/groups/sundayschool/export/email"]').should("contain", "Email Export");
    });

    it("Sunday School classes table has action menus", () => {
        cy.visit("groups/sundayschool/dashboard");
        cy.get("#sundayschoolClasses tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
        cy.get("#sundayschoolClasses tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show:not(.navbar-vertical .dropdown-menu)").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.contains("Delete").should("exist");
        });
    });

    it("shows the class with its students, gender chart and sidebar", () => {
        cy.visit(`groups/sundayschool/class/${ANGELS_CLASS_GROUP_ID}`);
        cy.get("a.btn-ghost-success").contains("Add Students").should("exist");
        cy.get("a.btn-ghost-primary").contains("Edit Class").should("exist");
        // Regression: students of a deactivated family must still be listed
        cy.get("#sundayschool tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get("#sundayschool tbody tr td").first().should("not.be.empty");
        cy.get("#sundayschool thead th").should((headers) => {
            const headerText = Array.from(headers).map((h) => h.textContent);
            ["Name", "Age", "Mobile", "Email", "Father", "Mother"].forEach((column) => {
                expect(headerText).to.include(column);
            });
        });
        cy.get("[id^='studentModal-']").first().find(".modal-body").should("contain", "Student Information");
        // ApexCharts must render into the container, not leave an empty div
        cy.get("#bar-chart .apexcharts-canvas").should("exist");
        cy.get("#bar-chart svg.apexcharts-svg").should("exist");
        cy.contains("Enrolled").should("exist");
        ["Boys", "Girls"].forEach((label) => {
            cy.get(".text-muted, .text-body-secondary")
                .contains(label)
                .prev(".fw-medium")
                .invoke("text")
                .then((text) => {
                    expect(Number(text.trim())).to.be.at.least(0);
                });
        });
        cy.contains(".card-title", "About").should("exist");
        cy.contains(".card-title", "Properties").should("exist");
        cy.contains(".card-title", "Events").should("exist");
    });

    it("Reports page is the attendance sheet form", () => {
        cy.visit("groups/sundayschool/reports");
        cy.contains("Report Details");
        ["GroupID[]", "FirstSunday", "LastSunday", "NoSchool1", "ExtraStudents", "ExtraTeachers"].forEach((name) => {
            cy.get(`input[name="${name}"]`).should("exist");
        });
        cy.get('button[name="SubmitClassAttendance"]').should("contain", "Create Attendance Sheet");
    });

    it("Reports page shows error when no group selected", () => {
        cy.visit("groups/sundayschool/reports?error=nogroup");
        cy.get(".alert-danger").should("contain", "At least one group must be selected");
    });

    describe("Delete a class that is an event's audience (#10126)", () => {
        const className = `Audience Class ${Date.now()}`;
        let groupId;
        let eventId;

        before(() => {
            cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName: className, isSundaySchool: true })
                .then((resp) => {
                    groupId = resp.body.Id;
                    return cy.makePrivateAdminAPICall("POST", "/api/events/quick-create", { groupId });
                })
                .then((resp) => {
                    eventId = resp.body.eventId;
                });
        });

        after(() => {
            if (eventId) cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventId}`);
            if (groupId) cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`);
        });

        it("shows the server's reason instead of a generic error", () => {
            cy.intercept("DELETE", `**/api/groups/${groupId}`).as("deleteClass");
            cy.visit("/groups/sundayschool/dashboard");
            cy.get("#sundayschoolClasses_wrapper input[type='search']").type(className);
            cy.contains("#sundayschoolClasses tbody tr", className).find('[data-bs-toggle="dropdown"]').click();
            cy.get(`.delete-ss-class[data-group-id="${groupId}"]`).click();
            cy.get(".bootbox .btn-danger").click();

            cy.wait("@deleteClass").then(({ response }) => {
                expect(response.statusCode).to.eq(409);
                cy.waitForNotification(response.body.message);
            });
        });
    });
});
