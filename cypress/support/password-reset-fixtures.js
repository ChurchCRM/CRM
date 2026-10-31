const FILE = "cypress/support/password-reset-fixtures.js";

function query(sql, params = []) {
    return cy.task("db:query", { sql, params, fixture: FILE }).then((result) => {
        expect(result.error).to.equal(null);
        return result.rows;
    });
}

export function resetUserFixture() {
    return query("SELECT * FROM user_usr WHERE usr_UserName = ?", ["tony.wade@example.com"]).then((rows) => {
        expect(rows).to.have.length(1);
        return rows[0];
    });
}

export function resetUserState(user) {
    return query("SELECT * FROM user_usr WHERE usr_per_ID = ?", [user.usr_per_ID]);
}

export function resetTokenState(key) {
    return query("SELECT * FROM tokens WHERE token = ?", [key]);
}

export function seedResetToken(key, userId, type = "password", expired = false, remaining = 1) {
    return query("INSERT INTO tokens (token, type, reference_id, valid_until_date, remainingUses) VALUES (?, ?, ?, ?, ?)",
        [key, type, userId, expired ? "2000-01-01 00:00:00" : "2099-01-01 00:00:00", remaining]);
}

export function blockedResetUser(column) {
    expect(["per_DateDeceased", "per_DateDeactivated"]).to.include(column);
    return query(`SELECT usr_per_ID FROM user_usr JOIN person_per ON per_ID = usr_per_ID WHERE ${column} IS NOT NULL LIMIT 1`).then((rows) => {
        expect(rows).to.have.length(1);
        return rows[0].usr_per_ID;
    });
}

export function cleanupResetFixture(key, user) {
    query("DELETE FROM tokens WHERE token = ?", [key]);
    const columns = ["usr_Password", "usr_apiKey", "usr_NeedPasswordChange", "usr_LastLogin", "usr_LoginCount", "usr_FailedLogins"];
    return query(`UPDATE user_usr SET ${columns.map((column) => `${column} = ?`).join(", ")} WHERE usr_per_ID = ?`,
        [...columns.map((column) => user[column]), user.usr_per_ID]);
}

export function resetCsrfFixture(user) {
    const key = crypto.randomUUID().replaceAll("-", "") + crypto.randomUUID().replaceAll("-", "");
    return seedResetToken(key, user.usr_per_ID)
        .then(() => cy.request(`/session/forgot-password/set/${key}`))
        .then(({ body }) => {
            const match = body.match(/name="csrf_token" value="([^"]+)"/);
            expect(match, "recovery form CSRF token").not.to.equal(null);
            return query("DELETE FROM tokens WHERE token = ?", [key]).then(() => match[1]);
        });
}
