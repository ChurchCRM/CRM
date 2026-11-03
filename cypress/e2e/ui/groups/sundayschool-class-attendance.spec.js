/// <reference types="cypress" />

import { pdfDrawing, pdfText } from "../../../support/pdf-text";
import { buildBlankPng } from "../../../support/synthetic-png";

const ANGELS_CLASS_GROUP_ID = 1;
const CLASS_NAMES = ["Angels class", "Class 1-3", "Class 4-5", "Class 6-7"];
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

    it("prints the church header on every class's page", () => {
        cy.setupAdminSession();
        cy.request("/admin/api/system/config/sChurchName").its("body.value").then((churchName) => {
            cy.request({ url: attendance(`GroupID=1,2,3,4&FYID=&AllRoles=&pictures=&${dates}`), encoding: "binary" })
                .then((response) => pdfText(response.body))
                .then((lines) => {
                    const classes = CLASS_NAMES.filter((name) => lines.some((line) => line.includes(name)));
                    expect(classes.length).to.be.greaterThan(1);
                    expect(lines.filter((line) => line.includes(churchName)).length).to.be.at.least(classes.length);
                });
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


describe("Attendance portraits and configured initials", () => {
    const people = [];
    const marker = `Attendance${Date.now()}`;
    let originalStyle;
    let originalNameStyle;
    const sheet = (pictures) => cy.request({
        url: attendance(`GroupID=1&pictures=${pictures}&${dates}`), encoding: "binary",
    });

    before(() => {
        cy.getSystemConfig("iPersonInitialStyle").then((value) => { originalStyle = value; });
        cy.getSystemConfig("iPersonNameStyle").then((value) => { originalNameStyle = value; });
        cy.setupAdminSession();
        cy.request("POST", "/admin/api/system/config/iPersonNameStyle", { value: "0" });
        ["Xenia", "Perry"].forEach((firstName) => {
            cy.visit("/PersonEditor.php");
            cy.get("#FirstName").type(firstName);
            cy.get("#LastName").type(`Yarrow${marker}`);
            cy.get("#Gender").select("1");
            cy.get("#Classification").select("1");
            cy.get('button[name="PersonSubmit"]').click();
            cy.location("pathname").should("match", /\/people\/view\/\d+$/).then((path) => {
                const id = Number(path.split("/").pop());
                people.push(id);
                cy.request("POST", `/api/groups/1/addperson/${id}`, { RoleID: 2 });
            });
        });
        cy.then(() => cy.wrap(buildBlankPng(200, 300)).then((imgBase64) => {
            cy.request("POST", `/api/person/${people[1]}/photo`, { imgBase64 });
        }));
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("iPersonInitialStyle", originalStyle);
        cy.restoreSystemConfig("iPersonNameStyle", originalNameStyle);
        cy.then(() => cy.request("DELETE", `/api/person/${people[1]}/photo`));
        cy.then(() => people.forEach((id) => cy.request("DELETE", `/api/groups/1/removeperson/${id}`)));
        cy.cleanupPeople(people);
    });

    [["0", "XY"], ["1", "XE"]].forEach(([style, initials]) => {
        it(`prints configured ${initials} initials and bounded photos`, () => {
            cy.request("POST", "/admin/api/system/config/iPersonInitialStyle", { value: style });
            sheet(1).then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return Promise.all([pdfText(response.body), pdfDrawing(response.body)]).then(([lines, drawing]) => {
                    expect(lines).to.include(initials);
                    expect(lines.join(" ")).to.include(`Xenia Yarrow${marker}`);
                    const portraits = drawing.filter((item) => item.image && Math.abs(item.width - 8 * 72 / 25.4) < 0.2);
                    expect(portraits, "8mm portraits").not.to.be.empty;
                    portraits.forEach((photo) => expect(photo.height).to.be.closeTo(8 * 72 / 25.4, 0.2));
                });
            });
        });
    });

    it("keeps the compact sheet free of portrait placeholders", () => {
        sheet(0).then((response) => pdfText(response.body)).then((lines) => {
            expect(lines).not.to.include("XY").and.not.to.include("XE");
            expect(lines.join(" ")).to.include(`Xenia Yarrow${marker}`);
        });
    });
});
