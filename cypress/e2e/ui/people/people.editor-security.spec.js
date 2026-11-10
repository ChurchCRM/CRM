const serialize = ($form) => Object.fromEntries($form.serializeArray().map(({ name, value }) => [name, value]));

describe("Staff record editors protect writes", () => {
    beforeEach(() => cy.setupPersonaSession("church-admin"));

    for (const kind of ["person", "family"]) {
        it(`refuses a ${kind} save without the session CSRF token`, () => {
            const id = kind === "person" ? 2 : 1;
            cy.request(`/api/${kind}/${id}`).its("body").as("before");
            cy.visit(`/people/${kind}/editor?${kind}Id=${id}`);
            cy.get(`#${kind}Editor`).then(($form) => {
                const body = serialize($form);
                delete body.csrf_token;
                cy.request({
                    method: "POST",
                    url: `/people/${kind}/editor?${kind}Id=${id}`,
                    form: true,
                    body,
                    failOnStatusCode: false,
                }).its("status").should("eq", 403);
            });
            cy.request(`/api/${kind}/${id}`).its("body").then((after) => cy.get("@before").should("deep.eq", after));
        });
    }

    it("rejects a member from another family before saving any family changes", () => {
        let targetFamilyId;
        let otherFamilyId;
        cy.request("/api/person/2").its("body").then((person) => {
            targetFamilyId = person.FamId;
            expect(targetFamilyId).to.be.greaterThan(0);
        });
        cy.request("/api/person/3").its("body").then((person) => {
            otherFamilyId = person.FamId;
            expect(otherFamilyId).not.to.eq(targetFamilyId);
        });
        cy.then(() => cy.request(`/api/family/${targetFamilyId}`).its("body").as("familyBefore"));
        cy.request("/api/person/3").its("body").as("personBefore");
        cy.then(() => cy.visit(`/people/family/editor?familyId=${targetFamilyId}`));
        cy.get("#familyEditor").then(($form) => {
            const body = { ...serialize($form), personId1: 3, name: "Must not be saved", firstName1: "Must not change" };
            cy.request({
                method: "POST",
                url: `/people/family/editor?familyId=${targetFamilyId}`,
                form: true,
                body,
                failOnStatusCode: false,
            }).its("status").should("eq", 400);
        });
        cy.then(() => cy.request(`/api/family/${targetFamilyId}`).its("body")).then((after) => cy.get("@familyBefore").should("deep.eq", after));
        cy.request("/api/person/3").its("body").then((after) => cy.get("@personBefore").should("deep.eq", after));
    });
});

describe("Office staff update records without changing giving envelopes", () => {
    const families = [];
    after(() => cy.cleanupFamilies(families));

    it("ignores forged envelope values on both editors", () => {
        cy.setupPersonaSession("records-intake");
        cy.visit("/people/family/editor");
        cy.get("#FamilyName").type(`EnvelopeGuard${Date.now()}`);
        cy.get('[name="firstName1"]').type("Casey");
        cy.get('[name="classification1"]').select("1", { force: true });
        cy.intercept("POST", "**/people/family/editor*").as("createFamily");
        cy.get('[name="familySubmit"]').click();
        cy.wait("@createFamily").its("response.statusCode").should("eq", 302);
        cy.location("pathname").then((path) => {
            const familyId = Number(path.split("/").pop());
            expect(familyId).to.be.greaterThan(0);
            families.push(familyId);
            cy.wrap(familyId).as("familyId");
        });

        cy.setupPersonaSession("office-staff");
        cy.get("@familyId").then((id) => cy.visit(`/people/family/editor?familyId=${id}`));
        cy.get('[name="personId1"]').invoke("val").then(Number).as("personId");
        cy.get('[name="envelope"]').should("not.exist");
        cy.get("#familyEditor").invoke("append", '<input type="hidden" name="envelope" value="99999">');
        cy.intercept("POST", "**/people/family/editor*").as("editFamily");
        cy.get('[name="familySubmit"]').click();
        cy.wait("@editFamily").its("response.statusCode").should("eq", 302);
        cy.get("@familyId").then((id) => cy.location("pathname").should("include", `/people/family/${id}`));

        cy.get("@personId").then((id) => cy.visit(`/people/person/editor?personId=${id}`));
        cy.get("#personEditor").invoke("append", '<input type="hidden" name="envId" value="99999">');
        cy.intercept("POST", "**/people/person/editor*").as("editPerson");
        cy.get('[name="personSubmit"]').click();
        cy.wait("@editPerson").its("response.statusCode").should("eq", 302);
        cy.get("@personId").then((id) => cy.location("pathname").should("include", `/people/view/${id}`));

        cy.get("@familyId").then((id) => cy.makePrivateAdminAPICall("GET", `/api/family/${id}`, null, 200).its("body.Envelope").should("eq", 0));
        cy.get("@personId").then((id) => cy.makePrivateAdminAPICall("GET", `/api/person/${id}`, null, 200).its("body.Envelope").should("eq", null));
    });
});
