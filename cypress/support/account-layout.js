export const ACCOUNT_VIEWPORTS = [
    { width: 1440, height: 900 },
    { width: 820, height: 1180 },
    { width: 390, height: 844 },
];

export function captureAccountLayout(name, mode) {
    cy.window().its("CRM.theme").invoke("setMode", mode);
    cy.document().then((doc) => doc.fonts.ready);
    cy.get("body").then(($body) => {
        const avatar = $body.find("#userAvatar");
        if (avatar.length) {
            cy.wrap(avatar).should(($image) => {
                expect($image[0].complete && $image[0].naturalWidth > 0, "account photo loaded").to.eq(true);
            });
        }
    });
    cy.document().should((doc) => {
        expect(doc.documentElement.scrollWidth, `${name}: no horizontal page scroll`).to.be.at.most(doc.documentElement.clientWidth + 1);
    });
    cy.get("#main-content").should("be.visible");
    cy.window().then((win) => {
        const screenshot = `account-layout/${name}-${win.innerWidth}-${mode}`;
        const scrollY = win.scrollY;
        cy.scrollTo("top", { ensureScrollable: false });
        cy.screenshot(screenshot, { capture: "viewport", overwrite: true });
        if (win.document.documentElement.scrollHeight > win.innerHeight) {
            cy.scrollTo("bottom", { ensureScrollable: false });
            cy.screenshot(`${screenshot}-bottom`, { capture: "viewport", overwrite: true });
        }
        cy.scrollTo(0, scrollY, { ensureScrollable: false });
    });
}
