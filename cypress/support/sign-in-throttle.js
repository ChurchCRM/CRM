// Ends a sign-in throttle window now, so a spec need not wait out the lockout
// period (#10197). Pass a key (a user id for "signin.lockout") to end one window.
Cypress.Commands.add("expireSignInThrottle", (bucket, key = null) => {
    const sql =
        key === null
            ? "UPDATE rate_limit_rlm SET rlm_ResetAt = UNIX_TIMESTAMP() - 1 WHERE rlm_Bucket = ?"
            : "UPDATE rate_limit_rlm SET rlm_ResetAt = UNIX_TIMESTAMP() - 1 WHERE rlm_Bucket = ? AND rlm_KeyHash = SHA2(?, 256)";
    const params = key === null ? [bucket] : [bucket, String(key)];

    return cy.dbQuery(sql, params, { fixture: "cypress/support/sign-in-throttle.js" }).then(({ error }) => {
        expect(error, `expiring ${bucket}`).to.eq(null);
    });
});
