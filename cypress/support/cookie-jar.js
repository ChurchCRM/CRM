// An x-api-key request answers with a session cookie of its own, which would
// replace the browser's session; run such steps in their own cookie jar.

/** Runs `call` in its own cookie jar and puts the browser's cookies back afterwards. */
export const inOwnCookieJar = (call) =>
    cy.getCookies().then((cookies) => {
        cy.clearCookies();
        call();
        cy.clearCookies();
        cookies.forEach(({ name, value, path, domain, httpOnly, secure, sameSite }) => {
            cy.setCookie(name, value, { path, domain, httpOnly, secure, sameSite });
        });
    });
