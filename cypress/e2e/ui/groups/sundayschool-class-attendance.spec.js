/// <reference types="cypress" />

const ANGELS_CLASS_GROUP_ID = 1;
const attendance = (query) => `groups/sundayschool/reports/class-attendance?${query}`;
const dates = "FirstSunday=2026-09-06&LastSunday=2026-12-27&NoSchool1=2026-11-29&ExtraStudents=3&ExtraTeachers=1";

describe("Sunday School class attendance sheet", () => {
    it("a group manager gets the PDF", () => {
        cy.setupAdminSession();
        cy.request({ url: attendance(`GroupID=${ANGELS_CLASS_GROUP_ID}&FYID=&AllRoles=&pictures=&${dates}`), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("an unknown class still returns a PDF instead of an error", () => {
        cy.setupAdminSession();
        cy.request({ url: attendance(`GroupID=999999&${dates}`), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });

    it("missing dates send the user back to the report form", () => {
        cy.setupAdminSession();
        cy.request({ url: attendance(`GroupID=${ANGELS_CLASS_GROUP_ID}&FirstSunday=&LastSunday=`), followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("groups/sundayschool/reports?error=dates");
        });
    });

    it("a user without group management is sent to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.request({ url: attendance(`GroupID=${ANGELS_CLASS_GROUP_ID}&${dates}`), followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied");
        });
    });
});
