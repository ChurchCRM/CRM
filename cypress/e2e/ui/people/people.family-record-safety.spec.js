const formValues = (url, id) =>
    cy.request(url).then(({ body }) => {
        const form = Cypress.$(new DOMParser().parseFromString(body, "text/html")).find(`#${id}Editor`);
        expect(form).to.have.length(1);
        return Object.fromEntries(form.serializeArray().map(({ name, value }) => [name, value]));
    });

const save = (url, body, status = 302) =>
    cy.request({ method: "POST", url, form: true, body, followRedirect: false, failOnStatusCode: false }).its("status").should("eq", status);

describe("Staff save family records within their permissions", () => {
    const families = [];
    let familyId;
    let otherFamilyId;
    let personId;
    let otherPersonId;
    const familyUrl = () => `/FamilyEditor.php?FamilyID=${familyId}`;
    const personUrl = () => `/PersonEditor.php?PersonID=${personId}`;

    const createFamily = (label, count) => {
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(`RecordSafety${label}${Date.now()}`);
        for (let row = 1; row <= count; row++) {
            cy.get(`[name="FirstName${row}"]`).type(`Member${row}`);
            cy.get(`[name="Classification${row}"]`).select("1", { force: true });
        }
        cy.intercept("POST", "**/FamilyEditor.php*").as("createFamily");
        cy.get('[name="FamilySubmit"]').click();
        cy.wait("@createFamily").its("response.statusCode").should("eq", 302);
        return cy.location("pathname").then((path) => {
            const id = Number(path.match(/\/people\/family\/(\d+)$/)[1]);
            families.push(id);
            return id;
        });
    };

    before(() => {
        cy.setupPersonaSession("church-admin");
        createFamily("Target", 2).then((id) => { familyId = id; });
        createFamily("Other", 1).then((id) => { otherFamilyId = id; });
        cy.then(() => formValues(familyUrl(), "family")).then((body) => { personId = Number(body.PersonID1); });
        cy.then(() => formValues(`/FamilyEditor.php?FamilyID=${otherFamilyId}`, "family"))
            .then((body) => { otherPersonId = Number(body.PersonID1); });
    });

    beforeEach(() => cy.setupPersonaSession("church-admin"));
    after(() => cy.cleanupFamilies(families));

    for (const kind of ["person", "family"]) {
        it(`rejects a ${kind} save without a valid CSRF token`, () => {
            const url = kind === "person" ? personUrl : familyUrl;
            const id = () => kind === "person" ? personId : familyId;
            cy.then(() => cy.request(`/api/${kind}/${id()}`).its("body")).as("before");
            cy.then(() => formValues(url(), kind)).then((body) => {
                body[kind === "person" ? "PersonSubmit" : "FamilySubmit"] = "";
                delete body.csrf_token;
                save(url(), body, 403);
                save(url(), { ...body, csrf_token: "invalid" }, 403);
            });
            cy.then(() => cy.request(`/api/${kind}/${id()}`).its("body"))
                .then((after) => cy.get("@before").should("deep.eq", after));
        });
    }

    for (const invalid of ["foreign member", "duplicate member", "missing member", "negative count", "excessive count", "malformed count"]) {
        it(`rejects a ${invalid} before changing the family or its members`, () => {
            cy.then(() => cy.request(`/api/family/${familyId}`).its("body")).as("familyBefore");
            cy.then(() => cy.request(`/api/person/${personId}`).its("body")).as("personBefore");
            cy.then(() => cy.request(`/api/person/${otherPersonId}`).its("body")).as("otherBefore");
            cy.then(() => formValues(familyUrl(), "family")).then((body) => {
                body.Name = "Must not be saved";
                body.FirstName1 = "Must not change";
                body.FamilySubmit = "";
                if (invalid === "foreign member") body.PersonID2 = otherPersonId;
                if (invalid === "duplicate member") body.PersonID2 = personId;
                if (invalid === "missing member") body.PersonID2 = 0;
                if (invalid === "negative count") body.FamCount = -1;
                if (invalid === "excessive count") body.FamCount = 501;
                if (invalid === "malformed count") body.FamCount = "invalid";
                save(familyUrl(), body, 400);
            });
            cy.then(() => cy.request(`/api/family/${familyId}`).its("body"))
                .then((after) => cy.get("@familyBefore").should("deep.eq", after));
            cy.then(() => cy.request(`/api/person/${personId}`).its("body"))
                .then((after) => cy.get("@personBefore").should("deep.eq", after));
            cy.then(() => cy.request(`/api/person/${otherPersonId}`).its("body"))
                .then((after) => cy.get("@otherBefore").should("deep.eq", after));
        });
    }

    it("lets Finance set envelopes and preserves them on staff edits and omitted inputs", () => {
        cy.then(() => formValues(familyUrl(), "family")).then((body) => save(familyUrl(), { ...body, FamilySubmit: "", Envelope: 345 }));
        cy.then(() => formValues(personUrl(), "person")).then((body) => save(personUrl(), { ...body, PersonSubmit: "", EnvID: 456 }));

        cy.setupPersonaSession("office-staff");
        cy.then(() => formValues(familyUrl(), "family")).then((body) =>
            save(familyUrl(), { ...body, FamilySubmit: "", Envelope: 99999, Name: "StaffUpdatedFamily" }));
        cy.then(() => formValues(personUrl(), "person")).then((body) =>
            save(personUrl(), { ...body, PersonSubmit: "", EnvID: 99999, FirstName: "StaffUpdatedPerson" }));

        cy.setupPersonaSession("church-admin");
        cy.then(() => cy.request(`/api/family/${familyId}`).its("body")).should("include", { Envelope: 345, Name: "StaffUpdatedFamily" });
        cy.then(() => cy.request(`/api/person/${personId}`).its("body")).should("include", { Envelope: 456, FirstName: "StaffUpdatedPerson" });
        cy.then(() => formValues(familyUrl(), "family")).then((body) => {
            delete body.Envelope;
            save(familyUrl(), { ...body, FamilySubmit: "" });
        });
        cy.then(() => formValues(personUrl(), "person")).then((body) => {
            delete body.EnvID;
            save(personUrl(), { ...body, PersonSubmit: "" });
        });
        cy.then(() => cy.request(`/api/family/${familyId}`).its("body.Envelope")).should("eq", 345);
        cy.then(() => cy.request(`/api/person/${personId}`).its("body.Envelope")).should("eq", 456);
    });
});

