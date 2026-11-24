/// <reference types="cypress" />

describe("API Private Admin Annual Calendar Activity Filters", () => {
    let familyId;
    const personIds = [];

    const createFromEditor = (url, fields, pattern) => {
        cy.freshAdminFormLogin();
        return cy.request(url).then(({ body }) => {
            const form = Cypress.$(new DOMParser().parseFromString(body, "text/html")).find("form");
            const values = {};
            form.serializeArray().forEach(({ name, value }) => {
                values[name] = value;
            });
            return cy.request({ method: "POST", url, form: true, body: { ...values, ...fields }, followRedirect: false });
        }).then(({ status, headers }) => {
            expect(status).to.eq(302);
            expect(headers.location).to.match(pattern);
            return Number(pattern.exec(headers.location)[1]);
        });
    };

    const expectCalendarEvent = (calendarId, entityId, visible) => {
        const year = new Date().getFullYear();
        ["events", "fullcalendar"].forEach((feed) => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${calendarId}/${feed}?start=${year}-01-01&end=${year}-12-31`,
                null,
                200,
            ).then(({ body }) => {
                const events = body.filter((event) => Number(event.Id ?? event.id) === entityId);
                expect(events, `${feed} calendar ${calendarId} event for ${entityId}`).to.have.length(visible ? 1 : 0);
                if (visible) {
                    expect(events[0].Start ?? events[0].start).to.include(`${year}-07-15`);
                }
            });
        });
        cy.makePrivateAdminAPICall("GET", `/api/systemcalendars/${calendarId}/events/${entityId}`, null, 200).then(({ body }) => {
            expect(body, "individual annual event lookup").to.have.length(visible ? 2 : 0);
        });
    };

    const expectBirthday = (personId, visible) => expectCalendarEvent(0, personId, visible);
    const expectAnniversary = (visible) => expectCalendarEvent(1, familyId, visible);

    before(() => {
        createFromEditor("/FamilyEditor.php", { Name: "Birthday Calendar Test", WeddingDate: "2000-07-15", FamilySubmit: "" }, /\/people\/family\/(\d+)/)
            .then((id) => { familyId = id; });
        [true, false].forEach((inFamily) => {
            cy.then(() => createFromEditor("/PersonEditor.php", {
                FirstName: `Birthday${inFamily ? "Family" : "Standalone"}`,
                LastName: "CalendarTest",
                Gender: "1",
                Classification: "1",
                Family: inFamily ? familyId : "0",
                FamilyRole: "1",
                BirthMonth: "07",
                BirthDay: "15",
                BirthYear: "1990",
                PersonSubmit: "",
            }, /\/people\/view\/(\d+)/)).then((id) => {
                personIds.push(id);
            });
        });
    });

    after(() => {
        personIds.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${id}`, null, 200);
        });
        if (familyId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}`, null, 200);
        }
    });

    it("hides birthdays and anniversaries in an inactive family and restores them when reactivated", () => {
        const [memberId, standaloneId] = personIds;
        expectBirthday(memberId, true);
        expectAnniversary(true);
        expectBirthday(standaloneId, true);
        cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/activate/false`, null, 200);
        expectBirthday(memberId, false);
        expectAnniversary(false);
        expectBirthday(standaloneId, true);
        cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/activate/true`, null, 200);
        expectBirthday(memberId, true);
        expectAnniversary(true);
    });

    it("hides an inactive person's birthday and restores it when reactivated", () => {
        const [, standaloneId] = personIds;
        cy.makePrivateAdminAPICall("POST", `/api/person/${standaloneId}/activate/false`, null, 200);
        expectBirthday(standaloneId, false);
        cy.makePrivateAdminAPICall("POST", `/api/person/${standaloneId}/activate/true`, null, 200);
        expectBirthday(standaloneId, true);
    });
});
