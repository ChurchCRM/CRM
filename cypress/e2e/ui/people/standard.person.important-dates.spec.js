/// <reference types="cypress" />

describe("Office staff records a baptism and keeps it current from the person page", () => {
    let baptism;

    before(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/important-date-types", { name: "Baptism UI", appliesTo: "person" }, 201).then(({ body }) => {
            baptism = body.types.find((t) => t.name === "Baptism UI");
        });
    });

    after(() => {
        cy.makePrivateAdminAPICall("GET", "/api/person/2/important-dates", null, 200).then(({ body }) => {
            for (const date of body.dates.filter((d) => d.typeId === baptism.id)) {
                cy.makePrivateAdminAPICall("DELETE", `/api/person/2/important-dates/${date.id}`, null, 200);
            }
        });
        cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${baptism.id}`, null, [200, 404]));
    });

    beforeEach(() => cy.setupStandardSession());

    it("adds the date, sees when it is next observed, corrects it, then removes it", () => {
        cy.visit("/people/view/2");
        cy.get("#important-dates-add").should("be.visible").click();

        cy.get("#important-dates-type").select("Baptism UI");
        cy.get("#important-dates-month").select("3");
        cy.get("#important-dates-day").select("12");
        cy.get("#important-dates-year").type("2015");
        cy.get("#important-dates-note").type("Easter service");
        cy.get("#important-dates-form").submit();

        cy.get("#important-dates-list").contains("Baptism UI").closest(".list-group-item").within(() => {
            cy.contains("Easter service");
            cy.get(".badge").invoke("text").should("match", /Today|in \d+ days?/);
        });
        cy.get("#important-dates-form").should("not.be.visible");

        cy.reload();
        cy.get("#important-dates-list").contains("Baptism UI").closest(".list-group-item").find(".important-date-edit").click();
        cy.get("#important-dates-type-row").should("not.be.visible");
        cy.get("#important-dates-day").should("have.value", "12").select("13");
        cy.get("#important-dates-form").submit();

        cy.request("/api/person/2/important-dates").its("body.dates").then((dates) => {
            expect(dates.find((d) => d.typeId === baptism.id)).to.include({ month: 3, day: 13, year: 2015, note: "Easter service" });
        });

        cy.get("#important-dates-add").click();
        cy.get("#important-dates-type option").contains("Baptism UI").should("be.disabled");
        cy.get("#important-dates-cancel").click();

        cy.get("#important-dates-list").contains("Baptism UI").closest(".list-group-item").find(".important-date-remove").click();
        cy.get(".bootbox-confirm").should("be.visible").find(".bootbox-accept").click();
        cy.get("#important-dates-list").should("not.contain", "Baptism UI");
        cy.request("/api/person/2/important-dates").its("body.dates").then((dates) => {
            expect(dates.filter((d) => d.typeId === baptism.id)).to.have.length(0);
        });
    });

    it("lists the dates already on the record in the same card", () => {
        cy.request("/api/person/2/important-dates").its("body.dates").then((dates) => {
            cy.visit("/people/view/2");
            for (const date of dates) {
                cy.get("#important-dates-list").should("contain", date.label);
            }
        });
    });
});
