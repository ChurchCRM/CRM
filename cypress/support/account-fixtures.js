// Account states no route sets on an existing user (cypress/db-access.json).

const expectNoError = (r) => expect(r.error).to.eq(null);

Cypress.Commands.add("setTwoFactorSecret", (userName, encryptedSecret) =>
    cy
        .dbQuery(
            "UPDATE user_usr SET usr_TwoFactorAuthSecret = ?, usr_TwoFactorAuthLastKeyTimestamp = NULL, usr_FailedLogins = 0 WHERE usr_UserName = ?",
            [encryptedSecret, userName],
        )
        .then(expectNoError),
);

Cypress.Commands.add("setNeedPasswordChange", (userId, needed) =>
    cy
        .dbQuery("UPDATE user_usr SET usr_NeedPasswordChange = ? WHERE usr_per_ID = ?", [needed ? 1 : 0, userId])
        .then(expectNoError),
);

Cypress.Commands.add("setUserAdmin", (userId, isAdmin) =>
    cy.dbQuery("UPDATE user_usr SET usr_Admin = ? WHERE usr_per_ID = ?", [isAdmin ? 1 : 0, userId]).then(expectNoError),
);

Cypress.Commands.add("clearLastPortalActivity", (userId) =>
    cy
        .dbQuery("UPDATE user_usr SET usr_LastPortalActivity = NULL WHERE usr_per_ID = ?", [userId])
        .then(expectNoError),
);
