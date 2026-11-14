export const ACCOUNT_VIEWPORTS = [
    { width: 1440, height: 900 },
    { width: 820, height: 1180 },
    { width: 390, height: 844 },
];

export function captureAccountLayout(name, mode) {
    cy.window().its("CRM.theme").invoke("setMode", mode);
    cy.document().then((doc) => doc.fonts.ready);
    cy.document().should((doc) => {
        expect(doc.documentElement.scrollWidth, `${name}: no horizontal page scroll`).to.be.at.most(doc.documentElement.clientWidth + 1);
    });
    cy.get("#main-content").should("be.visible");
    cy.screenshot(`account-layout/${name}-${Cypress.config("viewportWidth")}-${mode}`, { capture: "fullPage", overwrite: true });
}
