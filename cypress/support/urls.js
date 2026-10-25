/**
 * Absolute URL for a path relative to the install, keeping the base path.
 *
 * Cypress resolves root-relative paths in cy.request / cy.visit / cy.intercept
 * against baseUrl. Raw fetch, cy.task and win.fetch do not: new URL('/path',
 * 'http://host/churchcrm/') drops the install path and hits the origin.
 *
 * Strip a leading slash and resolve against the full baseUrl so both root and
 * subdirectory installs land on the right path.
 */
export function absoluteUrl(path, base = Cypress.config("baseUrl")) {
    const relative = String(path).replace(/^\/+/, "");
    // new URL replaces the last path segment when the base has no trailing slash
    // (http://host/churchcrm + api/x → http://host/api/x). Force the slash so the
    // install path is always kept.
    const root = String(base).replace(/\/+$/, "") + "/";
    const href = new URL(relative, root).href;
    if (!href.startsWith(root)) {
        throw new Error(
            `absoluteUrl dropped the install path\n` +
            `  path: ${path}\n` +
            `  base: ${base}\n` +
            `  got:  ${href}`,
        );
    }
    return href;
}
