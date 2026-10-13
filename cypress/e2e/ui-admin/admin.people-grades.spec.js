/// <reference types="cypress" />

// School grades (#10189): People Settings, the Grades editor, People by Grade and the
// class roster. Seed grades are the US names at list 6, option ID = level.
describe("School grades", () => {
    const SETTINGS = ["iGradePromotionMonth", "iGradePromotionDay", "sLastGradePromotionAt", "sLastGradePromotionResult", "sGradePromotionSetDate"];
    const saved = {};
    const people = {};
    let membershipClassId;

    const gradeOf = (key) =>
        cy.dbQuery("SELECT per_grd_ID AS grade FROM person_per WHERE per_ID = ?", [people[key]]).then((result) => result.rows[0].grade);
    const reportRows = () => cy.get("#reportResults tbody tr").filter((_, row) => row.innerText.includes("Gradeling"));
    const setGrades = (levels) =>
        Object.entries(levels).forEach(([key, level]) =>
            cy.dbQuery("UPDATE person_per SET per_grd_ID = ? WHERE per_ID = ?", [level, people[key]]),
        );

    before(() => {
        SETTINGS.forEach((name) => cy.getSystemConfig(name).then((value) => (saved[name] = value)));
        [
            ["kinder", "Kim", 5],
            ["second", "Sam", 7],
            ["seventh", "Sev", 12],
        ].forEach(([key, firstName, level]) => {
            cy.dbQuery(
                "INSERT INTO person_per (per_FirstName, per_LastName, per_DateEntered, per_grd_ID) VALUES (?, 'Gradeling', NOW(), ?)",
                [firstName, level],
            ).then((result) => (people[key] = result.rows.insertId));
        });
    });

    after(() => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/grades/presets/us/preview", null, 200).then((resp) =>
            cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/us", { expectedState: resp.body.state }, 200),
        );
        SETTINGS.forEach((name) => cy.restoreSystemConfig(name, saved[name]));
        cy.then(() => {
            if (membershipClassId) {
                cy.dbQuery("DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = ?", [people.second]);
            }
            cy.cleanupPeople(Object.values(people));
        });
    });

    beforeEach(() => cy.setupAdminSession());

    it("People Settings links to the Grades editor and holds the promotion date", () => {
        cy.intercept("POST", "**/admin/api/system/config/iGradePromotionMonth").as("saveMonth");
        cy.restoreSystemConfig("iGradePromotionMonth", "0");
        cy.visit("/admin/people");
        cy.get("#gradeNextPromotion").should("have.text", "Off");
        cy.get(".container-fluid a.card").contains("Grades").closest("a").should("have.attr", "href").and("include", "mode=grades");
        cy.get("#peopleGrades .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
        cy.get("#peopleGrades select[name='iGradePromotionMonth']").select("6");
        cy.wait("@saveMonth").its("response.statusCode").should("eq", 200);
        cy.getSystemConfig("iGradePromotionMonth").should("eq", "6");
        cy.get("#gradeNextPromotion").should("contain", "June");
        cy.get("#peopleGrades input[name='iGradePromotionDay']").should("exist");
    });

    it("Promote Now asks first, then moves everyone up one grade", () => {
        cy.dbQuery("UPDATE person_per SET per_grd_ID = 5 WHERE per_ID = ?", [people.kinder]);
        cy.visit("/admin/people");
        cy.get("#gradePromoteNow").click();
        cy.get(".bootbox").should("be.visible").and("contain", "Moving up").and("contain", "Finishing the last grade (grade will be cleared)");
        cy.get(".bootbox .btn-primary").click();
        cy.get("#gradeLastPromotion").should("contain", "Church Admin");
        gradeOf("kinder").should("eq", 6);

        cy.get("#gradePromoteNow").click();
        cy.get(".bootbox").should("be.visible").and("contain", "Grades were already promoted this year");
        cy.get(".bootbox .btn-secondary").click();
        gradeOf("kinder").should("eq", 6);
    });

    it("refuses a grade change from the browser without the page's CSRF token", () => {
        cy.request({ method: "POST", url: "/admin/api/grades/promotion", body: { expectedLastAt: "" }, failOnStatusCode: false })
            .its("status")
            .should("eq", 403);
        cy.request({ method: "POST", url: "/admin/api/grades/presets/england-wales", failOnStatusCode: false })
            .its("status")
            .should("eq", 403);
    });

    it("the Grades editor switches to another country's names after a preview", () => {
        cy.visit("/admin/system/options?mode=grades");
        cy.get(".page-title").should("contain", "Grades Editor");
        cy.get("#gradePreset").should("have.value", "us");
        cy.get("#gradePreset").select("england-wales");
        cy.get("#applyGradePreset").click();
        cy.get(".bootbox").should("be.visible").and("contain", "1st → Year 2").and("contain", "Nursery");
        cy.get(".bootbox .btn-primary").click();
        cy.get(".option-name-input").first().should("have.value", "Nursery");
        cy.get("#gradePreset").should("have.value", "england-wales");
        cy.makePrivateAdminAPICall("GET", "/admin/api/grades/presets/us/preview", null, 200).then((resp) =>
            cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/us", { expectedState: resp.body.state }, 200),
        );
    });

    it("People by Grade lists a range of grades in grade order and adds them to the cart", () => {
        setGrades({ kinder: 5, second: 7, seventh: 12 });
        cy.request({ method: "DELETE", url: "/api/cart/", body: {} });
        cy.visit("/people/reports/people-by-grade?gradeFrom=7&gradeTo=5");
        reportRows().should("have.length", 2);
        reportRows().eq(0).should("contain", "Kindergarten").and("contain", "Kim Gradeling");
        reportRows().eq(1).should("contain", "2nd").and("contain", "Sam Gradeling");

        cy.get("#addAllToCart").click();
        cy.request("/api/cart/").its("body.PeopleCart").should("include.members", [people.kinder, people.second]);
        cy.request("/api/cart/").its("body.PeopleCart").should("not.include", people.seventh);
        cy.request({ method: "DELETE", url: "/api/cart/", body: {} });
    });

    it("People by Grade needs a starting grade and exports CSV", () => {
        setGrades({ seventh: 12 });
        cy.visit("/people/reports/people-by-grade");
        cy.get("#gradeFrom").should("not.have.class", "is-invalid");
        cy.get("#reportResults").should("not.exist");
        cy.visit("/people/reports/people-by-grade?gradeTo=6");
        cy.get("#gradeFrom").should("have.class", "is-invalid").select("5");
        cy.get("#gradeFrom").should("not.have.class", "is-invalid");
        cy.request(`/people/reports/people-by-grade/csv?gradeFrom=12`).then((response) => {
            const lines = response.body.trim().split(/\r?\n/);
            expect(lines[0]).to.eq("Grade,Name");
            expect(response.body).to.include("Sev Gradeling");
        });
    });

    it("the Sunday School class roster shows each student's grade", () => {
        cy.dbQuery(
            `SELECT g.grp_ID AS classId, l.lst_OptionID AS studentRole FROM group_grp g
             JOIN list_lst l ON l.lst_ID = g.grp_RoleListID AND l.lst_OptionName = 'Student'
             WHERE g.grp_Type = 4 ORDER BY g.grp_ID LIMIT 1`,
        ).then((result) => {
            const { classId, studentRole } = result.rows[0];
            membershipClassId = classId;
            setGrades({ second: 7 });
            cy.dbQuery("INSERT INTO person2group2role_p2g2r (p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID) VALUES (?, ?, ?)", [people.second, classId, studentRole]);
            cy.visit(`/groups/sundayschool/class/${classId}`);
            cy.get("#sundayschool thead th").eq(1).should("contain", "Grade");
            cy.get(`#sundayschool tr[data-person-id='${people.second}'] td`).eq(1).should("have.text", "2nd");
        });
    });
});
