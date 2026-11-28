/// <reference types="cypress" />

describe("Admin sets up the yearly dates the church records", () => {
    let firstCommunionOrder = 0;

    const types = () => cy.makePrivateAdminAPICall("GET", "/admin/api/important-date-types", null, 200).its("body.types");

    before(() => {
        types().then((list) => {
            const installed = list.find((t) => t.key === "first-communion");
            firstCommunionOrder = installed.sortOrder;
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${installed.id}`, null, 200);
        });
    });

    after(() => {
        types().then((list) => {
            for (const type of list.filter((t) => t.name.startsWith("Commissioning Admin"))) {
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${type.id}`, null, 200);
            }
            if (!list.some((t) => t.key === "first-communion")) {
                cy.makePrivateAdminAPICall("POST", "/admin/api/important-date-types", { preset: "first-communion" }, 201);
            }
        });
        types().then((list) => {
            const restored = list.find((t) => t.key === "first-communion");
            cy.makePrivateAdminAPICall("PATCH", `/admin/api/important-date-types/${restored.id}`, { sortOrder: firstCommunionOrder }, 200);
        });
    });

    beforeEach(() => cy.setupAdminSession());

    it("adds a suggested type and a custom one from People Settings, then adjusts them in place", () => {
        cy.visit("/admin/people");
        cy.contains("a", "Important Date Types").click();
        cy.location("pathname").should("contain", "/admin/people/important-dates");

        cy.get("#important-date-types-table").contains("button.idt-rename", "Baptism").should("be.visible");
        cy.get("#important-date-types-table").contains("tr", "Baptism").find(".idt-delete").should("not.exist");

        cy.get(".idt-preset[data-key='first-communion']").click();
        cy.get("#important-date-types-table").contains("button.idt-rename", "First Communion").should("be.visible");
        cy.get(".idt-preset[data-key='first-communion']").should("not.exist");

        cy.get("#idt-name").type("Commissioning Admin");
        cy.get("#idt-applies-to").select("family");
        cy.get("#idt-allow-multiple").check();
        cy.get("#idt-show-on-calendar").uncheck();
        cy.get("#important-date-type-form").submit();

        cy.get("#important-date-types-table").contains("tr", "Commissioning Admin").within(() => {
            cy.contains("Families");
            cy.get(".idt-toggle[data-field='allowMultiple']").should("be.checked");
            cy.get(".idt-toggle[data-field='showOnCalendar']").should("not.be.checked").check();
        });

        cy.reload();
        cy.get("#important-date-types-table").contains("tr", "Commissioning Admin").find(".idt-toggle[data-field='showOnCalendar']").should("be.checked");

        cy.get("#important-date-types-table").contains("tr", "Commissioning Admin").find(".idt-delete").click();
        cy.get(".bootbox-confirm").should("be.visible").find(".bootbox-accept").click();
        cy.get("#important-date-types-table").should("not.contain", "Commissioning Admin");
    });

    it("refuses a duplicate name and keeps the form for correction", () => {
        cy.visit("/admin/people/important-dates");
        cy.get("#idt-name").type("dedication");
        cy.get("#important-date-type-form").submit();
        cy.contains("already exists").should("be.visible");
        cy.get("#idt-name").should("have.value", "dedication");
    });
});
