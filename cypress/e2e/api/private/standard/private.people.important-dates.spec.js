/// <reference types="cypress" />

describe("API - important dates on people and families", () => {
    let personType;
    let repeatType;
    let familyType;

    const createType = (body) =>
        cy.makePrivateAdminAPICall("POST", "/admin/api/important-date-types", body, 201).then(({ body: list }) => list.types.find((t) => t.name === body.name));

    before(() => {
        createType({ name: "Baptism QA", appliesTo: "person" }).then((t) => {
            personType = t;
        });
        createType({ name: "Recommitment QA", appliesTo: "person", allowMultiple: true }).then((t) => {
            repeatType = t;
        });
        createType({ name: "House Blessing QA", appliesTo: "family" }).then((t) => {
            familyType = t;
        });
    });

    after(() => {
        for (const path of ["/api/person/2/important-dates", "/api/family/1/important-dates"]) {
            cy.makePrivateAdminAPICall("GET", path, null, 200).then(({ body }) => {
                const ours = [personType, repeatType, familyType].filter(Boolean).map((t) => t.id);
                for (const date of body.dates.filter((d) => ours.includes(d.typeId))) {
                    cy.makePrivateAdminAPICall("DELETE", `${path}/${date.id}`, null, 200);
                }
            });
        }
        for (const type of [personType, repeatType, familyType]) {
            if (type) {
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${type.id}`, null, [200, 404]);
            }
        }
    });

    it("records a date with an optional year and lists it with when it is next observed", () => {
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: personType.id, month: 6, day: 15, year: 2010, note: "Lake service" }, 201).then(({ body }) => {
            const date = body.dates.find((d) => d.typeId === personType.id);
            expect(date).to.include({ source: "custom", label: "Baptism QA", month: 6, day: 15, year: 2010, note: "Lake service", editable: true });
            expect(date.daysUntil).to.be.within(0, 366);
            expect(date.relative).to.be.a("string").and.not.be.empty;
            expect(date.yearsLabel).to.match(/^\d+ years?$/);
            expect(body.canEdit).to.eq(true);

            const days = body.dates.map((d) => d.daysUntil).filter((d) => d !== null);
            expect(days).to.deep.equal([...days].sort((a, b) => a - b));

            expect(body.types.find((t) => t.id === personType.id).recorded).to.eq(true);
        });

        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 1, day: 3 }, 201).then(({ body }) => {
            expect(body.dates.find((d) => d.typeId === repeatType.id)).to.include({ year: null, yearsLabel: null });
        });
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 9, day: 9 }, 201).then(({ body }) => {
            expect(body.dates.filter((d) => d.typeId === repeatType.id)).to.have.length(2);
        });
    });

    it("refuses a second date of a one-per-person type and impossible dates", () => {
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: personType.id, month: 7, day: 1 }, 409);
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 2, day: 29, year: 2023 }, 400);
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 4, day: 31 }, 400);
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 4, day: 1, year: 2999 }, 400);
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: familyType.id, month: 4, day: 1 }, 400);
    });

    it("accepts February 29 without a year or in a leap year", () => {
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 2, day: 29, year: 2024 }, 201).then(({ body }) => {
            const leap = body.dates.find((d) => d.typeId === repeatType.id && d.month === 2);
            expect(leap).to.include({ day: 29, year: 2024 });
            cy.makePrivateAdminAPICall("DELETE", `/api/person/2/important-dates/${leap.id}`, null, 200);
        });
    });

    it("changes the date and note in place, keeping the type", () => {
        cy.makePrivateAdminAPICall("GET", "/api/person/2/important-dates", null, 200).then(({ body }) => {
            const date = body.dates.find((d) => d.typeId === personType.id);
            cy.makePrivateAdminAPICall("PUT", `/api/person/2/important-dates/${date.id}`, { month: 6, day: 16, year: 2010, note: "" }, 200).then(({ body: after }) => {
                expect(after.dates.find((d) => d.id === date.id)).to.include({ typeId: personType.id, day: 16, note: null });
            });
            cy.makePrivateAdminAPICall("PUT", "/api/person/2/important-dates/999999", { month: 1, day: 1 }, 404);
        });
    });

    it("keeps recorded dates readable after the type is turned off, and stops new ones", () => {
        cy.makePrivateAdminAPICall("PATCH", `/admin/api/important-date-types/${repeatType.id}`, { active: false }, 200);
        cy.makePrivateAdminAPICall("GET", "/api/person/2/important-dates", null, 200).then(({ body }) => {
            expect(body.dates.filter((d) => d.typeId === repeatType.id)).to.have.length(2);
            expect(body.dates.find((d) => d.typeId === repeatType.id).typeActive).to.eq(false);
            expect(body.types.map((t) => t.id)).not.to.include(repeatType.id);
        });
        cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: repeatType.id, month: 3, day: 3 }, 400);
        cy.makePrivateAdminAPICall("PATCH", `/admin/api/important-date-types/${repeatType.id}`, { active: true }, 200);
    });

    it("refuses one date per record once several are recorded", () => {
        cy.makePrivateAdminAPICall("PATCH", `/admin/api/important-date-types/${repeatType.id}`, { allowMultiple: false }, 409);
    });

    it("places dates on the staff Important Dates calendar on the observed day", () => {
        const year = new Date().getFullYear();
        const range = `start=${year}-01-01T00:00:00&end=${year}-12-31T00:00:00`;
        cy.makePrivateAdminAPICall("GET", `/api/systemcalendars/4/fullcalendar?${range}`, null, 200).then(({ body }) => {
            const baptism = body.find((e) => e.title.startsWith("Baptism QA: "));
            expect(baptism.start).to.contain(`${year}-06-16`);
            expect(baptism.url).to.contain("/people/view/2");
        });
    });

    it("records family dates for users who edit records", () => {
        cy.makePrivateAdminAPICall("POST", "/api/family/1/important-dates", { typeId: familyType.id, month: 10, day: 2, year: 2018 }, 201).then(({ body }) => {
            const date = body.dates.find((d) => d.typeId === familyType.id);
            expect(date).to.include({ label: "House Blessing QA", year: 2018 });
            cy.makePrivateAdminAPICall("DELETE", `/api/family/1/important-dates/${date.id}`, null, 200);
        });
    });

    it("refuses a date from a staff user without Edit Records, who can still read them", () => {
        cy.makePrivateNoPermAPICall("POST", "/api/person/2/important-dates", { typeId: personType.id, month: 1, day: 1 }, 403);
        cy.makePrivateNoPermAPICall("GET", "/api/person/2/important-dates", null, 200).its("body.canEdit").should("eq", false);
    });
});
