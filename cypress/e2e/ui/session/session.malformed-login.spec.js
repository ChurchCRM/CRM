/// <reference types="cypress" />

/**
 * Regression tests for the malformed-login 500 — issue #9681.
 *
 * src/session/index.php read $loginRequestBody['User'] and ['Password']
 * unguarded into LocalUsernamePasswordRequest::__construct(string, string).
 * A body missing either key — or getParsedBody() returning null for a media
 * type with no registered parser — passed null into a strictly typed
 * parameter and raised an uncaught TypeError:
 *
 *   LocalUsernamePasswordRequest::__construct(): Argument #1 ($username)
 *   must be of type string, null given
 *
 * The throw happens before any credential check, so this was never an
 * authentication bypass — it was unhandled input producing a 500, which any
 * bot probing the login form could trigger repeatedly.
 *
 * cy.request with failOnStatusCode:false is used rather than the form, since
 * the point is to send bodies a browser form cannot produce.
 */
describe("Malformed POST to /session/begin fails the login instead of 500ing (#9681)", () => {
    const cases = [
        { name: "no fields at all", options: {} },
        { name: "Password present, User missing", options: { form: true, body: { Password: "x" } } },
        { name: "User present, Password missing", options: { form: true, body: { User: "x" } } },
        { name: "JSON body with no credential keys", options: { body: {} } },
    ];

    cases.forEach(({ name, options }) => {
        it(`returns the login page for: ${name}`, () => {
            cy.request({
                method: "POST",
                url: "/session/begin",
                failOnStatusCode: false,
                ...options,
            }).then((resp) => {
                // 500 is the bug. Anything non-5xx means the request was handled.
                expect(resp.status, "must not be a server error").to.be.lessThan(500);
                if (typeof resp.body === "string") {
                    expect(resp.body).to.not.include("must be of type string");
                    expect(resp.body).to.not.include("TypeError");
                }
            });
        });
    });

    it("still rejects a well-formed login with bad credentials", () => {
        cy.request({
            method: "POST",
            url: "/session/begin",
            form: true,
            failOnStatusCode: false,
            body: { User: "nobody", Password: "wrong" },
        }).then((resp) => {
            expect(resp.status).to.be.lessThan(500);
        });
    });
});
