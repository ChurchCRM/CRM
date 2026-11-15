/// <reference types="cypress" />

describe("Office staff checks the important dates coming up this month", () => {
    let type;
    let other;

    const inDays = (days) => {
        const date = new Date();
        date.setDate(date.getDate() + days);
        return { month: date.getMonth() + 1, day: date.getDate() };
    };

    const createType = (name) =>
        cy.makePrivateAdminAPICall("POST", "/admin/api/important-date-types", { name, appliesTo: "person" }, 201).then(({ body }) =>
            body.types.find((t) => t.name === name),
        );

    before(() => {
        cy.useChurchTimeZone();
        createType("Upcoming QA").then((t) => {
            type = t;
            cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: t.id, ...inDays(5), note: "Upcoming QA note" }, 201);
        });
        createType("Upcoming Other QA").then((t) => {
            other = t;
            cy.makePrivateAdminAPICall("POST", "/api/person/2/important-dates", { typeId: t.id, ...inDays(20) }, 201);
        });
    });

    after(() => {
        cy.makePrivateAdminAPICall("GET", "/api/person/2/important-dates", null, 200).then(({ body }) => {
            for (const date of body.dates.filter((d) => [type?.id, other?.id].includes(d.typeId))) {
                cy.makePrivateAdminAPICall("DELETE", `/api/person/2/important-dates/${date.id}`, null, 200);
            }
        });
        cy.then(() => {
            for (const t of [type, other]) {
                if (t) {
                    cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${t.id}`, null, [200, 404]);
                }
            }
        });
        cy.useHostTimeZone();
    });

    beforeEach(() => cy.setupStandardSession());

    it("finds the dates from the People menu, narrows by type and window, and opens the person", () => {
        cy.visit("/people/dashboard");
        cy.get("#important-dates-dashboard-link").click();
        cy.location("pathname").should("contain", "/people/important-dates");

        cy.get("#important-dates-days").should("have.value", "30");
        cy.get("#important-dates-table").contains("tr", "Upcoming QA note").within(() => {
            cy.contains("Upcoming QA");
            cy.contains("in 5 days");
            cy.get("a[href*='/people/view/2']").should("exist");
        });
        cy.get("#important-dates-table").should("contain", "Upcoming Other QA");

        cy.get("#important-dates-type-filter").select("Upcoming QA");
        cy.get("#important-dates-filter").submit();
        cy.location("search").should("contain", `type=${type.id}`);
        cy.get("#important-dates-table tbody tr").each(($row) => {
            expect($row.attr("data-type-id")).to.eq(String(type.id));
        });

        cy.get("#important-dates-type-filter").select("All date types");
        cy.get("#important-dates-days").select("7");
        cy.get("#important-dates-filter").submit();
        cy.get("#important-dates-table").should("contain", "Upcoming QA note").and("not.contain", "Upcoming Other QA");

        cy.get("#important-dates-table").contains("tr", "Upcoming QA note").find("a[href*='/people/view/2']").click();
        cy.location("pathname").should("contain", "/people/view/2");
        cy.get("#important-dates-list").should("contain", "Upcoming QA");
    });
});
