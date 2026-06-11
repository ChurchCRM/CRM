/// <reference types="cypress" />

/**
 * API spec for GET /api/attendance/person/{personId}
 *
 * Test data is seeded in before() by checking person 2 (Mathew Campbell)
 * into event 1 (Sunday School Class Changes) via the events checkin API.
 * Cleaned up in after(). This avoids hardcoded assumptions about seed data.
 *
 * Standard user (tony.wade@example.com) has no EditRecords permission and
 * is not in the same family as person 2, so requesting person 2's attendance
 * as the standard user should return 403.
 */
describe("Person Attendance History API", () => {
    // Person 2 (Mathew Campbell) in family 1 is used for attendance seeding.
    // Person 1 (Admin) has no attendance records (not seeded here).
    // Event 1 is active and can accept check-ins.
    const PERSON_WITH_ATTENDANCE = 2;
    const PERSON_WITHOUT_ATTENDANCE = 1;
    const NONEXISTENT_PERSON = 99999;
    const EVENT_ID = 1;

    before(() => {
        // Seed: check person 2 into event 1
        cy.makePrivateAdminAPICall("POST", `/api/events/${EVENT_ID}/checkin`, { personId: PERSON_WITH_ATTENDANCE }, 200);
    });

    after(() => {
        // Cleanup: checkout person 2 from event 1 (removes the attendance record)
        cy.makePrivateAdminAPICall("POST", `/api/events/${EVENT_ID}/checkout`, { personId: PERSON_WITH_ATTENDANCE }, 200);
    });

    context("GET /api/attendance/person/:id — admin caller", () => {
        beforeEach(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`,
                "",
                200,
            ).as("attendanceResponse");
        });

        it("returns 200 with records and summary keys", () => {
            cy.get("@attendanceResponse").then((response) => {
                expect(response.body).to.have.property("records").that.is.an("array");
                expect(response.body).to.have.property("summary").that.is.an("object");
            });
        });

        it("returns at least one attendance record for the seeded person", () => {
            cy.get("@attendanceResponse").then((response) => {
                expect(response.body.records.length).to.be.at.least(1);
            });
        });

        it("record has required shape including eventUrl", () => {
            cy.get("@attendanceResponse").then((response) => {
                const rec = response.body.records[0];
                expect(rec).to.have.all.keys(
                    "attendId",
                    "eventId",
                    "eventUrl",
                    "eventTitle",
                    "eventTypeId",
                    "eventTypeName",
                    "eventStart",
                    "eventEnd",
                    "checkinDate",
                    "checkoutDate",
                    "eventInactive",
                );
                expect(rec.attendId).to.be.a("number");
                expect(rec.eventId).to.be.a("number");
                expect(rec.eventUrl).to.be.a("string").and.include("/event/view/");
                expect(rec.eventTitle).to.be.a("string");
                expect(rec.eventTypeName).to.be.a("string");
                expect(rec.eventStart).to.be.a("string");
                expect(rec.eventEnd).to.be.a("string");
                expect(rec.eventInactive).to.be.a("boolean");
            });
        });

        it("checkinDate is a non-null string (only actual check-ins returned)", () => {
            cy.get("@attendanceResponse").then((response) => {
                response.body.records.forEach((rec) => {
                    // Fix 1 guarantees all returned records have a checkin date
                    expect(rec.checkinDate).to.be.a("string").and.not.be.null;
                    expect(rec.checkoutDate === null || typeof rec.checkoutDate === "string").to.be.true;
                });
            });
        });

        it("summary has required shape", () => {
            cy.get("@attendanceResponse").then((response) => {
                const summary = response.body.summary;
                expect(summary).to.have.property("totalEvents").that.is.a("number");
                expect(summary).to.have.property("lastAttendanceDate");
                expect(summary).to.have.property("streaks").that.is.an("array");
            });
        });

        it("summary.totalEvents matches records.length", () => {
            cy.get("@attendanceResponse").then((response) => {
                expect(response.body.summary.totalEvents).to.equal(response.body.records.length);
            });
        });

        it("records are sorted by eventStart descending", () => {
            cy.get("@attendanceResponse").then((response) => {
                const records = response.body.records;
                if (records.length < 2) return;
                for (let i = 1; i < records.length; i++) {
                    const prev = new Date(records[i - 1].eventStart);
                    const curr = new Date(records[i].eventStart);
                    expect(prev.getTime()).to.be.at.least(curr.getTime());
                }
            });
        });
    });

    context("GET /api/attendance/person/:id — person with no attendance", () => {
        it("returns empty records array and zero totalEvents", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/attendance/person/${PERSON_WITHOUT_ATTENDANCE}`,
                "",
                200,
            ).then((response) => {
                expect(response.body.records).to.be.an("array").that.is.empty;
                expect(response.body.summary.totalEvents).to.equal(0);
                expect(response.body.summary.lastAttendanceDate).to.be.null;
                expect(response.body.summary.streaks).to.be.an("array").that.is.empty;
            });
        });
    });

    context("GET /api/attendance/person/:id — 403 IDOR check", () => {
        it("returns 403 when standard user requests another person's attendance (IDOR prevention)", () => {
            // Standard user (tony.wade@example.com, person 35, family 0) has no EditRecords
            // permission and is not a family member of person 2 (family 1).
            // canEditPerson() should deny access → HTTP 403.
            cy.makePrivateUserAPICall(
                "GET",
                `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`,
                "",
                403,
            ).then((response) => {
                expect(response.status).to.equal(403);
            });
        });
    });

    context("GET /api/attendance/person/:id — error cases", () => {
        it("returns 404 for nonexistent person", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/attendance/person/${NONEXISTENT_PERSON}`,
                "",
                404,
            ).then((response) => {
                expect(response.status).to.equal(404);
            });
        });

        it("returns 401 without API key", () => {
            cy.request({
                method: "GET",
                url: `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`,
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });
    });
});
