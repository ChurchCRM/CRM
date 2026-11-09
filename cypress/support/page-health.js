export const expectPageHealth = (page) => {
    const baseUrl = new URL(Cypress.config("baseUrl"));
    const serverErrors = [];
    const documentResponses = [];
    const missingAssets = [];
    const javascriptErrors = [];

    cy.on("uncaught:exception", (error) => {
        const message = error.message.trim();
        if (!message.includes("ResizeObserver loop")) {
            javascriptErrors.push(message);
        }
        return false;
    });

    cy.intercept({ url: /.*/ }, (request) => {
        const url = new URL(request.url);
        if (url.origin !== baseUrl.origin) {
            return;
        }

        request.on("response", (response) => {
            const detail = `${request.method} ${url.pathname}${url.search} returned ${response.statusCode}`;
            if (response.statusCode >= 500) {
                serverErrors.push(detail);
            }
            if (request.resourceType === "document") {
                documentResponses.push({ url: url.href, status: response.statusCode });
            }
            if (["script", "stylesheet"].includes(request.resourceType) && response.statusCode === 404) {
                missingAssets.push(detail);
            }
        });
    });

    cy.visit(page, { failOnStatusCode: false });
    cy.get("body").should("be.visible");
    cy.wait(1000);
    cy.then(() => {
        expect(serverErrors, `server errors while loading ${page}`).to.deep.equal([]);
        expect(javascriptErrors, `uncaught JavaScript errors while loading ${page}`).to.deep.equal([]);
        expect(missingAssets, `missing scripts or styles while loading ${page}`).to.deep.equal([]);
        const finalDocument = documentResponses[documentResponses.length - 1];
        expect(finalDocument, `document response for ${page}`).to.exist;
        expect(finalDocument.status, `document status for ${finalDocument.url}`).to.be.within(200, 399);
    });
};
