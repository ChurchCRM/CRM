/// <reference types="cypress" />

describe("window.CRM.notify() toasts", () => {
    const notify = (message, options) => {
        cy.window().then((win) => win.CRM.notify(message, options));
        return cy.contains("#crm-toasts .toast", message);
    };

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/v2/dashboard");
        cy.window().its("CRM.notify").should("be.a", "function");
    });

    it("renders into the layout's live region", () => {
        cy.get("#crm-toasts").should("have.attr", "aria-live", "polite");
    });

    it('renders type: "error" as a danger alert', () => {
        notify("probe error", { type: "error", delay: 0 })
            .should("be.visible")
            .and("have.attr", "data-type", "danger")
            .and("have.attr", "role", "alert")
            .and("have.class", "text-bg-danger");
    });

    it("renders success with the Tabler success colour and auto-dismisses", () => {
        notify("probe success", { type: "success", delay: 500 })
            .should("be.visible")
            .and("have.attr", "role", "status")
            .and("have.class", "text-bg-success");
        cy.contains("#crm-toasts .toast", "probe success").should("not.exist");
    });

    it("falls back to info for an unknown type", () => {
        notify("probe unknown", { type: "not-a-real-type", delay: 0 }).should("have.attr", "data-type", "info");
    });

    it("keeps a delay: 0 toast until it is dismissed", () => {
        notify("probe sticky", { type: "warning", delay: 0 }).should("be.visible");
        cy.wait(3500);
        cy.contains("#crm-toasts .toast", "probe sticky").find(".btn-close").click();
        cy.contains("#crm-toasts .toast", "probe sticky").should("not.exist");
    });

    it("accepts the { message } object form", () => {
        cy.window().then((win) => win.CRM.notify({ message: "probe object" }, { type: "success", delay: 0 }));
        cy.contains("#crm-toasts .toast", "probe object").should("be.visible");
    });
});
