/// <reference types="cypress" />

describe("Admin sets up the yearly dates the church records", () => {
    const cleanup = () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/important-date-types", null, 200).then(({ body }) => {
            for (const type of body.types.filter((t) => ["confirmation", "dedication"].includes(t.key) || t.name.startsWith("Commissioning Admin"))) {
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/important-date-types/${type.id}`, null, [200, 409]);
            }
        });
    };

    before(cleanup);
    after(cleanup);

    beforeEach(() => cy.setupAdminSession());

    it("adds a suggested type and a custom one from People Settings, then adjusts them in place", () => {
        cy.visit("/admin/people");
        cy.contains("a", "Important Date Types").click();
        cy.location("pathname").should("contain", "/admin/people/important-dates");

        cy.get(".idt-preset[data-key='confirmation']").click();
        cy.get("#important-date-types-table").contains("button.idt-rename", "Confirmation").should("be.visible");
        cy.get(".idt-preset[data-key='confirmation']").should("not.exist");

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

        cy.get("#important-date-types-table").contains("tr", "Confirmation").find(".idt-delete").click();
        cy.get(".bootbox-confirm").should("be.visible").find(".bootbox-accept").click();
        cy.get("#important-date-types-table").should("not.contain", "Confirmation");
        cy.get(".idt-preset[data-key='confirmation']").should("be.visible");
    });

    it("refuses a duplicate name and keeps the form for correction", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/important-date-types", { preset: "dedication" }, 201);
        cy.visit("/admin/people/important-dates");
        cy.get("#idt-name").type("dedication");
        cy.get("#important-date-type-form").submit();
        cy.contains("already exists").should("be.visible");
        cy.get("#idt-name").should("have.value", "dedication");
    });
});
