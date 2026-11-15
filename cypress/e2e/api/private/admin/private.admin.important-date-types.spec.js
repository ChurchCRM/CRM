/// <reference types="cypress" />

describe("Admin API - important date types", () => {
    const created = [];

    const create = (body, status = 201) =>
        cy.makePrivateAdminAPICall("POST", "/admin/api/important-date-types", body, status);

    const typeNamed = (body, name) => body.types.find((t) => t.name === name);

    after(() => {
        for (const id of created) {
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${id}`, null, [200, 404]);
        }
    });

    it("offers an unused installed type again as a suggestion once deleted, and restores it in one step", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/important-date-types", null, 200).then(({ body }) => {
            const installed = body.types.find((t) => t.key === "ordination");
            expect(installed).to.include({ name: "Ordination", appliesTo: "person", usage: 0 });
            expect(body.presets.map((p) => p.key)).not.to.include("ordination");

            cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${installed.id}`, null, 200).then(({ body: afterDelete }) => {
                expect(afterDelete.presets.map((p) => p.key)).to.include("ordination");
            });

            create({ preset: "ordination" }).then(({ body: afterAdd }) => {
                const type = afterAdd.types.find((t) => t.key === "ordination");
                expect(type).to.include({ name: "Ordination", appliesTo: "person", allowMultiple: false, showOnCalendar: true, active: true, usage: 0 });
                expect(afterAdd.presets.map((p) => p.key)).not.to.include("ordination");
                cy.makePrivateAdminAPICall("PATCH", `/admin/api/important-date-types/${type.id}`, { sortOrder: installed.sortOrder }, 200);
            });
        });
    });

    it("adds, renames and switches options on a church-defined type", () => {
        create({ name: "Commissioning QA", appliesTo: "person", allowMultiple: true, showOnCalendar: false }).then(({ body }) => {
            const type = typeNamed(body, "Commissioning QA");
            created.push(type.id);
            expect(type).to.include({ allowMultiple: true, showOnCalendar: false, active: true });

            cy.makePrivateAdminAPICall("PATCH", `/admin/api/important-date-types/${type.id}`, { name: "Commissioned QA", showOnCalendar: true, active: false }, 200).then(
                ({ body: after }) => {
                    expect(typeNamed(after, "Commissioned QA")).to.include({ showOnCalendar: true, active: false, key: type.key });
                },
            );
        });
    });

    it("refuses a duplicate name, a missing name and an unknown audience", () => {
        create({ name: "Duplicate QA", appliesTo: "family" }).then(({ body }) => {
            created.push(typeNamed(body, "Duplicate QA").id);
        });
        create({ name: "duplicate qa", appliesTo: "family" }, 409);
        create({ name: "   ", appliesTo: "person" }, 400);
        create({ name: "Audience QA", appliesTo: "group" }, 400);
    });

    it("deletes a type only while no dates use it", () => {
        create({ name: "In Use QA", appliesTo: "person" }).then(({ body }) => {
            const type = typeNamed(body, "In Use QA");
            created.push(type.id);
            cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: type.id, month: 5, day: 4 }, 201).then(({ body: dates }) => {
                const record = dates.dates.find((d) => d.typeId === type.id);

                cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${type.id}`, null, 409);
                cy.makePrivateAdminAPICall("GET", "/admin/api/important-date-types", null, 200).its("body").then((list) => {
                    expect(typeNamed(list, "In Use QA").usage).to.eq(1);
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/person/2/important-dates/${record.id}`, null, 200);
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${type.id}`, null, 200).its("body").then((list) => {
                    expect(typeNamed(list, "In Use QA")).to.be.undefined;
                });
            });
        });
    });

    it("keeps the Important Dates calendar off the Member Portal choices", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/calendars", null, 200).then(({ body }) => {
            const system = body.calendars.filter((c) => c.type === "system").map((c) => c.id);
            expect(system).to.include(0);
            expect(system).not.to.include(4);
        });
    });
});
