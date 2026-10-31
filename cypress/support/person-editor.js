// Staff set a person's family role, birth date, death date and personal email in
// the person editor. Specs post its form the way the page does.

const signInAs = ({ username, password }) => {
    cy.clearCookies();
    return cy
        .request({ method: "POST", url: "/admin/", form: true, followRedirect: false, body: { User: username, Password: password } })
        .its("status")
        .should("eq", 302);
};

const administrator = () =>
    cy.readEnv("admin.username").then((username) => cy.readEnv("admin.password").then((password) => ({ username, password })));

/**
 * Signs in as `user` (the administrator when null) and saves the person editor with
 * every field as the form shows it, changed by `changes`. A checkbox takes true or
 * false. Yields the response: a 302 to the person page when it saved.
 */
export const savePersonEditor = (personId, changes, user = null) =>
    (user ? cy.wrap(user, { log: false }) : administrator()).then((who) => {
        signInAs(who);
        return cy.request(`/PersonEditor.php?PersonID=${personId}`).then((page) => {
            const form = Cypress.$(new DOMParser().parseFromString(page.body, "text/html")).find("#personEditor");
            expect(form.length, "person editor form").to.eq(1);
            const body = {};
            form.serializeArray().forEach(({ name, value }) => {
                body[name] = value;
            });
            Object.entries(changes).forEach(([name, value]) => {
                if (value === false) {
                    delete body[name];
                } else {
                    body[name] = value === true ? "1" : String(value);
                }
            });
            body.PersonSubmit = "";
            return cy.request({ method: "POST", url: `/PersonEditor.php?PersonID=${personId}`, form: true, body, followRedirect: false });
        });
    });
