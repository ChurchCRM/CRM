import { PEOPLE_EDITOR_GATES } from "../../../../support/gates/people";

for (const row of PEOPLE_EDITOR_GATES.filter((gate) => gate.api)) {
    describe(row.id, () => {
        for (const persona of [...row.allow, ...row.deny]) {
            const allowed = row.allow.includes(persona);
            if (allowed && row.denyOnly) continue;
            it(`${allowed ? "allows" : "refuses"} ${persona}`, () => {
                cy.personaAPICall(persona, row.api.method, row.api.url, row.api.body ?? null, allowed ? 200 : 403).then(({ body }) => {
                    if (!allowed) expect(body.success).to.eq(false);
                    else expect(body).to.include(`id="${row.id.startsWith("person") ? "person" : "family"}Editor"`);
                });
            });
        }
    });
}

for (const kind of ["person", "family"]) {
    describe(`${kind} editor record validation`, () => {
        it("rejects a malformed record ID", () => {
            cy.personaAPICall("church-admin", "GET", `/people/${kind}/editor?${kind}Id=invalid`, null, 400);
        });
        it("returns not found for a missing record", () => {
            cy.personaAPICall("church-admin", "GET", `/people/${kind}/editor?${kind}Id=9999999`, null, 404);
        });
    });
}
