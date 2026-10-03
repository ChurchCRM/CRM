// Shared readers for Cypress `env` values. cy.env() is async and read-only;
// allowCypressEnv is false, so Cypress.env() cannot be used.

const remembered = {};

function missing(key) {
    throw new Error(
        `${key} is not set. Add it to the Cypress config env or pass it with --env.`,
    );
}

/** Yield one secret. */
Cypress.Commands.add("readEnv", (key) =>
    cy.env([key]).then((env) => {
        const value = env[key];
        if (value === undefined || value === null || value === "") {
            missing(key);
        }
        return value;
    }),
);

/**
 * Load keys for later synchronous reads via Cypress.testEnv().
 * Call this in a before() that finishes before any test reads the values.
 */
Cypress.Commands.add("rememberTestEnv", (keys) =>
    cy.env(keys).then((env) => {
        keys.forEach((key) => {
            remembered[key] = env[key];
        });
        return env;
    }),
);

Cypress.testEnv = (key) => {
    if (!Object.prototype.hasOwnProperty.call(remembered, key)) {
        missing(key);
    }
    return remembered[key];
};

/**
 * Form login that does not go through cy.session(). Specs that call the API
 * before cy.visit() need this: cy.request() replaces the PHP session.
 */
Cypress.Commands.add("freshAdminFormLogin", (options = {}) => {
    return cy.env(["admin.username", "admin.password"]).then((env) => {
        const username = env["admin.username"];
        const password = env["admin.password"];
        if (!username || !password) {
            missing("admin.username");
        }
        if (options.clearAll) {
            cy.clearAllCookies();
        } else {
            cy.clearCookies();
        }
        cy.visit(options.path || "/session/begin");
        const userOpts = options.timeout ? { timeout: options.timeout } : undefined;
        const field = (selector, opts) => {
            let input = cy.get(selector, opts);
            if (options.visible) {
                input = input.should("be.visible");
            }
            if (options.enabled) {
                input = input.should("not.be.disabled");
            }
            return input;
        };
        field("input[name=User]", userOpts).type(username);
        field("input[name=Password]").type(`${password}{enter}`, { log: false });
        cy.url().should("not.include", "/session/begin");
        if (options.sessionCookie) {
            cy.getCookies().should("satisfy", (cookies) =>
                cookies.some((cookie) => cookie.name.startsWith("CRM-")),
            );
        }
    });
});
